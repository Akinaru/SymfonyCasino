<?php
namespace App\Controller\Game;

use App\Entity\Partie;
use App\Entity\Utilisateur;
use App\Enum\IssueType;
use App\Manager\TransactionManager;
use App\Notifier\SlotLastGameNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/games/slots', name: 'app_game_slots_')]
class SlotsController extends AbstractController
{
    /** @var array<string,array{name:string,index:int,rarity:string,mult:int}> */
    private const SLOT_META = [
        'slot1' => ['name' => 'Émeraude', 'index' => 1, 'rarity' => 'Très rare',   'mult' => 20],
        'slot2' => ['name' => 'Diamant',  'index' => 2, 'rarity' => 'Rare',        'mult' => 12],
        'slot3' => ['name' => 'Redstone', 'index' => 3, 'rarity' => 'Peu fréquent','mult' =>  9],
        'slot4' => ['name' => 'Or',       'index' => 4, 'rarity' => 'Intermédiaire','mult' => 6],
        'slot5' => ['name' => 'Lapis',    'index' => 5, 'rarity' => 'Intermédiaire','mult' => 4],
        'slot6' => ['name' => 'Fer',      'index' => 6, 'rarity' => 'Commun',      'mult' =>  3],
        'slot7' => ['name' => 'Charbon',  'index' => 7, 'rarity' => 'Commun',      'mult' =>  2],
        'slot8' => ['name' => 'Bâton',    'index' => 8, 'rarity' => 'Très commun', 'mult' =>  1],
    ];

    /** @var array<string,int> */
    private const WEIGHTS = [
        'slot1' => 1,
        'slot2' => 2,
        'slot3' => 3,
        'slot4' => 5,
        'slot5' => 8,
        'slot6' => 12,
        'slot7' => 18,
        'slot8' => 28,
    ];

    public function __construct(
        private SlotLastGameNotifier $slotLastGameNotifier,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $minBet = 1;
        $maxBet = 1000000;
        $descriptionInGame = "Slots 3×3, 8 lignes payantes (3 horizontales, 3 verticales, 2 diagonales). Symboles pondérés du plus rare au plus commun : Émeraude, Diamant, Redstone, Or, Lapis, Fer, Charbon, Bâton. Le résultat s’affiche à la fin de l’animation.";

        $items = array_values(self::SLOT_META);
        usort($items, fn($a, $b) => $a['index'] <=> $b['index']);

        $sum = array_sum(self::WEIGHTS);
        $percents = [];
        foreach (self::WEIGHTS as $k => $w) {
            $percents[$k] = round(($w * 100) / $sum, 2);
        }

        $qb = $em->getRepository(Partie::class)->createQueryBuilder('p')
            ->addSelect('u')
            ->join('p.utilisateur', 'u')
            ->where('p.game_key = :game')
            ->setParameter('game', 'slots')
            ->orderBy('p.debut_le', 'DESC')
            ->setMaxResults(10);

        $parties = $qb->getQuery()->getResult();

        $lastGames = [];
        foreach ($parties as $partie) {
            if (!$partie instanceof Partie) {
                continue;
            }

            $grid = null;
            $metaJson = $partie->getMetaJson();
            if ($metaJson) {
                $decoded = json_decode($metaJson, true);
                if (is_array($decoded) && isset($decoded['grid']) && is_array($decoded['grid'])) {
                    $grid = $decoded['grid'];
                }
            }

            $user = $partie->getUtilisateur();

            $lastGames[] = [
                'id'          => $partie->getId(),
                'mise'        => $partie->getMise(),
                'gain'        => $partie->getGain(),
                'resultatNet' => $partie->getResultatNet(),
                'issue'       => $partie->getIssue()?->value ?? null,
                'grid'        => $grid,
                'username'    => $user?->getPseudo() ?? ($user ? 'J'.$user->getId() : 'Joueur ?'),
                'avatarUrl'   => $user ? $user->getAvatarUrl() : 'https://mc-heads.net/avatar',
                'debut_le'    => $partie->getDebutLe(),
            ];
        }

        return $this->render('game/slots/index.html.twig', [
            'minBet'            => $minBet,
            'maxBet'            => $maxBet,
            'descriptionInGame' => $descriptionInGame,
            'items'             => $items,
            'percents'          => $percents,
            'lastGames'         => $lastGames,
        ]);
    }

    #[Route('/start', name: 'start', methods: ['POST'])]
    public function start(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $payload = json_decode($request->getContent(), true) ?? [];
        $rawAmount = $payload['amount'] ?? null;
        $token  = (string)($payload['_token'] ?? '');

        if (!$this->isCsrfTokenValid('slots_play', $token)) {
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
            return $this->json(['ok' => false, 'error' => 'Le montant doit être un entier.'], 400);
        }

        $amount = (int)$rawAmount;

        if ($amount < $minBet) {
            return $this->json(['ok'=>false,'error'=>sprintf('Montant trop faible : min %d.', $minBet)], 400);
        }
        if ($amount > $maxBet) {
            return $this->json(['ok'=>false,'error'=>sprintf('Montant trop élevé : max %d.', $maxBet)], 400);
        }
        if ($user->getBalance() < $amount) {
            return $this->json(['ok'=>false,'error'=>"Solde insuffisant."], 400);
        }

        $spinId = bin2hex(random_bytes(8));
        $session = $request->getSession();
        $key = 'slots.pending.'.$spinId;
        $session->set($key, [
            'uid' => $user->getId(),
            'amount' => $amount,
            'ts' => time(),
            'ttl' => 30,
            'used' => false,
        ]);

        return $this->json([
            'ok' => true,
            'spinId' => $spinId,
        ]);
    }

