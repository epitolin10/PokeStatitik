<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Real National Pokédex base stats, learnable moves and abilities, from PokeAPI —
 * the championsbattledata.com API's "baseStats" field is actually level-50
 * calculated stats, not base stats, and doesn't expose movesets/abilities at all.
 */
class PokeApiClient
{
    private const BASE_URL = 'https://pokeapi.co/api/v2';

    private const STAT_KEY_MAP = [
        'hp' => 'hp',
        'attack' => 'attack',
        'defense' => 'defense',
        'special-attack' => 'sp_attack',
        'special-defense' => 'sp_defense',
        'speed' => 'speed',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly PokedexResolver $pokedexResolver,
    ) {
    }

    /**
     * @return array{hp:int, attack:int, defense:int, sp_attack:int, sp_defense:int, speed:int, total:int}|null
     */
    public function getBaseStats(string $displayName, string $showdownId): ?array
    {
        $slug = $this->pokedexResolver->resolveSlug($displayName, $showdownId);
        if (null === $slug) {
            return null;
        }

        $pokemon = $this->fetchPokemon($slug);
        if (null === $pokemon) {
            return null;
        }

        return $this->statsFromApiData($pokemon);
    }

    /**
     * Real base stats/types/abilities for a Pokémon's mega form (works for official games'
     * megas AND every Champions-exclusive méga-gemme except Trevenant and Empoleon, which
     * PokeAPI has no mega data for at all — see AdminClassementController's note, or just
     * getMegaForm() returning null for those two).
     *
     * @param string $formName the champions API's form_name, e.g. "Mega Charizard X", to disambiguate X/Y forms
     *
     * @return array{stats: array{hp:int, attack:int, defense:int, sp_attack:int, sp_defense:int, speed:int, total:int}, types: array<string>, abilities: array<int, array{slug: string, name: string, isHidden: bool}>}|null
     */
    public function getMegaForm(string $displayName, string $showdownId, string $formName): ?array
    {
        $baseSlug = $this->pokedexResolver->resolveSlug($displayName, $showdownId);
        if (null === $baseSlug) {
            return null;
        }

        $pokemon = $this->fetchPokemon($this->buildMegaSlug($baseSlug, $formName));
        if (null === $pokemon) {
            return null;
        }

        $types = array_map(static fn (array $entry) => ucfirst($entry['type']['name']), $pokemon['types'] ?? []);

        return [
            'stats' => $this->statsFromApiData($pokemon),
            'types' => $types,
            'abilities' => $this->abilitiesFromApiData($pokemon),
        ];
    }

    /**
     * @return array{hp:int, attack:int, defense:int, sp_attack:int, sp_defense:int, speed:int, total:int}
     */
    private function statsFromApiData(array $pokemon): array
    {
        $stats = ['hp' => 0, 'attack' => 0, 'defense' => 0, 'sp_attack' => 0, 'sp_defense' => 0, 'speed' => 0];
        foreach ($pokemon['stats'] ?? [] as $entry) {
            $key = self::STAT_KEY_MAP[$entry['stat']['name']] ?? null;
            if (null !== $key) {
                $stats[$key] = $entry['base_stat'];
            }
        }
        $stats['total'] = array_sum($stats);

        return $stats;
    }

    /**
     * PokeAPI's mega slugs are "{base}-mega", except Charizard/Mewtwo-style X/Y forms
     * ("{base}-mega-x"/"-y") — disambiguated from the champions API's own form_name text.
     */
    private function buildMegaSlug(string $baseSlug, string $formName): string
    {
        $upper = strtoupper(trim($formName));
        if (str_ends_with($upper, ' X')) {
            return $baseSlug.'-mega-x';
        }
        if (str_ends_with($upper, ' Y')) {
            return $baseSlug.'-mega-y';
        }

        return $baseSlug.'-mega';
    }

    /**
     * The Pokémon's abilities, French name resolved, hidden ability flagged.
     *
     * @return array<int, array{slug: string, name: string, isHidden: bool}>
     */
    public function getAbilities(string $displayName, string $showdownId): array
    {
        $slug = $this->pokedexResolver->resolveSlug($displayName, $showdownId);
        $pokemon = null !== $slug ? $this->fetchPokemon($slug) : null;
        if (null === $pokemon) {
            return [];
        }

        return $this->abilitiesFromApiData($pokemon);
    }

