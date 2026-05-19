<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\FileRequestRepository;
use App\Service\FileRequestService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/file-requests')]
#[IsGranted('ROLE_USER')]
class FileRequestController extends AbstractController
{
    public function __construct(
        private FileRequestService $fileRequestService,
        private FileRequestRepository $fileRequestRepository,
    ) {
    }

    #[Route('/', name: 'app_file_requests')]
    public function list(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $requests = $this->fileRequestRepository->findByUserOrderedByRecent($user->getId());

        $session = $request->getSession();
        $tokenMap = $session->get('file_request_tokens', []);

        $resolvedRequests = [];
        foreach ($requests as $fr) {
            $resolvedRequests[] = [
                'entity' => $fr,
                'rawToken' => $tokenMap[$fr->getId()] ?? null,
            ];
        }

        return $this->render('file_request/list.html.twig', [
            'resolvedRequests' => $resolvedRequests,
        ]);
    }

    #[Route('/new', name: 'app_file_request_create', methods: ['GET'])]
    public function showCreateForm(): \Symfony\Component\HttpFoundation\Response
    {
        return $this->render('file_request/create.html.twig');
    }

    #[Route('/create', name: 'app_file_request_store', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $name = trim($request->request->get('name', ''));
        if (empty($name)) {
            return $this->json(['error' => 'Name is required'], 400);
        }

        $description = $request->request->get('description');
        $password = $request->request->get('password');
        $maxFiles = (int) ($request->request->get('max_files', 10));
        $expiryDays = (int) ($request->request->get('expiry_days', 30));

        $fileRequest = $this->fileRequestService->createRequest(
            $user,
            $name,
            $description ?: null,
            max(1, min($maxFiles, 100)),
            1073741824,
            5368709120,
            $password ?: null,
            max(1, min($expiryDays, 90)),
        );

        $rawToken = $fileRequest->getRawToken();

        $session = $request->getSession();
        $tokenMap = $session->get('file_request_tokens', []);
        $tokenMap[$fileRequest->getId()] = $rawToken;
        $session->set('file_request_tokens', $tokenMap);

        $uploadUrl = $this->generateUrl('app_file_request_upload', ['token' => $rawToken], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->json([
            'success' => true,
            'fileRequest' => [
                'id' => $fileRequest->getId(),
                'name' => $fileRequest->getName(),
                'uploadUrl' => $uploadUrl,
                'token' => $rawToken,
                'expiresAt' => $fileRequest->getExpiresAt()->format('c'),
                'maxFiles' => $fileRequest->getMaxFiles(),
            ],
        ]);
    }

    #[Route('/{id}/deactivate', name: 'app_file_request_deactivate', methods: ['POST'])]
    public function deactivate(string $id): JsonResponse
    {
        $fileRequest = $this->fileRequestRepository->find($id);
        if (!$fileRequest) {
            return $this->json(['error' => 'Not found'], 404);
        }

        if ($fileRequest->getUser()->getId() !== $this->getUser()->getId()) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        $this->fileRequestService->deactivateRequest($fileRequest);
        return $this->json(['success' => true]);
    }

    #[Route('/{id}/activate', name: 'app_file_request_activate', methods: ['POST'])]
    public function activate(string $id): JsonResponse
    {
        $fileRequest = $this->fileRequestRepository->find($id);
        if (!$fileRequest) {
            return $this->json(['error' => 'Not found'], 404);
        }

        if ($fileRequest->getUser()->getId() !== $this->getUser()->getId()) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        $this->fileRequestService->activateRequest($fileRequest);
        return $this->json(['success' => true]);
    }

    #[Route('/{id}/delete', name: 'app_file_request_delete', methods: ['POST'])]
    public function delete(string $id): JsonResponse
    {
        $fileRequest = $this->fileRequestRepository->find($id);
        if (!$fileRequest) {
            return $this->json(['error' => 'Not found'], 404);
        }

        if ($fileRequest->getUser()->getId() !== $this->getUser()->getId() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        $this->fileRequestService->deleteRequest($fileRequest);
        return $this->json(['success' => true]);
    }
}