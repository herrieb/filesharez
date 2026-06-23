<?php

namespace App\Command;

use App\Service\UploadCleanupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:upload:cleanup',
    description: 'Remove expired resumable upload sessions and their temp files',
)]
class UploadCleanupCommand extends Command
{
    public function __construct(
        private UploadCleanupService $cleanupService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = $this->cleanupService->cleanupExpired();
        $io->success("Cleaned up {$count} expired upload sessions");
        return Command::SUCCESS;
    }
}
