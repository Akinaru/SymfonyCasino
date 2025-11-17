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
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $qb = $em->createQueryBuilder()
            ->select('u AS user, COALESCE(SUM(p.mise), 0) AS wager')
            ->from(Utilisateur::class, 'u')
            ->leftJoin(Partie::class, 'p', 'WITH', 'p.utilisateur = u')
            ->groupBy('u.id')
            ->orderBy('wager', 'DESC')
            ->setMaxResults(10);

        $raw = $qb->getQuery()->getResult();

        $rows = [];
        foreach ($raw as $i => $row) {
            /** @var Utilisateur $user */
            $user  = $row['user'];
            $wager = (int) $row['wager'];

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
                'wager'     => $wager,
                'mcName'    => $mcName,
                'playerUrl' => "https://mc-heads.net/player/{$mcName}",
                'headUrl'   => "https://mc-heads.net/avatar/{$mcName}/64",
                'isEmpty'   => false,
            ];
        }

        // Podium: toujours les vrais joueurs (1..3 max)
        $podium = \array_slice($rows, 0, 3);

        // Reste: 4..10 avec slots vides marqués
        $rest = \array_slice($rows, 3);
        $count = \count($rows);
        for ($rank = $count + 1; $rank <= 10; $rank++) {
            $rest[] = [
                'rank'      => $rank,
                'user'      => null,
                'pseudo'    => null,
                'wager'     => 0,
                'mcName'    => null,
                'playerUrl' => null,
                'headUrl'   => null,
                'isEmpty'   => true,
            ];
        }

        return $this->render('leaderboard/index.html.twig', [
            'category' => 'wager',
            'podium'   => $podium,
            'rest'     => $rest,
        ]);
    }
}
