<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class RegisterController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        Security $security,
        LoggerInterface $logger
    ): Response {
        $user = new Utilisateur();

        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($email = $user->getEmail()) {
                $existingEmail = $em->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);
                if ($existingEmail) {
                    $form->get('email')->addError(new FormError('Cet e-mail est déjà utilisé.'));
                }
            }

            if ($pseudo = $user->getPseudo()) {
                $existingPseudo = $em->getRepository(Utilisateur::class)->findOneBy(['pseudo' => $pseudo]);
                if ($existingPseudo) {
                    $form->get('pseudo')->addError(new FormError('Ce pseudo est déjà pris.'));
                }
            }

            if (!$form->isValid()) {
                foreach ($form->getErrors(true) as $error) {
                    $origin = $error->getOrigin();
                    $logger->error('[REGISTER] Erreur formulaire', [
                        'message' => $error->getMessage(),
                        'field'   => $origin ? $origin->getName() : null,
                    ]);
                }
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $hashedPassword = $passwordHasher->hashPassword(
                $user,
                $form->get('plainPassword')->getData()
            );
            $user->setPassword($hashedPassword);

            $user->setBalance(50000);

            $em->persist($user);
            $em->flush();

            $security->login($user);

            return $this->redirectToRoute('app_home');
        }

        return $this->render('auth/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}
