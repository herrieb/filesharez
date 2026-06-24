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
        return $this->scanRecursive($rootPath, 1);
    }

    /**
     * Walks $rootPath up to $maxDepth levels deep. Each entry has:
     *  - path: absolute filesystem path
     *  - name: basename
     *  - parent_path: absolute path of the containing directory
     *  - relative_path: path relative to $rootPath (no leading slash)
     *  - size_bytes: regular files only; directories get recursive sum of regular files
     *  - is_directory, mime_type
     *
     * Symlinks are skipped by default (safer). Hidden entries (leading dot) are
     * included by default; pass $includeHidden = false to filter them.
     */
    public function scanRecursive(string $rootPath, int $maxDepth = 1, bool $includeHidden = true): array
    {
        $real = realpath($rootPath);
        if ($real === false || !is_dir($real)) {
            return [];
        }

        $results = [];
        $this->scanRecursiveInto($real, $real, 0, $maxDepth, $includeHidden, $results);
        return $results;
    }

    private function scanRecursiveInto(
        string $rootReal,
        string $currentDir,
        int $depth,
        int $maxDepth,
        bool $includeHidden,
        array &$results,
    ): void {
        $iter = new \DirectoryIterator($currentDir);
        foreach ($iter as $entry) {
            if ($entry->isDot()) {
                continue;
            }
            if ($entry->isLink()) {
                continue;
            }
            $name = $entry->getFilename();
            if (!$includeHidden && str_starts_with($name, '.')) {
                continue;
            }

            $path = $entry->getPathname();
            $isDir = $entry->isDir();
            $size = $isDir ? $this->dirSize($path) : (int) $entry->getSize();

            $relative = ltrim(substr($path, strlen($rootReal)), '/');

            $results[] = [
                'path' => $path,
                'name' => $name,
                'parent_path' => $currentDir,
                'relative_path' => $relative,
                'size_bytes' => $size,
                'is_directory' => $isDir,
                'mime_type' => $isDir ? null : $this->guessMime($path),
            ];

            if ($isDir && $depth + 1 < $maxDepth) {
                $this->scanRecursiveInto($rootReal, $path, $depth + 1, $maxDepth, $includeHidden, $results);
            }
        }
    }

    /**
     * Resolve a path inside a specific source root. $relativePath must start
     * with '/'. Resolves symlinks via realpath() and asserts the result is
     * still inside the source's real root. Throws on any escape attempt.
     */
    public function resolveUnderSource(string $sourceRoot, string $relativePath): string
    {
        $rootReal = realpath($sourceRoot);
        if ($rootReal === false) {
            throw new \RuntimeException('Source root does not exist: ' . $sourceRoot);
        }

        $relative = ltrim($relativePath, '/');
        if ($relative === '' || $relative === '.') {
            return $rootReal;
        }

        if (str_contains($relative, "\0")) {
            throw new \RuntimeException('Invalid path');
        }

        $candidate = $rootReal . '/' . $relative;
        $real = realpath($candidate);
        if ($real === false) {
            throw new \RuntimeException('Path does not exist: ' . $relativePath);
        }

        if ($real !== $rootReal && !str_starts_with($real, $rootReal . '/')) {
            throw new \RuntimeException('Path escapes source root: ' . $relativePath);
        }

        return $real;
    }

    /**
     * Assert that $absolutePath resolves inside $sourceRoot. Used to gate
     * browse/share operations so callers can't pass arbitrary paths.
     */
    public function assertInsideSource(string $sourceRoot, string $absolutePath): void
    {
        $rootReal = realpath($sourceRoot);
        $real = realpath($absolutePath);
        if ($rootReal === false || $real === false) {
            throw new \RuntimeException('Path does not exist');
        }
        if ($real !== $rootReal && !str_starts_with($real, $rootReal . '/')) {
            throw new \RuntimeException('Path escapes source root');
        }
    }

    /**
     * List direct children of $absolutePath (which must be inside an allowed
     * root, and inside $sourceRoot if provided). Returns the same shape as
     * scanRecursive() but only one level deep. Skips symlinks.
     */
    public function listDirectory(string $absolutePath, ?string $sourceRoot = null): array
    {
        if (!$this->isPathAllowed($absolutePath)) {
            throw new \RuntimeException('Library path is not inside an allowed root: ' . $absolutePath);
        }
        if ($sourceRoot !== null) {
            $this->assertInsideSource($sourceRoot, $absolutePath);
        }

        $real = realpath($absolutePath);
        if ($real === false || !is_dir($real)) {
            return [];
        }

        $results = [];
        $iter = new \DirectoryIterator($real);
        foreach ($iter as $entry) {
            if ($entry->isDot() || $entry->isLink()) {
                continue;
            }
            $path = $entry->getPathname();
            $isDir = $entry->isDir();
            $results[] = [
                'path' => $path,
                'name' => $entry->getFilename(),
                'parent_path' => $real,
                'relative_path' => $sourceRoot
                    ? ltrim(substr($path, strlen(realpath($sourceRoot))), '/')
                    : ltrim(substr($path, strlen($real)), '/'),
                'size_bytes' => $isDir ? $this->dirSize($path) : (int) $entry->getSize(),
                'is_directory' => $isDir,
                'mime_type' => $isDir ? null : $this->guessMime($path),
            ];
        }

        usort($results, fn($a, $b) => ($b['is_directory'] <=> $a['is_directory']) ?: strcasecmp($a['name'], $b['name']));
        return $results;
    }

    /**
     * Add a directory (or single file) to an already-open ZipStream. Paths
     * inside the ZIP mirror the directory structure relative to the
     * directory's basename. Used for bulk-share downloads where several
     * selections get packaged into one archive.
     */
    public function addDirectoryToZip(ZipStream $zip, string $absolutePath, string $prefixInZip = ''): void
    {
        if (!$this->isPathAllowed($absolutePath)) {
            throw new \RuntimeException('Library path is not inside an allowed root: ' . $absolutePath);
        }
        $real = realpath($absolutePath);
        $baseName = basename($real);

        if (!is_dir($real)) {
            $entryName = trim($prefixInZip . '/' . $baseName, '/');
            $this->addFileToZip($zip, $real, $entryName);
            return;
        }

        $zipBase = trim($prefixInZip . '/' . $baseName, '/');

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($real, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iter as $f) {
            /** @var \SplFileInfo $f */
            if ($f->isLink() || !$f->isFile()) {
                continue;
            }
            $relative = ltrim(substr($f->getPathname(), strlen($real)), '/');
            $entryName = trim($zipBase . '/' . $relative, '/');
            $this->addFileToZip($zip, $f->getPathname(), $entryName);
        }
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

    /**
     * Compute the absolute filesystem path where a file or folder with
     * $relativePath (under $sourceRoot) should live. Does NOT require the
     * file to exist — used for writes. Refuses symlinks and any path that
     * contains .. components.
     */
    public function resolveWriteTarget(string $sourceRoot, string $relativePath): string
    {
        $rootReal = realpath($sourceRoot);
        if ($rootReal === false) {
            throw new \RuntimeException('Source root does not exist: ' . $sourceRoot);
        }
        $rootReal = rtrim($rootReal, '/');
        $relative = ltrim($relativePath, '/');
        if ($relative === '' || $relative === '.') {
            return $rootReal;
        }
        if (str_contains($relative, "\0")) {
            throw new \RuntimeException('Invalid path');
        }
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '..' || $segment === '.') {
                throw new \RuntimeException('Path contains .. or . segments');
            }
        }
        return $rootReal . '/' . $relative;
    }

    /**
     * Write $sourceStream to $relativePath inside $sourceRoot. Streams
     * the bytes (no full-file buffering), then chmods to 0644.
     * Creates parent directories as needed. Returns the absolute path
     * of the file that was written.
     */
    public function writeStream(string $sourceRoot, string $relativePath, $sourceStream, int $maxBytes = -1): string
    {
        $absolute = $this->resolveWriteTarget($sourceRoot, $relativePath);

        if (is_dir($absolute)) {
            throw new \RuntimeException('Cannot overwrite a directory with a file');
        }

        $parent = dirname($absolute);
        $rootReal = realpath($sourceRoot);
        if ($rootReal === false) {
            throw new \RuntimeException('Source root does not exist: ' . $sourceRoot);
        }
        $parentReal = realpath($parent);
        if ($parentReal === false || ($parentReal !== $rootReal && !str_starts_with($parentReal, rtrim($rootReal, '/') . '/'))) {
            throw new \RuntimeException('Parent directory is outside the source root');
        }
        if (!is_dir($parent) && !@mkdir($parent, 0775, true) && !is_dir($parent)) {
            throw new \RuntimeException('Could not create parent directory: ' . $parent);
        }

        $tmp = $absolute . '.partial-' . bin2hex(random_bytes(4));
        $dest = fopen($tmp, 'wb');
        if ($dest === false) {
            throw new \RuntimeException('Could not open destination for write');
        }
        $written = 0;
        try {
            while (!feof($sourceStream)) {
                $chunk = fread($sourceStream, 65536);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $written += strlen($chunk);
                if ($maxBytes > 0 && $written > $maxBytes) {
                    throw new \RuntimeException('Upload exceeds maximum size');
                }
                fwrite($dest, $chunk);
            }
        } finally {
            if (is_resource($dest)) {
                fclose($dest);
            }
        }

        if (!@rename($tmp, $absolute)) {
            @unlink($tmp);
            throw new \RuntimeException('Could not finalize file');
        }
        @chmod($absolute, 0644);
        return $absolute;
    }

    /**
     * Create a directory at $relativePath inside $sourceRoot. Idempotent:
     * if the directory already exists, returns its absolute path.
     */
    public function mkdir(string $sourceRoot, string $relativePath): string
    {
        $absolute = $this->resolveWriteTarget($sourceRoot, $relativePath);
        $rootReal = realpath($sourceRoot);
        if ($rootReal === false) {
            throw new \RuntimeException('Source root does not exist: ' . $sourceRoot);
        }

        if (is_dir($absolute)) {
            return $absolute;
        }
        if (file_exists($absolute)) {
            throw new \RuntimeException('Path already exists as a file');
        }
        if (!@mkdir($absolute, 0775, true) && !is_dir($absolute)) {
            throw new \RuntimeException('Could not create directory: ' . $relativePath);
        }
        @chmod($absolute, 0755);
        return $absolute;
    }

    /**
     * Remove a file or directory at $absolutePath. The path must already
     * resolve to something inside $sourceRoot. Throws if the path doesn't
     * exist. Symlink targets are not followed — we delete the link only.
     */
    public function remove(string $sourceRoot, string $absolutePath): void
    {
        $this->assertInsideSource($sourceRoot, $absolutePath);
        $real = realpath($absolutePath);
        if ($real === false) {
            throw new \RuntimeException('Path does not exist');
        }
        $rootReal = realpath($sourceRoot);
        if ($real === $rootReal) {
            throw new \RuntimeException('Cannot delete a source root');
        }

        if (is_link($absolutePath)) {
            if (!@unlink($absolutePath)) {
                throw new \RuntimeException('Could not delete symlink');
            }
            return;
        }

        if (is_file($real) || is_file($absolutePath)) {
            if (!@unlink($real)) {
                throw new \RuntimeException('Could not delete file');
            }
            return;
        }

        if (is_dir($real)) {
            $this->rmdirRecursive($real);
            return;
        }

        throw new \RuntimeException('Path is neither file nor directory');
    }

    private function rmdirRecursive(string $dir): void
    {
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iter as $f) {
            if ($f->isLink() || $f->isFile()) {
                @unlink($f->getPathname());
            } elseif ($f->isDir()) {
                @rmdir($f->getPathname());
            }
        }
        if (!@rmdir($dir)) {
            throw new \RuntimeException('Could not delete directory: ' . $dir);
        }
    }
}
