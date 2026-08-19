<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin wrapper around the championsbattledata.com public API.
 *
 * @see https://championsbattledata.com/api_guide
 */
class ChampionsBattleDataClient
{
    private const BASE_URL = 'https://championsbattledata.com';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly PokedexResolver $pokedexResolver,
    ) {
    }

    /**
     * Every Pokémon in the Champions format, enriched with a resolved dex number,
     * sorted by that dex number (the site's chosen sort order for the usage list).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllPokemon(): array
    {
        $index = $this->fetchIndex();

        $pokemon = array_map(function (array $entry) {
            $entry['dexNumber'] = $this->pokedexResolver->resolve($entry['name'], $entry['showdownId']);
            return $entry;
        }, $index['pokemon'] ?? []);

        usort($pokemon, static function (array $a, array $b) {
            $dexA = $a['dexNumber'] ?? PHP_INT_MAX;
            $dexB = $b['dexNumber'] ?? PHP_INT_MAX;

            return $dexA <=> $dexB ?: strcmp($a['name'], $b['name']);
        });

        return $pokemon;
    }

    public function getPokemonBySlug(string $slug): ?array
    {
        foreach ($this->getAllPokemon() as $pokemon) {
            if ($pokemon['slug'] === $slug) {
                return $pokemon;
            }
        }

        return null;
    }

    /**
     * Per-move/item/teammate/ability/nature/EV-spread usage rows for one Pokémon,
     * each with its own usage percentage (richer than the index's top-10 name lists).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBattleRows(string $format, string $slug): array
    {
        $cacheKey = 'champions_battle_rows_'.$format.'_'.$slug;

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($format, $slug) {
            $item->expiresAfter(3600);

            $response = $this->httpClient->request('GET', self::BASE_URL.'/api/battle/'.$format.'/'.$slug);
            if (200 !== $response->getStatusCode()) {
                return [];
            }

            return $response->toArray()['rows'] ?? [];
        });
    }

    public function assetUrl(string $relativePath): string
    {
        return self::BASE_URL.'/'.ltrim($relativePath, '/');
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchIndex(): array
    {
        return $this->cache->get('champions_battle_data_index', function (ItemInterface $item) {
            $item->expiresAfter(3600); // the site's own data refreshes at most once a day

            $response = $this->httpClient->request('GET', self::BASE_URL.'/api/index');

            return $response->toArray();
        });
    }
}
