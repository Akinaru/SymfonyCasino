<?php

namespace App\Controller\Profil;

use App\Entity\Utilisateur;
use App\Form\ProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/profil')]
class ProfileController extends AbstractController
{
    #[Route('/', name: 'app_profile_index')]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        Security $security
    ): Response {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Sauvegarde de l'état initial pour éviter la déconnexion si l'identifier (email) change
        $originalEmail  = $user->getEmail();
        $originalPseudo = $user->getPseudo();

        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // Détection des collisions sur les nouvelles valeurs bindées par le form
            $newEmail  = $user->getEmail();
            $newPseudo = $user->getPseudo();

            // Collision email ?
            if ($newEmail !== $originalEmail) {
                $existingEmail = $em->getRepository(Utilisateur::class)->findOneBy(['email' => $newEmail]);
                if ($existingEmail && $existingEmail->getId() !== $user->getId()) {
                    // Restaure pour ne pas invalider le token de session
                    $user->setEmail($originalEmail);
                    $this->addFlash('danger', 'Cet email est déjà utilisé.');
                    return $this->redirectToRoute('app_profile');
                }
            }

            // Collision pseudo ?
            if ($newPseudo !== $originalPseudo) {
                $existingPseudo = $em->getRepository(Utilisateur::class)->findOneBy(['pseudo' => $newPseudo]);
                if ($existingPseudo && $existingPseudo->getId() !== $user->getId()) {
                    // Restaure le pseudo
                    $user->setPseudo($originalPseudo);
                    $this->addFlash('danger', 'Ce pseudo est déjà pris.');
                    return $this->redirectToRoute('app_profile');
                }
            }

            // --- Changement de mot de passe (optionnel) ---
            $currentPassword = $form->get('currentPassword')->getData();
            $newPassword     = $form->get('newPassword')->getData();
            $confirmPassword = $form->get('confirmPassword')->getData();

            if ($newPassword || $confirmPassword) {
                if (!$passwordHasher->isPasswordValid($user, $currentPassword ?? '')) {
                    // Restaure les champs sensibles en cas d'erreur
                    $user->setEmail($originalEmail);
                    $user->setPseudo($originalPseudo);
                    $this->addFlash('danger', 'Le mot de passe actuel est incorrect.');
                    return $this->redirectToRoute('app_profile');
                }

                if ($newPassword !== $confirmPassword) {
                    $user->setEmail($originalEmail);
                    $user->setPseudo($originalPseudo);
                    $this->addFlash('danger', 'Les nouveaux mots de passe ne correspondent pas.');
                    return $this->redirectToRoute('app_profile');
                }

                $hashed = $passwordHasher->hashPassword($user, $newPassword);
                $user->setPassword($hashed);
            }

            // L'entité $user est déjà managed, pas besoin de persist()
            $em->flush();

            // Si l'identifiant (email) a réellement changé, on refresh la session
            if ($user->getEmail() !== $originalEmail) {
                $security->login($user);
            }

            $this->addFlash('success', 'Profil mis à jour avec succès.');
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
