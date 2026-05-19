<?php

namespace App\Message;

class SendDownloadNotificationEmail
{
    public function __construct(
        public string $transferId,
        public string $rawToken,
        public string $downloaderEmail,
        public ?string $downloaderName = null,
    ) {
    }
}