<?php

namespace App\Repository;

use App\Entity\UploadSession;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UploadSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UploadSession::class);
    }

    public function save(UploadSession $s, bool $flush = false): void
    {
        $this->getEntityManager()->persist($s);
        if ($flush) $this->getEntityManager()->flush();
    }

    public function remove(UploadSession $s, bool $flush = false): void
    {
        $this->getEntityManager()->remove($s);
        if ($flush) $this->getEntityManager()->flush();
    }

    public function findActiveForUser(User $user): array
    {
        return $this->findBy(['user' => $user->getId()]);
    }

    public function findExpired(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.expiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    public function sumActiveBytesForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('SUM(s.sizeBytes)')
            ->andWhere('s.user = :uid')
            ->setParameter('uid', $user->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }
}
