<?php

namespace App\Controller;

use App\Entity\Compte;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class RegistrationController extends AbstractController
{
    #[Route('/inscription', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        #[CurrentUser] ?Compte $currentUser,
    ): Response {
        if ($currentUser) {
            return $this->redirectToRoute('app_home');
        }

        $errors = [];

        if ($request->isMethod('POST')) {
            $pseudo = trim((string) $request->request->get('pseudo'));
            $email = trim((string) $request->request->get('email'));
            $plainPassword = (string) $request->request->get('password');
            $passwordConfirm = (string) $request->request->get('password_confirm');

            if ('' === $pseudo || mb_strlen($pseudo) < 3) {
                $errors[] = 'Le pseudo doit faire au moins 3 caractères.';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Adresse email invalide.';
            }
            if (mb_strlen($plainPassword) < 8) {
                $errors[] = 'Le mot de passe doit faire au moins 8 caractères.';
            }
            if ($plainPassword !== $passwordConfirm) {
                $errors[] = 'Les deux mots de passe ne correspondent pas.';
            }
            if (!$errors && ($entityManager->getRepository(Compte::class)->findOneBy(['email' => $email]) || $entityManager->getRepository(Compte::class)->findOneBy(['pseudo' => $pseudo]))) {
                $errors[] = 'Ce pseudo ou cet email est déjà utilisé.';
            }

            if (!$errors) {
                $compte = new Compte();
                $compte->setPseudo($pseudo);
                $compte->setEmail($email);
                $compte->setPassword($passwordHasher->hashPassword($compte, $plainPassword));

                $entityManager->persist($compte);
                $entityManager->flush();

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/register.html.twig', [
            'errors' => $errors,
        ]);
    }
}
