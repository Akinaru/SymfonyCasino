<?php

namespace App\Controller\Profil;

use App\Entity\Partie;
use App\Entity\Utilisateur;
use App\Enum\TransactionType;
use App\Form\ProfileType;
use App\Game\GameRegistry;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/profil')]
class ProfileController extends AbstractController
{
    #[Route('/', name: 'app_profile_index')]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        Security $security,
        TransactionRepository $transactions,
        GameRegistry $registry
    ): Response {
        /** @var Utilisateur $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $originalEmail  = $user->getEmail();
        $originalPseudo = $user->getPseudo();

        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newEmail  = $user->getEmail();
            $newPseudo = $user->getPseudo();

            if ($newEmail !== $originalEmail) {
                $existingEmail = $em->getRepository(Utilisateur::class)->findOneBy(['email' => $newEmail]);
                if ($existingEmail && $existingEmail->getId() !== $user->getId()) {
                    $user->setEmail($originalEmail);
                    $this->addFlash('danger', 'Cet email est déjà utilisé.');
                    return $this->redirectToRoute('app_profile_index');
                }
            }

            if ($newPseudo !== $originalPseudo) {
                $existingPseudo = $em->getRepository(Utilisateur::class)->findOneBy(['pseudo' => $newPseudo]);
                if ($existingPseudo && $existingPseudo->getId() !== $user->getId()) {
                    $user->setPseudo($originalPseudo);
                    $this->addFlash('danger', 'Ce pseudo est déjà pris.');
                    return $this->redirectToRoute('app_profile_index');
                }
            }

            $currentPassword = $form->get('currentPassword')->getData();
            $newPassword     = $form->get('newPassword')->getData();
            $confirmPassword = $form->get('confirmPassword')->getData();

            if ($newPassword || $confirmPassword) {
                if (!$passwordHasher->isPasswordValid($user, $currentPassword ?? '')) {
                    $user->setEmail($originalEmail);
                    $user->setPseudo($originalPseudo);
                    $this->addFlash('danger', 'Le mot de passe actuel est incorrect.');
                    return $this->redirectToRoute('app_profile_index');
                }

                if ($newPassword !== $confirmPassword) {
                    $user->setEmail($originalEmail);
                    $user->setPseudo($originalPseudo);
                    $this->addFlash('danger', 'Les nouveaux mots de passe ne correspondent pas.');
                    return $this->redirectToRoute('app_profile_index');
                }

                $hashed = $passwordHasher->hashPassword($user, $newPassword);
                $user->setPassword($hashed);
            }

            $em->flush();

            if ($user->getEmail() !== $originalEmail) {
                $security->login($user);
            }

            $this->addFlash('success', 'Profil mis à jour avec succès.');
            return $this->redirectToRoute('app_profile_index');
        }

        $txs = $transactions->findBy(['utilisateur' => $user], ['cree_le' => 'DESC']);

        $bets = 0;
        $wins = 0;
        $sumBets = 0;
        $biggestWin = 0;

        foreach ($txs as $t) {
            $type = $t->getType();
            if ($type === TransactionType::MISE) {
                $bets++;
                $sumBets += abs($t->getMontant());
            } elseif ($type === TransactionType::GAIN) {
                $wins++;
                $m = $t->getMontant();
                if ($m > $biggestWin) {
                    $biggestWin = $m;
                }
            }
        }

        $favoriteGameKey = null;
        $favoriteRow = $em->createQueryBuilder()
            ->select('p.game_key AS gameKey, COUNT(p.id) AS betCount, SUM(p.mise) AS totalMise')
            ->from(Partie::class, 'p')
            ->where('p.utilisateur = :user')
            ->setParameter('user', $user)
            ->groupBy('p.game_key')
            ->orderBy('betCount', 'DESC')
            ->addOrderBy('totalMise', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($favoriteRow) {
            $favoriteGameKey = $favoriteRow['gameKey'] ?? null;
        }

        $lastGameKey = null;
        $lastPartie = $em->createQueryBuilder()
            ->select('p')
            ->from(Partie::class, 'p')
            ->where('p.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('p.debut_le', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($lastPartie instanceof Partie) {
            $lastGameKey = $lastPartie->getGameKey();
        }

        $stats = [
            'betsCount'       => $bets,
            'winRate'         => $bets > 0 ? ($wins / $bets) * 100 : 0,
            'avgBet'          => $bets > 0 ? ($sumBets / $bets) : 0,
            'biggestWin'      => $biggestWin,
            'favoriteGameKey' => $favoriteGameKey,
            'lastGameKey'     => $lastGameKey,
            'vipLevel'        => 1,
        ];

        $namesByKey = [];
        foreach ($registry->all() as $g) {
            $namesByKey[$g->getKey()] = $g->getName();
        }

        return $this->render('profile/index.html.twig', [
            'form'       => $form->createView(),
            'stats'      => $stats,
            'namesByKey' => $namesByKey,
        ]);
    }

    #[Route('/records', name: 'app_profile_records', methods: ['GET'])]
    public function records(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $buildSlotsRecord = static function (Partie $best): array {
            $meta = json_decode($best->getMetaJson() ?? '[]', true) ?: [];

            return [
                'partie' => $best,
                'grid'   => $meta['grid'] ?? null,
                'wins'   => $meta['wins'] ?? [],
            ];
        };

        $buildGenericRecord = static function (Partie $best): array {
            $meta = json_decode($best->getMetaJson() ?? '[]', true) ?: [];

            return [
                'partie' => $best,
                'meta'   => $meta,
            ];
        };

        $getBestRecordByGain = function (string $gameKey, bool $slotsMeta = false) use ($em, $user, $buildSlotsRecord, $buildGenericRecord) {
            $qb = $em->createQueryBuilder()
                ->select('p')
                ->from(Partie::class, 'p')
                ->where('p.utilisateur = :user')
                ->andWhere('p.game_key = :g')
                ->andWhere('p.gain > 0')
                ->orderBy('p.gain', 'DESC')
                ->addOrderBy('p.fin_le', 'DESC')
                ->setMaxResults(1)
                ->setParameter('user', $user)
                ->setParameter('g', $gameKey);

            $best = $qb->getQuery()->getOneOrNullResult();

            if (!$best instanceof Partie) {
                return null;
            }

            return $slotsMeta ? $buildSlotsRecord($best) : $buildGenericRecord($best);
        };

        $getBestRecordByMultiplier = function (string $gameKey, bool $slotsMeta = false) use ($em, $user, $buildSlotsRecord, $buildGenericRecord) {
            $qb = $em->createQueryBuilder()
                ->select('p')
                ->from(Partie::class, 'p')
                ->where('p.utilisateur = :user')
                ->andWhere('p.game_key = :g')
                ->andWhere('p.gain > 0')
                ->andWhere('p.mise > 0')
                ->orderBy('(p.gain * 1.0) / p.mise', 'DESC')
                ->addOrderBy('p.fin_le', 'DESC')
                ->setMaxResults(1)
                ->setParameter('user', $user)
                ->setParameter('g', $gameKey);

            $best = $qb->getQuery()->getOneOrNullResult();

            if (!$best instanceof Partie) {
                return null;
            }

            return $slotsMeta ? $buildSlotsRecord($best) : $buildGenericRecord($best);
        };

        $recordSlotsGain = $getBestRecordByGain('slots', true);
        $recordSlotsMult = $getBestRecordByMultiplier('slots', true);

        $recordDiceGain = $getBestRecordByGain('dice', false);

        $recordMinesGain = $getBestRecordByGain('mines', false);
        $recordMinesMult = $getBestRecordByMultiplier('mines', false);

        $recordRouletteGain = $getBestRecordByGain('roulette', false);

        return $this->render('profile/records.html.twig', [
            'recordSlotsGain'    => $recordSlotsGain,
            'recordSlotsMult'    => $recordSlotsMult,
            'recordDiceGain'     => $recordDiceGain,
            'recordMinesGain'    => $recordMinesGain,
            'recordMinesMult'    => $recordMinesMult,
            'recordRouletteGain' => $recordRouletteGain,
        ]);
    }
}
