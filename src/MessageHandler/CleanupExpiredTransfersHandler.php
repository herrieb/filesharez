<?php

namespace App\MessageHandler;

use App\Message\CleanupExpiredTransfers;
use App\Repository\TransferRepository;
use App\Storage\StorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CleanupExpiredTransfersHandler
{
    public function __construct(
        private TransferRepository $transferRepository,
        private EntityManagerInterface $entityManager,
        private StorageInterface $storage,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CleanupExpiredTransfers $message): void
    {
        $this->logger->info('Starting cleanup of expired transfers');

        $transfers = $this->transferRepository->findExpiredOrExhausted();
        $count = 0;

        foreach ($transfers as $transfer) {
            try {
                foreach ($transfer->getFiles() as $file) {
                    if ($transfer->isFromLibrary()) {
                        continue;
                    }
                    if ($this->storage->exists($file->getStoredFilename())) {
                        $this->storage->delete($file->getStoredFilename());
                    }
                }

                $this->entityManager->remove($transfer);
                $count++;
            } catch (\Throwable $e) {
                $this->logger->error('Failed to cleanup transfer: ' . $transfer->getId(), [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->entityManager->flush();
        $this->logger->info("Cleaned up {$count} expired/exhausted transfers");
    }
}