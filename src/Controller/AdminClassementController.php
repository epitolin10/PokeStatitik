<?php

namespace App\Controller;

use App\Entity\ClassementEntry;
use App\Entity\Tiers;
use App\Repository\ClassementEntryRepository;
use App\Repository\TiersRepository;
use App\Service\ChampionsBattleDataClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Lets the site owner curate the "classement" (top Pokémon ranking) shown on the
 * public Usage Pokémon page — independent from the automatic usage stats scraped
 * from championsbattledata.com. Every Pokémon is listed directly with a rank
 * number field per Tiers (Champions Solo/Duo); leaving a field empty means
 * "not ranked" for that tier.
 */
#[IsGranted('ROLE_ADMIN')]
class AdminClassementController extends AbstractController
{
    #[Route('/admin/classement', name: 'app_admin_classement', methods: ['GET'])]
    public function index(
        Request $request,
        TiersRepository $tiersRepository,
        ClassementEntryRepository $classementRepository,
        ChampionsBattleDataClient $client,
    ): Response {
        $query = trim((string) $request->query->get('q', ''));
        $allPokemon = $client->getAllPokemon();

        if ('' !== $query) {
            $needle = mb_strtolower($query);
            $allPokemon = array_values(array_filter(
                $allPokemon,
                static fn (array $p) => str_contains(mb_strtolower($p['name']), $needle)
            ));
        }

        $tiersList = $tiersRepository->findAll();
        // "Champions Solo" first, "Champions Duo" second — matches how the admin wants the fields laid out
        usort($tiersList, static fn (Tiers $a, Tiers $b) => str_contains(mb_strtolower((string) $b->getNomTiers()), 'solo') <=> str_contains(mb_strtolower((string) $a->getNomTiers()), 'solo'));

        // rangBySlug[tiersId][pokemonSlug] = rang, so each row can prefill both number fields
        $rangBySlug = [];
        foreach ($tiersList as $tiers) {
            $rangBySlug[$tiers->getId()] = [];
            foreach ($classementRepository->findByTiersOrdered($tiers) as $entry) {
                $rangBySlug[$tiers->getId()][$entry->getPokemonSlug()] = $entry->getRang();
            }
        }

        $rows = array_map(static fn (array $pokemon) => [
            'pokemon' => $pokemon,
            'rangByTiersId' => array_map(static fn (array $bySlug) => $bySlug[$pokemon['slug']] ?? null, $rangBySlug),
        ], $allPokemon);

        return $this->render('admin/classement.html.twig', [
            'tiersList' => $tiersList,
            'rows' => $rows,
            'query' => $query,
        ]);
    }

    /**
     * Saves a single Pokémon/tier rank, called via fetch from admin_classement_controller.js as the
     * admin types — no page reload. An empty rang removes the Pokémon from that tier's classement.
     */
    #[Route('/admin/classement/definir', name: 'app_admin_classement_set', methods: ['POST'])]
    public function setRang(
        Request $request,
        EntityManagerInterface $entityManager,
        ClassementEntryRepository $classementRepository,
        ChampionsBattleDataClient $client,
    ): JsonResponse {
        $tiers = $entityManager->getRepository(Tiers::class)->find((int) $request->request->get('tiersId'));
        $slug = trim((string) $request->request->get('slug'));
        $rangRaw = trim((string) $request->request->get('rang'));

        if (null === $tiers || '' === $slug || null === $client->getPokemonBySlug($slug)) {
            throw $this->createNotFoundException();
        }

        $existing = $classementRepository->findOneByTiersAndSlug($tiers, $slug);

        if ('' === $rangRaw) {
            if (null !== $existing) {
                $entityManager->remove($existing);
                $entityManager->flush();
            }

            return new JsonResponse(['rang' => null]);
        }

        $rang = max(1, (int) $rangRaw);

        if (null !== $existing) {
            $existing->setRang($rang);
        } else {
            $entry = new ClassementEntry();
            $entry->setTiers($tiers);
            $entry->setPokemonSlug($slug);
            $entry->setRang($rang);
            $entityManager->persist($entry);
        }
        $entityManager->flush();

        return new JsonResponse(['rang' => $rang]);
    }
}
