<?php
// src/Controller/Game/MinesController.php
namespace App\Controller\Game;

use App\Entity\Partie;
use App\Entity\Utilisateur;
use App\Enum\IssueType;
use App\Game\MinesGame;
use App\Manager\TransactionManager;
use App\Notifier\MinesLastGameNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/games/mines', name: 'app_game_mines_')]
class MinesController extends AbstractController
{
    public function __construct(
        private MinesLastGameNotifier $minesLastGameNotifier,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    #[Route('', name: 'index', methods: ['GET'])]
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $minBet = 1;
        $maxBet = 1000000;
        $descriptionInGame = MinesGame::getDescriptionInGame();

        // Dernières parties
        $qb = $em->getRepository(Partie::class)->createQueryBuilder('p')
            ->addSelect('u')
            ->join('p.utilisateur', 'u')
            ->where('p.game_key = :game')
            ->setParameter('game', 'mines')
            ->orderBy('p.debut_le', 'DESC')
            ->setMaxResults(10);

        $parties = $qb->getQuery()->getResult();

        $lastGames = [];
        foreach ($parties as $partie) {
            if (!$partie instanceof Partie) {
                continue;
            }

            $user = $partie->getUtilisateur();
            $meta = json_decode($partie->getMetaJson() ?? '{}', true) ?: [];

            $minesCount    = isset($meta['mines']) ? (int) $meta['mines'] : null;
            $revealed      = isset($meta['revealed']) && is_array($meta['revealed']) ? $meta['revealed'] : [];
            $revealedCount = count($revealed);

            $lastGames[] = [
                'id'            => $partie->getId(),
                'user_id'       => $user?->getId(),
                'game_key'      => $partie->getGameKey(),
                'mise'          => $partie->getMise(),
                'gain'          => $partie->getGain(),
                'resultat_net'  => $partie->getResultatNet(),
                'issue'         => $partie->getIssue()?->value ?? null,
                'username'      => $user?->getPseudo() ?: ($user?->getEmail() ?? 'Inconnu'),
                'avatar_url'    => $user?->getAvatarUrl() ?? 'https://mc-heads.net/avatar',
                'isWin'         => $partie->getResultatNet() > 0,
                'debut_le'      => $partie->getDebutLe(),
                'mines'         => $minesCount,
                'revealedCount' => $revealedCount,
            ];
        }

        // Grille 2D : lignes = nb de diamants révélés, colonnes = nb de mines
        $minMines    = MinesGame::MIN_MINES;
        $maxMines    = MinesGame::MAX_MINES;
        $maxDiamonds = MinesGame::GRID_SIZE - $minMines; // max de diamants possible (avec le moins de mines)

        /** @var array<int, array<int, float|null>> $multipliersGrid */
        $multipliersGrid = [];

        for ($revealed = 1; $revealed <= $maxDiamonds; $revealed++) {
            $row = [];

            for ($mines = $minMines; $mines <= $maxMines; $mines++) {
                // Impossible de révéler plus de diamants que de cases safe
                if ($revealed > MinesGame::GRID_SIZE - $mines) {
                    $row[$mines] = null;
                    continue;
                }

                try {
                    $row[$mines] = MinesGame::getMultiplier($mines, $revealed);
                } catch (\Throwable $e) {
                    $row[$mines] = null;
                }
            }

            $multipliersGrid[$revealed] = $row;
        }

        return $this->render('game/mines/index.html.twig', [
            'minBet'            => $minBet,
            'maxBet'            => $maxBet,
            'descriptionInGame' => $descriptionInGame,
            'lastGames'         => $lastGames,

            // Pour le tableau "comme l'image"
            'minMines'         => $minMines,
            'maxMines'         => $maxMines,
            'maxDiamonds'      => $maxDiamonds,
            'multipliersGrid'  => $multipliersGrid,
        ]);
    }

