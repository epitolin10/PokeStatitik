<?php

namespace App\Repository;

use App\Entity\Like;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Like>
 */
class LikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Like::class);
    }

    /**
     * Ids of every team the given account has liked, most recently liked first.
     *
     * @return int[]
     */
    public function findEquipeIdsLikedBy(int $compteId): array
    {
        $rows = $this->createQueryBuilder('l')
            ->select('l.idEquipe')
            ->andWhere('l.idCompte = :compteId')
            ->setParameter('compteId', $compteId)
            ->orderBy('l.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        return array_column($rows, 'idEquipe');
    }

    /**
     * Total number of likes received across every team in the given id list,
     * used to show a "likes received" stat on the profile page.
     *
     * @param int[] $equipeIds
     */
    public function countForEquipeIds(array $equipeIds): int
    {
        if ([] === $equipeIds) {
            return 0;
        }

        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.idEquipe IN (:ids)')
            ->setParameter('ids', $equipeIds)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    //    public function findOneBySomeField($value): ?Like
    //    {
    //        return $this->createQueryBuilder('l')
    //            ->andWhere('l.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
