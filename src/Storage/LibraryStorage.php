<?php

namespace App\Storage;

use Symfony\Component\Finder\Finder;
use ZipStream\CompressionMethod;
use ZipStream\OperationMode;
use ZipStream\ZipStream;

/**
 * Reads files from registered library roots (typically on a separate disk).
 * Implements strict path-traversal protection: every absolute path resolved
 * through this class is verified to be inside an explicitly registered root.
 */
class LibraryStorage
{
    /** @var string[] */
    private array $allowedRoots = [];

    public function __construct(string $libraryPath = null)
    {
        if ($libraryPath) {
            $this->addAllowedRoot($libraryPath);
        }
    }

    public function addAllowedRoot(string $path): void
    {
        $real = realpath($path);
        if ($real === false) {
            return;
        }
        $this->allowedRoots[] = rtrim($real, '/');
        $this->allowedRoots = array_values(array_unique($this->allowedRoots));
    }

    public function isPathAllowed(string $absolutePath): bool
    {
        $real = realpath($absolutePath);
        if ($real === false) {
            return false;
        }
        foreach ($this->allowedRoots as $root) {
            if ($real === $root || str_starts_with($real, $root . '/')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Open a file for reading. Throws if the path escapes every allowed root.
     * If the path is a directory, opens php://temp and writes a ZIP of the
     * directory into it, returning a stream that yields the ZIP bytes.
     */
    public function readStream(string $absolutePath): mixed
    {
        if (!$this->isPathAllowed($absolutePath)) {
            throw new \RuntimeException('Library path is not inside an allowed root: ' . $absolutePath);
        }
        $real = realpath($absolutePath);
        if (is_dir($real)) {
            $tmp = fopen('php://temp', 'w+b');
            $this->streamAsZip($real, $tmp, basename($real) . '.zip');
            rewind($tmp);
            return $tmp;
        }
        if (!is_file($real) || !is_readable($real)) {
            throw new \RuntimeException('Library file not readable: ' . $absolutePath);
        }
        return fopen($real, 'rb');
    }

    public function size(string $absolutePath): int
    {
        if (!$this->isPathAllowed($absolutePath)) {
            throw new \RuntimeException('Library path is not inside an allowed root: ' . $absolutePath);
        }
        $real = realpath($absolutePath);
        if (is_dir($real)) {
            return $this->dirSize($real);
        }
        return filesize($real);
    }

    public function mimeType(string $absolutePath): string
    {
        if (!$this->isPathAllowed($absolutePath)) {
            throw new \RuntimeException('Library path is not inside an allowed root: ' . $absolutePath);
        }
        $real = realpath($absolutePath);
        if (is_dir($real)) {
            return 'application/zip';
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        return finfo_file($finfo, $real);
    }

    public function exists(string $absolutePath): bool
    {
        if (!$this->isPathAllowed($absolutePath)) {
            return false;
        }
        $real = realpath($absolutePath);
        return $real !== false && file_exists($real);
    }

    /**
     * Walks a registered root and yields top-level entries: files at the root
     * and directories one level deep. Directories are returned as size = sum
     * of regular files inside them (recursive).
     */
    public function scanRoot(string $rootPath): array
    {
        $real = realpath($rootPath);
        if ($real === false || !is_dir($real)) {
            return [];
        }

        $results = [];
        $iter = new \DirectoryIterator($real);
        foreach ($iter as $entry) {
            if ($entry->isDot()) {
                continue;
            }
            $path = $entry->getPathname();
            $isDir = $entry->isDir();
            $size = $isDir ? $this->dirSize($path) : (int) $entry->getSize();

            $results[] = [
                'path' => $path,
                'name' => $entry->getFilename(),
                'relative_path' => null,
                'size_bytes' => $size,
                'is_directory' => $isDir,
                'mime_type' => $isDir ? null : $this->guessMime($path),
            ];
        }

        usort($results, fn($a, $b) => ($b['is_directory'] <=> $a['is_directory']) ?: strcasecmp($a['name'], $b['name']));
        return $results;
    }

    private function dirSize(string $path): int
    {
        $total = 0;
        try {
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($iter as $f) {
                if ($f->isFile()) {
                    $total += (int) $f->getSize();
                }
            }
        } catch (\Throwable) {
            // unreadable subdir, ignore
        }
        return $total;
    }

    private function guessMime(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);
        return $mime ?: 'application/octet-stream';
    }

    /**
     * Stream a directory (or single file) as a ZIP into the given output stream.
     * Used by the download controller for library-backed folder shares.
     */
    public function streamAsZip(string $absolutePath, $output, string $zipName): void
    {
        if (!$this->isPathAllowed($absolutePath)) {
            throw new \RuntimeException('Library path is not inside an allowed root: ' . $absolutePath);
        }
        $real = realpath($absolutePath);
        $isDir = is_dir($real);

        $zip = new ZipStream(
            operationMode: OperationMode::NORMAL,
            outputStream: $output,
            defaultCompressionMethod: CompressionMethod::STORE,
            outputName: $zipName,
        );

        if (!$isDir) {
            $this->addFileToZip($zip, $real, basename($real));
        } else {
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($real, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($iter as $f) {
                /** @var \SplFileInfo $f */
                if (!$f->isFile()) {
                    continue;
                }
                $relative = ltrim(substr($f->getPathname(), strlen($real)), '/');
                $this->addFileToZip($zip, $f->getPathname(), $relative);
            }
        }

        $zip->finish();
    }

    private function addFileToZip(ZipStream $zip, string $absoluteFile, string $entryName): void
    {
        $stream = fopen($absoluteFile, 'rb');
        if ($stream === false) {
            return;
        }
        try {
            $zip->addFileFromStream(
                fileName: $entryName,
                stream: $stream,
                compressionMethod: CompressionMethod::STORE,
                exactSize: filesize($absoluteFile),
            );
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
