<?php

namespace App\Entity;

use App\Repository\EquipeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EquipeRepository::class)]
class Equipe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Compte::class, inversedBy: 'equipes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Compte $compte = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * Les 6 Pokémon construits qui composent cette équipe.
     *
     * @var Collection<int, BuildPokemon>
     */
    #[ORM\OneToMany(targetEntity: BuildPokemon::class, mappedBy: 'equipe', orphanRemoval: true, cascade: ['persist'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $buildPokemons;

    /** Identifiant officiel de l'équipe côté jeu Pokémon Champions (partage in-game). */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $idEquipePokemonChampions = null;

    #[ORM\ManyToOne(targetEntity: Tiers::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank]
    private ?Tiers $tiers = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->buildPokemons = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompte(): ?Compte
    {
        return $this->compte;
    }

    public function setCompte(?Compte $compte): static
    {
        $this->compte = $compte;

        return $this;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return Collection<int, BuildPokemon>
     */
    public function getBuildPokemons(): Collection
    {
        return $this->buildPokemons;
    }

    public function addBuildPokemon(BuildPokemon $buildPokemon): static
    {
        if (!$this->buildPokemons->contains($buildPokemon)) {
            $this->buildPokemons->add($buildPokemon);
            $buildPokemon->setEquipe($this);
        }

        return $this;
    }

    public function removeBuildPokemon(BuildPokemon $buildPokemon): static
    {
        if ($this->buildPokemons->removeElement($buildPokemon)) {
            if ($buildPokemon->getEquipe() === $this) {
                $buildPokemon->setEquipe(null);
            }
        }

        return $this;
    }

    public function getIdEquipePokemonChampions(): ?string
    {
        return $this->idEquipePokemonChampions;
    }

    public function setIdEquipePokemonChampions(?string $idEquipePokemonChampions): static
    {
        $this->idEquipePokemonChampions = $idEquipePokemonChampions;

        return $this;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
