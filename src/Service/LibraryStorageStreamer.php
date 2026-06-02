<?php

namespace App\Service;

use App\Storage\LibraryStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Helper that produces a StreamedResponse which streams a library file or
 * directory ZIP to the HTTP client. For directories, the archive is built
 * into a php://temp buffer first (to compute Content-Length and keep the
 * ZIP structurally valid), then replayed. For very large archives this
 * means the client must wait until the ZIP is fully built before receiving
 * any bytes; the time bound is governed by php-fpm's fastcgi_read_timeout.
 */
class LibraryStorageStreamer
{
    public function __construct(
        private LibraryStorage $libraryStorage,
    ) {
    }

    public function buildFileResponse(string $absolutePath, string $filename, ?int $approxSize = null): StreamedResponse
    {
        $isDir = is_dir($absolutePath);

        if ($isDir) {
            if (!str_ends_with(strtolower($filename), '.zip')) {
                $filename .= '.zip';
            }
            $tmp = fopen('php://temp', 'w+b');
            $this->libraryStorage->streamAsZip($absolutePath, $tmp, basename($absolutePath) . '.zip');
            rewind($tmp);
            $size = fstat($tmp)['size'] ?? 0;
            $stream = $tmp;
            $mime = 'application/zip';
        } else {
            $stream = fopen($absolutePath, 'rb');
            $size = $approxSize ?? ($stream !== false ? fstat($stream)['size'] : 0);
            $mime = $this->libraryStorage->mimeType($absolutePath);
        }

        $response = new StreamedResponse(function () use ($stream, $isDir) {
            if (is_resource($stream)) {
                rewind($stream);
                fpassthru($stream);
                fclose($stream);
            }
        });

        $response->headers->set('Content-Type', $mime);
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        if ($size > 0) {
            $response->headers->set('Content-Length', (string) $size);
        }
        $response->headers->set('X-Accel-Buffering', 'no');
        return $response;
    }
}
