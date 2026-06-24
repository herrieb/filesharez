<?php

namespace App\Controller;

use App\Entity\LibraryItem;
use App\Entity\LibrarySource;
use App\Entity\User;
use App\Repository\LibraryItemRepository;
use App\Repository\LibrarySourceRepository;
use App\Repository\SavedTransferTokenRepository;
use App\Repository\TransferRepository;
use App\Service\LibraryAccessService;
use App\Service\LibraryService;
use App\Service\LibraryStorageStreamer;
use App\Storage\LibraryStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/library')]
#[IsGranted('ROLE_USER')]
class LibraryController extends AbstractController
{
    public function __construct(
        private LibraryService $libraryService,
        private LibrarySourceRepository $sourceRepository,
        private LibraryItemRepository $itemRepository,
        private SavedTransferTokenRepository $savedTokenRepository,
        private TransferRepository $transferRepository,
        private EntityManagerInterface $entityManager,
        private LibraryStorage $libraryStorage,
        private LibraryStorageStreamer $libraryStreamer,
        private LibraryAccessService $accessService,
    ) {
    }

    #[Route('/', name: 'app_library')]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $sources = $this->sourceRepository->findActiveForOwner($user->getId());

        $items = [];
        foreach ($sources as $source) {
            foreach ($source->getItems() as $item) {
                if ($item->getParentPath() === realpath($source->getPath())) {
                    $items[] = [
                        'entity' => $item,
                        'source' => $source,
                    ];
                }
            }
        }

        usort($items, fn($a, $b) => strcasecmp($a['entity']->getName(), $b['entity']->getName()));

        $savedTokens = [];
        foreach ($sources as $source) {
            foreach ($source->getItems() as $item) {
                if ($item->getParentPath() !== realpath($source->getPath())) {
                    continue;
                }
                $token = $this->savedTokenRepository->findOneByUserAndRelativePath(
                    $user,
                    $source,
                    '/' . ltrim((string) $item->getRelativePath(), '/')
                );
                if ($token) {
                    $savedTokens[$item->getId()] = $token->getRawToken();
                }
            }
        }

