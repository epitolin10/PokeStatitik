<?php

namespace App\Service;

use App\Repository\ObjetRepository;
use Symfony\Component\Asset\Packages;

/**
 * Combines PokeAPI's competitive items with the Objet table (Champions-exclusive
 * Méga-Gemmes with no PokeAPI data) into one sorted catalogue, shared by the team
 * builder's "Choix de l'objet" modal and the damage calculator.
 */
class ItemCatalog
{
    public function __construct(
        private readonly PokeApiClient $pokeApi,
        private readonly ObjetRepository $objetRepository,
        private readonly Packages $assets,
    ) {
    }

    /**
     * @return array<int, array{slug:string, name:string, effect:string, sprite:?string}>
     */
    public function all(): array
    {
        $items = $this->pokeApi->getCompetitiveItems();

        foreach ($this->objetRepository->findAll() as $objet) {
            $items[] = [
                'slug' => 'db-'.$objet->getId(),
                'name' => $objet->getNom(),
                'effect' => $objet->getDescription(),
                'sprite' => $objet->getUrlImage() ? $this->assets->getUrl($objet->getUrlImage()) : null,
            ];
        }

        usort($items, static fn (array $a, array $b) => $a['name'] <=> $b['name']);

        return $items;
    }

    /**
     * @return array<int, array{slug:string, name:string, effect:string, sprite:?string}>
     */
    public function search(string $query): array
    {
        $items = $this->all();
        if ('' === $query) {
            return $items;
        }

        $needle = mb_strtolower($query);

        return array_values(array_filter(
            $items,
            static fn (array $i) => str_contains(mb_strtolower($i['name']), $needle)
        ));
    }

    public function findByName(string $name): ?array
    {
        foreach ($this->all() as $item) {
            if ($item['name'] === $name) {
                return $item;
            }
        }

        return null;
    }

    public function findBySlug(string $slug): ?array
    {
        foreach ($this->all() as $item) {
            if ($item['slug'] === $slug) {
                return $item;
            }
        }

        return null;
    }
}
