<?php

namespace App\Service;

use App\Entity\SavedTransferToken;
use App\Entity\Transfer;
use App\Entity\TransferFile;
use App\Entity\User;
use App\Message\SendTransferEmail;
use App\Repository\SavedTransferTokenRepository;
use App\Repository\TransferRepository;
use App\Storage\StorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;

class TransferService
{
    public function __construct(
        private TransferRepository $transferRepository,
        private SavedTransferTokenRepository $savedTokenRepository,
        private EntityManagerInterface $entityManager,
        private StorageInterface $storage,
        private MessageBusInterface $messageBus,
        private int $defaultExpiryDays,
        private int $defaultMaxDownloads,
    ) {
    }

    private function saveTokenForOwner(Transfer $transfer, User $user): void
    {
        if ($transfer->getRawToken() === null) {
            return;
        }
        $existing = $this->savedTokenRepository->findOneByUserAndTransfer($user, $transfer);
        if ($existing) {
            return;
        }
        $saved = new SavedTransferToken();
        $saved->setUser($user)
            ->setTransfer($transfer)
            ->setRawToken($transfer->getRawToken());
        $this->entityManager->persist($saved);
        $this->entityManager->flush();
    }

    public function createFileTransfer(
        User $user,
        array $files,
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
        $transfer->setUser($user)
            ->setTokenHash($tokenHash)
            ->setRawToken($rawToken)
            ->setMaxDownloads($maxDownloads)
            ->setExpiresAt(new \DateTimeImmutable("+{$expiryDays} days"))
            ->setRecipientEmail($recipientEmail)
            ->setMessage($message);

        if ($password) {
            $transfer->setPasswordHash(password_hash($password, PASSWORD_BCRYPT));
        }

        $totalSize = 0;
        foreach ($files as $file) {
            $uploadResult = $this->storage->uploadFile($file);

            $transferFile = new TransferFile();
            $transferFile->setTransfer($transfer)
                ->setOriginalFilename($this->sanitizeFilename($file->getClientOriginalName()))
                ->setStoredFilename($uploadResult['path'])
                ->setMimeType($uploadResult['mimeType'])
                ->setSizeBytes($uploadResult['size']);

            $this->entityManager->persist($transferFile);
            $transfer->addFile($transferFile);
            $totalSize += $uploadResult['size'];
        }

        $transfer->setTotalSizeBytes($totalSize);

        $this->entityManager->persist($transfer);
        $this->entityManager->flush();

        $this->saveTokenForOwner($transfer, $user);

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

    public function createTextTransfer(
        User $user,
        string $filename,
        string $content,
        int $maxDownloads = null,
        int $expiryDays = null,
        ?string $password = null,
        ?string $recipientEmail = null,
        ?string $message = null,
    ): Transfer {
        $maxDownloads = $maxDownloads ?? $this->defaultMaxDownloads;
        $expiryDays = $expiryDays ?? $this->defaultExpiryDays;

        $safeFilename = $this->sanitizeFilename($filename);
        if (!str_ends_with($safeFilename, '.txt')) {
            $safeFilename .= '.txt';
        }

        $uploadResult = $this->storage->writeTextContent($content, $safeFilename);

        $rawToken = bin2hex(random_bytes(48));
        $tokenHash = hash('sha256', $rawToken);

        $transfer = new Transfer();
        $transfer->setUser($user)
            ->setTokenHash($tokenHash)
            ->setRawToken($rawToken)
            ->setMaxDownloads($maxDownloads)
            ->setExpiresAt(new \DateTimeImmutable("+{$expiryDays} days"))
            ->setRecipientEmail($recipientEmail)
            ->setMessage($message);

        if ($password) {
            $transfer->setPasswordHash(password_hash($password, PASSWORD_BCRYPT));
        }

        $transferFile = new TransferFile();
        $transferFile->setTransfer($transfer)
            ->setOriginalFilename($safeFilename)
            ->setStoredFilename($uploadResult['path'])
            ->setMimeType('text/plain')
            ->setSizeBytes($uploadResult['size'])
            ->setIsText(true)
            ->setTextContent($content);

        $this->entityManager->persist($transferFile);
        $transfer->addFile($transferFile);
        $transfer->setTotalSizeBytes($uploadResult['size']);

        $this->entityManager->persist($transfer);
        $this->entityManager->flush();

        $this->saveTokenForOwner($transfer, $user);

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

    public function addFileToTransfer(Transfer $transfer, UploadedFile $file): TransferFile
    {
        $uploadResult = $this->storage->uploadFile($file);

        $transferFile = new TransferFile();
        $transferFile->setTransfer($transfer)
            ->setOriginalFilename($this->sanitizeFilename($file->getClientOriginalName()))
            ->setStoredFilename($uploadResult['path'])
            ->setMimeType($uploadResult['mimeType'])
            ->setSizeBytes($uploadResult['size']);

        $this->entityManager->persist($transferFile);
        $transfer->addFile($transferFile);
        $transfer->recalculateTotalSize();

        $this->entityManager->flush();
        return $transferFile;
    }

    public function deleteTransfer(Transfer $transfer): void
    {
        if (!$transfer->isFromLibrary()) {
            foreach ($transfer->getFiles() as $file) {
                if ($this->storage->exists($file->getStoredFilename())) {
                    $this->storage->delete($file->getStoredFilename());
                }
            }
        }

        $this->entityManager->remove($transfer);
        $this->entityManager->flush();
    }

    public function revokeTransfer(Transfer $transfer): void
    {
        $transfer->setIsRevoked(true);
        $this->entityManager->flush();
    }

    public function extendExpiry(Transfer $transfer, int $days): void
    {
        $newExpiry = $transfer->getExpiresAt()->modify("+{$days} days");
        $transfer->setExpiresAt($newExpiry);
        $this->entityManager->flush();
    }

    public function resendEmail(Transfer $transfer): void
    {
        if ($transfer->getRecipientEmail()) {
            $rawToken = $transfer->getRawToken();
            if (!$rawToken) {
                $rawToken = bin2hex(random_bytes(48));
                $tokenHash = hash('sha256', $rawToken);
                $transfer->setTokenHash($tokenHash);
                $transfer->setRawToken($rawToken);
                $this->entityManager->flush();
            }
            $this->messageBus->dispatch(new SendTransferEmail(
                $transfer->getId(),
                $transfer->getRecipientEmail(),
                $rawToken,
                $transfer->getMessage()
            ));
        }
    }

    public function flushChanges(Transfer $transfer): void
    {
        $this->entityManager->flush();
    }

    private function sanitizeFilename(string $filename): string
    {
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        $filename = preg_replace('/_+/', '_', $filename);
        $filename = trim($filename, '_');

        if (empty($filename) || $filename === '.') {
            $filename = 'file_' . bin2hex(random_bytes(4));
        }

        if (strlen($filename) > 255) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $name = pathinfo($filename, PATHINFO_FILENAME);
            $name = substr($name, 0, 250 - strlen($ext) - 1);
            $filename = $name . '.' . $ext;
        }

        return $filename;
    }
}