<?php
// src/Controller/LeaderboardController.php
namespace App\Controller;

use App\Entity\Partie;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/leaderboard', name: 'app_leaderboard_')]
class LeaderboardController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function redirectToDefault(): Response
    {
        return $this->redirectToRoute('app_leaderboard_wager');
    }

    #[Route('/wager', name: 'wager', methods: ['GET'])]
    public function wager(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $qb = $this->em->createQueryBuilder()
            ->select('u AS user, COALESCE(SUM(p.mise), 0) AS value')
            ->from(Utilisateur::class, 'u')
            ->leftJoin(Partie::class, 'p', 'WITH', 'p.utilisateur = u')
            ->groupBy('u.id')
            ->orderBy('value', 'DESC')
            ->setMaxResults(10);

        $raw = $qb->getQuery()->getResult();
        [$podium, $rest] = $this->buildPodiumAndRest($raw);

        return $this->render('leaderboard/index.html.twig', [
            'category'   => 'wager',
            'label'      => 'Wager',
            'subtitle'   => 'Top 10 (somme des mises)',
            'active_tab' => 'wager',
            'tabs'       => $this->getTabs(),
            'podium'     => $podium,
            'rest'       => $rest,
        ]);
    }

    #[Route('/balance', name: 'balance', methods: ['GET'])]
    public function balance(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $qb = $this->em->createQueryBuilder()
            ->select('u AS user, (u.balance / 100.0) AS value')
            ->from(Utilisateur::class, 'u')
            ->orderBy('value', 'DESC')
            ->setMaxResults(10);

        $raw = $qb->getQuery()->getResult();
        [$podium, $rest] = $this->buildPodiumAndRest($raw);

        return $this->render('leaderboard/index.html.twig', [
            'category'   => 'balance',
            'label'      => 'Balance',
            'subtitle'   => 'Top 10 (solde actuel)',
            'active_tab' => 'balance',
            'tabs'       => $this->getTabs(),
            'podium'     => $podium,
            'rest'       => $rest,
        ]);
    }

    private function buildPodiumAndRest(array $raw): array
    {
        $rows = [];

        foreach ($raw as $i => $row) {
            /** @var Utilisateur $user */
            $user  = $row['user'];
            $value = (float) $row['value'];

            $avatarUrl = (string) ($user->getAvatarUrl() ?? '');
            $mcName = null;

            if (\preg_match('~mc-heads\.net/(?:avatar|player)/([^/]+)~i', $avatarUrl, $m)) {
                $mcName = $m[1];
            }

            if (!$mcName || $mcName === '') {
                $mcName = $user->getPseudo() ?: 'MHF_Steve';
            }

            $rows[] = [
                'rank'      => $i + 1,
                'user'      => $user,
                'pseudo'    => $user->getPseudo() ?: $mcName,
                'value'     => $value,
                'mcName'    => $mcName,
                'playerUrl' => "https://mc-heads.net/player/{$mcName}",
                'headUrl'   => "https://mc-heads.net/avatar/{$mcName}/64",
                'isEmpty'   => false,
            ];
        }

        $podium = \array_slice($rows, 0, 3);

        $rest   = \array_slice($rows, 3);
        $count  = \count($rows);

        for ($rank = $count + 1; $rank <= 10; $rank++) {
            $rest[] = [
                'rank'      => $rank,
                'user'      => null,
                'pseudo'    => null,
                'value'     => 0,
                'mcName'    => null,
                'playerUrl' => null,
                'headUrl'   => null,
                'isEmpty'   => true,
            ];
        }

        return [$podium, $rest];
    }

    private function getTabs(): array
    {
        return [
            [
                'key'   => 'wager',
                'route' => 'app_leaderboard_wager',
                'label' => 'Wager',
            ],
            [
                'key'   => 'balance',
                'route' => 'app_leaderboard_balance',
                'label' => 'Balance',
            ],
            [
                'key'   => 'soon',
                'route' => null,
                'label' => 'Bientôt…',
            ],
        ];
    }
}
