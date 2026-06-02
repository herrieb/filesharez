<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\SavedTransferTokenRepository;
use App\Repository\TransferRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard')]
#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(TransferRepository $transferRepository, SavedTransferTokenRepository $savedTokenRepository): \Symfony\Component\HttpFoundation\Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $transfers = $transferRepository->findByUserOrderedByRecent($user->getId());
        $activeTransfers = $transferRepository->countActiveTransfersForUser($user->getId());

        $totalUsed = $user->getUsedStorage();
        $quota = $user->getQuotaBytes();
        $quotaPercent = $quota > 0 ? round(($totalUsed / $quota) * 100, 1) : 0;

        $activeTransfersList = array_filter($transfers, fn($t) => $t->isDownloadable());
        $expiringSoon = array_filter($activeTransfersList, fn($t) => $t->getExpiresAt() < (new \DateTimeImmutable())->modify('+24 hours'));

        $ids = array_map(fn($t) => $t->getId(), $transfers);
        $savedTokens = $savedTokenRepository->findRawTokensForUser($user, $ids);

        return $this->render('dashboard/index.html.twig', [
            'activeTransfers' => $activeTransfers,
            'quotaUsed' => $totalUsed,
            'quotaTotal' => $quota,
            'quotaPercent' => $quotaPercent,
            'recentTransfers' => array_slice($transfers, 0, 10),
            'expiringSoon' => count($expiringSoon),
            'savedTokens' => $savedTokens,
        ]);
    }
}