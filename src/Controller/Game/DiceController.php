<?php
// src/Controller/Game/DiceController.php
namespace App\Controller\Game;

use App\Entity\Partie;
use App\Entity\Utilisateur;
use App\Enum\IssueType;
use App\Game\DiceGame;
use App\Manager\TransactionManager;
use App\Notifier\DiceLastGameNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/games/dice', name: 'app_game_dice_')]
class DiceController extends AbstractController
{
    public function __construct(
        private DiceLastGameNotifier $diceLastGameNotifier,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $minBet = 1;
        $maxBet = 1000000;
        $descriptionInGame = DiceGame::getDescriptionInGame();

        // 🔹 10 dernières parties "dice" (même pattern que Slots)
        $qb = $em->getRepository(Partie::class)->createQueryBuilder('p')
            ->addSelect('u')
            ->join('p.utilisateur', 'u')
            ->where('p.game_key = :game')
            ->setParameter('game', 'dice')
            ->orderBy('p.debut_le', 'DESC')
            ->setMaxResults(10);

        /** @var Partie[] $parties */
        $parties = $qb->getQuery()->getResult();

        $lastGames = [];
        foreach ($parties as $partie) {
            if (!$partie instanceof Partie) {
                continue;
            }

            $user = $partie->getUtilisateur();
            $meta = json_decode($partie->getMetaJson() ?? '{}', true) ?: [];
            $roll = $meta['roll'] ?? null;

            $lastGames[] = [
                'id'           => $partie->getId(),
                'user_id'      => $user?->getId(),
                'game_key'     => $partie->getGameKey(),
                'mise'         => $partie->getMise(),
                'gain'         => $partie->getGain(),
                'resultat_net' => $partie->getResultatNet(),
                'issue'        => $partie->getIssue()?->value ?? null,
                'username'     => $user?->getPseudo() ?: ($user?->getEmail() ?? 'Inconnu'),
                'avatar_url'   => $user?->getAvatarUrl() ?? 'https://mc-heads.net/avatar',
                'roll'         => $roll,
                'isWin'        => $partie->getResultatNet() > 0,
            ];
        }

        return $this->render('game/dice/index.html.twig', [
            'minBet'            => $minBet,
            'maxBet'            => $maxBet,
            'descriptionInGame' => $descriptionInGame,
            'lastGames'         => $lastGames,
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

        $data      = json_decode($request->getContent(), true) ?? [];
        $rawAmount = $data['amount'] ?? null;
        $token     = (string)($data['_token'] ?? '');

        if (!$this->isCsrfTokenValid('dice_play', $token)) {
            return $this->json(['ok' => false, 'error' => 'Invalid CSRF token.'], 400);
        }

        $minBet = 1;
        $maxBet = 1000000;

        if ($rawAmount === null || $rawAmount === '') {
            return $this->json(['ok' => false, 'error' => 'Veuillez saisir un montant.'], 400);
        }
        if (!is_numeric($rawAmount)) {
            return $this->json(['ok' => false, 'error' => 'Montant invalide : saisissez un nombre.'], 400);
        }
        if ((string)(int)$rawAmount !== (string)$rawAmount) {
            return $this->json(['ok' => false, 'error' => 'Le montant doit être un entier, sans décimales.'], 400);
        }

        $amount = (int)$rawAmount;

        if ($amount < $minBet) {
            return $this->json([
                'ok'    => false,
                'error' => sprintf('Montant trop faible : le minimum est de %d.', $minBet),
            ], 400);
        }

        if ($amount > $maxBet) {
            return $this->json([
                'ok'    => false,
                'error' => sprintf('Montant trop élevé : le maximum est de %d.', $maxBet),
            ], 400);
        }

        if ($user->getBalance() < $amount) {
            return $this->json(['ok' => false, 'error' => "Tu n'as pas assez d'argent..."], 400);
        }

        $now = new \DateTimeImmutable();

        $result = $em->wrapInTransaction(function () use ($em, $user, $amount, $now, $txm) {
            // Débit
            $txBet = $txm->debit($user, $amount, 'dice', null, $now);

            // Tirage
            $roll   = random_int(1, 6);
            $payout = $roll > 3 ? $amount * 2 : 0;

            // Partie
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
            $txBet->setPartie($partie);

            if ($payout > 0) {
                $txm->credit($user, $payout, 'dice', $partie, $now);
            }

            $em->flush();

            // Mercure
            $this->diceLastGameNotifier->notifyPartie($partie, $roll);

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
