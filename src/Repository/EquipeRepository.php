<?php

namespace App\Repository;

use App\Entity\Compte;
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

    /**
     * Every team owned by the given account, most recent first — used by the profile page's "Mes équipes".
     *
     * @return Equipe[]
     */
    public function findByCompteOrdered(Compte $compte): array
    {
        return $this->createQueryBuilder('e')
            ->addSelect('t', 'b')
            ->leftJoin('e.tiers', 't')
            ->leftJoin('e.buildPokemons', 'b')
            ->andWhere('e.compte = :compte')
            ->setParameter('compte', $compte)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Teams matching the given ids, most recent first — used by the profile page's "Mes favoris"
     * to hydrate the teams a user has liked.
     *
     * @param int[] $ids
     *
     * @return Equipe[]
     */
    public function findByIdsOrdered(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return $this->createQueryBuilder('e')
            ->addSelect('c', 't', 'b')
            ->leftJoin('e.compte', 'c')
            ->leftJoin('e.tiers', 't')
            ->leftJoin('e.buildPokemons', 'b')
            ->andWhere('e.id IN (:ids)')
            ->setParameter('ids', $ids)
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
