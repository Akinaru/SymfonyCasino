<?php

namespace App\Controller\Game;

use App\Entity\Partie;
use App\Entity\Utilisateur;
use App\Enum\IssueType;
use App\Game\RouletteGame;
use App\Manager\TransactionManager;
use App\Notifier\RouletteLastGameNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/games/roulette', name: 'app_game_roulette_')]
class RouletteController extends AbstractController
{
    public function __construct(
        private RouletteLastGameNotifier $rouletteLastGameNotifier,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        $minBet = 1;
        $maxBet = 1000000;
        $descriptionInGame = RouletteGame::getDescriptionInGame();

        $qb = $em->getRepository(Partie::class)->createQueryBuilder('p')
            ->addSelect('u')
            ->join('p.utilisateur', 'u')
            ->where('p.game_key = :game')
            ->setParameter('game', 'roulette')
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

            $number       = $meta['number']        ?? null;
            $betColor     = $meta['bet_color']     ?? null;
            $resultColor  = $meta['result_color']  ?? null;
            $multiplier   = $meta['multiplier']    ?? null;

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

                'number'       => $number,
                'bet_color'    => $betColor,
                'result_color' => $resultColor,
                'multiplier'   => $multiplier,
                'isWin'        => $partie->getResultatNet() > 0,
                'debut_le'     => $partie->getDebutLe(),
            ];
        }

        $myLastGames = [];
        if ($currentUser instanceof Utilisateur) {
            $qbMy = $em->getRepository(Partie::class)->createQueryBuilder('p')
                ->where('p.utilisateur = :user')
                ->andWhere('p.game_key = :game')
                ->setParameter('user', $currentUser)
                ->setParameter('game', 'roulette')
                ->orderBy('p.debut_le', 'DESC')
                ->setMaxResults(10);

            /** @var Partie[] $myParties */
            $myParties = $qbMy->getQuery()->getResult();

            if (!empty($myParties)) {
                $myParties = array_reverse($myParties);
            }

            foreach ($myParties as $partie) {
                if (!$partie instanceof Partie) {
                    continue;
                }

                $meta = json_decode($partie->getMetaJson() ?? '{}', true) ?: [];

                $number      = $meta['number']       ?? null;
                $resultColor = $meta['result_color'] ?? null;

                $myLastGames[] = [
                    'id'           => $partie->getId(),
                    'number'       => $number,
                    'result_color' => $resultColor,
                    'debut_le'     => $partie->getDebutLe(),
                ];
            }
        }

        return $this->render('game/roulette/index.html.twig', [
            'minBet'            => $minBet,
            'maxBet'            => $maxBet,
            'descriptionInGame' => $descriptionInGame,
            'lastGames'         => $lastGames,
            'myLastGames'       => $myLastGames,
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
        $betColor  = isset($data['choice']) ? (string)$data['choice'] : '';
        $token     = (string)($data['_token'] ?? '');

        if (!$this->isCsrfTokenValid('roulette_play', $token)) {
            return $this->json(['ok' => false, 'error' => 'Invalid CSRF token.'], 400);
        }

        $betColor = strtolower($betColor);
        $allowedChoices = ['red', 'black', 'green'];
        if (!in_array($betColor, $allowedChoices, true)) {
            return $this->json(['ok' => false, 'error' => 'Choix de couleur invalide.'], 400);
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

        $result = $em->wrapInTransaction(function () use ($em, $user, $amount, $now, $txm, $betColor) {
            $txBet = $txm->debit($user, $amount, 'roulette', null, $now);

            $number = random_int(0, 36);

            $resultColor = $this->colorFromNumber($number);

            $multiplier = 0;
            if ($betColor === 'green') {
                $multiplier = ($resultColor === 'green') ? 36 : 0;
            } else {
                $multiplier = ($betColor === $resultColor) ? 2 : 0;
            }

            $payout = $amount * $multiplier;

            $partie = (new Partie())
                ->setUtilisateur($user)
                ->setGameKey('roulette')
                ->setMise($amount)
                ->setGain($payout)
                ->setResultatNet($payout - $amount)
                ->setIssue($payout > 0 ? IssueType::GAGNE : IssueType::PERDU)
                ->setDebutLe($now)
                ->setFinLe($now)
                ->setMetaJson(json_encode([
                    'number'       => $number,
                    'bet_color'    => $betColor,
                    'result_color' => $resultColor,
                    'multiplier'   => $multiplier,
                ], JSON_UNESCAPED_UNICODE));

            $em->persist($partie);
            $txBet->setPartie($partie);

            if ($payout > 0) {
                $txm->credit($user, $payout, 'roulette', $partie, $now);
            }

            $em->flush();

            $this->rouletteLastGameNotifier->notifyPartie(
                $partie,
                $number,
                $betColor,
                $resultColor,
                $multiplier
            );

            return [
                'number'       => $number,
                'bet_color'    => $betColor,
                'result_color' => $resultColor,
                'multiplier'   => $multiplier,
                'payout'       => $payout,
                'net'          => $payout - $amount,
                'balance'      => $user->getBalance(),
                'partie_id'    => $partie->getId(),
            ];
        });

        return $this->json(['ok' => true, ...$result]);
    }

    private function colorFromNumber(int $number): string
    {
        if ($number === 0) {
            return 'green';
        }

        if (($number >= 1 && $number <= 10) || ($number >= 19 && $number <= 28)) {
            return ($number % 2 === 1) ? 'red' : 'black';
        }

        if (($number >= 11 && $number <= 18) || ($number >= 29 && $number <= 36)) {
            return ($number % 2 === 1) ? 'black' : 'red';
        }

        return 'green';
    }
}
