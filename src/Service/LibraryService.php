<?php

namespace App\Service;

use App\Entity\LibraryItem;
use App\Entity\LibrarySource;
use App\Entity\SavedTransferToken;
use App\Entity\Transfer;
use App\Entity\TransferFile;
use App\Entity\User;
use App\Message\SendTransferEmail;
use App\Repository\LibraryItemRepository;
use App\Repository\LibrarySourceRepository;
use App\Repository\SavedTransferTokenRepository;
use App\Storage\LibraryStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class LibraryService
{
    public function __construct(
        private LibrarySourceRepository $sourceRepository,
        private LibraryItemRepository $itemRepository,
        private SavedTransferTokenRepository $savedTokenRepository,
        private EntityManagerInterface $entityManager,
        private LibraryStorage $libraryStorage,
        private MessageBusInterface $messageBus,
        private int $defaultExpiryDays,
        private int $defaultMaxDownloads,
    ) {
    }

    public function createSource(User $owner, string $name, string $path): LibrarySource
    {
        $real = realpath($path);
        if ($real === false || !is_dir($real)) {
            throw new \InvalidArgumentException('Library path does not exist or is not a directory: ' . $path);
        }

        $source = new LibrarySource();
        $source->setOwner($owner)
            ->setName($name)
            ->setPath($real)
            ->setIsActive(true);

        $this->libraryStorage->addAllowedRoot($real);
        $this->entityManager->persist($source);
        $this->entityManager->flush();

        $this->rescanSource($source);
        return $source;
    }

    public function rescanSource(LibrarySource $source): int
    {
        if (!$source->isActive()) {
            return 0;
        }

        $entries = $this->libraryStorage->scanRoot($source->getPath());

        $existingByPath = [];
        foreach ($source->getItems() as $item) {
            $existingByPath[$item->getPath()] = $item;
        }

        $seen = [];
        $totalSize = 0;
        foreach ($entries as $entry) {
            $path = $entry['path'];
            $seen[$path] = true;
            $totalSize += $entry['size_bytes'];

            if (isset($existingByPath[$path])) {
                $item = $existingByPath[$path];
                $item->setName($entry['name'])
                    ->setSizeBytes($entry['size_bytes'])
                    ->setIsDirectory($entry['is_directory'])
                    ->setMimeType($entry['mime_type'])
                    ->setRelativePath($entry['relative_path']);
            } else {
                $item = new LibraryItem();
                $item->setSource($source)
                    ->setPath($path)
                    ->setName($entry['name'])
                    ->setRelativePath($entry['relative_path'])
                    ->setSizeBytes($entry['size_bytes'])
                    ->setIsDirectory($entry['is_directory'])
                    ->setMimeType($entry['mime_type']);
                $this->entityManager->persist($item);
            }
        }

        foreach ($source->getItems() as $item) {
            if (!isset($seen[$item->getPath()])) {
                $this->entityManager->remove($item);
            }
        }

        $source->setItemCount(count($entries))
            ->setTotalSizeBytes($totalSize)
            ->setLastScannedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
        return count($entries);
    }

    public function deleteSource(LibrarySource $source): void
    {
        $this->entityManager->remove($source);
        $this->entityManager->flush();
    }

    /**
     * Build a Transfer for a library item. No upload, no copy.
     * The TransferFile.storedFilename is the absolute library path; the
     * isFromLibrary flag tells the rest of the system not to delete it.
     */
    public function shareItem(
        LibraryItem $item,
        User $owner,
        int $maxDownloads = null,
        int $expiryDays = null,
        ?string $password = null,
        ?string $recipientEmail = null,
        ?string $message = null,
    ): Transfer {
        $maxDownloads = $maxDownloads ?? $this->defaultMaxDownloads;
        $expiryDays = $expiryDays ?? $this->defaultExpiryDays;

        $rawToken = bin2hex(random_bytes(48));
        $tokenHash = hash('sha256', $rawToken);

        $transfer = new Transfer();
        $transfer->setUser($owner)
            ->setTokenHash($tokenHash)
            ->setRawToken($rawToken)
            ->setMaxDownloads($maxDownloads)
            ->setExpiresAt(new \DateTimeImmutable("+{$expiryDays} days"))
            ->setRecipientEmail($recipientEmail)
            ->setMessage($message)
            ->setIsFromLibrary(true)
            ->setLibraryItem($item)
            ->setTotalSizeBytes($item->getSizeBytes());

        if ($password) {
            $transfer->setPasswordHash(password_hash($password, PASSWORD_BCRYPT));
        }

        $file = new TransferFile();
        $file->setTransfer($transfer)
            ->setOriginalFilename($item->getName())
            ->setStoredFilename($item->getPath())
            ->setMimeType($item->getMimeType() ?? 'application/octet-stream')
            ->setSizeBytes($item->getSizeBytes())
            ->setIsText(false);

        $this->entityManager->persist($file);
        $transfer->addFile($file);
        $this->entityManager->persist($transfer);
        $this->entityManager->flush();

        if ($transfer->getRawToken() !== null) {
            $existing = $this->savedTokenRepository->findOneByUserAndTransfer($owner, $transfer);
            if (!$existing) {
                $saved = new SavedTransferToken();
                $saved->setUser($owner)
                    ->setTransfer($transfer)
                    ->setRawToken($transfer->getRawToken());
                $this->entityManager->persist($saved);
                $this->entityManager->flush();
            }
        }

        if ($recipientEmail) {
            $this->messageBus->dispatch(new SendTransferEmail(
                $transfer->getId(),
                $recipientEmail,
                $rawToken,
                $message
            ));
        }

        return $transfer;
    }
}
