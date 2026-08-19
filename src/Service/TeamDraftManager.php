<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Holds the in-progress team a user is building, in session, until they publish it.
 * Nothing touches the database until TeamBuilderController::publish().
 */
class TeamDraftManager
{
    private const SESSION_KEY = 'team_draft';

    public const EMPTY_SLOT = [
        'pokemonUrl' => null,
        'objet' => null,
        'capacite1' => null,
        'capacite2' => null,
        'capacite3' => null,
        'capacite4' => null,
        'nature' => null,
        'talent' => null,
        'ivPv' => 0,
        'ivAtq' => 0,
        'ivDef' => 0,
        'ivAtqSpe' => 0,
        'ivDefSpe' => 0,
        'ivVitesse' => 0,
    ];

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function get(): array
    {
        $session = $this->requestStack->getSession();
        $draft = $session->get(self::SESSION_KEY);

        if (!\is_array($draft)) {
            $draft = $this->blank();
            $session->set(self::SESSION_KEY, $draft);
        }

        return $draft;
    }

    public function blank(): array
    {
        return [
            'titre' => '',
            'description' => '',
            'idEquipePokemonChampions' => '',
            'tiersId' => null,
            'slots' => array_fill(1, 6, null),
        ];
    }

    public function save(array $draft): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $draft);
    }

    public function updateInfo(string $titre, ?string $description, ?string $idEquipeChampions, ?int $tiersId): void
    {
        $draft = $this->get();
        $draft['titre'] = $titre;
        $draft['description'] = $description;
        $draft['idEquipePokemonChampions'] = $idEquipeChampions;
        $draft['tiersId'] = $tiersId;
        $this->save($draft);
    }

    public function setSlotPokemon(int $position, string $pokemonUrl): void
    {
        $draft = $this->get();
        $slot = $draft['slots'][$position] ?? self::EMPTY_SLOT;
        $slot['pokemonUrl'] = $pokemonUrl;
        $draft['slots'][$position] = array_merge(self::EMPTY_SLOT, $slot);
        $this->save($draft);
    }

    public function updateSlot(int $position, array $data): void
    {
        $draft = $this->get();
        $slot = $draft['slots'][$position] ?? self::EMPTY_SLOT;
        $draft['slots'][$position] = array_merge(self::EMPTY_SLOT, $slot, $data);
        $this->save($draft);
    }

    public function removeSlot(int $position): void
    {
        $draft = $this->get();
        $draft['slots'][$position] = null;
        $this->save($draft);
    }

    public function getSlot(int $position): ?array
    {
        return $this->get()['slots'][$position] ?? null;
    }

    public function clear(): void
    {
        $this->requestStack->getSession()->remove(self::SESSION_KEY);
    }

    public function filledSlotCount(): int
    {
        return \count(array_filter($this->get()['slots'], static fn ($slot) => null !== $slot && null !== $slot['pokemonUrl']));
    }
}
