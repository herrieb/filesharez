<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UploadType;
use App\Form\TextUploadType;
use App\Repository\TransferRepository;
use App\Service\TransferService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/upload')]
#[IsGranted('ROLE_USER')]
class UploadController extends AbstractController
{
    public function __construct(
        private TransferService $transferService,
        private TransferRepository $transferRepository,
    ) {
    }

    #[Route('/', name: 'app_upload')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(UploadType::class);
        $textForm = $this->createForm(TextUploadType::class);

        return $this->render('transfer/upload.html.twig', [
            'form' => $form->createView(),
            'textForm' => $textForm->createView(),
            'quotaRemaining' => $user->getQuotaRemaining(),
        ]);
    }

    #[Route('/file', name: 'app_upload_file', methods: ['POST'])]
    public function uploadFile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $files = $request->files->get('files', []);
        $singleFile = $request->files->get('file');

        if ($singleFile) {
            $files[] = $singleFile;
        }

        if (empty($files)) {
            return $this->json(['error' => 'No files provided'], 400);
        }

        $allowedMimeTypes = [
            'application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
            'text/plain', 'text/csv', 'application/zip', 'application/x-rar-compressed',
            'application/x-7z-compressed', 'application/gzip', 'video/mp4', 'video/webm',
            'audio/mpeg', 'audio/ogg', 'audio/wav',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];

        $validatedFiles = [];
        foreach ($files as $file) {
            $mimeType = $file->getMimeType();
            if (!in_array($mimeType, $allowedMimeTypes) && !str_starts_with($mimeType, 'image/') && !str_starts_with($mimeType, 'text/')) {
                $mimeType = 'application/octet-stream';
            }
            $validatedFiles[] = $file;
        }

        $maxDownloads = (int) $request->request->get('max_downloads', 1);
        $expiryDays = (int) $request->request->get('expiry_days', 7);
        $password = $request->request->get('password');
        $recipientEmail = $request->request->get('recipient_email');
        $message = $request->request->get('message');

        $totalSize = 0;
        foreach ($validatedFiles as $f) {
            $totalSize += $f->getSize();
        }
        if ($user->getQuotaRemaining() < $totalSize) {
            return $this->json(['error' => 'Insufficient storage quota. You have ' . $this->formatBytes($user->getQuotaRemaining()) . ' remaining, but this upload requires ' . $this->formatBytes($totalSize) . '.'], 400);
        }

        try {
            $transfer = $this->transferService->createFileTransfer(
                $user,
                $validatedFiles,
                $maxDownloads ?: null,
                $expiryDays ?: null,
                $password ?: null,
                $recipientEmail ?: null,
                $message ?: null,
            );

            $filesData = [];
            foreach ($transfer->getFiles() as $file) {
                $filesData[] = [
                    'id' => $file->getId(),
                    'filename' => $file->getOriginalFilename(),
                    'size' => $file->getFormattedSize(),
                    'mimeType' => $file->getMimeType(),
                ];
            }

            return $this->json([
                'success' => true,
                'transfer' => [
                    'id' => $transfer->getId(),
                    'token' => $transfer->getRawToken(),
                    'downloadUrl' => $this->generateUrl('app_download', ['token' => $transfer->getRawToken()], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL),
                    'files' => $filesData,
                    'fileCount' => $transfer->getFileCount(),
                    'totalSize' => $transfer->getFormattedSize(),
                    'expiresAt' => $transfer->getExpiresAt()->format('c'),
                    'maxDownloads' => $transfer->getMaxDownloads(),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Upload failed: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/text', name: 'app_upload_text', methods: ['POST'])]
    public function uploadText(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $filename = $request->request->get('filename');
        $content = $request->request->get('content');

        if (!$filename || !$content) {
            return $this->json(['error' => 'Filename and content are required'], 400);
        }

        $contentSize = strlen($content);
        if ($user->getQuotaRemaining() < $contentSize) {
            return $this->json(['error' => 'Insufficient storage quota.'], 400);
        }

        $maxDownloads = (int) $request->request->get('max_downloads', 1);
        $expiryDays = (int) $request->request->get('expiry_days', 7);
        $password = $request->request->get('password');
        $recipientEmail = $request->request->get('recipient_email');
        $message = $request->request->get('message');

        try {
            $transfer = $this->transferService->createTextTransfer(
                $user,
                $filename,
                $content,
                $maxDownloads ?: null,
                $expiryDays ?: null,
                $password ?: null,
                $recipientEmail ?: null,
                $message ?: null,
            );

            return $this->json([
                'success' => true,
                'transfer' => [
                    'id' => $transfer->getId(),
                    'token' => $transfer->getRawToken(),
                    'downloadUrl' => $this->generateUrl('app_download', ['token' => $transfer->getRawToken()], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL),
                    'filename' => $transfer->getOriginalFilename(),
                    'size' => $transfer->getFormattedSize(),
                    'expiresAt' => $transfer->getExpiresAt()->format('c'),
                    'maxDownloads' => $transfer->getMaxDownloads(),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Upload failed. Please try again.'], 500);
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 1) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}