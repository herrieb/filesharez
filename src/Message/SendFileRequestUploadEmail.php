<?php

namespace App\Message;

class SendFileRequestUploadEmail
{
    public function __construct(
        public string $transferId,
        public string $ownerEmail,
        public string $rawToken,
        public ?string $senderName = null,
        public ?string $senderEmail = null,
    ) {
    }
}