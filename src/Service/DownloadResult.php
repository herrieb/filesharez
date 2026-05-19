<?php

namespace App\Service;

class DownloadResult
{
    public function __construct(
        public readonly mixed $stream,
        public readonly string $filename,
        public readonly string $mimeType,
        public readonly int $size,
    ) {
    }
}