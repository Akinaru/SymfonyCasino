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

        // Catégorie: Wager (somme des mises)
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

            // Déduire le nom MC à partir de getAvatarUrl() OU pseudo, sinon fallback "MHF_Steve"
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
            ];
        }

        // Pad jusqu’à 10 avec des slots vides (MHF_Steve)
        $count = \count($rows);
        for ($i = $count; $i < 10; $i++) {
            $rank = $i + 1;
            $mc   = 'MHF_Steve';
            $rows[] = [
                'rank'      => $rank,
                'user'      => null,
                'pseudo'    => 'MHF_Steve',
                'wager'     => 0,
                'mcName'    => $mc,
                'playerUrl' => "https://mc-heads.net/player/{$mc}",
                'headUrl'   => "https://mc-heads.net/avatar/{$mc}/64",
            ];
        }

        // Podium (1..3) + reste (4..10) dans **la même carte**
        $podium = \array_slice($rows, 0, 3);
        $rest   = \array_slice($rows, 3);

        return $this->render('leaderboard/index.html.twig', [
            'category' => 'wager',
            'podium'   => $podium,
            'rest'     => $rest,
        ]);
    }
}