    /**
     * @return array<int, array{slug: string, name: string, isHidden: bool}>
     */
    private function abilitiesFromApiData(array $pokemon): array
    {
        $abilities = [];
        foreach ($pokemon['abilities'] ?? [] as $entry) {
            $abilitySlug = $entry['ability']['name'];
            $detail = $this->fetchNamedResource('ability', $abilitySlug);
            $abilities[] = [
                'slug' => $abilitySlug,
                'name' => $this->frenchName($detail) ?? $abilitySlug,
                'isHidden' => (bool) $entry['is_hidden'],
            ];
        }

        return $abilities;
    }

    /**
     * Moves this Pokémon can actually learn in the "Champions" version group,
     * with power/pp/accuracy/type/category and French name — cached long-term
     * since a movepool essentially never changes.
     *
     * @return array<int, array{slug:string, name:string, type:string, damageClass:string, power:?int, pp:?int, accuracy:?int, isSpread:bool}>
     */
    public function getLearnableMoves(string $displayName, string $showdownId): array
    {
        $slug = $this->pokedexResolver->resolveSlug($displayName, $showdownId);
        if (null === $slug) {
            return [];
        }

        return $this->cache->get('pokeapi_learnable_moves_v2_'.$slug, function (ItemInterface $item) use ($slug) {
            $item->expiresAfter(604800); // a movepool doesn't change week to week

            $pokemon = $this->fetchPokemon($slug);
            if (null === $pokemon) {
                return [];
            }

            $moveSlugs = [];
            foreach ($pokemon['moves'] ?? [] as $moveEntry) {
                foreach ($moveEntry['version_group_details'] as $vgd) {
                    if ('champions' === $vgd['version_group']['name']) {
                        $moveSlugs[] = $moveEntry['move']['name'];
                        break;
                    }
                }
            }

            // fall back to the full movepool if this species has no "champions" tagged data yet
            if ([] === $moveSlugs) {
                $moveSlugs = array_map(static fn (array $m) => $m['move']['name'], $pokemon['moves'] ?? []);
            }

            $moves = [];
            foreach ($moveSlugs as $moveSlug) {
                $detail = $this->fetchNamedResource('move', $moveSlug);
                if (null === $detail) {
                    continue;
                }
                // moves that hit every foe (or every other Pokémon) at once take the
                // doubles "spread" damage penalty — Champions/VGC is doubles-only,
                // so the calculator applies it whenever a move can do this
                $targetName = $detail['target']['name'] ?? null;
                $isSpread = \in_array($targetName, ['all-opponents', 'all-other-pokemon'], true);

                $moves[] = [
                    'slug' => $moveSlug,
                    'name' => $this->frenchName($detail) ?? $moveSlug,
                    'type' => $detail['type']['name'],
                    'damageClass' => $detail['damage_class']['name'],
                    'power' => $detail['power'],
                    'pp' => $detail['pp'],
                    'accuracy' => $detail['accuracy'],
                    'isSpread' => $isSpread,
                ];
            }

            usort($moves, static fn (array $a, array $b) => $a['name'] <=> $b['name']);

            return $moves;
        });
    }