    #[Route('/start', name: 'start', methods: ['POST'])]
    public function start(
        Request $request,
        EntityManagerInterface $em,
        TransactionManager $txm
    ): JsonResponse {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var Utilisateur $user */
        $user = $this->getUser();

        $data      = json_decode($request->getContent(), true) ?? [];
        $rawAmount = $data['amount'] ?? null;
        $rawMines  = $data['mines'] ?? null;
        $token     = (string)($data['_token'] ?? '');

        if (!$this->isCsrfTokenValid('mines_play', $token)) {
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

        if ($rawMines === null || $rawMines === '') {
            return $this->json(['ok' => false, 'error' => 'Veuillez choisir un nombre de mines.'], 400);
        }
        if (!is_numeric($rawMines)) {
            return $this->json(['ok' => false, 'error' => 'Nombre de mines invalide.'], 400);
        }

        $mines = (int)$rawMines;
        if ($mines < MinesGame::MIN_MINES || $mines > MinesGame::MAX_MINES) {
            return $this->json([
                'ok'    => false,
                'error' => sprintf(
                    'Nombre de mines invalide : entre %d et %d.',
                    MinesGame::MIN_MINES,
                    MinesGame::MAX_MINES
                ),
            ], 400);
        }

        $now = new \DateTimeImmutable();

        $result = $em->wrapInTransaction(function () use ($em, $txm, $user, $amount, $mines, $now) {
            $txBet = $txm->debit($user, $amount, 'mines', null, $now);

            $cells = range(0, MinesGame::GRID_SIZE - 1);
            shuffle($cells);
            $bombs = array_slice($cells, 0, $mines);
            sort($bombs);

            $meta = [
                'mines'              => $mines,
                'bombs'              => $bombs,
                'revealed'           => [],
                'state'              => 'in_progress',
                'current_multiplier' => 1.0,
            ];

            $partie = (new Partie())
                ->setUtilisateur($user)
                ->setGameKey('mines')
                ->setMise($amount)
                ->setGain(0)
                ->setResultatNet(-$amount)
                ->setIssue(IssueType::PERDU)
                ->setDebutLe($now)
                ->setMetaJson(json_encode($meta, JSON_UNESCAPED_UNICODE));

            $em->persist($partie);
            $txBet->setPartie($partie);

            $em->flush();

            return [
                'game_id'    => $partie->getId(),
                'mines'      => $mines,
                'multiplier' => 1.0,
                'balance'    => $user->getBalance(),
            ];
        });

        return $this->json(['ok' => true, ...$result]);
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

        $data    = json_decode($request->getContent(), true) ?? [];
        $rawGame = $data['game_id'] ?? null;
        $rawCell = $data['cell'] ?? null;
        $token   = (string)($data['_token'] ?? '');

        if (!$this->isCsrfTokenValid('mines_play', $token)) {
            return $this->json(['ok' => false, 'error' => 'Invalid CSRF token.'], 400);
        }

        if ($rawGame === null || !is_numeric($rawGame)) {
            return $this->json(['ok' => false, 'error' => 'Partie invalide.'], 400);
        }
        if ($rawCell === null || !is_numeric($rawCell)) {
            return $this->json(['ok' => false, 'error' => 'Case invalide.'], 400);
        }

        $gameId = (int)$rawGame;
        $cell   = (int)$rawCell;

        if ($cell < 0 || $cell >= MinesGame::GRID_SIZE) {
            return $this->json(['ok' => false, 'error' => 'Case en dehors de la grille.'], 400);
        }

        $partie = $em->getRepository(Partie::class)->find($gameId);
        if (!$partie) {
            return $this->json(['ok' => false, 'error' => 'Partie introuvable.'], 404);
        }

        if ($partie->getGameKey() !== 'mines') {
            return $this->json(['ok' => false, 'error' => 'Partie invalide pour ce jeu.'], 400);
        }

        if ($partie->getUtilisateur()?->getId() !== $user->getId()) {
            return $this->json(['ok' => false, 'error' => 'Cette partie ne vous appartient pas.'], 403);
        }

        $meta = json_decode($partie->getMetaJson() ?? '{}', true) ?: [];

        if (($meta['state'] ?? null) !== 'in_progress') {
            return $this->json(['ok' => false, 'error' => 'Cette partie est déjà terminée.'], 400);
        }

        $mines    = isset($meta['mines']) ? (int)$meta['mines'] : null;
        $bombs    = isset($meta['bombs']) && is_array($meta['bombs']) ? $meta['bombs'] : [];
        $revealed = isset($meta['revealed']) && is_array($meta['revealed']) ? $meta['revealed'] : [];

        if (in_array($cell, $revealed, true)) {
            return $this->json(['ok' => false, 'error' => 'Case déjà révélée.'], 400);
        }

        $now      = new \DateTimeImmutable();
        $exploded = in_array($cell, $bombs, true);

        if ($exploded) {
            $revealed[] = $cell;
            $meta['revealed']      = array_values(array_unique($revealed));
            $meta['state']         = 'lost';
            $meta['exploded_cell'] = $cell;

            $partie->setFinLe($now);
            $partie->setGain(0);
            $partie->setResultatNet(-$partie->getMise());
            $partie->setIssue(IssueType::PERDU);
            $partie->setMetaJson(json_encode($meta, JSON_UNESCAPED_UNICODE));

            $em->flush();

            // ➜ mines + revealedCount pour le notifier
            $revealedCount = count($meta['revealed']);
            $this->minesLastGameNotifier->notifyPartie($partie, $mines ?? 0, $revealedCount);

            return $this->json([
                'ok'        => true,
                'exploded'  => true,
                'cell'      => $cell,
                'bombs'     => $bombs,
                'revealed'  => $meta['revealed'],
                'mines'     => $mines,
                'balance'   => $user->getBalance(),
            ]);
        }

        // ➜ case safe
        $revealed      = array_values(array_unique([...$revealed, $cell]));
        $revealedCount = count($revealed);

        if ($mines === null) {
            return $this->json(['ok' => false, 'error' => 'Configuration de partie invalide.'], 500);
        }

        try {
            $multiplier = MinesGame::getMultiplier($mines, $revealedCount);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'error' => 'Erreur calcul multiplicateur.'], 500);
        }

