<?php

namespace App\Twig;

use App\Service\NatureCatalog;
use App\Service\PokeApiClient;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class PokemonExtension extends AbstractExtension
{
    public function __construct(private readonly PokeApiClient $pokeApi)
    {
    }

    /**
     * Base-stat quality tiers, as given by the site owner.
     * "Excellent" applies whenever a stat exceeds 150, regardless of the upper bound.
     */
    private const STAT_TIERS = [
        ['max' => 59, 'color' => '#db2828'],  // très faible
        ['max' => 79, 'color' => '#ef6e33'],  // faible
        ['max' => 99, 'color' => '#FBBD08'],  // moyen
        ['max' => 119, 'color' => '#B5CC18'], // bon
        ['max' => 150, 'color' => '#21BA45'], // très bon
        ['max' => PHP_INT_MAX, 'color' => '#7B2FF7'], // excellent (> 150)
    ];

    private const TYPE_NAMES_FR = [
        'Normal' => 'Normal', 'Fire' => 'Feu', 'Water' => 'Eau', 'Electric' => 'Électrik',
        'Grass' => 'Plante', 'Ice' => 'Glace', 'Fighting' => 'Combat', 'Poison' => 'Poison',
        'Ground' => 'Sol', 'Flying' => 'Vol', 'Psychic' => 'Psy', 'Bug' => 'Insecte',
        'Rock' => 'Roche', 'Ghost' => 'Spectre', 'Dragon' => 'Dragon', 'Dark' => 'Ténèbres',
        'Steel' => 'Acier', 'Fairy' => 'Fée',
    ];

    public function getFunctions(): array
    {
        return [
            new TwigFunction('stat_color', [$this, 'statColor']),
            new TwigFunction('type_name_fr', [$this, 'typeNameFr']),
            new TwigFunction('type_icon', [$this, 'typeIcon']),
            new TwigFunction('champions_asset', [$this, 'championsAsset']),
            new TwigFunction('pokemon_name_fr', [$this, 'pokemonNameFr']),
            new TwigFunction('move_category_fr', [$this, 'moveCategoryFr']),
            new TwigFunction('move_name_fr', [$this, 'moveNameFr']),
            new TwigFunction('item_name_fr', [$this, 'itemNameFr']),
            new TwigFunction('ability_name_fr', [$this, 'abilityNameFr']),
            new TwigFunction('nature_name_fr', [NatureCatalog::class, 'nameFr']),
        ];
    }

    public function moveCategoryFr(string $damageClass): string
    {
        return match ($damageClass) {
            'physical' => 'Physique',
            'special' => 'Spécial',
            'status' => 'Statut',
            default => $damageClass,
        };
    }

    public function championsAsset(string $relativePath): string
    {
        return 'https://championsbattledata.com/'.ltrim($relativePath, '/');
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('stat_color', [$this, 'statColor']),
        ];
    }

    public function statColor(?int $value): string
    {
        $value ??= 0;
        foreach (self::STAT_TIERS as $tier) {
            if ($value <= $tier['max']) {
                return $tier['color'];
            }
        }

        return end(self::STAT_TIERS)['color'];
    }

    public function typeNameFr(string $englishType): string
    {
        return self::TYPE_NAMES_FR[$englishType] ?? $englishType;
    }
    public function pokemonNameFr(string $englishPokemon, string $showdownId = ''): string
    {
        return $this->pokeApi->getPokemonNameFr($englishPokemon, $showdownId);
    }

    public function moveNameFr(string $englishMove): string
    {
        return $this->pokeApi->getMoveNameFr($englishMove);
    }

    public function itemNameFr(string $englishItem): string
    {
        return $this->pokeApi->getItemNameFr($englishItem);
    }

    public function abilityNameFr(string $englishAbility): string
    {
        return $this->pokeApi->getAbilityNameFr($englishAbility);
    }

    public function typeIcon(string $englishType): string
    {
        return 'images/Icone_type/IconeSimple/'.$englishType.'_icon_HOME3.png';
    }
}
