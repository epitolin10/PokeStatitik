<?php

namespace App\Entity;

use App\Repository\CommentaireRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommentaireRepository::class)]
class Commentaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $commentaire = null;

    #[ORM\Column]
    private ?int $idCompte = null;

    #[ORM\Column]
    private ?int $idEquipe = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(string $commentaire): static
    {
        $this->commentaire = $commentaire;

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

    public function getIdEquipe(): ?int
    {
        return $this->idEquipe;
    }

    public function setIdEquipe(int $idEquipe): static
    {
        $this->idEquipe = $idEquipe;

        return $this;
    }
}
