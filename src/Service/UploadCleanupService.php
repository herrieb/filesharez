<?php

namespace App\Service;

use App\Entity\UploadSession;
use App\Repository\UploadSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class UploadCleanupService
{
    public function __construct(
        private UploadSessionRepository $sessionRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function cleanupExpired(): int
    {
        $expired = $this->sessionRepository->findExpired();
        $count = 0;
        foreach ($expired as $session) {
            try {
                $this->purgeSession($session);
                $count++;
            } catch (\Throwable $e) {
                $this->logger->error('Failed to clean up upload session: ' . $session->getId(), [
                    'error' => $e->getMessage(),
                ]);
            }
        }
        return $count;
    }

    private function purgeSession(UploadSession $session): void
    {
        $session->getUser()->releaseBytes($session->getSizeBytes() - $session->getOffsetBytes());

        if (is_file($session->getTempPath())) {
            @unlink($session->getTempPath());
        }
        $this->entityManager->remove($session);
        $this->entityManager->flush();
    }
}
