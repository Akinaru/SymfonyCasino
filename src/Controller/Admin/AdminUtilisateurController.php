<?php

namespace App\Controller\Admin;

use App\Entity\Utilisateur;
use App\Form\UtilisateurType;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/utilisateur')]
final class AdminUtilisateurController extends AbstractController
{
    #[Route(name: 'app_admin_utilisateur_index', methods: ['GET'])]
    public function index(UtilisateurRepository $utilisateurRepository): Response
    {
        $utilisateurs = $utilisateurRepository->findAll();

        // ➜ Construire un formulaire (FormView) par utilisateur pour la modale d'édition
        $forms = [];
        foreach ($utilisateurs as $u) {
            $forms[$u->getId()] = $this->createForm(
                UtilisateurType::class,
                $u,
                [
                    // Le submit de la modale renverra sur ta route d'édition
                    'action' => $this->generateUrl('app_admin_utilisateur_edit', ['id' => $u->getId()]),
                    'method' => 'POST',
                ]
            )->createView();
        }

        return $this->render('admin/utilisateur/index.html.twig', [
            'utilisateurs' => $utilisateurs,
            'forms' => $forms, // <-- indispensable pour le Twig
        ]);
    }

    #[Route('/{id}', name: 'app_admin_utilisateur_show', methods: ['GET'])]
    public function show(Utilisateur $utilisateur): Response
    {
        return $this->render('admin/utilisateur/show.html.twig', [
            'utilisateur' => $utilisateur,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_utilisateur_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Utilisateur $utilisateur, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(UtilisateurType::class, $utilisateur);
        $form->handleRequest($request);

        if ($request->isMethod('POST')) {
            if ($form->isSubmitted() && $form->isValid()) {
                $entityManager->flush();
                $this->addFlash('success', 'Utilisateur mis à jour.');
            } else {
                // ==> Collecte des erreurs pour comprendre la cause
                $messages = [];
                foreach ($form->getErrors(true, true) as $error) {
                    $origin = $error->getOrigin();
                    $name = $origin ? $origin->getName() : 'form';
                    $messages[] = sprintf('%s: %s', $name, $error->getMessage());
                }
                $this->addFlash('danger', 'Échec de la mise à jour (formulaire invalide). ' . implode(' | ', $messages));
            }

            return $this->redirectToRoute('app_admin_utilisateur_index');
        }

        // GET classique
        return $this->render('admin/utilisateur/edit.html.twig', [
            'utilisateur' => $utilisateur,
            'form' => $form,
        ]);
    }


    #[Route('/{id}', name: 'app_admin_utilisateur_delete', methods: ['POST'])]
    public function delete(Request $request, Utilisateur $utilisateur, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$utilisateur->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($utilisateur);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_admin_utilisateur_index', [], Response::HTTP_SEE_OTHER);
    }
}
