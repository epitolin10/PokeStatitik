<?php

namespace App\Repository;

use App\Entity\Equipe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Equipe>
 */
class EquipeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Equipe::class);
    }

    /**
     * Every published team, most recent first — used by the community "Équipes"
     * list and the home page's teaser. Eager-loads compte/tiers/buildPokemons
     * so rendering the list doesn't trigger N+1 queries.
     *
     * @return Equipe[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('e')
            ->addSelect('c', 't', 'b')
            ->leftJoin('e.compte', 'c')
            ->leftJoin('e.tiers', 't')
            ->leftJoin('e.buildPokemons', 'b')
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    //    public function findOneBySomeField($value): ?Equipe
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
