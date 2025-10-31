<?php
// src/Controller/Widget/SlotsWinsWidgetController.php
namespace App\Controller\Widget;

use App\Entity\Partie;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SlotsWinsWidgetController extends AbstractController
{
    #[Route('/widgets/slots/last-wins', name: 'app_widgets_slots_last_wins', methods: ['GET'])]
    public function lastWins(EntityManagerInterface $em): Response
    {
        $rowsRaw = $em->createQueryBuilder()
            ->select('p', 'u')
            ->from(Partie::class, 'p')
            ->leftJoin('p.utilisateur', 'u')
            ->where('p.game_key = :g')          // <-- propriété correcte
            ->andWhere('p.gain > 0')
            ->orderBy('p.fin_le', 'DESC')       // si null chez toi, mets 'p.debut_le'
            ->setMaxResults(10)
            ->setParameter('g', 'slots')
            ->getQuery()
            ->getResult();

        $rows = [];
        foreach ($rowsRaw as $p) {
            $meta = [];
            if ($p->getMetaJson()) {
                $decoded = json_decode($p->getMetaJson(), true);
                if (is_array($decoded)) {
                    $meta = $decoded;
                }
            }
            $rows[] = [
                'partie' => $p,
                'grid'   => $meta['grid'] ?? null,
                'wins'   => $meta['wins'] ?? [],
            ];
        }

        return $this->render('widgets/slots_last_wins.html.twig', [
            'rows' => $rows,
        ]);
    }
}
