<?php

namespace App\Entity;

use App\Repository\ClassementEntryRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une entrée du classement des Pokémon les plus utilisés, curée manuellement
 * par l'admin (indépendant des statistiques d'usage automatiques). Une entrée
 * par Pokémon et par tier (Champions Solo / Champions Duo), ordonnée par rang.
 */
#[ORM\Entity(repositoryClass: ClassementEntryRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_CLASSEMENT_TIERS_POKEMON', fields: ['tiers', 'pokemonSlug'])]
class ClassementEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Tiers::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Tiers $tiers = null;

    /** Showdown slug résolu via ChampionsBattleDataClient, ex. "garchomp". */
    #[ORM\Column(length: 100)]
    private ?string $pokemonSlug = null;

    /** Position dans le classement, 1 = le plus utilisé. */
    #[ORM\Column]
    private ?int $rang = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTiers(): ?Tiers
    {
        return $this->tiers;
    }

    public function setTiers(?Tiers $tiers): static
    {
        $this->tiers = $tiers;

        return $this;
    }

    public function getPokemonSlug(): ?string
    {
        return $this->pokemonSlug;
    }

    public function setPokemonSlug(string $pokemonSlug): static
    {
        $this->pokemonSlug = $pokemonSlug;

        return $this;
    }

    public function getRang(): ?int
    {
        return $this->rang;
    }

    public function setRang(int $rang): static
    {
        $this->rang = $rang;

        return $this;
    }
}