        $meta['revealed']           = $revealed;
        $meta['current_multiplier'] = $multiplier;

        // ➜ si toutes les cases safe sont révélées, on auto-cashout
        $allSafeRevealed = $revealedCount >= (MinesGame::GRID_SIZE - $mines);

        if ($allSafeRevealed) {
            $amount = $partie->getMise();

            $result = $em->wrapInTransaction(function () use (
                $em,
                $txm,
                $user,
                $partie,
                $meta,
                $mines,
                $revealedCount,
                $amount,
                $now,
                $multiplier
            ) {
                $payout = (int) floor($amount * $multiplier);

                $meta['state']            = 'cashed_out';
                $meta['final_multiplier'] = $multiplier;

                $partie->setGain($payout);
                $partie->setResultatNet($payout - $amount);
                $partie->setIssue(IssueType::GAGNE);
                $partie->setFinLe($now);
                $partie->setMetaJson(json_encode($meta, JSON_UNESCAPED_UNICODE));

                $txm->credit($user, $payout, 'mines', $partie, $now);

                $em->flush();

                // Partie gagnée : on notifie avec mines + revealedCount
                $this->minesLastGameNotifier->notifyPartie($partie, $mines, $revealedCount);

                return [
                    'payout'  => $payout,
                    'net'     => $payout - $amount,
                    'balance' => $user->getBalance(),
                ];
            });

            return $this->json([
                'ok'          => true,
                'exploded'    => false,
                'cell'        => $cell,
                'revealed'    => $revealed,
                'mines'       => $mines,
                'multiplier'  => $multiplier,
                'balance'     => $result['balance'],
                'auto_cashed' => true,
                'payout'      => $result['payout'],
                'net'         => $result['net'],
            ]);
        }

