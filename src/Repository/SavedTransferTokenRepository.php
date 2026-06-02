<?php

namespace App\Repository;

use App\Entity\SavedTransferToken;
use App\Entity\Transfer;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SavedTransferTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SavedTransferToken::class);
    }

    public function findOneByUserAndTransfer(User $user, Transfer $transfer): ?SavedTransferToken
    {
        return $this->findOneBy(['user' => $user->getId(), 'transfer' => $transfer->getId()]);
    }

    /**
     * @return array<string,string> transferId => rawToken
     */
    public function findRawTokensForUser(User $user, array $transferIds): array
    {
        if (empty($transferIds)) {
            return [];
        }
        $rows = $this->createQueryBuilder('s')
            ->select('IDENTITY(s.transfer) AS transferId, s.rawToken AS rawToken')
            ->andWhere('s.user = :userId')
            ->andWhere('IDENTITY(s.transfer) IN (:transferIds)')
            ->setParameter('userId', $user->getId())
            ->setParameter('transferIds', $transferIds)
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['transferId']] = (string) $row['rawToken'];
        }
        return $out;
    }
}
