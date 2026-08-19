<?php

namespace App\Entity;

use App\Repository\BuildPokemonRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un Pokémon tel que configuré dans une équipe : objet tenu, capacités,
 * nature, talent et répartition de points de stats (0 à 32 par stat,
 * 66 au total — les règles du format Pokémon Champions, pas les EV classiques).
 */
#[ORM\Entity(repositoryClass: BuildPokemonRepository::class)]
class BuildPokemon
{
    public const MAX_POINTS_PAR_STAT = 32;
    public const MAX_POINTS_TOTAL = 66;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Equipe::class, inversedBy: 'buildPokemons')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Equipe $equipe = null;

    /** Position dans l'équipe, de 1 à 6. */
    #[ORM\Column]
    #[Assert\Range(min: 1, max: 6)]
    private ?int $position = null;

    /**
     * Référence vers l'API championsbattledata.com (URL complète ou simple
     * showdown id, ex. "garchomp") — le nom/sprite/types ne sont jamais
     * dupliqués en base, on les résout via ChampionsBattleDataClient.
     */
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private ?string $pokemonUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $objet = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $capacite1 = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $capacite2 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $capacite3 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $capacite4 = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $nature = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $talent = null;

    #[ORM\Column]
    #[Assert\Range(min: 0, max: self::MAX_POINTS_PAR_STAT)]
    private int $ivPv = 0;

    #[ORM\Column]
    #[Assert\Range(min: 0, max: self::MAX_POINTS_PAR_STAT)]
    private int $ivAtq = 0;

    #[ORM\Column]
    #[Assert\Range(min: 0, max: self::MAX_POINTS_PAR_STAT)]
    private int $ivDef = 0;

    #[ORM\Column]
    #[Assert\Range(min: 0, max: self::MAX_POINTS_PAR_STAT)]
    private int $ivAtqSpe = 0;

    #[ORM\Column]
    #[Assert\Range(min: 0, max: self::MAX_POINTS_PAR_STAT)]
    private int $ivDefSpe = 0;

    #[ORM\Column]
    #[Assert\Range(min: 0, max: self::MAX_POINTS_PAR_STAT)]
    private int $ivVitesse = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEquipe(): ?Equipe
    {
        return $this->equipe;
    }

    public function setEquipe(?Equipe $equipe): static
    {
        $this->equipe = $equipe;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getPokemonUrl(): ?string
    {
        return $this->pokemonUrl;
    }

    public function setPokemonUrl(string $pokemonUrl): static
    {
        $this->pokemonUrl = $pokemonUrl;

        return $this;
    }

    /**
     * Le showdown id extrait de {@see self::$pokemonUrl}, que la valeur stockée
     * soit une URL complète ("https://championsbattledata.com/api/pokemon/garchomp")
     * ou déjà un simple slug ("garchomp").
     */
    public function getPokemonSlug(): ?string
    {
        if (null === $this->pokemonUrl || '' === $this->pokemonUrl) {
            return null;
        }

        $trimmed = rtrim($this->pokemonUrl, '/');
        $segments = explode('/', $trimmed);

        return strtolower(end($segments));
    }

    public function getObjet(): ?string
    {
        return $this->objet;
    }

    public function setObjet(?string $objet): static
    {
        $this->objet = $objet;

        return $this;
    }

    public function getCapacite1(): ?string
    {
        return $this->capacite1;
    }

    public function setCapacite1(string $capacite1): static
    {
        $this->capacite1 = $capacite1;

        return $this;
    }

    public function getCapacite2(): ?string
    {
        return $this->capacite2;
    }

    public function setCapacite2(string $capacite2): static
    {
        $this->capacite2 = $capacite2;

        return $this;
    }

    public function getCapacite3(): ?string
    {
        return $this->capacite3;
    }

    public function setCapacite3(?string $capacite3): static
    {
        $this->capacite3 = $capacite3;

        return $this;
    }

    public function getCapacite4(): ?string
    {
        return $this->capacite4;
    }

    public function setCapacite4(?string $capacite4): static
    {
        $this->capacite4 = $capacite4;

        return $this;
    }

    public function getNature(): ?string
    {
        return $this->nature;
    }

    public function setNature(string $nature): static
    {
        $this->nature = $nature;

        return $this;
    }

    public function getTalent(): ?string
    {
        return $this->talent;
    }

    public function setTalent(string $talent): static
    {
        $this->talent = $talent;

        return $this;
    }

    public function getIvPv(): int
    {
        return $this->ivPv;
    }

    public function setIvPv(int $ivPv): static
    {
        $this->ivPv = $ivPv;

        return $this;
    }

    public function getIvAtq(): int
    {
        return $this->ivAtq;
    }

    public function setIvAtq(int $ivAtq): static
    {
        $this->ivAtq = $ivAtq;

        return $this;
    }

    public function getIvDef(): int
    {
        return $this->ivDef;
    }

    public function setIvDef(int $ivDef): static
    {
        $this->ivDef = $ivDef;

        return $this;
    }

    public function getIvAtqSpe(): int
    {
        return $this->ivAtqSpe;
    }

    public function setIvAtqSpe(int $ivAtqSpe): static
    {
        $this->ivAtqSpe = $ivAtqSpe;

        return $this;
    }

    public function getIvDefSpe(): int
    {
        return $this->ivDefSpe;
    }

    public function setIvDefSpe(int $ivDefSpe): static
    {
        $this->ivDefSpe = $ivDefSpe;

        return $this;
    }

    public function getIvVitesse(): int
    {
        return $this->ivVitesse;
    }

    public function setIvVitesse(int $ivVitesse): static
    {
        $this->ivVitesse = $ivVitesse;

        return $this;
    }

    public function getTotalPointsInvestis(): int
    {
        return $this->ivPv + $this->ivAtq + $this->ivDef + $this->ivAtqSpe + $this->ivDefSpe + $this->ivVitesse;
    }

    #[Assert\Callback]
    public function validateTotalPoints(ExecutionContextInterface $context): void
    {
        if ($this->getTotalPointsInvestis() > self::MAX_POINTS_TOTAL) {
            $context->buildViolation(sprintf(
                'Le total des points investis (%d) dépasse le maximum autorisé (%d).',
                $this->getTotalPointsInvestis(),
                self::MAX_POINTS_TOTAL
            ))
                ->atPath('ivPv')
                ->addViolation();
        }
    }
}
