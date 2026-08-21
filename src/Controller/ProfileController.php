<?php

namespace App\Controller;

use App\Entity\Commentaire;
use App\Entity\Compte;
use App\Entity\Equipe;
use App\Repository\EquipeRepository;
use App\Repository\LikeRepository;
use App\Service\ChampionsBattleDataClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    private const ALLOWED_AVATAR_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const MAX_AVATAR_SIZE = 3 * 1024 * 1024;

    public function __construct(
        private readonly EquipeRepository $equipeRepository,
        private readonly LikeRepository $likeRepository,
        private readonly ChampionsBattleDataClient $championsClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly KernelInterface $kernel,
    ) {
    }

    #[Route('/mon-profil', name: 'app_profile', methods: ['GET'])]
    public function index(#[CurrentUser] Compte $compte): Response
    {
        $myTeams = $this->equipeRepository->findByCompteOrdered($compte);
        $myTeamIds = array_map(static fn (Equipe $e) => $e->getId(), $myTeams);

        $favoriteEquipeIds = $this->likeRepository->findEquipeIdsLikedBy($compte->getId());
        $favoriteTeams = $this->equipeRepository->findByIdsOrdered($favoriteEquipeIds);

        return $this->render('profile/index.html.twig', [
            'compte' => $compte,
            'myTeams' => $this->buildTeamRows($myTeams, $compte),
            'favoriteTeams' => $this->buildTeamRows($favoriteTeams, $compte),
            'teamCount' => \count($myTeams),
            'likesReceived' => $this->likeRepository->countForEquipeIds($myTeamIds),
        ]);
    }

    #[Route('/mon-profil/avatar', name: 'app_profile_avatar', methods: ['POST'])]
    public function uploadAvatar(Request $request, #[CurrentUser] Compte $compte): RedirectResponse
    {
        $file = $request->files->get('avatar');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            $this->addFlash('error', "L'envoi de l'image a échoué, réessayez.");

            return $this->redirectToRoute('app_profile');
        }

        if (!\in_array($file->getMimeType(), self::ALLOWED_AVATAR_MIME_TYPES, true)) {
            $this->addFlash('error', "Format d'image non supporté (JPEG, PNG, WebP ou GIF uniquement).");

            return $this->redirectToRoute('app_profile');
        }

        if ($file->getSize() > self::MAX_AVATAR_SIZE) {
            $this->addFlash('error', "L'image ne doit pas dépasser 3 Mo.");

            return $this->redirectToRoute('app_profile');
        }

        $uploadDir = $this->kernel->getProjectDir().'/public/uploads/avatars';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $filename = \sprintf('%d-%s.%s', $compte->getId(), bin2hex(random_bytes(8)), $file->guessExtension() ?: 'jpg');

        try {
            $file->move($uploadDir, $filename);
        } catch (FileException) {
            $this->addFlash('error', "L'envoi de l'image a échoué, réessayez.");

            return $this->redirectToRoute('app_profile');
        }

        $oldFilename = $compte->getAvatarFilename();
        if (null !== $oldFilename) {
            @unlink($uploadDir.'/'.$oldFilename);
        }

        $compte->setAvatarFilename($filename);
        $this->entityManager->flush();

        $this->addFlash('success', 'Photo de profil mise à jour.');

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/mon-profil/avatar/supprimer', name: 'app_profile_avatar_remove', methods: ['POST'])]
    public function removeAvatar(#[CurrentUser] Compte $compte): RedirectResponse
    {
        $filename = $compte->getAvatarFilename();
        if (null !== $filename) {
            @unlink($this->kernel->getProjectDir().'/public/uploads/avatars/'.$filename);
            $compte->setAvatarFilename(null);
            $this->entityManager->flush();
        }

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/mon-profil/equipe/{id}/supprimer', name: 'app_profile_team_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteTeam(int $id, #[CurrentUser] Compte $compte): RedirectResponse
    {
        $equipe = $this->entityManager->getRepository(Equipe::class)->find($id);
        if (null === $equipe) {
            throw $this->createNotFoundException();
        }
        if ($equipe->getCompte()?->getId() !== $compte->getId()) {
            throw $this->createAccessDeniedException();
        }

        foreach ($this->likeRepository->findBy(['idEquipe' => $id]) as $like) {
            $this->entityManager->remove($like);
        }
        foreach ($this->entityManager->getRepository(Commentaire::class)->findBy(['idEquipe' => $id]) as $comment) {
            $this->entityManager->remove($comment);
        }

        $this->entityManager->remove($equipe);
        $this->entityManager->flush();

        $this->addFlash('success', 'Équipe supprimée.');

        return $this->redirectToRoute('app_profile');
    }

    /**
     * Same "Pseudo / Pokémons / Titre / Tier / Favoris" row shape as HomeController::buildTeamRows(),
     * reused here for both the "Mes équipes" and "Mes favoris" lists on the profile page.
     *
     * @param Equipe[] $equipes
     *
     * @return array<int, array{id: int, pseudo: string, avatarFilename: ?string, pokemons: array<int, array{sprite: string, name: string}>, titre: string, tier: string, favoris: int, hasLiked: bool}>
     */
    private function buildTeamRows(array $equipes, Compte $currentUser): array
    {
        $equipeIds = array_map(static fn (Equipe $e) => $e->getId(), $equipes);
        $likedEquipeIds = [];
        if ([] !== $equipeIds) {
            foreach ($this->likeRepository->findBy(['idEquipe' => $equipeIds, 'idCompte' => $currentUser->getId()]) as $like) {
                $likedEquipeIds[$like->getIdEquipe()] = true;
            }
        }

        $rows = [];
        foreach ($equipes as $equipe) {
            $pokemons = [];
            foreach ($equipe->getBuildPokemons() as $build) {
                $pokemon = $this->championsClient->getPokemonBySlug($build->getPokemonSlug());
                if (null === $pokemon) {
                    continue;
                }
                $pokemons[] = [
                    'sprite' => $this->championsClient->assetUrl($pokemon['summary']['sprite']),
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
                'favoris' => $this->likeRepository->count(['idEquipe' => $equipe->getId()]),
                'hasLiked' => isset($likedEquipeIds[$equipe->getId()]),
            ];
        }

        return $rows;
    }
}
