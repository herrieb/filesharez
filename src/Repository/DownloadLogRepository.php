<?php

namespace App\Repository;

use App\Entity\DownloadLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DownloadLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DownloadLog::class);
    }

    public function save(DownloadLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByTransfer(string $transferId): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.transfer = :transferId')
            ->setParameter('transferId', $transferId)
            ->orderBy('d.downloadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}