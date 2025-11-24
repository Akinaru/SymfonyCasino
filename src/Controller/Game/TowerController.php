<?php

namespace App\Controller\Game;

use App\Entity\Partie;
use App\Entity\Utilisateur;
use App\Enum\IssueType;
use App\Game\TowerGame;
use App\Manager\TransactionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/games/tower', name: 'app_game_tower_')]
class TowerController extends AbstractController
{
    private const ROWS = 9;
    private const COLS = 3;
    private const EASY_ROWS = 3;
    private const RESCUE_CHANCE_PERCENT = 60;

    /** @var array<int,float> */
    private const MULTIPLIERS = [
        0 => 1.00,
        1 => 1.25,
        2 => 1.55,
        3 => 1.90,
        4 => 2.35,
        5 => 2.95,
        6 => 3.70,
        7 => 4.65,
        8 => 6.00,
        9 => 8.00,
    ];

    private function getSessionKey(Utilisateur $user): string
    {
        return 'tower.current.' . $user->getId();
    }

    private function computeMultiplier(int $height): float
    {
        if ($height < 0) {
            $height = 0;
        }
        if (\array_key_exists($height, self::MULTIPLIERS)) {
            return self::MULTIPLIERS[$height];
        }

        $values = array_values(self::MULTIPLIERS);
        return (float) end($values);
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $minBet = 1;
        $maxBet = 1000000;
        $descriptionInGame = TowerGame::getDescriptionInGame();

        $qb = $em->getRepository(Partie::class)->createQueryBuilder('p')
            ->addSelect('u')
            ->join('p.utilisateur', 'u')
            ->where('p.game_key = :g')
            ->setParameter('g', 'tower')
            ->orderBy('p.debut_le', 'DESC')
            ->setMaxResults(10);

        /** @var Partie[] $parties */
        $parties = $qb->getQuery()->getResult();

        $lastGames = [];
        foreach ($parties as $partie) {
            if (!$partie instanceof Partie) {
                continue;
            }

            $meta = json_decode($partie->getMetaJson() ?? '{}', true) ?: [];
            $user = $partie->getUtilisateur();

            $lastGames[] = [
                'id'           => $partie->getId(),
                'mise'         => $partie->getMise(),
                'gain'         => $partie->getGain(),
                'resultat_net' => $partie->getResultatNet(),
                'issue'        => $partie->getIssue()?->value ?? null,
                'username'     => $user?->getPseudo() ?: ($user?->getEmail() ?? 'Inconnu'),
                'avatar_url'   => $user?->getAvatarUrl() ?? 'https://mc-heads.net/avatar',
                'height'       => (int)($meta['height'] ?? 0),
                'cashed_out'   => (bool)($meta['cashed_out'] ?? false),
                'debut_le'     => $partie->getDebutLe(),
            ];
        }

        return $this->render('game/tower/index.html.twig', [
            'minBet'            => $minBet,
            'maxBet'            => $maxBet,
            'descriptionInGame' => $descriptionInGame,
            'maxRows'           => self::ROWS,
            'cols'              => self::COLS,
            'multipliers'       => self::MULTIPLIERS,
            'lastGames'         => $lastGames,
        ]);
    }

