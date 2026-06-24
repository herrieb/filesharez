<?php

namespace App\Service;

use App\Entity\LibrarySource;
use App\Entity\OwnerAccessLog;
use App\Entity\User;
use App\Repository\OwnerAccessLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class LibraryAccessService
{
    public function __construct(
        private OwnerAccessLogRepository $logRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function record(
        User $user,
        LibrarySource $source,
        string $path,
        string $action,
        ?int $sizeBytes = null,
        ?Request $request = null,
    ): void {
        $log = new OwnerAccessLog();
        $log->setUser($user)
            ->setSource($source)
            ->setPath($path)
            ->setAction($action)
            ->setSizeBytes($sizeBytes);
        if ($request !== null) {
            $log->setIpAddress($request->getClientIp());
            $ua = $request->headers->get('User-Agent');
            if ($ua !== null) {
                $log->setUserAgent(substr($ua, 0, 250));
            }
        }
        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    /**
     * @return OwnerAccessLog[]
     */
    public function recentForUser(User $user, int $limit = 100): array
    {
        return $this->logRepository->findRecentForUser($user, $limit);
    }
}
