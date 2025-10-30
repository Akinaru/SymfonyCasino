<?php
// src/Controller/Game/DiceController.php
namespace App\Controller\Game;

use App\Entity\Partie;
use App\Entity\Transaction;
use App\Entity\Utilisateur;
use App\Enum\IssueType;
use App\Enum\TransactionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/games/dice', name: 'app_game_dice_')]
class DiceController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // borne simple (tu peux les déplacer dans une classe DiceGame si tu préfères)
        $minBet = 1;
        $maxBet = 1000;

        return $this->render('game/dice/index.html.twig', [
            'minBet' => $minBet,
            'maxBet' => $maxBet,
            'csrf_token' => $this->container->get('security.csrf.token_manager')->getToken('dice_play'),
        ]);
    }

    #[Route('/play', name: 'play', methods: ['POST'])]
    public function play(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true) ?? [];
        $amount = (int)($data['amount'] ?? 0);
        $token  = (string)($data['_token'] ?? '');

        if (!$this->isCsrfTokenValid('dice_play', $token)) {
            return $this->json(['ok' => false, 'error' => 'Invalid CSRF token.'], 400);
        }

        $minBet = 1;
        $maxBet = 1000;

        if ($amount < $minBet || $amount > $maxBet) {
            return $this->json(['ok' => false, 'error' => 'Invalid bet amount.'], 400);
        }

        if ($user->getBalance() < $amount) {
            return $this->json(['ok' => false, 'error' => 'Insufficient balance.'], 400);
        }

        // Résolution et écritures en 1 transaction
        $now = new \DateTimeImmutable();
        $result = $em->wrapInTransaction(function () use ($em, $user, $amount, $now) {
            // 1) Débit immédiat
            $before = $user->getBalance();
            $user->setBalance($before - $amount);

            $txBet = (new Transaction())
                ->setUtilisateur($user)
                ->setType(TransactionType::MISE)
                ->setMontant($amount)
                ->setSoldeAvant($before)
                ->setSoldeApres($user->getBalance())
                ->setGameKey('dice')
                ->setCreeLe($now);

            // 2) Résoudre le lancer (animation côté front, mais résultat calculé ici)
            // Simple dé à 6 faces : gain = 2x mise si roll > 3, sinon 0
            $roll   = random_int(1, 6);
            $payout = $roll > 3 ? $amount * 2 : 0;

            // 3) Crédit si gagné
            $txPayout = null;
            if ($payout > 0) {
                $before2 = $user->getBalance();
                $user->setBalance($before2 + $payout);

                $txPayout = (new Transaction())
                    ->setUtilisateur($user)
                    ->setType(TransactionType::GAIN)
                    ->setMontant($payout)
                    ->setSoldeAvant($before2)
                    ->setSoldeApres($user->getBalance())
                    ->setGameKey('dice')
                    ->setCreeLe($now);
            }

            // 4) Partie
            $partie = (new Partie())
                ->setUtilisateur($user)
                ->setGameKey('dice')
                ->setMise($amount)
                ->setGain($payout)
                ->setResultatNet($payout - $amount)
                ->setIssue($payout > 0 ? IssueType::GAGNE : IssueType::PERDU)
                ->setDebutLe($now)
                ->setFinLe($now)
                ->setMetaJson(json_encode(['roll' => $roll], JSON_UNESCAPED_UNICODE));

            // Lier les transactions à la partie
            $txBet->setPartie($partie);
            if ($txPayout) {
                $txPayout->setPartie($partie);
            }

            $em->persist($user);
            $em->persist($partie);
            $em->persist($txBet);
            if ($txPayout) { $em->persist($txPayout); }

            return [
                'roll'      => $roll,
                'payout'    => $payout,
                'net'       => $payout - $amount,
                'balance'   => $user->getBalance(),
                'partie_id' => $partie->getId(), // sera connu après flush
            ];
        });

        // Le flush est fait par wrapInTransaction
        return $this->json(['ok' => true, ...$result]);
    }
}
