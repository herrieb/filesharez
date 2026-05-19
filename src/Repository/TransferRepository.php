<?php

namespace App\Repository;

use App\Entity\Transfer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TransferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transfer::class);
    }

    public function save(Transfer $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Transfer $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByTokenHash(string $tokenHash): ?Transfer
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.tokenHash = :tokenHash')
            ->setParameter('tokenHash', $tokenHash)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findExpired(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.expiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    public function findExhausted(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.downloadCount >= t.maxDownloads')
            ->getQuery()
            ->getResult();
    }

    public function findExpiredOrExhausted(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.expiresAt < :now OR t.downloadCount >= t.maxDownloads OR t.isRevoked = true')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    public function findByUserOrderedByRecent(string $userId): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countActiveTransfersForUser(string $userId): int
    {
        return $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.user = :userId')
            ->andWhere('t.expiresAt > :now')
            ->andWhere('t.isRevoked = false')
            ->setParameter('userId', $userId)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findExpiringSoon(\DateTimeImmutable $threshold): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.expiresAt BETWEEN :now AND :threshold')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();
    }

    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.user', 'u')
            ->addSelect('u')
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}