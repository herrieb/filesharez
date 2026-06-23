<?php

namespace App\Command;

use App\Repository\LibrarySourceRepository;
use App\Repository\UserRepository;
use App\Service\LibraryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:library:rescan',
    description: 'Rescan all active library sources and refresh their items',
)]
class LibraryRescanCommand extends Command
{
    public function __construct(
        private LibrarySourceRepository $sourceRepository,
        private LibraryService $libraryService,
        private UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('source', 's', InputOption::VALUE_REQUIRED, 'Rescan only the source with this ID')
            ->addOption('add', 'a', InputOption::VALUE_NONE, 'Add a new source (requires --name and --path; --email to set owner)')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Display name for new source (use with --add)')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Absolute path for new source (use with --add)')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Owner email (use with --add)')
            ->addOption('depth', 'd', InputOption::VALUE_REQUIRED, 'Override scan depth (default uses LIBRARY_SCAN_DEPTH env or 5)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('add')) {
            $email = $input->getOption('email');
            $name = $input->getOption('name');
            $path = $input->getOption('path');

            if (!$email || !$name || !$path) {
                $io->error('--add requires --email, --name, and --path');
                return Command::FAILURE;
            }

            $user = $this->userRepository->findOneBy(['email' => $email]);
            if (!$user) {
                $io->error("No user with email {$email}");
                return Command::FAILURE;
            }

            $source = $this->libraryService->createSource($user, $name, $path);
            $io->success(sprintf('Added source "%s" (%s) — %d items', $source->getName(), $source->getPath(), $source->getItemCount()));
            return Command::SUCCESS;
        }

        $sourceId = $input->getOption('source');
        $sources = $sourceId
            ? [$this->sourceRepository->find($sourceId)]
            : $this->sourceRepository->findBy(['isActive' => true]);

        if ($sourceId && !$sources[0]) {
            $io->error("Source {$sourceId} not found");
            return Command::FAILURE;
        }

        $depth = $input->getOption('depth');
        $depth = $depth !== null ? max(1, (int) $depth) : null;

        $total = 0;
        foreach ($sources as $source) {
            $count = $this->libraryService->rescanSource($source, $depth);
            $total += $count;
            $io->writeln(sprintf(
                '<info>%s</info> — %d items (%s)',
                $source->getName(),
                $count,
                $source->getPath()
            ));
        }

        $io->success("Done. {$total} items across " . count($sources) . " source(s).");
        return Command::SUCCESS;
    }
}
