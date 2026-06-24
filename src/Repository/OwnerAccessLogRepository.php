<?php

namespace App\Repository;

use App\Entity\OwnerAccessLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OwnerAccessLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OwnerAccessLog::class);
    }

    public function save(OwnerAccessLog $log, bool $flush = false): void
    {
        $this->getEntityManager()->persist($log);
        if ($flush) $this->getEntityManager()->flush();
    }

    /**
     * @return OwnerAccessLog[]
     */
    public function findRecentForUser(User $user, int $limit = 100): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.user = :user')
            ->setParameter('user', $user->getId())
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