        // Sinon, partie toujours en cours
        $partie->setMetaJson(json_encode($meta, JSON_UNESCAPED_UNICODE));
        $em->flush();

        return $this->json([
            'ok'          => true,
            'exploded'    => false,
            'cell'        => $cell,
            'revealed'    => $revealed,
            'mines'       => $mines,
            'multiplier'  => $multiplier,
            'balance'     => $user->getBalance(),
            'auto_cashed' => false,
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

        $data    = json_decode($request->getContent(), true) ?? [];
        $rawGame = $data['game_id'] ?? null;
        $token   = (string)($data['_token'] ?? '');

        if (!$this->isCsrfTokenValid('mines_play', $token)) {
            return $this->json(['ok' => false, 'error' => 'Invalid CSRF token.'], 400);
        }

        if ($rawGame === null || !is_numeric($rawGame)) {
            return $this->json(['ok' => false, 'error' => 'Partie invalide.'], 400);
        }

        $gameId = (int)$rawGame;

        $partie = $em->getRepository(Partie::class)->find($gameId);
        if (!$partie) {
            return $this->json(['ok' => false, 'error' => 'Partie introuvable.'], 404);
        }

        if ($partie->getGameKey() !== 'mines') {
            return $this->json(['ok' => false, 'error' => 'Partie invalide pour ce jeu.'], 400);
        }

        if ($partie->getUtilisateur()?->getId() !== $user->getId()) {
            return $this->json(['ok' => false, 'error' => 'Cette partie ne vous appartient pas.'], 403);
        }

        $meta = json_decode($partie->getMetaJson() ?? '{}', true) ?: [];

        if (($meta['state'] ?? null) !== 'in_progress') {
            return $this->json(['ok' => false, 'error' => 'Cette partie est déjà terminée.'], 400);
        }

        $mines         = isset($meta['mines']) ? (int)$meta['mines'] : null;
        $revealed      = isset($meta['revealed']) && is_array($meta['revealed']) ? $meta['revealed'] : [];
        $revealedCount = count($revealed);

        if ($mines === null || $revealedCount < 1) {
            return $this->json(['ok' => false, 'error' => 'Aucun diamant révélé, cashout impossible.'], 400);
        }

        $amount = $partie->getMise();
        $now    = new \DateTimeImmutable();

        $result = $em->wrapInTransaction(function () use (
            $em,
            $txm,
            $user,
            $partie,
            $meta,
            $mines,
            $revealedCount,
            $amount,
            $now
        ) {
            $multiplier = isset($meta['current_multiplier'])
                ? (float)$meta['current_multiplier']
                : MinesGame::getMultiplier($mines, $revealedCount);

            $payout = (int) floor($amount * $multiplier);

            $meta['state']            = 'cashed_out';
            $meta['final_multiplier'] = $multiplier;

            $partie->setGain($payout);
            $partie->setResultatNet($payout - $amount);
            $partie->setIssue(IssueType::GAGNE);
            $partie->setFinLe($now);
            $partie->setMetaJson(json_encode($meta, JSON_UNESCAPED_UNICODE));

            $txm->credit($user, $payout, 'mines', $partie, $now);

            $em->flush();

            // ➜ Partie gagnée : on notifie avec mines + revealedCount
            $this->minesLastGameNotifier->notifyPartie($partie, $mines, $revealedCount);

            return [
                'multiplier' => $multiplier,
                'payout'     => $payout,
                'net'        => $payout - $amount,
                'revealed'   => $meta['revealed'],
                'mines'      => $mines,
                'balance'    => $user->getBalance(),
            ];
        });

        return $this->json(['ok' => true, 'cashed_out' => true, ...$result]);
    }
}
