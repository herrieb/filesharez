<?php

namespace App\Controller;

use App\Entity\Transfer;
use App\Repository\TransferRepository;
use App\Service\TransferService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/transfers')]
#[IsGranted('ROLE_USER')]
class TransferController extends AbstractController
{
    #[Route('/', name: 'app_transfers')]
    public function list(TransferRepository $transferRepository): \Symfony\Component\HttpFoundation\Response
    {
        $user = $this->getUser();
        $transfers = $transferRepository->findByUserOrderedByRecent($user->getId());

        return $this->render('transfer/list.html.twig', [
            'transfers' => $transfers,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_transfer_delete', methods: ['POST'])]
    public function delete(string $id, TransferRepository $transferRepository, TransferService $transferService): JsonResponse
    {
        $transfer = $transferRepository->find($id);
        if (!$transfer) {
            return $this->json(['error' => 'Transfer not found'], 404);
        }

        if ($transfer->getUser()->getId() !== $this->getUser()->getId() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        $transferService->deleteTransfer($transfer);
        return $this->json(['success' => true]);
    }

    #[Route('/{id}/revoke', name: 'app_transfer_revoke', methods: ['POST'])]
    public function revoke(string $id, TransferRepository $transferRepository, TransferService $transferService): JsonResponse
    {
        $transfer = $transferRepository->find($id);
        if (!$transfer) {
            return $this->json(['error' => 'Transfer not found'], 404);
        }

        if ($transfer->getUser()->getId() !== $this->getUser()->getId() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        $transferService->revokeTransfer($transfer);
        return $this->json(['success' => true]);
    }

    #[Route('/{id}/resend', name: 'app_transfer_resend', methods: ['POST'])]
    public function resend(string $id, TransferRepository $transferRepository, TransferService $transferService): JsonResponse
    {
        $transfer = $transferRepository->find($id);
        if (!$transfer) {
            return $this->json(['error' => 'Transfer not found'], 404);
        }

        if ($transfer->getUser()->getId() !== $this->getUser()->getId()) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        $transferService->resendEmail($transfer);
        return $this->json(['success' => true]);
    }

    #[Route('/{id}/extend', name: 'app_transfer_extend', methods: ['POST'])]
    public function extend(string $id, TransferRepository $transferRepository, TransferService $transferService): JsonResponse
    {
        $transfer = $transferRepository->find($id);
        if (!$transfer) {
            return $this->json(['error' => 'Transfer not found'], 404);
        }

        if ($transfer->getUser()->getId() !== $this->getUser()->getId() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        $transferService->extendExpiry($transfer, 7);
        return $this->json(['success' => true]);
    }
}