<?php

namespace App\Controller;

use App\Service\ChampionsBattleDataClient;
use App\Service\ItemCatalog;
use App\Service\NatureCatalog;
use App\Service\PokeApiClient;
use App\Twig\PokemonExtension;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The damage calculator runs entirely client-side: this controller's only job
 * is to hand the browser data once — the Pokémon list, the item catalogue, and
 * (on request) one Pokémon's full stats/movepool — so every stat tweak, nature
 * change or toggle recalculates instantly in JS instead of reloading the page.
 */
class CalculatorController extends AbstractController
{
    private const ALL_TYPES = [
        'Normal', 'Fire', 'Water', 'Electric', 'Grass', 'Ice', 'Fighting', 'Poison',
        'Ground', 'Flying', 'Psychic', 'Bug', 'Rock', 'Ghost', 'Dragon', 'Dark', 'Steel', 'Fairy',
    ];

    private const WEATHER_OPTIONS = ['pluie' => 'Pluie', 'soleil' => 'Soleil', 'sable' => 'Tempête de sable', 'neige' => 'Neige'];
    private const TERRAIN_OPTIONS = ['electrique' => 'Électrique', 'herbu' => 'Herbu', 'psychique' => 'Psychique', 'brumeux' => 'Brumeux'];

    public function __construct(
        private readonly ChampionsBattleDataClient $championsClient,
        private readonly PokeApiClient $pokeApi,
        private readonly ItemCatalog $itemCatalog,
        private readonly PokemonExtension $pokemonExtension,
        private readonly Packages $assets,
    ) {
    }

    #[Route('/calculateur-de-degats', name: 'app_calculator', methods: ['GET'])]
    public function index(): Response
    {
        $typeIcons = [];
        foreach (self::ALL_TYPES as $type) {
            $typeIcons[$type] = $this->assets->getUrl($this->pokemonExtension->typeIcon($type));
        }
        $typeNamesFr = [];
        foreach (self::ALL_TYPES as $type) {
            $typeNamesFr[$type] = $this->pokemonExtension->typeNameFr($type);
        }

        $pokemonList = array_map(fn (array $p) => [
            'slug' => $p['slug'],
            'name' => $this->pokemonExtension->pokemonNameFr($p['name'], $p['showdownId']),
            'sprite' => $this->pokemonExtension->championsAsset($p['summary']['sprite']),
            'types' => $p['summary']['types'],
        ], $this->championsClient->getAllPokemon());

        return $this->render('calculator/index.html.twig', [
            'bootstrap' => [
                'pokemonList' => $pokemonList,
                'itemCatalog' => $this->itemCatalog->all(),
                'natures' => NatureCatalog::all(),
                'weatherOptions' => self::WEATHER_OPTIONS,
                'terrainOptions' => self::TERRAIN_OPTIONS,
                'typeIcons' => $typeIcons,
                'typeNamesFr' => $typeNamesFr,
            ],
        ]);
    }

    #[Route('/calculateur-de-degats/api/pokemon/{slug}', name: 'app_calculator_pokemon_json', methods: ['GET'])]
    public function pokemonJson(string $slug): JsonResponse
    {
        $pokemon = $this->championsClient->getPokemonBySlug($slug);
        if (null === $pokemon) {
            throw $this->createNotFoundException();
        }

        return $this->json($this->serializePokemon($pokemon));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePokemon(array $pokemon): array
    {
        $baseStats = $this->pokeApi->getBaseStats($pokemon['name'], $pokemon['showdownId'])
            ?? ['hp' => 1, 'attack' => 1, 'defense' => 1, 'sp_attack' => 1, 'sp_defense' => 1, 'speed' => 1];
        $abilities = $this->pokeApi->getAbilities($pokemon['name'], $pokemon['showdownId']);
        $moves = $this->pokeApi->getLearnableMoves($pokemon['name'], $pokemon['showdownId']);

        return [
            'slug' => $pokemon['slug'],
            'name' => $this->pokemonExtension->pokemonNameFr($pokemon['name'], $pokemon['showdownId']),
            'sprite' => $this->pokemonExtension->championsAsset($pokemon['summary']['sprite']),
            'types' => $pokemon['summary']['types'],
            'baseStats' => $baseStats,
            'abilities' => $abilities,
            'moves' => $moves,
            'forms' => $this->serializeMegaForms($pokemon),
        ];
    }

    /**
     * Real stats/types/abilities (via PokeAPI, not the champions API's level-50-calculated
     * numbers) for each of this Pokémon's mega forms — only the ones PokeAPI actually has
     * data for are included, so the calculator's "Forme" picker never offers a dead option.
     *
     * @return array<int, array{slug: string, label: string, sprite: string, types: array<string>, baseStats: array, abilities: array}>
     */
    private function serializeMegaForms(array $pokemon): array
    {
        $forms = [];
        foreach ($pokemon['summary']['forms'] ?? [] as $formEntry) {
            if ('Base' === ($formEntry['form_kind'] ?? 'Base')) {
                continue;
            }

            $megaForm = $this->pokeApi->getMegaForm($pokemon['name'], $pokemon['showdownId'], $formEntry['form_name']);
            if (null === $megaForm) {
                continue;
            }

            $suffix = '';
            $upperFormName = strtoupper($formEntry['form_name']);
            if (str_ends_with($upperFormName, ' X')) {
                $suffix = ' X';
            } elseif (str_ends_with($upperFormName, ' Y')) {
                $suffix = ' Y';
            }

            $forms[] = [
                'slug' => $formEntry['slug'],
                'label' => 'Méga-'.$this->pokemonExtension->pokemonNameFr($pokemon['name'], $pokemon['showdownId']).$suffix,
                'sprite' => $this->pokemonExtension->championsAsset(str_replace('\\', '/', $formEntry['image_path'])),
                'types' => $megaForm['types'],
                'baseStats' => $megaForm['stats'],
                'abilities' => $megaForm['abilities'],
            ];
        }

        return $forms;
    }
}
