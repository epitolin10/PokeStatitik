<?php

namespace App\Controller;

use App\Entity\Equipe;
use App\Repository\EquipeRepository;
use App\Service\ChampionsBattleDataClient;
use App\Service\PokeApiClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(EquipeRepository $equipeRepository, ChampionsBattleDataClient $client): Response
    {
        $recentTeams = \array_slice($this->buildTeamRows($equipeRepository->findAllOrdered(), $client), 0, 4);

        return $this->render('home/index.html.twig', [
            'recentTeams' => $recentTeams,
        ]);
    }

    #[Route('/usage-pokemon', name: 'app_usage')]
    public function usage(Request $request, ChampionsBattleDataClient $client, PokeApiClient $pokeApi): Response
    {
        $format = 'Singles' === $request->query->get('format') ? 'Singles' : 'Doubles';

        $allPokemon = $client->getAllPokemon();

        $selectedSlug = $request->query->get('pokemon');
        $selected = $selectedSlug ? $client->getPokemonBySlug($selectedSlug) : null;
        $selected ??= $allPokemon[0] ?? null;

        $battleRows = $selected ? $client->getBattleRows($format, $selected['slug']) : [];
        $rowsByCategory = [];
        foreach ($battleRows as $row) {
            $rowsByCategory[$row['category']][] = $row;
        }

        $baseStats = $selected ? $pokeApi->getBaseStats($selected['name'], $selected['showdownId']) : null;

        return $this->render('home/usage.html.twig', [
            'allPokemon' => $allPokemon,
            'selected' => $selected,
            'format' => $format,
            'rowsByCategory' => $rowsByCategory,
            'baseStats' => $baseStats,
        ]);
    }

    #[Route('/equipes', name: 'app_teams')]
    public function teams(EquipeRepository $equipeRepository, ChampionsBattleDataClient $client): Response
    {
        $teams = $this->buildTeamRows($equipeRepository->findAllOrdered(), $client);

        return $this->render('home/teams.html.twig', [
            'teams' => $teams,
        ]);
    }

    #[Route('/calculateur-de-degats', name: 'app_calculator')]
    public function calculator(): Response
    {
        return $this->render('home/coming_soon.html.twig', [
            'pageTitle' => 'Calculateur de dégâts',
        ]);
    }

    /**
     * Equipe entities shaped for the .recent-teams table (home teaser and the
     * full "Équipes" list share this): pseudo, resolved Pokémon sprites, titre,
     * tier name and a link to that team's page.
     *
     * @param Equipe[] $equipes
     *
     * @return array<int, array{id: int, pseudo: string, pokemons: array<int, array{sprite: string, name: string}>, titre: string, tier: string, favoris: int}>
     */
    private function buildTeamRows(array $equipes, ChampionsBattleDataClient $client): array
    {
        $rows = [];
        foreach ($equipes as $equipe) {
            $pokemons = [];
            foreach ($equipe->getBuildPokemons() as $build) {
                $pokemon = $client->getPokemonBySlug($build->getPokemonSlug());
                if (null === $pokemon) {
                    continue;
                }
                $pokemons[] = [
                    'sprite' => $client->assetUrl($pokemon['summary']['sprite']),
                    'name' => $pokemon['name'],
                ];
            }

            $rows[] = [
                'id' => $equipe->getId(),
                'pseudo' => $equipe->getCompte()?->getPseudo() ?? '—',
                'pokemons' => $pokemons,
                'titre' => $equipe->getTitre(),
                'tier' => $equipe->getTiers()?->getNomTiers() ?? '—',
                'favoris' => 0,
            ];
        }

        return $rows;
    }
}
