<?php

namespace App\Command;

use App\Entity\SavedTransferToken;
use App\Entity\Transfer;
use App\Entity\User;
use App\Repository\SavedTransferTokenRepository;
use App\Repository\TransferRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:transfer:backfill-tokens',
    description: 'Mint fresh raw tokens for transfers that have no saved token (legacy transfers)',
)]
class BackfillTransferTokensCommand extends Command
{
    public function __construct(
        private TransferRepository $transferRepository,
        private SavedTransferTokenRepository $savedTokenRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would change without writing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $existingTransferIds = array_map(
            fn(SavedTransferToken $s) => $s->getTransfer()->getId(),
            $this->savedTokenRepository->findAll()
        );

        $allTransfers = $this->transferRepository->findAll();
        $missing = array_filter($allTransfers, fn(Transfer $t) => !in_array($t->getId(), $existingTransferIds, true));

        $io->writeln(sprintf('Found <info>%d</info> transfers without a saved token (out of %d).', count($missing), count($allTransfers)));

        if ($dryRun) {
            $io->note('Dry run — no changes written.');
            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($missing as $transfer) {
            $rawToken = bin2hex(random_bytes(48));
            $tokenHash = hash('sha256', $rawToken);

            $transfer->setTokenHash($tokenHash);

            $saved = new SavedTransferToken();
            $saved->setUser($transfer->getUser())
                ->setTransfer($transfer)
                ->setRawToken($rawToken);
            $this->entityManager->persist($saved);

            $count++;
        }

        $this->entityManager->flush();
        $io->success(sprintf('Minted fresh tokens for %d legacy transfer(s). Their old share links no longer work — recipients will need the new link.', $count));

        return Command::SUCCESS;
    }
}
