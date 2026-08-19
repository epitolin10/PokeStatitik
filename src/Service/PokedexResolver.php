<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Resolves a National Pokédex number for a Pokémon Champions entry.
 *
 * The championsbattledata.com API does not reliably expose dex numbers
 * (the field is present but always null at the time of writing), so we
 * cross-reference against a static PokeAPI species dump instead. Regional
 * forms, breeds and appliance forms share their base species' number.
 */
class PokedexResolver
{
    /** @var array<string, int> */
    private array $dexByName;

    private const REGIONAL_PREFIXES = ['alolan ', 'galarian ', 'hisuian ', 'paldean '];

    public function __construct(#[Autowire('%kernel.project_dir%')] string $projectDir)
    {
        $path = $projectDir.'/assets/data/pokedex-numbers.json';
        $this->dexByName = json_decode(file_get_contents($path), true) ?? [];
    }

    public function resolve(string $displayName, string $showdownId): ?int
    {
        $slug = $this->resolveSlug($displayName, $showdownId);

        return null !== $slug ? $this->dexByName[$slug] : null;
    }

    /**
     * Returns the PokeAPI species slug this Champions entry best matches
     * (e.g. "garchomp" for both "Garchomp" and "Mega Garchomp"), or null.
     * Useful for looking up real base stats via PokeAPI's /pokemon/{slug} endpoint.
     */
    public function resolveSlug(string $displayName, string $showdownId): ?string
    {
        $lower = strtolower($displayName);
        $base = $displayName;
        foreach (self::REGIONAL_PREFIXES as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                $base = substr($displayName, strlen($prefix));
                break;
            }
        }

        $candidates = [$showdownId, $this->slugify($displayName), $this->slugify($base)];
        $candidates[] = $this->slugify(strtok($base, ' '));
        foreach (explode(' ', $base) as $word) {
            $candidates[] = $this->slugify($word);
        }

        foreach ($candidates as $candidate) {
            if (isset($this->dexByName[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }

    private function slugify(string $name): string
    {
        $name = strtolower(trim($name));
        $name = str_replace(["'", '.'], '', $name);
        $name = preg_replace('/[^a-z0-9]+/', '-', $name);

        return trim($name, '-');
    }
}
