<?php

namespace App\Command;

use App\Message\CleanupExpiredTransfers;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Repository\TransferRepository;
use App\Storage\StorageInterface;
use Doctrine\ORM\EntityManagerInterface;

#[AsCommand(
    name: 'app:cleanup-expired-transfers',
    description: 'Remove expired and exhausted transfers',
)]
class CleanupExpiredTransfersCommand extends Command
{
    public function __construct(
        private TransferRepository $transferRepository,
        private EntityManagerInterface $entityManager,
        private StorageInterface $storage,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Cleaning up expired transfers');

        $transfers = $this->transferRepository->findExpiredOrExhausted();
        $count = 0;

        foreach ($transfers as $transfer) {
            try {
                foreach ($transfer->getFiles() as $file) {
                    if ($this->storage->exists($file->getStoredFilename())) {
                        $this->storage->delete($file->getStoredFilename());
                    }
                }

                $this->entityManager->remove($transfer);
                $count++;
                $io->text("Removed: {$transfer->getId()}");
            } catch (\Throwable $e) {
                $io->error("Failed to cleanup transfer {$transfer->getId()}: {$e->getMessage()}");
            }
        }

        $this->entityManager->flush();
        $io->success("Cleaned up {$count} expired/exhausted transfers");

        return Command::SUCCESS;
    }
}