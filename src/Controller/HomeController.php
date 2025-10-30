<?php

namespace App\Controller;

use App\Game\GameRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\SecurityBundle\Security;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(Security $security, GameRegistry $registry): Response
    {
        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
            'games' => $registry->all(),
        ]);
    }
}
