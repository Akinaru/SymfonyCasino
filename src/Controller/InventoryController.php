<?php

namespace App\Controller;

use App\Repository\MarketItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/inventory', name: 'app_inventory_')]
class InventoryController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(MarketItemRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /** @var \App\Entity\Utilisateur $user */
        $user = $this->getUser();

        return $this->render('profile/inventaire.html.twig', [
            'items' => $repo->findByOwner($user),
        ]);
    }
}
