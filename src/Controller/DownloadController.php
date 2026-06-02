<?php

namespace App\Controller;

use App\Message\SendDownloadNotificationEmail;
use App\Service\DownloadService;
use App\Service\LibraryStorageStreamer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

class DownloadController extends AbstractController
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private LibraryStorageStreamer $libraryStreamer,
    ) {
    }

    #[Route('/d/{token}', name: 'app_download')]
    public function download(string $token, DownloadService $downloadService): StreamedResponse|Response
    {
        $transfer = $downloadService->findByToken($token);

        if (!$transfer) {
            return $this->render('errors/expired.html.twig', [
                'reason' => 'not_found',
            ], new Response('', 404));
        }

        $result = $downloadService->canDownload($transfer);

        if (!$result['allowed']) {
            return $this->render('errors/expired.html.twig', [
                'reason' => $result['reason'],
                'transfer' => $transfer,
            ]);
        }

        $fileCount = $transfer->getFileCount();

        return $this->render('transfer/download.html.twig', [
            'transfer' => $transfer,
            'token' => $token,
            'needsPassword' => $transfer->getPasswordHash() !== null,
            'isMultiFile' => $fileCount > 1,
            'fileCount' => $fileCount,
        ]);
    }

    #[Route('/d/{token}/file/{fileId}', name: 'app_download_file')]
    public function downloadFile(string $token, string $fileId, DownloadService $downloadService, Request $request): StreamedResponse|Response
    {
        $transfer = $downloadService->findByToken($token);

        if (!$transfer) {
            return new Response('', 404);
        }

        $password = $request->request->get('password');
        $result = $downloadService->canDownload($transfer, $password);

        if (!$result['allowed']) {
            return $this->json(['error' => $result['reason']], 403);
        }

        $transferFile = null;
        foreach ($transfer->getFiles() as $file) {
            if ($file->getId() === $fileId) {
                $transferFile = $file;
                break;
            }
        }

        if (!$transferFile) {
            return new Response('', 404);
        }

        $wasFirstDownload = $transfer->getDownloadCount() === 0;
        $downloadResult = $downloadService->download($transfer, $request, $transferFile);

        if ($wasFirstDownload) {
            $this->messageBus->dispatch(new SendDownloadNotificationEmail(
                $transfer->getId(),
                $transfer->getRawToken() ?? '',
                $transfer->getSenderEmail() ?? '',
                $transfer->getSenderName(),
            ));
        }

        // For library-backed files (especially directories that need to be
        // packaged as ZIP on the fly), stream directly to the output so the
        // client starts receiving bytes immediately instead of waiting for
        // the whole archive to buffer in PHP memory.
        if ($transfer->isFromLibrary()) {
            return $this->libraryStreamer->buildFileResponse(
                $transferFile->getStoredFilename(),
                $downloadResult->filename,
                $downloadResult->size > 0 ? $downloadResult->size : null,
            );
        }

        $response = new StreamedResponse(function () use ($downloadResult) {
            fpassthru($downloadResult->stream);
        });

        $response->headers->set('Content-Type', $downloadResult->mimeType);
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $downloadResult->filename . '"');
        $response->headers->set('Content-Length', (string) $downloadResult->size);
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    #[Route('/d/{token}/preview/{fileId}', name: 'app_download_preview')]
    public function previewFile(string $token, string $fileId, DownloadService $downloadService, Request $request): StreamedResponse|Response
    {
        $transfer = $downloadService->findByToken($token);

        if (!$transfer) {
            return new Response('', 404);
        }

        $password = $request->query->get('password') ?? $request->request->get('password');
        $result = $downloadService->canDownload($transfer, $password);
        if (!$result['allowed']) {
            return new Response('', 403);
        }

        $transferFile = null;
        foreach ($transfer->getFiles() as $file) {
            if ($file->getId() === $fileId) {
                $transferFile = $file;
                break;
            }
        }

        if (!$transferFile) {
            return new Response('', 404);
        }

        $stream = $downloadService->readStream($transferFile);
        $mimeType = $transferFile->getMimeType();
        $filename = $transferFile->getOriginalFilename();
        $size = $transferFile->getSizeBytes();

        $inlineTypes = ['image/', 'video/', 'audio/', 'application/pdf', 'text/'];
        $isInline = false;
        foreach ($inlineTypes as $type) {
            if (str_starts_with($mimeType, $type)) {
                $isInline = true;
                break;
            }
        }

        $response = new StreamedResponse(function () use ($stream) {
            fpassthru($stream);
        });

        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Content-Disposition', $isInline ? 'inline' : 'attachment', $filename);
        $response->headers->set('Content-Length', (string) $size);
        $response->headers->set('X-Accel-Buffering', 'no');
        if ($isInline) {
            $response->headers->set('Cache-Control', 'public, max-age=3600');
        }

        return $response;
    }

    #[Route('/d/{token}/text/{fileId}', name: 'app_download_text')]
    public function textContent(string $token, string $fileId, DownloadService $downloadService): Response
    {
        $transfer = $downloadService->findByToken($token);

        if (!$transfer) {
            return $this->json(['error' => 'not_found'], 404);
        }

        $result = $downloadService->canDownload($transfer);
        if (!$result['allowed']) {
            return $this->json(['error' => $result['reason']], 403);
        }

        foreach ($transfer->getFiles() as $file) {
            if ($file->getId() === $fileId && $file->isText() && $file->getTextContent()) {
                return $this->json([
                    'content' => $file->getTextContent(),
                    'filename' => $file->getOriginalFilename(),
                    'mimeType' => $file->getMimeType(),
                ]);
            }
        }

        return $this->json(['error' => 'not_text'], 404);
    }

    #[Route('/d/{token}/zip', name: 'app_download_zip')]
    public function downloadZip(string $token, DownloadService $downloadService, Request $request): StreamedResponse|Response
    {
        $transfer = $downloadService->findByToken($token);

        if (!$transfer) {
            return new Response('', 404);
        }

        $password = $request->request->get('password') ?? $request->query->get('password');
        $result = $downloadService->canDownload($transfer, $password);

        if (!$result['allowed']) {
            return $this->json(['error' => $result['reason']], 403);
        }

        $zipName = 'transfer_' . substr($transfer->getTokenHash(), 0, 8) . '.zip';

        $response = new StreamedResponse(function () use ($transfer, $request, $downloadService) {
            $downloadService->streamZip($transfer, $request);
        });

        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $zipName . '"');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}