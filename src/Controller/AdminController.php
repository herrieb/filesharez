<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\FileRequest;
use App\Form\CreateUserType;
use App\Repository\FileRequestRepository;
use App\Repository\TransferRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('/', name: 'app_admin_dashboard')]
    public function dashboard(
        UserRepository $userRepository,
        TransferRepository $transferRepository,
        FileRequestRepository $fileRequestRepository
    ): Response {
        return $this->render('admin/dashboard.html.twig', [
            'userCount' => $userRepository->countActiveUsers(),
            'activeTransfers' => $transferRepository->count([]),
            'totalStorage' => $this->getTotalStorage($transferRepository),
            'fileRequestCount' => $fileRequestRepository->count([]),
            'recentTransfers' => $transferRepository->findRecent(10),
        ]);
    }

    #[Route('/users', name: 'app_admin_users')]
    public function users(UserRepository $userRepository): Response
    {
        return $this->render('admin/users.html.twig', [
            'users' => $userRepository->findActiveUsers(),
        ]);
    }

    #[Route('/users/create', name: 'app_admin_user_create')]
    public function createUser(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $user = new User();
        $form = $this->createForm(CreateUserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword(
                $passwordHasher->hashPassword($user, $form->get('plainPassword')->getData())
            );
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'User created successfully.');
            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/create_user.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/users/{id}/toggle', name: 'app_admin_user_toggle')]
    public function toggleUser(string $id, UserRepository $userRepository, EntityManagerInterface $entityManager): Response
    {
        $user = $userRepository->find($id);
        if (!$user) {
            throw $this->createNotFoundException();
        }

        $user->setIsActive(!$user->isActive());
        $entityManager->flush();

        $this->addFlash('success', $user->isActive() ? 'User enabled.' : 'User disabled.');
        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/file-requests', name: 'app_admin_file_requests')]
    public function fileRequests(FileRequestRepository $fileRequestRepository): Response
    {
        $requests = $fileRequestRepository->createQueryBuilder('fr')
            ->leftJoin('fr.user', 'u')
            ->addSelect('u')
            ->orderBy('fr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/file_requests.html.twig', [
            'fileRequests' => $requests,
        ]);
    }

    #[Route('/file-requests/{id}/toggle', name: 'app_admin_file_request_toggle', methods: ['POST'])]
    public function toggleFileRequest(string $id, FileRequestRepository $fileRequestRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $fileRequest = $fileRequestRepository->find($id);
        if (!$fileRequest) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        $fileRequest->setIsActive(!$fileRequest->isActive());
        $entityManager->flush();

        return new JsonResponse(['success' => true, 'isActive' => $fileRequest->isActive()]);
    }

    #[Route('/file-requests/{id}/delete', name: 'app_admin_file_request_delete', methods: ['POST'])]
    public function deleteFileRequest(string $id, FileRequestRepository $fileRequestRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $fileRequest = $fileRequestRepository->find($id);
        if (!$fileRequest) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        $entityManager->remove($fileRequest);
        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/system-health', name: 'app_admin_system_health')]
    public function systemHealth(
        TransferRepository $transferRepository,
        UserRepository $userRepository,
        FileRequestRepository $fileRequestRepository
    ): Response {
        $conn = $transferRepository->getEntityManager()->getConnection();

        $transferCount = $transferRepository->count([]);
        $activeTransfers = $transferRepository->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.expiresAt > :now')
            ->andWhere('t.isRevoked = false')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();

        $expiredTransfers = $transferRepository->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.expiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();

        $totalStorage = $this->getTotalStorage($transferRepository);
        $userCount = $userRepository->countActiveUsers();
        $fileRequestCount = $fileRequestRepository->count([]);

        $activeFileRequests = $fileRequestRepository->createQueryBuilder('fr')
            ->select('COUNT(fr.id)')
            ->andWhere('fr.isActive = true')
            ->andWhere('fr.expiresAt > :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();

        $libraryPath = (string) $this->getParameter('app.library_path');
        $libraryReal = realpath($libraryPath) ?: $libraryPath;
        $diskFree  = @disk_free_space($libraryReal);
        $diskTotal = @disk_total_space($libraryReal);
        $diskUsed  = ($diskFree !== false && $diskTotal !== false) ? $diskTotal - $diskFree : 0;
        $diskPercent = ($diskTotal > 0) ? ($diskUsed / $diskTotal) * 100 : 0;
        $diskStatus = match (true) {
            $diskPercent >= 90 => 'critical',
            $diskPercent >= 75 => 'warning',
            default            => 'ok',
        };

        $phpVersion = PHP_VERSION;
        $symfonyVersion = \Symfony\Component\HttpKernel\Kernel::MAJOR_VERSION . '.' . \Symfony\Component\HttpKernel\Kernel::MINOR_VERSION;

        $dbSizeResult = $conn->executeQuery("SELECT pg_database_size(current_database())")->fetchOne();

        $userStorage = $transferRepository->createQueryBuilder('t')
            ->select('IDENTITY(t.user) as userId, u.email, SUM(t.totalSizeBytes) as totalBytes, COUNT(t.id) as transferCount')
            ->leftJoin('t.user', 'u')
            ->groupBy('t.user', 'u.email')
            ->orderBy('totalBytes', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/system_health.html.twig', [
            'transferCount' => $transferCount,
            'activeTransfers' => $activeTransfers,
            'expiredTransfers' => $expiredTransfers,
            'totalStorage' => $totalStorage,
            'userCount' => $userCount,
            'fileRequestCount' => $fileRequestCount,
            'activeFileRequests' => $activeFileRequests,
            'diskFree' => $diskFree,
            'diskTotal' => $diskTotal,
            'diskUsed' => $diskUsed,
            'libraryPath' => $libraryPath,
            'diskStatus' => $diskStatus,
            'phpVersion' => $phpVersion,
            'symfonyVersion' => $symfonyVersion,
            'dbSize' => $dbSizeResult,
            'userStorage' => $userStorage,
        ]);
    }

    private function getTotalStorage(TransferRepository $transferRepository): int
    {
        $qb = $transferRepository->createQueryBuilder('t')
            ->select('SUM(t.totalSizeBytes)')
            ->getQuery()
            ->getSingleScalarResult();
        return (int) ($qb ?? 0);
    }
}