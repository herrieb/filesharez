<?php

namespace App\Repository;

use App\Entity\LibrarySource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LibrarySourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LibrarySource::class);
    }

    public function findActiveForOwner(string $userId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.owner = :userId')
            ->andWhere('s.isActive = true')
            ->setParameter('userId', $userId)
            ->orderBy('s.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
