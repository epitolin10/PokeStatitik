<?php

namespace App\Entity;

use App\Repository\TiersRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TiersRepository::class)]
class Tiers
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $Nom_Tiers = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomTiers(): ?string
    {
        return $this->Nom_Tiers;
    }

    public function setNomTiers(string $Nom_Tiers): static
    {
        $this->Nom_Tiers = $Nom_Tiers;

        return $this;
    }
}
