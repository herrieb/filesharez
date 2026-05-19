<?php

namespace App\Message;

class SendTransferEmail
{
    public function __construct(
        public string $transferId,
        public string $recipientEmail,
        public string $rawToken,
        public ?string $message = null,
    ) {
    }
}