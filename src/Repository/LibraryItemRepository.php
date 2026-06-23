<?php

namespace App\Repository;

use App\Entity\LibraryItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LibraryItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LibraryItem::class);
    }

    public function findRecent(int $limit = 50): array
    {
        return $this->createQueryBuilder('i')
            ->orderBy('i.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findBySource(string $sourceId): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.source = :sourceId')
            ->setParameter('sourceId', $sourceId)
            ->orderBy('i.isDirectory', 'DESC')
            ->addOrderBy('i.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function searchByName(string $query, int $limit = 100): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('LOWER(i.name) LIKE LOWER(:q) OR LOWER(i.relativePath) LIKE LOWER(:q)')
            ->setParameter('q', '%' . $query . '%')
            ->orderBy('i.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countActiveTransfersForItem(string $itemId): int
    {
        return (int) $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(\App\Entity\Transfer::class, 't')
            ->andWhere('t.libraryItem = :id')
            ->andWhere('t.expiresAt > :now')
            ->andWhere('t.isRevoked = false')
            ->setParameter('id', $itemId)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();
    }
}
