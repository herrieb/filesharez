<?php

namespace App\Service;

use App\Entity\Transfer;
use App\Entity\TransferFile;
use App\Entity\UploadSession;
use App\Entity\User;
use App\Message\SendTransferEmail;
use App\Repository\UploadSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Storage\LocalStorage;
use TusPhp\Cache\CacheFactory;
use TusPhp\Tus\Server as TusServer;

class UploadSessionService
{
    public const SESSION_TTL_HOURS = 24;
    public const TUS_TEMP_DIR = 'var/tus';

    private TusServer $tusServer;

    public function __construct(
        private UploadSessionRepository $sessionRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
        private \App\Storage\StorageInterface $storage,
        private string $projectDir,
    ) {
        $cache = CacheFactory::make('file');
        $this->tusServer = new TusServer($cache);
        $this->tusServer->setApiPath('/upload/resumable');
        $this->tusServer->setUploadDir($projectDir . '/' . self::TUS_TEMP_DIR);
    }

    public function getTusServer(): TusServer
    {
        return $this->tusServer;
    }

    /**
     * Pre-allocate a session: reserve quota, create the row + temp file.
     * Returns the session id (used as the tus URL).
     *
     * @param array{filename?:string,filetype?:string,max_downloads?:int,expiry_days?:int,password?:string,recipient_email?:string,message?:string} $tusMetadata
     */
    public function create(User $user, int $sizeBytes, array $tusMetadata, ?string $mimeType = null): UploadSession
    {
        if ($sizeBytes <= 0) {
            throw new \InvalidArgumentException('Upload-Length must be > 0');
        }

        $used = 0;
        foreach ($user->getTransfers() as $t) {
            $used += $t->getTotalSizeBytes();
        }
        $available = $user->getQuotaBytes() - $used - $user->getReservedBytes();
        if ($sizeBytes > $available) {
            throw new \InvalidArgumentException('Upload exceeds available quota');
        }

        $filename = (string) ($tusMetadata['filename'] ?? 'upload.bin');
        $filename = $this->sanitizeFilename($filename);

        $session = new UploadSession();
        $session->setUser($user)
            ->setOriginalFilename($filename)
            ->setMimeType($mimeType ?? ($tusMetadata['filetype'] ?? null))
            ->setSizeBytes($sizeBytes)
            ->setTempPath($this->projectDir . '/' . self::TUS_TEMP_DIR . '/' . $session->getId());

        $shareMeta = array_filter([
            'max_downloads' => isset($tusMetadata['max_downloads']) ? (int) $tusMetadata['max_downloads'] : null,
            'expiry_days' => isset($tusMetadata['expiry_days']) ? (int) $tusMetadata['expiry_days'] : null,
            'password' => $tusMetadata['password'] ?? null,
            'recipient_email' => $tusMetadata['recipient_email'] ?? null,
            'message' => $tusMetadata['message'] ?? null,
        ], fn($v) => $v !== null && $v !== '');
        $session->setMetadataArray($shareMeta);

        $this->ensureTempDirExists();
        touch($session->getTempPath());

        $user->reserveBytes($sizeBytes);

        $this->entityManager->persist($session);
        $this->entityManager->flush();

        return $session;
    }

    public function getOwnedById(string $id, User $user): ?UploadSession
    {
        $s = $this->sessionRepository->find($id);
        if (!$s) return null;
        if ($s->getUser()->getId() !== $user->getId()) {
            return null;
        }
        return $s;
    }

    public function cancel(UploadSession $session): void
    {
        $reserved = $session->getSizeBytes() - $session->getOffsetBytes();
        $session->getUser()->releaseBytes($reserved);

        $this->removeTempFile($session->getTempPath());
        $this->entityManager->remove($session);
        $this->entityManager->flush();
    }

    public function recordChunk(UploadSession $session, int $bytes): void
    {
        $session->addBytes($bytes)->touch();
        $this->entityManager->flush();
    }

    /**
     * Finalize a complete upload: move temp file to LocalStorage, create Transfer
     * + TransferFile, consume the reservation, dispatch email if requested.
     */
    public function finalize(UploadSession $session): Transfer
    {
        if (!$session->isComplete()) {
            throw new \RuntimeException(sprintf(
                'Upload incomplete: %d / %d bytes',
                $session->getOffsetBytes(),
                $session->getSizeBytes()
            ));
        }

        $user = $session->getUser();
        $meta = $session->getMetadataArray();

        $uploadResult = $this->storage->ingestFromPath(
            $session->getTempPath(),
            $session->getOriginalFilename()
        );

        $transfer = new Transfer();
        $rawToken = bin2hex(random_bytes(48));
        $transfer->setUser($user)
            ->setTokenHash(hash('sha256', $rawToken))
            ->setRawToken($rawToken)
            ->setMaxDownloads((int) ($meta['max_downloads'] ?? 1))
            ->setExpiresAt(new \DateTimeImmutable('+' . (int) ($meta['expiry_days'] ?? 7) . ' days'))
            ->setRecipientEmail($meta['recipient_email'] ?? null)
            ->setMessage($meta['message'] ?? null)
            ->setIsFromLibrary(false)
            ->setTotalSizeBytes($uploadResult['size']);
        if (!empty($meta['password'])) {
            $transfer->setPasswordHash(password_hash($meta['password'], PASSWORD_BCRYPT));
        }

        $file = new TransferFile();
        $file->setTransfer($transfer)
            ->setOriginalFilename($session->getOriginalFilename())
            ->setStoredFilename($uploadResult['path'])
            ->setMimeType($uploadResult['mimeType'])
            ->setSizeBytes($uploadResult['size']);
        $transfer->addFile($file);

        $this->entityManager->persist($file);
        $this->entityManager->persist($transfer);

        $user->releaseBytes($session->getSizeBytes());

        $this->entityManager->flush();

        $this->removeTempFile($session->getTempPath());
        $this->entityManager->remove($session);
        $this->entityManager->flush();

        if (!empty($meta['recipient_email'])) {
            $this->messageBus->dispatch(new SendTransferEmail(
                $transfer->getId(),
                $meta['recipient_email'],
                $rawToken,
                $meta['message'] ?? null
            ));
        }

        return $transfer;
    }

    public function ensureTempDirExists(): void
    {
        $dir = $this->projectDir . '/' . self::TUS_TEMP_DIR;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    private function removeTempFile(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function sanitizeFilename(string $filename): string
    {
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        $filename = preg_replace('/_+/', '_', $filename);
        $filename = trim($filename, '_');
        if ($filename === '' || $filename === '.') {
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
