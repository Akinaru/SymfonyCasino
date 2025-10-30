<?php
// src/Controller/Game/DiceController.php
namespace App\Controller\Game;

use App\Entity\Partie;
use App\Entity\Utilisateur;
use App\Enum\IssueType;
use App\Game\DiceGame;
use App\Manager\TransactionManager;
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

        $minBet = 1;
        $maxBet = 1000;
        $descriptionInGame = DiceGame::getDescriptionInGame();

        // Pas besoin de passer un token ici : dans Twig, utilise {{ csrf_token('dice_play') }}
        return $this->render('game/dice/index.html.twig', [
            'minBet' => $minBet,
            'maxBet' => $maxBet,
            'descriptionInGame' => $descriptionInGame
        ]);
    }

    #[Route('/play', name: 'play', methods: ['POST'])]
    public function play(
        Request $request,
        EntityManagerInterface $em,
        TransactionManager $txm
    ): JsonResponse {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var Utilisateur $user */
        $user = $this->getUser();

        $data   = json_decode($request->getContent(), true) ?? [];
        $amount = (int)($data['amount'] ?? 0);
        $token  = (string)($data['_token'] ?? '');

        if (!$this->isCsrfTokenValid('dice_play', $token)) {
            return $this->json(['ok' => false, 'error' => 'Invalid CSRF token.'], 400);
        }

        $minBet = 1;
        $maxBet = 1000;

        if ($amount < $minBet || $amount > $maxBet) {
            return $this->json(['ok' => false, 'error' => 'Montant du pari invalide.'], 400);
        }

        if ($user->getBalance() < $amount) {
            return $this->json(['ok' => false, 'error' => "Tu n'as pas assez d'argent..."], 400);
        }

        $now = new \DateTimeImmutable();

        $result = $em->wrapInTransaction(function () use ($em, $user, $amount, $now, $txm) {
            // 1) Débit immédiat via le manager (persiste user + transaction)
            $txBet = $txm->debit($user, $amount, 'dice', null, $now);

            // 2) Résolution du lancer
            $roll   = random_int(1, 6);
            $payout = $roll > 3 ? $amount * 2 : 0;

            // 3) Création de la partie
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

            $em->persist($partie);

            // Lier la mise à la partie
            $txBet->setPartie($partie);

            // 4) Crédit si gagné via le manager
            $txm->credit($user, $payout, 'dice', $partie, $now);

            // On flush pour garantir un ID de partie
            $em->flush();

            return [
                'roll'      => $roll,
                'payout'    => $payout,
                'net'       => $payout - $amount,
                'balance'   => $user->getBalance(),
                'partie_id' => $partie->getId(),
            ];
        });

        return $this->json(['ok' => true, ...$result]);
    }
}
