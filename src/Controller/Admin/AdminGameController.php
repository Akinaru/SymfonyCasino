<?php

namespace App\Controller\Admin;

use App\Game\GameRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/games', name: 'app_admin_games_')]
class AdminGameController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(GameRegistry $registry): Response
    {
        return $this->render('admin/game/index.html.twig', [
            'games' => $registry->all(),
        ]);
    }
}
