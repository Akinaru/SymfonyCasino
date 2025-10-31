<?php
// src/Controller/Profil/ProfilTransactionController.php
namespace App\Controller\Profil;

use App\Game\GameRegistry;
use App\Form\Profile\TransactionFilterType;
use App\Repository\TransactionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/profil')]
class ProfilTransactionController extends AbstractController
{
    #[Route('/transactions', name: 'app_profile_transactions', methods: ['GET'])]
    public function index(Request $request, TransactionRepository $transactions, GameRegistry $registry): Response
    {
        $user = $this->getUser() ?? $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $form = $this->createForm(TransactionFilterType::class, null);
        $form->handleRequest($request);
        $data    = $form->getData() ?? [];
        $gameKey = $data['gameKey'] ?? null;
        $types   = $data['types'] ?? [];

        // pagination existante…
        $perPage = 25;
        $page = max(1, (int) $request->query->get('page', 1));
        $offset = ($page - 1) * $perPage;

        $items = $transactions->searchUserTransactionsPaginated($this->getUser(), $gameKey, $types, $perPage, $offset);
        $total = $transactions->countUserTransactions($this->getUser(), $gameKey, $types);
        $lastPage = max(1, (int) ceil($total / $perPage));

        // 👇 map clé→nom pour Twig (simple et efficace)
        $namesByKey = [];
        foreach ($registry->all() as $g) {
            $namesByKey[$g->getKey()] = $g->getName();
        }

        return $this->render('profile/transactions.html.twig', [
            'transactions' => $items,
            'filterForm'   => $form->createView(),
            'namesByKey'   => $namesByKey,
            'pagination'   => [
                'page' => $page, 'perPage' => $perPage, 'total' => $total, 'lastPage' => $lastPage,
                'from' => $total ? $offset + 1 : 0, 'to' => $total ? min($offset + $perPage, $total) : 0,
            ],
        ]);
    }
}
