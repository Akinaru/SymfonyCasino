<?php
// src/Controller/Game/PlinkoController.php

namespace App\Controller\Game;

use App\Entity\Partie;
use App\Entity\Utilisateur;
use App\Enum\IssueType;
use App\Game\PlinkoGame;
use App\Manager\TransactionManager;
use App\Notifier\PlinkoLastGameNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/games/plinko', name: 'app_game_plinko_')]
class PlinkoController extends AbstractController
{
    /**
     * Plateau fixe : 16 rangées -> 17 cases.
     * Symétrique, risque moyen.
     *
     * RTP théorique ~95 % sur ce set (peut être ajusté en changeant les multiplicateurs).
     */
    private const ROWS = 16;

    /**
     * Index = position finale (0 = extrême gauche, 16 = extrême droite).
     *
     * Inspiré des Plinko “medium risk” : centre autour de 0.4–1x,
     * bords beaucoup plus élevés mais ultra rares.
     */
    private const MULTIPLIERS = [
        20.0,
        10.0,
        5.0,
        3.0,
        2.0,
        1.5,
        1.0,
        0.7,
        0.4,
        0.7,
        1.0,
        1.5,
        2.0,
        3.0,
        5.0,
        10.0,
        20.0,
    ];

    public function __construct(
        private PlinkoLastGameNotifier $plinkoLastGameNotifier,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $minBet = 1;
        $maxBet = 1_000_000;
        $descriptionInGame = PlinkoGame::getDescriptionInGame();

        $qb = $em->getRepository(Partie::class)->createQueryBuilder('p')
            ->addSelect('u')
            ->join('p.utilisateur', 'u')
            ->where('p.game_key = :game')
            ->setParameter('game', 'plinko')
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

            $rows       = $meta['rows'] ?? self::ROWS;
            $position   = $meta['position'] ?? null;
            $multiplier = $meta['multiplier'] ?? null;

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

                'rows'         => $rows,
                'position'     => $position,
                'multiplier'   => $multiplier,
                'isWin'        => $partie->getResultatNet() > 0,
                'debut_le'     => $partie->getDebutLe(),
            ];
        }

        return $this->render('game/plinko/index.html.twig', [
            'minBet'        => $minBet,
            'maxBet'        => $maxBet,
            'descriptionInGame' => $descriptionInGame,
            'rows'          => self::ROWS,
            'multipliers'   => self::MULTIPLIERS,
            'lastGames'     => $lastGames,
        ]);
    }

    #[Route('/play', name: 'play', methods: ['POST'])]
    public function play(
        Request $request,
        EntityManagerInterface $em,
        TransactionManager $txm,
    ): JsonResponse {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var Utilisateur $user */
        $user = $this->getUser();

        $data      = json_decode($request->getContent(), true) ?? [];
        $rawAmount = $data['amount'] ?? null;
        $token     = (string)($data['_token'] ?? '');

        if (!$this->isCsrfTokenValid('plinko_play', $token)) {
            return $this->json(['ok' => false, 'error' => 'Invalid CSRF token.'], 400);
        }

        $minBet = 1;
        $maxBet = 1_000_000;

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
            // Débit initial
            $txBet = $txm->debit($user, $amount, 'plinko', null, $now);

            // Simulation d'un chemin Plinko :
            // à chaque rangée la bille part à gauche (0) ou à droite (1).
            $rows = self::ROWS;
            $position = 0;
            for ($i = 0; $i < $rows; $i++) {
                // 0 ou 1 -> binomial (n=16, p=0.5)
                $step = random_int(0, 1);
                $position += $step;
            }

            // Clamp par sécurité (0..ROWS)
            if ($position < 0) {
                $position = 0;
            } elseif ($position > $rows) {
                $position = $rows;
            }

            $multipliers = self::MULTIPLIERS;
            $multiplier  = $multipliers[$position] ?? 0.0;

            // Payout arrondi à l'entier le plus proche
            $payout = (int) round($amount * $multiplier);

            $partie = (new Partie())
                ->setUtilisateur($user)
                ->setGameKey('plinko')
                ->setMise($amount)
                ->setGain($payout)
                ->setResultatNet($payout - $amount)
                ->setIssue($payout > 0 ? IssueType::GAGNE : IssueType::PERDU)
                ->setDebutLe($now)
                ->setFinLe($now)
                ->setMetaJson(json_encode([
                    'rows'       => $rows,
                    'position'   => $position,
                    'multiplier' => $multiplier,
                ], JSON_UNESCAPED_UNICODE));

            $em->persist($partie);
            $txBet->setPartie($partie);

            if ($payout > 0) {
                $txm->credit($user, $payout, 'plinko', $partie, $now);
            }

            $em->flush();

            $this->plinkoLastGameNotifier->notifyPartie($partie, $rows, $position, $multiplier);

            return [
                'rows'       => $rows,
                'position'   => $position,
                'multiplier' => $multiplier,
                'payout'     => $payout,
                'net'        => $payout - $amount,
                'balance'    => $user->getBalance(),
                'partie_id'  => $partie->getId(),
            ];
        });

        return $this->json(['ok' => true, ...$result]);
    }
}
