<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UploadSession;
use App\Service\UploadSessionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/upload/resumable')]
#[IsGranted('ROLE_USER')]
class ResumableUploadController extends AbstractController
{
    public function __construct(
        private UploadSessionService $uploadSessionService,
    ) {
    }

    /**
     * Discovery / capability for the endpoint root and any {id}.
     */
    #[Route('', name: 'app_upload_resumable_root', methods: ['OPTIONS'])]
    #[Route('/{id}', name: 'app_upload_resumable_options', methods: ['OPTIONS'], requirements: ['id' => '[a-f0-9]{32}'])]
    public function options(string $id = null): Response
    {
        return $this->empty(204);
    }

    /**
     * Create a session.
     */
    #[Route('', name: 'app_upload_resumable_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $length = (int) ($request->headers->get('Upload-Length') ?? 0);
        $metadataHeader = (string) $request->headers->get('Upload-Metadata', '');
        $meta = $this->parseMetadata($metadataHeader);
        $mime = $request->headers->get('Upload-Mime-Type');

        if ($length <= 0) {
            return $this->json(['error' => 'Upload-Length required'], 400);
        }

        try {
            $session = $this->uploadSessionService->create($user, $length, $meta, $mime);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Could not start upload: ' . $e->getMessage()], 500);
        }

        $url = $this->generateUrl('app_upload_resumable_patch', ['id' => $session->getId()], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);

        $response = $this->empty(201);
        $response->headers->set('Location', $url);
        $response->headers->set('Upload-Offset', '0');
        $response->headers->set('Upload-Expires', $session->getExpiresAt()->format(\DateTimeInterface::RFC7231));
        return $response;
    }

    /**
     * Upload a chunk. tus sends the chunk body, Content-Length equals the
     * chunk size, Upload-Offset is the file position the chunk should land at.
     */
    #[Route('/{id}', name: 'app_upload_resumable_patch', methods: ['PATCH'], requirements: ['id' => '[a-f0-9]{32}'])]
    public function patch(string $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $session = $this->uploadSessionService->getOwnedById($id, $user);
        if (!$session) {
            return $this->json(['error' => 'Session not found'], 404);
        }
        if ($session->isComplete()) {
            return $this->empty(409);
        }

        $expectedOffset = (int) ($request->headers->get('Upload-Offset') ?? -1);
        if ($expectedOffset !== $session->getOffsetBytes()) {
            return $this->empty(409);
        }

        $tempPath = $session->getTempPath();
        $incoming = $request->getContent();
        $bytes = strlen($incoming);

        $written = @file_put_contents($tempPath, $incoming, FILE_APPEND | LOCK_EX);
        if ($written === false || $written !== $bytes) {
            return $this->json(['error' => 'Write failed'], 500);
        }

        $this->uploadSessionService->recordChunk($session, $bytes);

        $response = $this->empty(204);
        $response->headers->set('Upload-Offset', (string) $session->getOffsetBytes());
        $response->headers->set('Upload-Expires', $session->getExpiresAt()->format(\DateTimeInterface::RFC7231));
        return $response;
    }

    /**
     * Report current Upload-Offset so the client can resume.
     */
    #[Route('/{id}', name: 'app_upload_resumable_head', methods: ['HEAD'], requirements: ['id' => '[a-f0-9]{32}'])]
    public function head(string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $session = $this->uploadSessionService->getOwnedById($id, $user);
        if (!$session) {
            return $this->empty(404);
        }

        $response = $this->empty(200);
        $response->headers->set('Upload-Offset', (string) $session->getOffsetBytes());
        $response->headers->set('Upload-Length', (string) $session->getSizeBytes());
        $response->headers->set('Cache-Control', 'no-store');
        return $response;
    }

    /**
     * Cancel and free quota.
     */
    #[Route('/{id}', name: 'app_upload_resumable_delete', methods: ['DELETE'], requirements: ['id' => '[a-f0-9]{32}'])]
    public function delete(string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $session = $this->uploadSessionService->getOwnedById($id, $user);
        if (!$session) {
            return $this->empty(404);
        }

        $this->uploadSessionService->cancel($session);

        return $this->empty(204);
    }

    /**
     * App-specific finalize: client calls this after the last chunk. Creates
     * the Transfer, returns the share URL.
     */
    #[Route('/{id}/finalize', name: 'app_upload_resumable_finalize', methods: ['POST'], requirements: ['id' => '[a-f0-9]{32}'])]
    public function finalize(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $session = $this->uploadSessionService->getOwnedById($id, $user);
        if (!$session) {
            return $this->json(['error' => 'Session not found'], 404);
        }
        if (!$session->isComplete()) {
            return $this->json([
                'error' => 'Upload incomplete',
                'offset' => $session->getOffsetBytes(),
                'length' => $session->getSizeBytes(),
            ], 409);
        }

        try {
            $transfer = $this->uploadSessionService->finalize($session);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Could not finalize: ' . $e->getMessage()], 500);
        }

        $rawToken = $transfer->getRawToken();
        $downloadUrl = $this->generateUrl(
            'app_download',
            ['token' => $rawToken],
            \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL
        );

        $file = $transfer->getFiles()->first();

        return $this->json([
            'success' => true,
            'transfer' => [
                'id' => $transfer->getId(),
                'token' => $rawToken,
                'downloadUrl' => $downloadUrl,
                'filename' => $transfer->getOriginalFilename(),
                'size' => $transfer->getFormattedSize(),
                'mimeType' => $file?->getMimeType(),
                'expiresAt' => $transfer->getExpiresAt()->format('c'),
                'maxDownloads' => $transfer->getMaxDownloads(),
            ],
        ]);
    }

    /**
     * Parse the tus Upload-Metadata header (key b64value, key b64value, ...).
     *
     * @return array<string,string>
     */
    private function parseMetadata(string $header): array
    {
        $meta = [];
        foreach (explode(',', $header) as $pair) {
            $pair = trim($pair);
            if ($pair === '') continue;
            $parts = preg_split('/\s+/', $pair, 2);
            $key = $parts[0] ?? '';
            $value = $parts[1] ?? '';
            if ($key === '') continue;
            $decoded = base64_decode($value, true);
            $meta[$key] = $decoded === false ? $value : $decoded;
        }
        return $meta;
    }

    private function empty(int $status = 200): Response
    {
        $r = new Response('', $status);
        $r->headers->set('Tus-Resumable', '1.0.0');
        return $r;
    }
}