    /**
     * The held items, Méga-Gemmes and baies actually available in Pokémon Champions
     * (cross-referenced against https://op.gg/fr/pokemon-champions/items — not PokeAPI's
     * full ~2000 item catalogue, most of which is irrelevant here). A number of Champions'
     * own Méga-Gemmes (for Pokémon with no official mega evolution, e.g. Empiflorite,
     * Golgopathite) aren't in PokeAPI at all and so can't be listed with real data here.
     *
     * @return array<int, array{slug:string, name:string, effect:string, sprite:?string}>
     */
    public function getCompetitiveItems(): array
    {
        return $this->cache->get('pokeapi_competitive_items_v2', function (ItemInterface $item) {
            $item->expiresAfter(604800);

            $slugs = [
                // Objets tenus
                'big-root', 'black-belt', 'black-glasses', 'bright-powder', 'charcoal', 'choice-scarf',
                'damp-rock', 'dragon-fang', 'expert-belt', 'fairy-feather', 'focus-band', 'focus-sash',
                'hard-stone', 'heat-rock', 'icy-rock', 'iron-ball', 'kings-rock', 'leftovers',
                'life-orb', 'light-ball', 'light-clay', 'magnet', 'mental-herb', 'metal-coat',
                'metronome', 'miracle-seed', 'muscle-band', 'mystic-water', 'never-melt-ice', 'poison-barb',
                'quick-claw', 'scope-lens', 'sharp-beak', 'shed-shell', 'shell-bell', 'silk-scarf',
                'silver-powder', 'smooth-rock', 'soft-sand', 'spell-tag', 'twisted-spoon', 'white-herb',
                'wide-lens', 'wise-glasses',
                // Méga-pierres
                'abomasite', 'absolite', 'aerodactylite', 'aggronite', 'alakazite', 'altarianite',
                'ampharosite', 'audinite', 'banettite', 'beedrillite', 'blastoisinite', 'cameruptite',
                'charizardite-x', 'charizardite-y', 'galladite', 'garchompite', 'gardevoirite', 'gengarite',
                'glalitite', 'gyaradosite', 'heracronite', 'houndoominite', 'kangaskhanite', 'lopunnite',
                'lucarionite', 'manectite', 'medichamite', 'pidgeotite', 'pinsirite', 'sablenite',
                'scizorite', 'sharpedonite', 'slowbronite', 'steelixite', 'tyranitarite', 'venusaurite',
                'sceptilite', 'blazikenite', 'swampertite', 'mawilite', 'metagrossite',
                // Baies
                'aspear-berry', 'babiri-berry', 'charti-berry', 'cheri-berry', 'chesto-berry', 'chilan-berry',
                'chople-berry', 'coba-berry', 'colbur-berry', 'haban-berry', 'kasib-berry', 'kebia-berry',
                'leppa-berry', 'lum-berry', 'occa-berry', 'oran-berry', 'passho-berry', 'payapa-berry',
                'pecha-berry', 'persim-berry', 'rawst-berry', 'rindo-berry', 'roseli-berry', 'shuca-berry',
                'sitrus-berry', 'tanga-berry', 'wacan-berry', 'yache-berry',
            ];

            $items = [];
            foreach ($slugs as $slug) {
                $detail = $this->fetchNamedResource('item', $slug);
                if (null === $detail) {
                    continue;
                }
                $effect = null;
                foreach ($detail['effect_entries'] ?? [] as $entry) {
                    if ('fr' === $entry['language']['name']) {
                        $effect = $entry['short_effect'] ?? $entry['effect'];
                        break;
                    }
                }
                $items[] = [
                    'slug' => $slug,
                    'name' => $this->frenchName($detail) ?? $slug,
                    'effect' => $effect ?? '',
                    'sprite' => $detail['sprites']['default'] ?? null,
                ];
            }

            usort($items, static fn (array $a, array $b) => $a['name'] <=> $b['name']);

            return $items;
        });
    }

    /**
     * The Pokémon's real French name (e.g. "Florizarre" for "Venusaur"), resolved
     * via PokeAPI's species data. Falls back to the English display name if the
     * species can't be matched or PokeAPI has no French translation for it.
     */
    public function getPokemonNameFr(string $displayName, string $showdownId): string
    {
        $slug = $this->pokedexResolver->resolveSlug($displayName, $showdownId);
        if (null === $slug) {
            return $displayName;
        }

        $species = $this->fetchNamedResource('pokemon-species', $slug);
        if (null === $species) {
            return $displayName;
        }

        return $this->frenchName($species) ?? $displayName;
    }

    /**
     * French name for a move given its English display name (as returned by
     * championsbattledata.com's usage stats, e.g. "Rock Slide"). Falls back to
     * the English name if it can't be matched on PokeAPI.
     */
    public function getMoveNameFr(string $englishName): string
    {
        $detail = $this->fetchNamedResource('move', $this->slugifyName($englishName));

        return null !== $detail ? ($this->frenchName($detail) ?? $englishName) : $englishName;
    }

