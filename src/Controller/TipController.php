<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\Utilisateur;
use App\Notifier\ChatMessageNotifier;
use App\Notifier\TipNotifier;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/tips', name: 'tips_')]
class TipController extends AbstractController
{
    public function __construct(
        private TipNotifier $tipNotifier,
        private ChatMessageNotifier $chatNotifier,
        private EntityManagerInterface $em,
        private UtilisateurRepository $userRepo,
    ) {}

    #[Route('/send', name: 'send', methods: ['POST'])]
    public function send(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var Utilisateur $from */
        $from = $this->getUser();

        $pseudo = trim((string) $request->request->get('pseudo', ''));
        $amount = (float) $request->request->get('amount', 0);

        if ($pseudo === '' || $amount <= 0) {
            return new JsonResponse(['error' => 'invalid'], 400);
        }

        /** @var Utilisateur|null $to */
        $to = $this->userRepo->findOneBy(['pseudo' => $pseudo]);

        if (!$to) {
            return new JsonResponse(['error' => 'user_not_found'], 404);
        }

        if ($to->getId() === $from->getId()) {
            return new JsonResponse(['error' => 'no_self_tip'], 400);
        }

        if ($from->getBalance() < $amount) {
            return new JsonResponse(['error' => 'insufficient_balance'], 400);
        }

        // Màj balances
        $from->setBalance($from->getBalance() - $amount);
        $to->setBalance($to->getBalance() + $amount);

        // Message système dans le chat
        $fromLabel = $from->getPseudo() ?? $from->getEmail();
        $toLabel   = $to->getPseudo() ?? $to->getEmail();

        $msg = new Message();
        $msg->setContent(sprintf(
            '%s a envoyé un tip de %.2f € à %s',
            $fromLabel,
            $amount,
            $toLabel
        ));
        $msg->setSystem(true);
        $msg->setUser(null); // message système → pas de user

        $this->em->persist($msg);
        $this->em->flush();

        // 1) Notification directe de tip (alerte sender + receiver)
        $this->tipNotifier->notifyTip($from, $to, $amount);

        // 2) Message dans le chat (visible par tous)
        $this->chatNotifier->notify($msg);

        return new JsonResponse(['success' => true]);
    }
}