        return $this->render('library/index.html.twig', [
            'sources' => $sources,
            'items' => $items,
            'itemTokens' => $savedTokens,
        ]);
    }

    #[Route('/sources/{id}/browse', name: 'app_library_source_browse')]
    public function browseSource(string $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $source = $this->sourceRepository->find($id);
        if (!$source) {
            throw $this->createNotFoundException('Source not found');
        }
        if ($source->getOwner()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $relativePath = $request->query->get('path', '/');
        $includeHidden = $request->query->getBoolean('hidden') || $request->query->get('hidden') === '1';
        $page = max(1, (int) $request->query->get('page', 1));

        try {
            $state = $this->libraryService->browsePath($source, $relativePath, $user, $includeHidden, $page);
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_library');
        }

        return $this->render('library/browse.html.twig', [
            'state' => $state,
            'showHidden' => $includeHidden,
        ]);
    }

    #[Route('/sources', name: 'app_library_source_create', methods: ['POST'])]
    public function createSource(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $name = trim((string) $request->request->get('name', ''));
        $path = trim((string) $request->request->get('path', ''));

        if ($name === '' || $path === '') {
            return $this->json(['error' => 'Name and path are required'], 400);
        }
        if (strlen($name) > 255) {
            return $this->json(['error' => 'Name is too long'], 400);
        }
        if (!preg_match('#^[a-zA-Z0-9 ._\-\[\]()&]+$#', $name)) {
            return $this->json(['error' => 'Name contains invalid characters'], 400);
        }
        if (!preg_match('#^/[a-zA-Z0-9._/\- \[\]()&]+$#', $path)) {
            return $this->json(['error' => 'Path must be an absolute path on the server'], 400);
        }

        try {
            $source = $this->libraryService->createSource($user, $name, $path);
            return $this->json([
                'success' => true,
                'source' => [
                    'id' => $source->getId(),
                    'name' => $source->getName(),
                    'path' => $source->getPath(),
                    'itemCount' => $source->getItemCount(),
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Could not register library: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/sources/{id}/rescan', name: 'app_library_source_rescan', methods: ['POST'])]
    public function rescan(string $id, Request $request): JsonResponse
    {
        $source = $this->sourceRepository->find($id);
        if (!$source) {
            return $this->json(['error' => 'Source not found'], 404);
        }
        if ($source->getOwner()->getId() !== $this->getUser()->getId()) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        $depth = $request->request->get('depth');
        $depth = $depth !== null && $depth !== '' ? max(1, (int) $depth) : null;

        try {
            $count = $this->libraryService->rescanSource($source, $depth);
            return $this->json([
                'success' => true,
                'itemCount' => $count,
                'lastScannedAt' => $source->getLastScannedAt()?->format('c'),
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/sources/{id}/delete', name: 'app_library_source_delete', methods: ['POST'])]
    public function deleteSource(string $id): JsonResponse
    {
        $source = $this->sourceRepository->find($id);
        if (!$source) {
            return $this->json(['error' => 'Source not found'], 404);
        }
        if ($source->getOwner()->getId() !== $this->getUser()->getId() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        $this->libraryService->deleteSource($source);
        return $this->json(['success' => true]);
    }

    /**
     * Share a single item (legacy item-id-based endpoint).
     */
    #[Route('/items/{id}/share', name: 'app_library_item_share', methods: ['POST'])]
    public function share(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $item = $this->itemRepository->find($id);
        if (!$item) {
            return $this->json(['error' => 'Item not found'], 404);
        }
        if ($item->getSource()->getOwner()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Access denied'], 403);
        }
        if (!$item->getSource()->isActive()) {
            return $this->json(['error' => 'Source is not active'], 400);
        }

        $maxDownloads = (int) $request->request->get('max_downloads', 1);
        $expiryDays = (int) $request->request->get('expiry_days', 7);
        $password = $request->request->get('password');
        $recipientEmail = $request->request->get('recipient_email');
        $message = $request->request->get('message');

        try {
            $transfer = $this->libraryService->shareItem(
                $item,
                $user,
                $maxDownloads ?: null,
                $expiryDays ?: null,
                $password ?: null,
                $recipientEmail ?: null,
                $message ?: null,
            );

            $rawToken = $transfer->getRawToken();
            $session = $request->getSession();
            $tokenMap = $session->get('library_tokens', []);
            $tokenMap[$item->getId()] = $rawToken;
            $session->set('library_tokens', $tokenMap);

            return $this->jsonShareResponse($transfer, $item);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Could not create share: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Share one or more paths inside a source. Accepts either:
     *  - sourceId + relativePaths[]  (the new bulk path used by the browse view), or
     *  - sourceId + relativePath     (single path, used when no checkboxes were checked)
     */
    #[Route('/share', name: 'app_library_share', methods: ['POST'])]
    public function shareSelection(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $sourceId = (string) $request->request->get('sourceId', '');
        if ($sourceId === '') {
            return $this->json(['error' => 'sourceId required'], 400);
        }
        $source = $this->sourceRepository->find($sourceId);
        if (!$source) {
            return $this->json(['error' => 'Source not found'], 404);
        }
        if ($source->getOwner()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Access denied'], 403);
        }
        if (!$source->isActive()) {
            return $this->json(['error' => 'Source is not active'], 400);
        }

        $relativePaths = $request->request->all('relativePaths');
        if (!is_array($relativePaths) || count($relativePaths) === 0) {
            $single = (string) $request->request->get('relativePath', '');
            if ($single !== '') {
                $relativePaths = [$single];
            }
        }
        $relativePaths = array_values(array_filter(array_map('strval', (array) $relativePaths), fn($p) => $p !== ''));
        if (count($relativePaths) === 0) {
            return $this->json(['error' => 'No paths selected'], 400);
        }
        if (count($relativePaths) > 500) {
            return $this->json(['error' => 'Too many paths (max 500)'], 400);
        }

        $maxDownloads = $request->request->get('max_downloads');
        $expiryDays = $request->request->get('expiry_days');
        $password = $request->request->get('password');
        $recipientEmail = $request->request->get('recipient_email');
        $message = $request->request->get('message');

        try {
            $transfer = $this->libraryService->shareSelection(
                $source,
                $relativePaths,
                $user,
                $maxDownloads !== null && $maxDownloads !== '' ? (int) $maxDownloads : null,
                $expiryDays !== null && $expiryDays !== '' ? (int) $expiryDays : null,
                $password !== '' ? (string) $password : null,
                $recipientEmail !== '' ? (string) $recipientEmail : null,
                $message !== '' ? (string) $message : null,
            );

            $rawToken = $transfer->getRawToken();
            $downloadUrl = $this->generateUrl(
                'app_download',
                ['token' => $rawToken],
                \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL
            );

            return $this->json([
                'success' => true,
                'transfer' => [
                    'id' => $transfer->getId(),
                    'token' => $rawToken,
                    'downloadUrl' => $downloadUrl,
                    'filename' => $transfer->getOriginalFilename(),
                    'size' => $transfer->getFormattedSize(),
                    'expiresAt' => $transfer->getExpiresAt()->format('c'),
                    'maxDownloads' => $transfer->getMaxDownloads(),
                    'fileCount' => $transfer->getFileCount(),
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Could not create share: ' . $e->getMessage()], 500);
        }
    }

    private function jsonShareResponse(\App\Entity\Transfer $transfer, LibraryItem $item): JsonResponse
    {
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
                'fileId' => $file?->getId(),
                'token' => $rawToken,
                'downloadUrl' => $downloadUrl,
                'filename' => $transfer->getOriginalFilename(),
                'size' => $transfer->getFormattedSize(),
                'expiresAt' => $transfer->getExpiresAt()->format('c'),
                'maxDownloads' => $transfer->getMaxDownloads(),
                'isDirectory' => $item->isDirectory(),
            ],
        ]);
    }

    /**
     * Upload a file into a library source. The relative folder is the
     * `path` field; the file is uploaded as a multipart `file` form field.
     */
    #[Route('/sources/{id}/upload', name: 'app_library_upload', methods: ['POST'])]
    public function uploadFile(string $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $source = $this->sourceRepository->find($id);
        if (!$source) {
            return $this->json(['error' => 'Source not found'], 404);
        }
        if ($source->getOwner()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Access denied'], 403);
        }
        if (!$source->isActive()) {
            return $this->json(['error' => 'Source is not active'], 400);
        }

        $file = $request->files->get('file');
        if ($file === null) {
            return $this->json(['error' => 'No file uploaded'], 400);
        }

        $relativeFolder = (string) $request->request->get('path', '/');

        try {
            $absolute = $this->libraryService->uploadFile($source, $user, $file, $relativeFolder);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Upload failed: ' . $e->getMessage()], 500);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'success' => true,
                'file' => [
                    'name' => basename($absolute),
                    'size' => filesize($absolute),
                    'path' => '/' . ltrim($relativeFolder, '/') . ($relativeFolder === '/' ? '' : '/') . basename($absolute),
                ],
            ]);
        }

        $this->addFlash('success', 'Uploaded ' . basename($absolute) . '.');
        return $this->redirectToRoute('app_library_source_browse', [
            'id' => $source->getId(),
            'path' => '/' . ltrim($relativeFolder, '/'),
        ]);
    }

    /**
     * Create a folder inside a library source.
     */
    #[Route('/sources/{id}/mkdir', name: 'app_library_mkdir', methods: ['POST'])]
    public function createFolder(string $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $source = $this->sourceRepository->find($id);
        if (!$source) {
            return $this->json(['error' => 'Source not found'], 404);
        }
        if ($source->getOwner()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Access denied'], 403);
        }
        if (!$source->isActive()) {
            return $this->json(['error' => 'Source is not active'], 400);
        }

        $relativeFolder = (string) $request->request->get('path', '/');
        $name = trim((string) $request->request->get('name', ''));
        if ($name === '') {
            return $this->json(['error' => 'Folder name is required'], 400);
        }

        try {
            $absolute = $this->libraryService->createFolder($source, $user, $relativeFolder, $name);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Could not create folder: ' . $e->getMessage()], 500);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true, 'name' => basename($absolute)]);
        }

        $this->addFlash('success', 'Created folder ' . basename($absolute) . '.');
        return $this->redirectToRoute('app_library_source_browse', [
            'id' => $source->getId(),
            'path' => '/' . ltrim($relativeFolder, '/'),
        ]);
    }

    /**
     * Delete a file or folder from a library source.
     */
    #[Route('/sources/{id}/item', name: 'app_library_item_delete', methods: ['DELETE', 'POST'])]
    public function deleteItem(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $source = $this->sourceRepository->find($id);
        if (!$source) {
            return $this->json(['error' => 'Source not found'], 404);
        }
        if ($source->getOwner()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Access denied'], 403);
        }
        if (!$source->isActive()) {
            return $this->json(['error' => 'Source is not active'], 400);
        }

        $relativePath = (string) $request->request->get('path', '');
        if ($relativePath === '') {
            return $this->json(['error' => 'path is required'], 400);
        }

        try {
            $this->libraryService->deleteItem($source, $user, $relativePath);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Delete failed: ' . $e->getMessage()], 500);
        }

        return $this->json(['success' => true]);
    }

    /**
     * Owner-direct download of a library file or folder. No transfer token,
     * no expiry — the user is already logged in as the source owner. Folders
     * stream as ZIP on the fly. All accesses are logged.
     */
    #[Route('/sources/{id}/download', name: 'app_library_owner_download', methods: ['GET'])]
    public function ownerDownload(string $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $source = $this->sourceRepository->find($id);
        if (!$source) return new Response('Source not found', 404);
        if ($source->getOwner()->getId() !== $user->getId()) return new Response('Access denied', 403);
        if (!$source->isActive()) return new Response('Source is not active', 400);

        $relativePath = '/' . ltrim((string) $request->query->get('path', '/'), '/');
        if ($relativePath !== '/' && str_contains($relativePath, '..')) {
            return new Response('Invalid path', 400);
        }

        try {
            $absolute = $this->libraryStorage->resolveUnderSource($source->getPath(), $relativePath);
        } catch (\Throwable $e) {
            return new Response('Path not found', 404);
        }

        if (is_dir($absolute)) {
            $this->accessService->record($user, $source, $relativePath, 'zip', null, $request);
            $zipName = basename($absolute) . '.zip';
            $response = new StreamedResponse(function () use ($absolute, $zipName) {
                $this->libraryStorage->streamAsZip($absolute, fopen('php://output', 'wb'), $zipName);
            });
            $response->headers->set('Content-Type', 'application/zip');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $zipName . '"');
            $response->headers->set('X-Accel-Buffering', 'no');
            return $response;
        }

        if (!is_file($absolute)) {
            return new Response('File not found', 404);
        }

        $size = filesize($absolute);
        $name = basename($absolute);
        $this->accessService->record($user, $source, $relativePath, 'download', $size, $request);

        return $this->libraryStreamer->buildFileResponse($absolute, $name, $size);
    }

    /**
     * Owner-direct preview of a library file. Inline for image/video/audio/
     * pdf/text, attachment for everything else. Folders redirect to download.
     */
    #[Route('/sources/{id}/preview', name: 'app_library_owner_preview', methods: ['GET'])]
    public function ownerPreview(string $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $source = $this->sourceRepository->find($id);
        if (!$source) return new Response('Source not found', 404);
        if ($source->getOwner()->getId() !== $user->getId()) return new Response('Access denied', 403);
        if (!$source->isActive()) return new Response('Source is not active', 400);

        $relativePath = '/' . ltrim((string) $request->query->get('path', '/'), '/');
        if ($relativePath !== '/' && str_contains($relativePath, '..')) {
            return new Response('Invalid path', 400);
        }

        try {
            $absolute = $this->libraryStorage->resolveUnderSource($source->getPath(), $relativePath);
        } catch (\Throwable $e) {
            return new Response('Path not found', 404);
        }

        if (is_dir($absolute)) {
            return $this->redirectToRoute('app_library_owner_download', [
                'id' => $source->getId(),
                'path' => $relativePath,
            ]);
        }
        if (!is_file($absolute)) {
            return new Response('File not found', 404);
        }

        $size = filesize($absolute);
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $absolute);
        finfo_close($finfo);
        $mime = $mime ?: 'application/octet-stream';
        $name = basename($absolute);

        $inlineTypes = ['image/', 'video/', 'audio/', 'application/pdf', 'text/'];
        $isInline = false;
        foreach ($inlineTypes as $type) {
            if (str_starts_with($mime, $type)) { $isInline = true; break; }
        }

        $this->accessService->record($user, $source, $relativePath, 'preview', $size, $request);

        $response = new StreamedResponse(function () use ($absolute) {
            $stream = fopen($absolute, 'rb');
            if ($stream) { fpassthru($stream); fclose($stream); }
        });
        $response->headers->set('Content-Type', $mime);
        $response->headers->set('Content-Disposition', $isInline ? 'inline' : 'attachment', $name);
        $response->headers->set('Content-Length', (string) $size);
        $response->headers->set('X-Accel-Buffering', 'no');
        if ($isInline) {
            $response->headers->set('Cache-Control', 'private, max-age=3600');
        }
        return $response;
    }
}