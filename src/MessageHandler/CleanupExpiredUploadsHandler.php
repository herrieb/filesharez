<?php

namespace App\MessageHandler;

use App\Message\CleanupExpiredUploads;
use App\Service\UploadCleanupService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CleanupExpiredUploadsHandler
{
    public function __construct(
        private UploadCleanupService $cleanupService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CleanupExpiredUploads $message): void
    {
        try {
            $count = $this->cleanupService->cleanupExpired();
            if ($count > 0) {
                $this->logger->info("Cleaned up {$count} expired upload sessions");
            }
        } catch (\Throwable $e) {
            $this->logger->error('Failed to clean up expired uploads: ' . $e->getMessage());
        }
    }
}
