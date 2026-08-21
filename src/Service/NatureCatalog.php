<?php

namespace App\Service;

/**
 * The 25 Pokémon natures — fixed game data, not sourced from any API.
 * Grid convention: row = stat increased, column = stat decreased.
 */
class NatureCatalog
{
    public const STATS = ['attaque' => 'Attaque', 'defense' => 'Défense', 'atqSpe' => 'Atq. Spé.', 'defSpe' => 'Déf. Spé.', 'vitesse' => 'Vitesse'];

    /**
     * @return array<string, array{name: string, up: ?string, down: ?string}>
     */
    public static function all(): array
    {
        return [
            'serieux' => ['name' => 'Sérieux', 'up' => null, 'down' => null],
            'solo' => ['name' => 'Solo', 'up' => 'attaque', 'down' => 'defense'],
            'rigide' => ['name' => 'Rigide', 'up' => 'attaque', 'down' => 'atqSpe'],
            'mauvais' => ['name' => 'Mauvais', 'up' => 'attaque', 'down' => 'defSpe'],
            'brave' => ['name' => 'Brave', 'up' => 'attaque', 'down' => 'vitesse'],

            'assure' => ['name' => 'Assuré', 'up' => 'defense', 'down' => 'attaque'],
            'docile' => ['name' => 'Docile', 'up' => null, 'down' => null],
            'malin' => ['name' => 'Malin', 'up' => 'defense', 'down' => 'atqSpe'],
            'lache' => ['name' => 'Lâche', 'up' => 'defense', 'down' => 'defSpe'],
            'relax' => ['name' => 'Relax', 'up' => 'defense', 'down' => 'vitesse'],

            'modeste' => ['name' => 'Modeste', 'up' => 'atqSpe', 'down' => 'attaque'],
            'doux' => ['name' => 'Doux', 'up' => 'atqSpe', 'down' => 'defense'],
            'farceur' => ['name' => 'Farceur', 'up' => null, 'down' => null],
            'foufou' => ['name' => 'Foufou', 'up' => 'atqSpe', 'down' => 'defSpe'],
            'discret' => ['name' => 'Discret', 'up' => 'atqSpe', 'down' => 'vitesse'],

            'calme' => ['name' => 'Calme', 'up' => 'defSpe', 'down' => 'attaque'],
            'gentil' => ['name' => 'Gentil', 'up' => 'defSpe', 'down' => 'defense'],
            'prudent' => ['name' => 'Prudent', 'up' => 'defSpe', 'down' => 'atqSpe'],
            'bizarre' => ['name' => 'Bizarre', 'up' => null, 'down' => null],
            'malpoli' => ['name' => 'Malpoli', 'up' => 'defSpe', 'down' => 'vitesse'],

            'timide' => ['name' => 'Timide', 'up' => 'vitesse', 'down' => 'attaque'],
            'presse' => ['name' => 'Pressé', 'up' => 'vitesse', 'down' => 'defense'],
            'jovial' => ['name' => 'Jovial', 'up' => 'vitesse', 'down' => 'atqSpe'],
            'naif' => ['name' => 'Naïf', 'up' => 'vitesse', 'down' => 'defSpe'],
            'hardi' => ['name' => 'Hardi', 'up' => null, 'down' => null],
        ];
    }

    /**
     * Grid indexed [rowStatKey][colStatKey] => nature key, for rendering the 5x5 table.
     *
     * @return array<string, array<string, string>>
     */
    public static function grid(): array
    {
        $grid = [];
        foreach (self::all() as $key => $nature) {
            $row = $nature['up'] ?? self::neutralRowFor($key);
            $col = $nature['down'] ?? $row;
            $grid[$row][$col] = $key;
        }

        return $grid;
    }

    private static function neutralRowFor(string $key): string
    {
        return match ($key) {
            'serieux' => 'attaque',
            'docile' => 'defense',
            'farceur' => 'atqSpe',
            'bizarre' => 'defSpe',
            'hardi' => 'vitesse',
            default => 'attaque',
        };
    }

    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function label(string $key): string
    {
        return self::find($key)['name'] ?? $key;
    }

    /** English nature name (as returned by championsbattledata.com's usage stats) => French key above. */
    private const ENGLISH_TO_KEY = [
        'hardy' => 'serieux', 'lonely' => 'solo', 'brave' => 'brave', 'adamant' => 'rigide', 'naughty' => 'mauvais',
        'bold' => 'assure', 'docile' => 'docile', 'relaxed' => 'relax', 'impish' => 'malin', 'lax' => 'lache',
        'timid' => 'timide', 'hasty' => 'presse', 'serious' => 'serieux', 'jolly' => 'jovial', 'naive' => 'naif',
        'modest' => 'modeste', 'mild' => 'doux', 'quiet' => 'discret', 'bashful' => 'farceur', 'rash' => 'foufou',
        'calm' => 'calme', 'gentle' => 'gentil', 'sassy' => 'malpoli', 'careful' => 'prudent', 'quirky' => 'bizarre',
    ];

    /**
     * French name for a nature given its English display name (e.g. "Adamant" => "Rigide").
     * Falls back to the English name if it isn't recognized.
     */
    public static function nameFr(string $englishName): string
    {
        $key = self::ENGLISH_TO_KEY[strtolower(trim($englishName))] ?? null;

        return null !== $key ? self::label($key) : $englishName;
    }
}
