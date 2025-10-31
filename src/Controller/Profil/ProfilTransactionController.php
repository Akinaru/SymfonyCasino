<?php

namespace App\Controller\Profil;

use App\Form\Profile\TransactionFilterType;
use App\Repository\TransactionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/profil')]
class ProfilTransactionController extends AbstractController
{
    #[Route('/transactions', name: 'app_profile_transactions', methods: ['GET'])]
    public function index(Request $request, TransactionRepository $transactions): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw new AccessDeniedException('Vous devez être connecté pour voir vos transactions.');
        }

        $form = $this->createForm(TransactionFilterType::class, null, [
        ]);
        $form->handleRequest($request);

        $data = $form->getData() ?? [];
        $gameKey = $data['gameKey'] ?? null;
        $types   = $data['types'] ?? [];

        $items = $transactions->searchUserTransactions($user, $gameKey, $types);

        return $this->render('profile/transactions.html.twig', [
            'transactions' => $items,
            'filterForm'   => $form->createView(),
        ]);
    }
}
