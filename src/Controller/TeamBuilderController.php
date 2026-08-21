<?php

namespace App\Controller;

use App\Entity\BuildPokemon;
use App\Entity\Commentaire;
use App\Entity\Compte;
use App\Entity\Equipe;
use App\Entity\Like;
use App\Entity\Tiers;
use App\Repository\CommentaireRepository;
use App\Repository\CompteRepository;
use App\Repository\LikeRepository;
use App\Repository\TiersRepository;
use App\Service\ChampionsBattleDataClient;
use App\Service\ItemCatalog;
use App\Service\NatureCatalog;
use App\Service\PokeApiClient;
use App\Service\TeamDraftManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TeamBuilderController extends AbstractController
{
    /** French labels for BuildPokemon fields, used to turn a bare "This value should not be blank." into a message that says what's actually missing. */
    private const BUILD_FIELD_LABELS = [
        'capacite1' => 'Capacité 1',
        'capacite2' => 'Capacité 2',
        'capacite3' => 'Capacité 3',
        'capacite4' => 'Capacité 4',
        'nature' => 'Nature',
        'talent' => 'Talent',
        'pokemonUrl' => 'Pokémon',
    ];

    private const EQUIPE_FIELD_LABELS = [
        'titre' => 'Titre',
        'tiers' => 'Tiers',
    ];

    public function __construct(
        private readonly TeamDraftManager $draft,
        private readonly ChampionsBattleDataClient $championsClient,
        private readonly PokeApiClient $pokeApi,
        private readonly TiersRepository $tiersRepository,
    ) {
    }

    #[Route('/equipes/creer', name: 'app_team_builder', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('team_builder/index.html.twig', [
            'draft' => $this->draft->get(),
            'slots' => $this->hydrateSlots(),
            'tiersList' => $this->tiersRepository->findAll(),
            'isEditing' => null !== $this->draft->getEditingEquipeId(),
        ]);
    }

    #[Route('/equipes/{id}/modifier', name: 'app_team_builder_edit', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function edit(int $id, EntityManagerInterface $entityManager, #[CurrentUser] Compte $compte): Response
    {
        $equipe = $entityManager->getRepository(Equipe::class)->find($id);
        if (null === $equipe) {
            throw $this->createNotFoundException();
        }
        if ($equipe->getCompte()?->getId() !== $compte->getId()) {
            throw $this->createAccessDeniedException();
        }

        $this->draft->loadFromEquipe($equipe);

        return $this->redirectToRoute('app_team_builder');
    }

    #[Route('/equipes/creer/info', name: 'app_team_builder_info', methods: ['POST'])]
    public function saveInfo(Request $request): RedirectResponse
    {
        $this->draft->updateInfo(
            trim((string) $request->request->get('titre')),
            $request->request->get('description'),
            $request->request->get('idEquipeChampions') ?: null,
            $request->request->get('tiersId') ? (int) $request->request->get('tiersId') : null,
        );

        return $this->redirectToRoute('app_team_builder');
    }

    #[Route('/equipes/creer/slot/{position}/remove', name: 'app_team_builder_slot_remove', requirements: ['position' => '[1-6]'], methods: ['POST'])]
    public function removeSlot(int $position): RedirectResponse
    {
        $this->draft->removeSlot($position);

        return $this->redirectToRoute('app_team_builder');
    }

    #[Route('/equipes/creer/slot/{position}', name: 'app_team_builder_slot', requirements: ['position' => '[1-6]'], methods: ['GET'])]
    public function slot(int $position, Request $request): Response
    {
        $context = $this->buildPanelContext($position);
        if (null === $context) {
            return $this->redirectToRoute('app_team_builder');
        }

        if ($this->isAjax($request)) {
            return $this->render('team_builder/fragments/build_panel.html.twig', $context);
        }

        return $this->render('team_builder/slot.html.twig', $context + ['slots' => $this->hydrateSlots()]);
    }

    #[Route('/equipes/creer/slot/{position}/stats', name: 'app_team_builder_slot_stats', requirements: ['position' => '[1-6]'], methods: ['POST'])]
    public function saveStats(int $position, Request $request): Response
    {
        $points = [];
        foreach (['ivPv', 'ivAtq', 'ivDef', 'ivAtqSpe', 'ivDefSpe', 'ivVitesse'] as $key) {
            $value = max(0, min(BuildPokemon::MAX_POINTS_PAR_STAT, (int) $request->request->get($key, 0)));
            $points[$key] = $value;
        }

        $total = array_sum($points);
        if ($total > BuildPokemon::MAX_POINTS_TOTAL) {
            // scale every stat down proportionally rather than silently discarding the excess
            $factor = BuildPokemon::MAX_POINTS_TOTAL / $total;
            foreach ($points as $key => $value) {
                $points[$key] = (int) floor($value * $factor);
            }
        }

        $points['talent'] = $request->request->get('talent') ?: null;

        $this->draft->updateSlot($position, $points);

        return $this->respondBackToBuildPanel($position, $request);
    }

    #[Route('/equipes/creer/slot/{position}/pokemon', name: 'app_team_builder_choose_pokemon', requirements: ['position' => '[1-6]'], methods: ['GET'])]
    public function choosePokemon(int $position, Request $request): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $allPokemon = $this->championsClient->getAllPokemon();

        if ('' !== $query) {
            $needle = mb_strtolower($query);
            $allPokemon = array_values(array_filter(
                $allPokemon,
                static fn (array $p) => str_contains(mb_strtolower($p['name']), $needle)
            ));
        }

        $context = [
            'position' => $position,
            'pokemonList' => $allPokemon,
            'query' => $query,
        ];

        if ($this->isAjax($request)) {
            return $this->render('team_builder/fragments/pokemon.html.twig', $context);
        }

        return $this->render('team_builder/modal_pokemon.html.twig', $context);
    }

    #[Route('/equipes/creer/slot/{position}/pokemon', name: 'app_team_builder_set_pokemon', requirements: ['position' => '[1-6]'], methods: ['POST'])]
    public function setPokemon(int $position, Request $request): Response
    {
        $slug = (string) $request->request->get('slug');
        if ('' !== $slug) {
            $this->draft->setSlotPokemon($position, $slug);
        }

        if ($this->isAjax($request)) {
            // the Pokémon list only fills the slot — the build panel opens on a later, separate click
            return new JsonResponse(['reload' => true]);
        }

        return $this->redirectToRoute('app_team_builder_slot', ['position' => $position]);
    }

    #[Route('/equipes/creer/slot/{position}/nature', name: 'app_team_builder_choose_nature', requirements: ['position' => '[1-6]'], methods: ['GET'])]
    public function chooseNature(int $position, Request $request): Response
    {
        $context = [
            'position' => $position,
            'grid' => NatureCatalog::grid(),
            'stats' => NatureCatalog::STATS,
            'natures' => NatureCatalog::all(),
        ];

        if ($this->isAjax($request)) {
            return $this->render('team_builder/fragments/nature.html.twig', $context);
        }

        return $this->render('team_builder/modal_nature.html.twig', $context);
    }

    #[Route('/equipes/creer/slot/{position}/nature', name: 'app_team_builder_set_nature', requirements: ['position' => '[1-6]'], methods: ['POST'])]
    public function setNature(int $position, Request $request): Response
    {
        $this->draft->updateSlot($position, ['nature' => (string) $request->request->get('nature')]);

        return $this->respondBackToBuildPanel($position, $request);
    }

    #[Route('/equipes/creer/slot/{position}/objet', name: 'app_team_builder_choose_objet', requirements: ['position' => '[1-6]'], methods: ['GET'])]
    public function chooseObjet(int $position, Request $request, ItemCatalog $itemCatalog): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $items = $itemCatalog->search($query);

        $context = [
            'position' => $position,
            'items' => $items,
            'query' => $query,
        ];

        if ($this->isAjax($request)) {
            return $this->render('team_builder/fragments/objet.html.twig', $context);
        }

        return $this->render('team_builder/modal_objet.html.twig', $context);
    }

    #[Route('/equipes/creer/slot/{position}/objet', name: 'app_team_builder_set_objet', requirements: ['position' => '[1-6]'], methods: ['POST'])]
    public function setObjet(int $position, Request $request): Response
    {
        $this->draft->updateSlot($position, ['objet' => (string) $request->request->get('objet')]);

        return $this->respondBackToBuildPanel($position, $request);
    }

    #[Route('/equipes/creer/slot/{position}/capacite/{slotIndex}', name: 'app_team_builder_choose_capacite', requirements: ['position' => '[1-6]', 'slotIndex' => '[1-4]'], methods: ['GET'])]
    public function chooseCapacite(int $position, int $slotIndex, Request $request): Response
    {
        $slot = $this->draft->getSlot($position);
        $pokemon = null !== $slot ? $this->championsClient->getPokemonBySlug($slot['pokemonUrl']) : null;
        if (null === $pokemon) {
            return $this->redirectToRoute('app_team_builder');
        }

        $moves = $this->pokeApi->getLearnableMoves($pokemon['name'], $pokemon['showdownId']);

        $availableTypes = array_values(array_unique(array_column($moves, 'type')));
        sort($availableTypes);

        $query = trim((string) $request->query->get('q', ''));
        if ('' !== $query) {
            $needle = mb_strtolower($query);
            $moves = array_values(array_filter(
                $moves,
                static fn (array $m) => str_contains(mb_strtolower($m['name']), $needle)
            ));
        }

        $selectedType = (string) $request->query->get('type', '');
        if ('' !== $selectedType) {
            $moves = array_values(array_filter($moves, static fn (array $m) => $m['type'] === $selectedType));
        }

        $context = [
            'position' => $position,
            'slotIndex' => $slotIndex,
            'pokemon' => $pokemon,
            'moves' => $moves,
            'availableTypes' => $availableTypes,
            'query' => $query,
            'selectedType' => $selectedType,
        ];

        if ($this->isAjax($request)) {
            return $this->render('team_builder/fragments/capacite.html.twig', $context);
        }

        return $this->render('team_builder/modal_capacite.html.twig', $context);
    }

    #[Route('/equipes/creer/slot/{position}/capacite/{slotIndex}', name: 'app_team_builder_set_capacite', requirements: ['position' => '[1-6]', 'slotIndex' => '[1-4]'], methods: ['POST'])]
    public function setCapacite(int $position, int $slotIndex, Request $request): Response
    {
        $this->draft->updateSlot($position, ['capacite'.$slotIndex => (string) $request->request->get('capacite')]);

        return $this->respondBackToBuildPanel($position, $request);
    }

    #[Route('/equipes/creer/publier', name: 'app_team_builder_publish', methods: ['POST'])]
    public function publish(
        Request $request,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        #[CurrentUser] Compte $compte,
    ): Response {
        // the "Titre / Description / Tiers" fields share this form's submit with the
        // publish button (via formaction) so a Tiers pick is captured even if the
        // user never separately clicked "Enregistrer" first
        $this->draft->updateInfo(
            trim((string) $request->request->get('titre')),
            $request->request->get('description'),
            $request->request->get('idEquipeChampions') ?: null,
            $request->request->get('tiersId') ? (int) $request->request->get('tiersId') : null,
        );

        $draftData = $this->draft->get();

        $tiers = $draftData['tiersId'] ? $entityManager->getRepository(Tiers::class)->find($draftData['tiersId']) : null;

        $editingEquipeId = $draftData['editingEquipeId'] ?? null;
        $equipe = null !== $editingEquipeId ? $entityManager->getRepository(Equipe::class)->find($editingEquipeId) : null;
        if (null !== $equipe && $equipe->getCompte()?->getId() !== $compte->getId()) {
            throw $this->createAccessDeniedException();
        }
        $isEditing = null !== $equipe;
        $equipe ??= new Equipe();

        $equipe->setCompte($compte);
        $equipe->setTitre($draftData['titre'] ?: 'Équipe sans titre');
        $equipe->setDescription($draftData['description']);
        $equipe->setIdEquipePokemonChampions($draftData['idEquipePokemonChampions']);
        $equipe->setTiers($tiers);

        if ($isEditing) {
            foreach ($equipe->getBuildPokemons()->toArray() as $existingBuild) {
                $equipe->removeBuildPokemon($existingBuild);
            }
        }

        $equipeErrors = $validator->validate($equipe);

        $position = 0;
        $slotErrors = [];
        foreach ($draftData['slots'] as $slotPosition => $slot) {
            if (null === $slot || null === $slot['pokemonUrl']) {
                continue;
            }

            $build = new BuildPokemon();
            $build->setPokemonUrl($slot['pokemonUrl']);
            $build->setPosition(++$position);
            $build->setObjet($slot['objet']);
            $build->setCapacite1((string) ($slot['capacite1'] ?? ''));
            $build->setCapacite2((string) ($slot['capacite2'] ?? ''));
            $build->setCapacite3($slot['capacite3']);
            $build->setCapacite4($slot['capacite4']);
            $build->setNature((string) ($slot['nature'] ?? ''));
            $build->setTalent((string) ($slot['talent'] ?? ''));
            $build->setIvPv($slot['ivPv']);
            $build->setIvAtq($slot['ivAtq']);
            $build->setIvDef($slot['ivDef']);
            $build->setIvAtqSpe($slot['ivAtqSpe']);
            $build->setIvDefSpe($slot['ivDefSpe']);
            $build->setIvVitesse($slot['ivVitesse']);

            $buildErrors = $validator->validate($build);
            if (\count($buildErrors) > 0) {
                $missingLabels = [];
                $otherMessages = [];
                foreach ($buildErrors as $violation) {
                    if (NotBlank::IS_BLANK_ERROR === $violation->getCode()) {
                        $missingLabels[] = self::BUILD_FIELD_LABELS[$violation->getPropertyPath()] ?? $violation->getPropertyPath();
                    } else {
                        $otherMessages[] = $violation->getMessage();
                    }
                }
                $slotErrors[$slotPosition] = [
                    'missing' => array_unique($missingLabels),
                    'other' => $otherMessages,
                ];
            }
            $equipe->addBuildPokemon($build);
        }

        if ($position < 1) {
            $this->addFlash('error', 'Ajoutez au moins un Pokémon avant de publier votre équipe.');

            return $this->redirectToRoute('app_team_builder');
        }

        if (\count($equipeErrors) > 0 || [] !== $slotErrors) {
            foreach ($equipeErrors as $violation) {
                $label = self::EQUIPE_FIELD_LABELS[$violation->getPropertyPath()] ?? $violation->getPropertyPath();
                $this->addFlash('error', \sprintf('%s : %s', $label, $violation->getMessage()));
            }

            foreach ($slotErrors as $slotPosition => $fieldErrors) {
                if ([] !== $fieldErrors['missing']) {
                    $this->addFlash('error', \sprintf(
                        'Pokémon en position %d incomplet : %s manquant(s). Ouvrez-le et complétez son entrainement avant de publier.',
                        $slotPosition,
                        implode(', ', $fieldErrors['missing'])
                    ));
                }
                foreach ($fieldErrors['other'] as $message) {
                    $this->addFlash('error', \sprintf('Pokémon en position %d : %s', $slotPosition, $message));
                }
            }

            return $this->redirectToRoute('app_team_builder');
        }

        if (!$isEditing) {
            $entityManager->persist($equipe);
        }
        $entityManager->flush();

        $this->draft->clear();

        $this->addFlash('success', $isEditing ? 'Équipe modifiée avec succès !' : 'Équipe publiée avec succès !');

        return $this->redirectToRoute('app_team_builder_published', ['id' => $equipe->getId()]);
    }

    #[Route('/equipes/{id}/publiee', name: 'app_team_builder_published', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function published(
        int $id,
        EntityManagerInterface $entityManager,
        LikeRepository $likeRepository,
        CommentaireRepository $commentaireRepository,
        CompteRepository $compteRepository,
    ): Response {
        $equipe = $entityManager->getRepository(Equipe::class)->find($id);
        if (null === $equipe) {
            throw $this->createNotFoundException();
        }

        $slots = [];
        foreach ($equipe->getBuildPokemons() as $build) {
            $pokemon = $this->championsClient->getPokemonBySlug($build->getPokemonSlug());

            $moveTypes = [];
            if (null !== $pokemon) {
                foreach ($this->pokeApi->getLearnableMoves($pokemon['name'], $pokemon['showdownId']) as $move) {
                    $moveTypes[$move['name']] = $move['type'];
                }
            }

            $slots[] = ['build' => $build, 'pokemon' => $pokemon, 'moveTypes' => $moveTypes];
        }

        $currentUser = $this->getUser();
        $hasLiked = $currentUser instanceof Compte
            && null !== $likeRepository->findOneBy(['idEquipe' => $id, 'idCompte' => $currentUser->getId()]);

        $commentEntities = $commentaireRepository->findBy(['idEquipe' => $id], ['id' => 'DESC']);
        $compteIds = array_unique(array_map(static fn (Commentaire $c) => $c->getIdCompte(), $commentEntities));
        $pseudoById = [];
        if ([] !== $compteIds) {
            foreach ($compteRepository->findBy(['id' => $compteIds]) as $compte) {
                $pseudoById[$compte->getId()] = $compte->getPseudo();
            }
        }
        $comments = array_map(static fn (Commentaire $c) => [
            'pseudo' => $pseudoById[$c->getIdCompte()] ?? 'Utilisateur supprimé',
            'text' => $c->getCommentaire(),
        ], $commentEntities);

        return $this->render('team_builder/published.html.twig', [
            'equipe' => $equipe,
            'slots' => $slots,
            'natures' => NatureCatalog::all(),
            'likeCount' => $likeRepository->count(['idEquipe' => $id]),
            'hasLiked' => $hasLiked,
            'comments' => $comments,
        ]);
    }

    #[Route('/equipes/{id}/like', name: 'app_team_like', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function toggleLike(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        LikeRepository $likeRepository,
        #[CurrentUser] Compte $compte,
    ): RedirectResponse {
        if (null === $entityManager->getRepository(Equipe::class)->find($id)) {
            throw $this->createNotFoundException();
        }

        $existing = $likeRepository->findOneBy(['idEquipe' => $id, 'idCompte' => $compte->getId()]);
        if (null !== $existing) {
            $entityManager->remove($existing);
        } else {
            $like = new Like();
            $like->setIdEquipe($id);
            $like->setIdCompte($compte->getId());
            $entityManager->persist($like);
        }
        $entityManager->flush();

        // liking from the community list or the home teaser should stay there, not jump to the team page
        $referer = $request->headers->get('referer');
        if (null !== $referer && str_starts_with($referer, $request->getSchemeAndHttpHost())) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_team_builder_published', ['id' => $id]);
    }

    #[Route('/equipes/{id}/commentaire', name: 'app_team_comment', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addComment(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        #[CurrentUser] Compte $compte,
    ): RedirectResponse {
        if (null === $entityManager->getRepository(Equipe::class)->find($id)) {
            throw $this->createNotFoundException();
        }

        $text = trim((string) $request->request->get('commentaire'));
        if ('' !== $text) {
            $comment = new Commentaire();
            $comment->setIdEquipe($id);
            $comment->setIdCompte($compte->getId());
            $comment->setCommentaire($text);
            $entityManager->persist($comment);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_team_builder_published', ['id' => $id]);
    }

    /**
     * True when the request was made via the modal JS layer (fetch with our marker header),
     * as opposed to a direct navigation / no-JS form submit.
     */
    private function isAjax(Request $request): bool
    {
        return 'XMLHttpRequest' === $request->headers->get('X-Requested-With');
    }

    /**
     * After a sub-selection (nature/objet/capacité/stats) is saved, AJAX callers get the
     * build panel fragment back so the modal "returns" to that view; non-JS callers get
     * the classic redirect.
     */
    private function respondBackToBuildPanel(int $position, Request $request): Response
    {
        if ($this->isAjax($request)) {
            $context = $this->buildPanelContext($position);
            if (null === $context) {
                return new JsonResponse(['reload' => true]);
            }

            return $this->render('team_builder/fragments/build_panel.html.twig', $context);
        }

        return $this->redirectToRoute('app_team_builder_slot', ['position' => $position]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildPanelContext(int $position): ?array
    {
        $slot = $this->draft->getSlot($position);
        if (null === $slot || null === $slot['pokemonUrl']) {
            return null;
        }

        $pokemon = $this->championsClient->getPokemonBySlug($slot['pokemonUrl']);
        if (null === $pokemon) {
            return null;
        }

        return [
            'position' => $position,
            'slot' => $slot,
            'pokemon' => $pokemon,
            'baseStats' => $this->pokeApi->getBaseStats($pokemon['name'], $pokemon['showdownId']),
            'abilities' => $this->pokeApi->getAbilities($pokemon['name'], $pokemon['showdownId']),
            'natures' => NatureCatalog::all(),
        ];
    }

    /**
     * @return array<int, array{raw: array, pokemon: array|null}|null>
     */
    private function hydrateSlots(): array
    {
        $slots = [];
        foreach ($this->draft->get()['slots'] as $position => $slot) {
            $slots[$position] = $this->hydrateSlotSummary($slot);
        }

        return $slots;
    }

    private function hydrateSlotSummary(?array $slot): ?array
    {
        if (null === $slot || null === $slot['pokemonUrl']) {
            return null;
        }

        $pokemon = $this->championsClient->getPokemonBySlug($slot['pokemonUrl']);

        return [
            'raw' => $slot,
            'pokemon' => $pokemon,
        ];
    }
}
