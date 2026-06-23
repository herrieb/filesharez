<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\TextUploadType;
use App\Form\UploadType;
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
            'resumableEndpoint' => $this->generateUrl('app_upload_resumable_create', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    #[Route('/file', name: 'app_upload_file_legacy', methods: ['POST'])]
    public function uploadFileLegacy(): JsonResponse
    {
        return $this->json([
            'error' => 'Legacy multipart upload has been removed. Use the resumable /upload/resumable endpoint (tus protocol).',
        ], 410);
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