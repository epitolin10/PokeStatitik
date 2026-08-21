<?php

namespace App\Controller;

use App\Entity\Compte;
use App\Entity\Equipe;
use App\Repository\ClassementEntryRepository;
use App\Repository\EquipeRepository;
use App\Repository\LikeRepository;
use App\Repository\TiersRepository;
use App\Service\ChampionsBattleDataClient;
use App\Service\PokeApiClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(EquipeRepository $equipeRepository, ChampionsBattleDataClient $client, LikeRepository $likeRepository): Response
    {
        $recentTeams = \array_slice($this->buildTeamRows($equipeRepository->findAllOrdered(), $client, $likeRepository), 0, 4);

        return $this->render('home/index.html.twig', [
            'recentTeams' => $recentTeams,
        ]);
    }

    #[Route('/usage-pokemon', name: 'app_usage')]
    public function usage(
        Request $request,
        ChampionsBattleDataClient $client,
        PokeApiClient $pokeApi,
        TiersRepository $tiersRepository,
        ClassementEntryRepository $classementRepository,
    ): Response {
        $format = 'Singles' === $request->query->get('format') ? 'Singles' : 'Doubles';

        $allPokemon = $this->applyClassementRanking($client->getAllPokemon(), $format, $tiersRepository, $classementRepository);

        $selectedSlug = $request->query->get('pokemon');
        $selected = $selectedSlug ? $client->getPokemonBySlug($selectedSlug) : null;
        $selected ??= $allPokemon[0] ?? null;

        $battleRows = $selected ? $client->getBattleRows($format, $selected['slug']) : [];
        $rowsByCategory = [];
        foreach ($battleRows as $row) {
            $rowsByCategory[$row['category']][] = $row;
        }

        $selectedForm = null;
        $formSlug = $request->query->get('form');
        if (null !== $selected && null !== $formSlug) {
            foreach ($selected['summary']['forms'] ?? [] as $formEntry) {
                if ($formEntry['slug'] === $formSlug) {
                    $selectedForm = $formEntry;
                    break;
                }
            }
        }
        $isMega = null !== $selectedForm && 'Base' !== ($selectedForm['form_kind'] ?? 'Base');

        $baseStats = $selected ? $pokeApi->getBaseStats($selected['name'], $selected['showdownId']) : null;
        $megaStatsUnavailable = false;
        $megaDisplayName = null;
        if ($isMega && null !== $selected) {
            $megaForm = $pokeApi->getMegaForm($selected['name'], $selected['showdownId'], $selectedForm['form_name']);
            if (null !== $megaForm) {
                $baseStats = $megaForm['stats'];
            } else {
                $megaStatsUnavailable = true;
            }

            $suffix = '';
            $upperFormName = strtoupper($selectedForm['form_name']);
            if (str_ends_with($upperFormName, ' X')) {
                $suffix = ' X';
            } elseif (str_ends_with($upperFormName, ' Y')) {
                $suffix = ' Y';
            }
            $megaDisplayName = 'Méga-'.$pokeApi->getPokemonNameFr($selected['name'], $selected['showdownId']).$suffix;
        }

        $context = [
            'allPokemon' => $allPokemon,
            'selected' => $selected,
            'format' => $format,
            'rowsByCategory' => $rowsByCategory,
            'baseStats' => $baseStats,
            'selectedForm' => $selectedForm,
            'isMega' => $isMega,
            'megaStatsUnavailable' => $megaStatsUnavailable,
            'megaDisplayName' => $megaDisplayName,
        ];

        // clicking a Pokémon/forme fetches just the detail panel via JS (usage_controller.js),
        // so the page never reloads and the list keeps its scroll position
        if ('XMLHttpRequest' === $request->headers->get('X-Requested-With')) {
            return $this->render('home/fragments/usage_detail.html.twig', $context);
        }

        return $this->render('home/usage.html.twig', $context);
    }

    /**
     * Merges in the admin-curated classement (see AdminClassementController) for the tier matching
     * the current usage format: ranked Pokémon come first in rank order (with their rang exposed for
     * the numbered badge), everything else keeps the existing dex-number order after them.
     *
     * @param array<int, array<string, mixed>> $allPokemon
     *
     * @return array<int, array<string, mixed>>
     */
    private function applyClassementRanking(array $allPokemon, string $format, TiersRepository $tiersRepository, ClassementEntryRepository $classementRepository): array
    {
        $needle = 'Doubles' === $format ? 'duo' : 'solo';
        $tiers = null;
        foreach ($tiersRepository->findAll() as $candidate) {
            if (str_contains(mb_strtolower((string) $candidate->getNomTiers()), $needle)) {
                $tiers = $candidate;
                break;
            }
        }

        $rangBySlug = [];
        if (null !== $tiers) {
            foreach ($classementRepository->findByTiersOrdered($tiers) as $entry) {
                $rangBySlug[$entry->getPokemonSlug()] = $entry->getRang();
            }
        }

        foreach ($allPokemon as &$pokemon) {
            $pokemon['rang'] = $rangBySlug[$pokemon['slug']] ?? null;
        }
        unset($pokemon);

        usort($allPokemon, static function (array $a, array $b) {
            if (null !== $a['rang'] && null !== $b['rang']) {
                return $a['rang'] <=> $b['rang'];
            }
            if (null !== $a['rang']) {
                return -1;
            }
            if (null !== $b['rang']) {
                return 1;
            }

            return ($a['dexNumber'] ?? PHP_INT_MAX) <=> ($b['dexNumber'] ?? PHP_INT_MAX);
        });

        return $allPokemon;
    }

    #[Route('/equipes', name: 'app_teams')]
    public function teams(EquipeRepository $equipeRepository, ChampionsBattleDataClient $client, LikeRepository $likeRepository): Response
    {
        $teams = $this->buildTeamRows($equipeRepository->findAllOrdered(), $client, $likeRepository);

        return $this->render('home/teams.html.twig', [
            'teams' => $teams,
        ]);
    }

    /**
     * Equipe entities shaped for the .recent-teams table (home teaser and the
     * full "Équipes" list share this): pseudo, resolved Pokémon sprites, titre,
     * tier name and a link to that team's page.
     *
     * @param Equipe[] $equipes
     *
     * @return array<int, array{id: int, pseudo: string, avatarFilename: ?string, pokemons: array<int, array{sprite: string, name: string}>, titre: string, tier: string, favoris: int, hasLiked: bool}>
     */
    private function buildTeamRows(array $equipes, ChampionsBattleDataClient $client, LikeRepository $likeRepository): array
    {
        $currentUser = $this->getUser();
        $likedEquipeIds = [];
        if ($currentUser instanceof Compte && [] !== $equipes) {
            $equipeIds = array_map(static fn (Equipe $e) => $e->getId(), $equipes);
            foreach ($likeRepository->findBy(['idEquipe' => $equipeIds, 'idCompte' => $currentUser->getId()]) as $like) {
                $likedEquipeIds[$like->getIdEquipe()] = true;
            }
        }

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
                'avatarFilename' => $equipe->getCompte()?->getAvatarFilename(),
                'pokemons' => $pokemons,
                'titre' => $equipe->getTitre(),
                'tier' => $equipe->getTiers()?->getNomTiers() ?? '—',
                'favoris' => $likeRepository->count(['idEquipe' => $equipe->getId()]),
                'hasLiked' => isset($likedEquipeIds[$equipe->getId()]),
            ];
        }

        return $rows;
    }
}
