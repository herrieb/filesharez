<?php

namespace App\Repository;

use App\Entity\FileRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FileRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FileRequest::class);
    }

    public function findByTokenHash(string $tokenHash): ?FileRequest
    {
        return $this->createQueryBuilder('fr')
            ->andWhere('fr.tokenHash = :tokenHash')
            ->setParameter('tokenHash', $tokenHash)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByUserOrderedByRecent(string $userId): array
    {
        return $this->createQueryBuilder('fr')
            ->andWhere('fr.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('fr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('fr')
            ->andWhere('fr.isActive = true')
            ->andWhere('fr.expiresAt > :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }
}