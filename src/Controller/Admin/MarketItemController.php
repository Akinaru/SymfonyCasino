<?php

namespace App\Controller\Admin;

use App\Entity\MarketItem;
use App\Enum\ItemType;
use App\Form\MarketItemType;
use App\Repository\MarketItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/market', name: 'app_admin_market_')]
class MarketItemController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(MarketItemRepository $repo): Response
    {
        return $this->render('admin/market_item/index.html.twig', [
            'items' => $repo->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET','POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        // Par défaut, on propose un type quelconque (premier case)
        $initialType = ItemType::cases()[0];
        $marketItem = new MarketItem($initialType);

        $form = $this->createForm(MarketItemType::class, $marketItem);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Si l’admin a laissé un prix incohérent, force au min 0
            if ($marketItem->getPrice() < 0) {
                $marketItem->setPrice(0);
            }
            $em->persist($marketItem);
            $em->flush();

            $this->addFlash('success', 'Objet ajouté au marché.');
            return $this->redirectToRoute('app_admin_market_index');
        }

        return $this->render('admin/market_item/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET','POST'])]
    public function edit(MarketItem $marketItem, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(MarketItemType::class, $marketItem);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($marketItem->getPrice() < 0) {
                $marketItem->setPrice(0);
            }
            $em->flush();
            $this->addFlash('success', 'Objet mis à jour.');
            return $this->redirectToRoute('app_admin_market_index');
        }

        return $this->render('admin/market_item/edit.html.twig', [
            'form' => $form,
            'item' => $marketItem,
        ]);
    }
}