    #[Route('/resolve', name: 'resolve', methods: ['POST'])]
    public function resolve(
        Request $request,
        EntityManagerInterface $em,
        TransactionManager $txm
    ): JsonResponse {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $payload = json_decode($request->getContent(), true) ?? [];
        $spinId = (string)($payload['spinId'] ?? '');
        $token  = (string)($payload['_token'] ?? '');

        if (!$this->isCsrfTokenValid('slots_play', $token)) {
            return $this->json(['ok' => false, 'error' => 'Invalid CSRF token.'], 400);
        }
        if ($spinId === '') {
            return $this->json(['ok'=>false,'error'=>'Spin manquant.'], 400);
        }

        $session = $request->getSession();
        $key = 'slots.pending.'.$spinId;
        $pending = $session->get($key);
        if (!is_array($pending)) {
            return $this->json(['ok'=>false,'error'=>'Spin introuvable ou expiré.'], 400);
        }
        if (($pending['uid'] ?? null) !== $user->getId()) {
            return $this->json(['ok'=>false,'error'=>'Spin invalide.'], 400);
        }
        if (($pending['used'] ?? false) === true) {
            return $this->json(['ok'=>false,'error'=>'Spin déjà consommé.'], 400);
        }
        $ts  = (int)($pending['ts'] ?? 0);
        $ttl = (int)($pending['ttl'] ?? 0);
        if ($ts <= 0 || $ttl <= 0 || (time() - $ts) > $ttl) {
            $session->remove($key);
            return $this->json(['ok'=>false,'error'=>'Spin expiré.'], 400);
        }

        $amount = (int)$pending['amount'];
        $now = new \DateTimeImmutable();

        $result = $em->wrapInTransaction(function () use ($em, $user, $amount, $now, $txm, $session, $key) {
            $session->set($key, ['used'=>true]);

            $weights = self::WEIGHTS;

            $paytable = [];
            foreach (self::SLOT_META as $k => $m) {
                $paytable[$k] = (int)$m['mult'];
            }

            $draw = static function(array $weights): string {
                $sum = array_sum($weights);
                $pick = random_int(1, $sum);
                $cumul = 0;
                foreach ($weights as $sym => $w) {
                    $cumul += $w;
                    if ($pick <= $cumul) return $sym;
                }
                return 'slot8';
            };

            $grid = [];
            for ($r=0;$r<3;$r++) {
                $row = [];
                for ($c=0;$c<3;$c++) {
                    $row[] = $draw($weights);
                }
                $grid[] = $row;
            }

            $lines = [
                ['name'=>'H1','pos'=>[[0,0],[0,1],[0,2]]],
                ['name'=>'H2','pos'=>[[1,0],[1,1],[1,2]]],
                ['name'=>'H3','pos'=>[[2,0],[2,1],[2,2]]],
                ['name'=>'V1','pos'=>[[0,0],[1,0],[2,0]]],
                ['name'=>'V2','pos'=>[[0,1],[1,1],[2,1]]],
                ['name'=>'V3','pos'=>[[0,2],[1,2],[2,2]]],
                ['name'=>'D1','pos'=>[[0,0],[1,1],[2,2]]],
                ['name'=>'D2','pos'=>[[0,2],[1,1],[2,0]]],
            ];

            $wins = [];
            $payout = 0;

            foreach ($lines as $line) {
                [$r1,$c1] = $line['pos'][0];
                [$r2,$c2] = $line['pos'][1];
                [$r3,$c3] = $line['pos'][2];

                $a = $grid[$r1][$c1];
                $b = $grid[$r2][$c2];
                $c = $grid[$r3][$c3];

                if ($a === $b && $b === $c) {
                    $mult = $paytable[$a] ?? 0;
                    if ($mult > 0) {
                        $linePayout = $amount * $mult;
                        $payout += $linePayout;
                        $meta = self::SLOT_META[$a];
                        $wins[] = [
                            'name'        => $line['name'],
                            'symbol'      => $a,
                            'itemLabel'   => $meta['name'],
                            'itemIndex'   => $meta['index'],
                            'multiplier'  => $mult,
                            'linePayout'  => $linePayout,
                            'positions'   => $line['pos'],
                        ];
                    }
                }
            }

            $txBet = $txm->debit($user, $amount, 'slots', null, $now);

            $partie = (new Partie())
                ->setUtilisateur($user)
                ->setGameKey('slots')
                ->setMise($amount)
                ->setGain($payout)
                ->setResultatNet($payout - $amount)
                ->setIssue($payout > 0 ? IssueType::GAGNE : IssueType::PERDU)
                ->setDebutLe($now)
                ->setFinLe($now)
                ->setMetaJson(json_encode([
                    'grid' => $grid,
                    'wins' => $wins,
                ], JSON_UNESCAPED_UNICODE));

            $em->persist($partie);
            $txBet->setPartie($partie);

            if ($payout > 0) {
                $txm->credit($user, $payout, 'slots', $partie, $now);
            }

            $em->flush();

            // 🔔 Envoi Mercure pour la nouvelle partie
            $this->slotLastGameNotifier->notifyPartie($partie);

            return [
                'grid'      => $grid,
                'wins'      => $wins,
                'payout'    => $payout,
                'net'       => $payout - $amount,
                'balance'   => $user->getBalance(),
                'partie_id' => $partie->getId(),
            ];
        });

        $session->remove($key);

        return $this->json(['ok' => true] + $result);
    }
}
