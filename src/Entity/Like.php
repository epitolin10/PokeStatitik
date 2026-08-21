<?php

namespace App\Entity;

use App\Repository\LikeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LikeRepository::class)]
#[ORM\Table(name: '`like`')]
class Like
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $idEquipe = null;

    #[ORM\Column]
    private ?int $idCompte = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdEquipe(): ?int
    {
        return $this->idEquipe;
    }

    public function setIdEquipe(int $idEquipe): static
    {
        $this->idEquipe = $idEquipe;

        return $this;
    }

    public function getIdCompte(): ?int
    {
        return $this->idCompte;
    }

    public function setIdCompte(int $idCompte): static
    {
        $this->idCompte = $idCompte;

        return $this;
    }
}
