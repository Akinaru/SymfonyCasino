<?php

namespace App\Controller;

use App\Entity\MarketItem;
use App\Repository\MarketItemRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/market', name: 'app_market_')]
class MarketController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(MarketItemRepository $repo): Response
    {
        return $this->render('market/index.html.twig', [
            'items' => $repo->findOnMarket(),
        ]);
    }

    #[Route('/buy/{id}', name: 'buy', methods: ['POST'])]
    public function buy(MarketItem $item, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        if (!$this->isCsrfTokenValid('buy_item_'.$item->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'CSRF invalide.');
            return $this->redirectToRoute('app_market_index');
        }

        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('danger', 'Vous devez être connecté.');
            return $this->redirectToRoute('app_market_index');
        }

        $em->beginTransaction();
        try {
            // Verrou pessimiste pour éviter double achat
            $em->lock($item, LockMode::PESSIMISTIC_WRITE);
            $em->refresh($item);

            if (!$item->isOnMarket()) {
                $this->addFlash('warning', 'Déjà acheté par quelqu’un d’autre.');
                $em->rollback();
                return $this->redirectToRoute('app_market_index');
            }

            /** @var \App\Entity\Utilisateur $user */
            if ($user->getBalance() < $item->getPrice()) {
                $this->addFlash('danger', 'Balance insuffisante.');
                $em->rollback();
                return $this->redirectToRoute('app_market_index');
            }

            // débit + transfert de propriété
            $user->setBalance($user->getBalance() - $item->getPrice());
            $item->setOwner($user);

            $em->flush();
            $em->commit();

            $this->addFlash('success', sprintf('Achat réussi : %s pour %d.', $item->getName(), $item->getPrice()));
        } catch (\Throwable $e) {
            if ($em->getConnection()->isTransactionActive()) {
                $em->rollback();
            }
            $this->addFlash('danger', 'Erreur pendant l’achat.');
        } finally {
            $em->close();
        }

        return $this->redirectToRoute('app_market_index');
    }
}
