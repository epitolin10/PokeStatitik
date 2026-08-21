<?php

namespace App\Repository;

use App\Entity\ClassementEntry;
use App\Entity\Tiers;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClassementEntry>
 */
class ClassementEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClassementEntry::class);
    }

    /**
     * @return ClassementEntry[]
     */
    public function findByTiersOrdered(Tiers $tiers): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.tiers = :tiers')
            ->setParameter('tiers', $tiers)
            // rang isn't unique (the admin can type any number freely), so id breaks ties deterministically
            ->orderBy('c.rang', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findOneByTiersAndSlug(Tiers $tiers, string $pokemonSlug): ?ClassementEntry
    {
        return $this->findOneBy(['tiers' => $tiers, 'pokemonSlug' => $pokemonSlug]);
    }
}