    /**
     * French name for a held item given its English display name (e.g. "Life Orb").
     * Falls back to the English name if it can't be matched on PokeAPI.
     */
    /**
     * Champions-exclusive méga-gemmes (mega stones with no official PokeAPI entry).
     * Maps championsbattledata.com's raw display name to the site's own French item name.
     */
    private const CHAMPIONS_MEGA_GEMS_FR = [
        'staraptite' => 'Étouraptorite',
        'dragoninite' => 'Dracolossite',
        'feraligite' => 'Aligatueurite',
        'mega niumite' => 'Méganiumite',
        'meganiumite' => 'Méganiumite',
        'pyroarite' => 'Néméliosite',
        'scovillainite' => 'Scovilainite',
        'skarmorite' => 'Airmurite',
        'starminite' => 'Starossite',
        'crabominite' => 'Crabominablite',
        'chesnaughtite' => 'Blindépiquite',
        'chimechite' => 'Éokite',
        'clefablite' => 'Mélodelfite',
        'delphoxite' => 'Goupelinite',
        'excadrite' => 'Minotaupite',
        'froslassite' => 'Momartikite',
        'glimmoranite' => 'Floréclatite',
        'golurkite' => 'Golemastokite',
        'malamarite' => 'Sepiatrocite',
        'meowsticite' => 'Mistigrixite',
        'scolipite' => 'Brutapodite',
        'barbaracite' => 'Golgopathite',
        'drampanite' => 'Draïeulite',
        'eelektrossite' => 'Ohmassacrite',
        'emboarite' => 'Roitiflamite',
        'falinksite' => 'Hexadronite',
        'hawluchanite' => 'Brutalibrite',
        'scraftinite' => 'Baggaïdite',
        'dragalgite' => 'Kravarekite',
        'greninjite' => 'Amphinolite',
        'raichunite x' => 'Raichuïte X',
        'raichunite y' => 'Raichuïte Y',
    ];

    public function getItemNameFr(string $englishName): string
    {
        $megaGem = self::CHAMPIONS_MEGA_GEMS_FR[strtolower(trim($englishName))] ?? null;
        if (null !== $megaGem) {
            return $megaGem;
        }

        $detail = $this->fetchNamedResource('item', $this->slugifyName($englishName));

        return null !== $detail ? ($this->frenchName($detail) ?? $englishName) : $englishName;
    }

    /**
     * French name for an ability given its English display name (e.g. "Sand Veil").
     * Falls back to the English name if it can't be matched on PokeAPI.
     */
    public function getAbilityNameFr(string $englishName): string
    {
        $detail = $this->fetchNamedResource('ability', $this->slugifyName($englishName));

        return null !== $detail ? ($this->frenchName($detail) ?? $englishName) : $englishName;
    }

    private function slugifyName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = str_replace(["'", '’', '.'], '', $name);
        $name = preg_replace('/[^a-z0-9]+/', '-', $name);

        return trim($name, '-');
    }

    private function frenchName(array $resource): ?string
    {
        foreach ($resource['names'] ?? [] as $entry) {
            if ('fr' === $entry['language']['name']) {
                return $entry['name'];
            }
        }

        return null;
    }

    private function fetchPokemon(string $slug): ?array
    {
        return $this->cache->get('pokeapi_pokemon_'.$slug, function (ItemInterface $item) use ($slug) {
            $item->expiresAfter(86400);

            try {
                $response = $this->httpClient->request('GET', self::BASE_URL.'/pokemon/'.$slug);
                if (200 !== $response->getStatusCode()) {
                    return null;
                }

                return $response->toArray();
            } catch (\Throwable) {
                return null;
            }
        });
    }

    private function fetchNamedResource(string $type, string $slug): ?array
    {
        return $this->cache->get('pokeapi_'.$type.'_'.$slug, function (ItemInterface $item) use ($type, $slug) {
            $item->expiresAfter(604800);

            try {
                $response = $this->httpClient->request('GET', self::BASE_URL.'/'.$type.'/'.$slug);
                if (200 !== $response->getStatusCode()) {
                    return null;
                }

                return $response->toArray();
            } catch (\Throwable) {
                return null;
            }
        });
    }
}
