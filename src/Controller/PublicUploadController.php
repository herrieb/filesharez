<?php

namespace App\Controller;

use App\Entity\FileRequest;
use App\Entity\User;
use App\Message\SendFileRequestUploadEmail;
use App\Service\FileRequestService;
use App\Service\TransferService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\RateLimiter\Attribute\RateLimit;

class PublicUploadController extends AbstractController
{
    public function __construct(
        private FileRequestService $fileRequestService,
        private TransferService $transferService,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/r/{token}', name: 'app_file_request_upload')]
    public function uploadPage(string $token): Response
    {
        $fileRequest = $this->fileRequestService->findByToken($token);

        if (!$fileRequest) {
            return $this->render('errors/expired.html.twig', [
                'reason' => 'not_found',
            ], new Response('', 404));
        }

        if (!$fileRequest->isAcceptingUploads()) {
            $reason = $fileRequest->isExpired() ? 'expired' : 'unavailable';
            return $this->render('errors/expired.html.twig', [
                'reason' => $reason,
            ]);
        }

        $needsPassword = $fileRequest->getPasswordHash() !== null;

        return $this->render('public_upload/index.html.twig', [
            'fileRequest' => $fileRequest,
            'token' => $token,
            'needsPassword' => $needsPassword,
            'maxFileSize' => $this->formatBytes($fileRequest->getMaxFileSizeBytes()),
        ]);
    }

    #[Route('/r/{token}/upload', name: 'app_file_request_submit', methods: ['POST'])]
    #[RateLimit(name: 'public_upload')]
    public function handleUpload(string $token, Request $request): JsonResponse
    {
        $fileRequest = $this->fileRequestService->findByToken($token);

        if (!$fileRequest) {
            return $this->json(['error' => 'Request not found'], 404);
        }

        if (!$fileRequest->isAcceptingUploads()) {
            $reason = $fileRequest->isExpired() ? 'expired' : 'unavailable';
            return $this->json(['error' => $reason], 400);
        }

        if ($fileRequest->getPasswordHash()) {
            $password = $request->request->get('password');
            if (!password_verify($password ?? '', $fileRequest->getPasswordHash())) {
                return $this->json(['error' => 'Invalid password'], 403);
            }
        }

        if ($fileRequest->getRemainingUploads() <= 0) {
            return $this->json(['error' => 'This request is no longer accepting uploads'], 400);
        }

        $files = $request->files->get('files', []);
        $singleFile = $request->files->get('file');
        if ($singleFile) {
            $files[] = $singleFile;
        }

        if (empty($files)) {
            return $this->json(['error' => 'No files provided'], 400);
        }

        $totalSize = 0;
        foreach ($files as $file) {
            if ($file->getSize() > $fileRequest->getMaxFileSizeBytes()) {
                return $this->json(['error' => 'File ' . $file->getClientOriginalName() . ' exceeds maximum file size'], 400);
            }
            $totalSize += $file->getSize();
        }

        $owner = $fileRequest->getUser();
        $ownerUsed = $owner->getUsedStorage();
        if ($ownerUsed + $totalSize > $owner->getQuotaBytes()) {
            return $this->json(['error' => 'Upload would exceed storage quota'], 400);
        }

        $senderName = $request->request->get('sender_name');
        $senderEmail = $request->request->get('sender_email');

        if (!$senderEmail) {
            return $this->json(['error' => 'Email address is required'], 400);
        }

        $maxDownloads = 1;
        $expiryDays = max(1, (int) $fileRequest->getExpiresAt()->diff(new \DateTimeImmutable())->days);

        try {
            $transfer = $this->transferService->createFileTransfer(
                $owner,
                $files,
                $maxDownloads,
                $expiryDays,
                null,
                null,
                null,
            );

            $transfer->setFileRequest($fileRequest);
            $transfer->setSenderName($senderName);
            $transfer->setSenderEmail($senderEmail);

            $this->transferService->flushChanges($transfer);

            $this->messageBus->dispatch(new SendFileRequestUploadEmail(
                $transfer->getId(),
                $owner->getEmail(),
                $transfer->getRawToken() ?? '',
                $senderName,
                $senderEmail,
            ));

            return $this->json([
                'success' => true,
                'transfer' => [
                    'id' => $transfer->getId(),
                    'fileCount' => $transfer->getFileCount(),
                    'totalSize' => $transfer->getFormattedSize(),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Upload failed: ' . $e->getMessage()], 500);
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 1) . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }
        return number_format($bytes / 1024, 1) . ' KB';
    }
}