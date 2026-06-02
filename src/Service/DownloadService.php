<?php

namespace App\Service;

use App\Entity\DownloadLog;
use App\Entity\Transfer;
use App\Entity\TransferFile;
use App\Repository\DownloadLogRepository;
use App\Repository\TransferRepository;
use App\Storage\LibraryStorage;
use App\Storage\StorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use ZipStream\ZipStream;
use ZipStream\OperationMode;
use ZipStream\CompressionMethod;

class DownloadService
{
    public function __construct(
        private TransferRepository $transferRepository,
        private DownloadLogRepository $downloadLogRepository,
        private EntityManagerInterface $entityManager,
        private StorageInterface $storage,
        private LibraryStorage $libraryStorage,
    ) {
    }

    private function openStream(TransferFile $file)
    {
        if ($file->getTransfer()->isFromLibrary()) {
            return $this->libraryStorage->readStream($file->getStoredFilename());
        }
        return $this->storage->readStream($file->getStoredFilename());
    }

    private function resolveSize(TransferFile $file): int
    {
        if ($file->getTransfer()->isFromLibrary()) {
            return $this->libraryStorage->size($file->getStoredFilename());
        }
        return $file->getSizeBytes();
    }

    private function resolveMime(TransferFile $file): string
    {
        if ($file->getTransfer()->isFromLibrary()) {
            return $this->libraryStorage->mimeType($file->getStoredFilename()) ?: ($file->getMimeType() ?: 'application/octet-stream');
        }
        return $file->getMimeType();
    }

    public function findByToken(string $token): ?Transfer
    {
        $tokenHash = hash('sha256', $token);
        return $this->transferRepository->findByTokenHash($tokenHash);
    }

    public function canDownload(Transfer $transfer, ?string $password = null): array
    {
        if ($transfer->isExpired()) {
            return ['allowed' => false, 'reason' => 'expired'];
        }

        if ($transfer->isExhausted()) {
            return ['allowed' => false, 'reason' => 'exhausted'];
        }

        if ($transfer->isRevoked()) {
            return ['allowed' => false, 'reason' => 'revoked'];
        }

        if ($transfer->getPasswordHash() && !password_verify($password ?? '', $transfer->getPasswordHash())) {
            return ['allowed' => false, 'reason' => 'password'];
        }

        return ['allowed' => true, 'reason' => null];
    }

    public function download(Transfer $transfer, Request $request, ?TransferFile $transferFile = null): DownloadResult
    {
        $transfer->incrementDownloadCount();

        $log = new DownloadLog();
        $log->setTransfer($transfer)
            ->setIpAddress($request->getClientIp())
            ->setUserAgent($request->headers->get('User-Agent'));

        if ($transferFile) {
            $log->setTransferFile($transferFile);
            $transferFile->incrementDownloadCount();
        }

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        $file = $transferFile ?? $transfer->getFiles()->first();
        $stream = $this->openStream($file);
        $mimeType = $this->resolveMime($file);
        $size = $this->resolveSize($file);
        $filename = $file->getOriginalFilename();
        if ($transfer->isFromLibrary() && $this->libraryStorage->exists($file->getStoredFilename())
            && is_dir($file->getStoredFilename())
            && !str_ends_with(strtolower($filename), '.zip')) {
            $filename .= '.zip';
            $mimeType = 'application/zip';
        }

        return new DownloadResult($stream, $filename, $mimeType, $size);
    }

    public function getDownloadLogs(Transfer $transfer): array
    {
        return $this->downloadLogRepository->findByTransfer($transfer->getId());
    }

    public function readStream(TransferFile $transferFile): mixed
    {
        return $this->openStream($transferFile);
    }

    public function streamZip(Transfer $transfer, Request $request): void
    {
        $transfer->incrementDownloadCount();

        $log = new DownloadLog();
        $log->setTransfer($transfer)
            ->setIpAddress($request->getClientIp())
            ->setUserAgent($request->headers->get('User-Agent'));

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        $zipName = 'transfer_' . substr($transfer->getTokenHash(), 0, 8) . '.zip';
        $zip = new ZipStream(
            operationMode: OperationMode::NORMAL,
            outputPathName: 'php://output',
            defaultCompressionMethod: CompressionMethod::STORE,
            outputName: $zipName,
        );

        foreach ($transfer->getFiles() as $file) {
            if ($file->getTransfer()->isFromLibrary()) {
                $this->libraryStorage->streamAsZip(
                    $file->getStoredFilename(),
                    fopen('php://temp', 'w+b'),
                    $zipName,
                );
                continue;
            }

            $stream = $this->storage->readStream($file->getStoredFilename());
            $zip->addFileFromStream(
                fileName: $file->getOriginalFilename(),
                stream: $stream,
                compressionMethod: CompressionMethod::STORE,
                exactSize: $file->getSizeBytes(),
            );
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $zip->finish();
    }
}