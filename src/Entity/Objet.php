<?php

namespace App\Entity;

use App\Repository\ObjetRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ObjetRepository::class)]
class Objet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $Nom = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $urlImage = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->Nom;
    }

    public function setNom(string $Nom): static
    {
        $this->Nom = $Nom;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Normalized to a forward-slash path relative to assets/ (usable with the
     * asset() Twig function) — some rows were seeded with a Windows-style
     * "assets\images\..." path copy-pasted from the filesystem, and a few of
     * those also picked up a stray line break mid-filename in the process
     * (e.g. "Miniature_Momartikite\n_LPZA.png"), which breaks the URL.
     */
    public function getUrlImage(): ?string
    {
        if (null === $this->urlImage) {
            return null;
        }

        $normalized = str_replace('\\', '/', $this->urlImage);
        $normalized = preg_replace('/\s+/', '', $normalized);

        return preg_replace('#^assets/#i', '', $normalized);
    }

    public function setUrlImage(?string $urlImage): static
    {
        $this->urlImage = $urlImage;

        return $this;
    }
}