    #[Route('/start', name: 'start', methods: ['POST'])]
    public function start(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $payload   = json_decode($request->getContent(), true) ?? [];
        $rawAmount = $payload['amount'] ?? null;
        $token     = (string)($payload['_token'] ?? '');

        if (!$this->isCsrfTokenValid('tower_play', $token)) {
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

        $amount = (int) $rawAmount;

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

        $session = $request->getSession();
        $key     = $this->getSessionKey($user);

        $layout = [];
        for ($row = 0; $row < self::ROWS; $row++) {
            $safe = random_int(0, self::COLS - 1);
            $bombs = [];
            for ($c = 0; $c < self::COLS; $c++) {
                if ($c === $safe) {
                    continue;
                }
                $bombs[] = $c;
            }
            $layout[$row] = [
                'safe'  => $safe,
                'bombs' => $bombs,
            ];
        }

        $state = [
            'user_id'     => $user->getId(),
            'bet'         => $amount,
            'rows'        => self::ROWS,
            'cols'        => self::COLS,
            'height'      => 0,
            'currentRow'  => 0,
            'layout'      => $layout,
            'started_at'  => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        $session->set($key, $state);

        return $this->json([
            'ok'         => true,
            'game_id'    => bin2hex(random_bytes(8)),
            'multiplier' => $this->computeMultiplier(0),
        ]);
    }

    #[Route('/reveal', name: 'reveal', methods: ['POST'])]
    public function reveal(
        Request $request,
        EntityManagerInterface $em,
        TransactionManager $txm
    ): JsonResponse {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $payload = json_decode($request->getContent(), true) ?? [];
        $row     = (int) ($payload['row'] ?? -1);
        $col     = (int) ($payload['col'] ?? -1);
        $token   = (string)($payload['_token'] ?? '');

        if (!$this->isCsrfTokenValid('tower_play', $token)) {
            return $this->json(['ok' => false, 'error' => 'Invalid CSRF token.'], 400);
        }

        $session = $request->getSession();
        $key     = $this->getSessionKey($user);
        $state   = $session->get($key);

        if (!\is_array($state) || (($state['user_id'] ?? null) !== $user->getId())) {
            $session->remove($key);
            return $this->json(['ok' => false, 'error' => 'Aucune partie Tower en cours.'], 400);
        }

        $bet        = (int)($state['bet'] ?? 0);
        $height     = (int)($state['height'] ?? 0);
        $currentRow = (int)($state['currentRow'] ?? 0);
        $rows       = (int)($state['rows'] ?? self::ROWS);
        $cols       = (int)($state['cols'] ?? self::COLS);
        $layout     = $state['layout'] ?? [];

        if ($row < 0 || $row >= $rows || $col < 0 || $col >= $cols) {
            return $this->json(['ok' => false, 'error' => 'Coup invalide.'], 400);
        }

        if ($row !== $currentRow) {
            return $this->json(['ok' => false, 'error' => 'Tu dois jouer l’étage en cours.'], 400);
        }

        if (!isset($layout[$row]) || !\is_array($layout[$row]) || !isset($layout[$row]['safe'])) {
            $session->remove($key);
            return $this->json(['ok' => false, 'error' => 'État de partie invalide, Tower a été réinitialisé.'], 400);
        }

        $safeCol = (int) $layout[$row]['safe'];

        $isBombClick = ($col !== $safeCol);

        if ($isBombClick && $row < self::EASY_ROWS && self::RESCUE_CHANCE_PERCENT > 0) {
            $roll = random_int(1, 100);
            if ($roll <= self::RESCUE_CHANCE_PERCENT) {
                $newBombs = [];
                for ($c = 0; $c < $cols; $c++) {
                    if ($c === $col) {
                        continue;
                    }
                    $newBombs[] = $c;
                }
                $layout[$row] = [
                    'safe'  => $col,
                    'bombs' => $newBombs,
                ];
                $state['layout'] = $layout;
                $session->set($key, $state);

                $safeCol = $col;
                $isBombClick = false;
            }
        }

        if ($isBombClick) {
            $now = new \DateTimeImmutable();
            $heightAtLoss = $height;

            $result = $em->wrapInTransaction(function () use ($em, $txm, $user, $bet, $heightAtLoss, $now) {
                $payout = 0;

                $txBet = $txm->debit($user, $bet, 'tower', null, $now);

                $partie = (new Partie())
                    ->setUtilisateur($user)
                    ->setGameKey('tower')
                    ->setMise($bet)
                    ->setGain($payout)
                    ->setResultatNet($payout - $bet)
                    ->setIssue(IssueType::PERDU)
                    ->setDebutLe($now)
                    ->setFinLe($now)
                    ->setMetaJson(json_encode([
                        'height'     => $heightAtLoss,
                        'cashed_out' => false,
                    ], JSON_UNESCAPED_UNICODE));

                $em->persist($partie);
                $txBet->setPartie($partie);
                $em->flush();

                return [
                    'payout'  => $payout,
                    'net'     => $payout - $bet,
                    'balance' => $user->getBalance(),
                ];
            });

            $session->remove($key);

            return $this->json([
                    'ok'          => true,
                    'exploded'    => true,
                    'auto_cashed' => false,
                    'multiplier'  => $this->computeMultiplier($heightAtLoss),
                    'height'      => $heightAtLoss,
                ] + $result);
        }

        $height++;
        $currentRow = $height;
        $state['height']      = $height;
        $state['currentRow']  = $currentRow;
        $state['multiplier']  = $this->computeMultiplier($height);
        $session->set($key, $state);

        if ($height >= $rows) {
            $now  = new \DateTimeImmutable();
            $mult = $this->computeMultiplier($height);
            $payout = (int) \floor($bet * $mult);
            $net    = $payout - $bet;

            $result = $em->wrapInTransaction(function () use ($em, $txm, $user, $bet, $payout, $net, $height, $now) {
                $txBet = $txm->debit($user, $bet, 'tower', null, $now);

                $partie = (new Partie())
                    ->setUtilisateur($user)
                    ->setGameKey('tower')
                    ->setMise($bet)
                    ->setGain($payout)
                    ->setResultatNet($net)
                    ->setIssue($net > 0 ? IssueType::GAGNE : IssueType::PERDU)
                    ->setDebutLe($now)
                    ->setFinLe($now)
                    ->setMetaJson(json_encode([
                        'height'     => $height,
                        'cashed_out' => true,
                    ], JSON_UNESCAPED_UNICODE));

                $em->persist($partie);
                $txBet->setPartie($partie);

                if ($payout > 0) {
                    $txm->credit($user, $payout, 'tower', $partie, $now);
                }

                $em->flush();

                return [
                    'balance' => $user->getBalance(),
                ];
            });

            $session->remove($key);

            return $this->json([
                    'ok'          => true,
                    'exploded'    => false,
                    'auto_cashed' => true,
                    'multiplier'  => $mult,
                    'height'      => $height,
                    'payout'      => $payout,
                    'net'         => $net,
                ] + $result);
        }

        return $this->json([
            'ok'          => true,
            'exploded'    => false,
            'auto_cashed' => false,
            'multiplier'  => $this->computeMultiplier($height),
            'height'      => $height,
            'next_row'    => $currentRow,
        ]);
    }

    #[Route('/cashout', name: 'cashout', methods: ['POST'])]
    public function cashout(
        Request $request,
        EntityManagerInterface $em,
        TransactionManager $txm
    ): JsonResponse {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $payload = json_decode($request->getContent(), true) ?? [];
        $token   = (string)($payload['_token'] ?? '');

        if (!$this->isCsrfTokenValid('tower_play', $token)) {
            return $this->json(['ok' => false, 'error' => 'Invalid CSRF token.'], 400);
        }

        $session = $request->getSession();
        $key     = $this->getSessionKey($user);
        $state   = $session->get($key);

        if (!\is_array($state) || (($state['user_id'] ?? null) !== $user->getId())) {
            $session->remove($key);
            return $this->json(['ok' => false, 'error' => 'Aucune partie Tower en cours.'], 400);
        }

        $bet    = (int)($state['bet'] ?? 0);
        $height = (int)($state['height'] ?? 0);

        if ($height <= 0 || $bet <= 0) {
            $session->remove($key);

            return $this->json([
                'ok'         => true,
                'payout'     => 0,
                'net'        => 0,
                'height'     => 0,
                'multiplier' => $this->computeMultiplier(0),
            ]);
        }

        $now  = new \DateTimeImmutable();
        $mult = $this->computeMultiplier($height);
        $payout = (int) \floor($bet * $mult);
        $net    = $payout - $bet;

        $result = $em->wrapInTransaction(function () use ($em, $txm, $user, $bet, $payout, $net, $height, $now) {
            $txBet = $txm->debit($user, $bet, 'tower', null, $now);

            $partie = (new Partie())
                ->setUtilisateur($user)
                ->setGameKey('tower')
                ->setMise($bet)
                ->setGain($payout)
                ->setResultatNet($net)
                ->setIssue($net > 0 ? IssueType::GAGNE : IssueType::PERDU)
                ->setDebutLe($now)
                ->setFinLe($now)
                ->setMetaJson(json_encode([
                    'height'     => $height,
                    'cashed_out' => true,
                ], JSON_UNESCAPED_UNICODE));

            $em->persist($partie);
            $txBet->setPartie($partie);

            if ($payout > 0) {
                $txm->credit($user, $payout, 'tower', $partie, $now);
            }

            $em->flush();

            return [
                'balance' => $user->getBalance(),
            ];
        });

        $session->remove($key);

        return $this->json([
                'ok'         => true,
                'payout'     => $payout,
                'net'        => $net,
                'height'     => $height,
                'multiplier' => $mult,
            ] + $result);
    }

    #[Route('/status', name: 'status', methods: ['GET'])]
    public function status(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $session = $request->getSession();
        $key     = $this->getSessionKey($user);
        $state   = $session->get($key);

        if (!\is_array($state) || (($state['user_id'] ?? null) !== $user->getId())) {
            if ($state !== null) {
                $session->remove($key);
            }

            return $this->json([
                'ok'          => true,
                'in_progress' => false,
            ]);
        }

        $bet        = (int)($state['bet'] ?? 0);
        $height     = (int)($state['height'] ?? 0);
        $currentRow = (int)($state['currentRow'] ?? 0);
        $rows       = (int)($state['rows'] ?? self::ROWS);

        $multiplier = isset($state['multiplier'])
            ? (float)$state['multiplier']
            : $this->computeMultiplier($height);

        return $this->json([
            'ok'          => true,
            'in_progress' => true,
            'bet'         => $bet,
            'height'      => $height,
            'current_row' => $currentRow,
            'max_rows'    => $rows,
            'multiplier'  => $multiplier,
        ]);
    }
}
