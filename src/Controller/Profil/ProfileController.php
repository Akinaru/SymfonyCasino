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

        $stats = [
            'betsCount'       => $bets,
            'winRate'         => $bets > 0 ? ($wins / $bets) * 100 : 0,
            'avgBet'          => $bets > 0 ? ($sumBets / $bets) : 0,
            'biggestWin'      => $biggestWin,
            'favoriteGameKey' => null,
            'lastGameKey'     => null,
            'vipLevel'        => 1,
        ];

        $namesByKey = [];
        foreach ($registry->all() as $g) {
            $namesByKey[$g->getKey()] = $g->getName();
        }

        return $this->render('profile/index.html.twig', [
            'form' => $form->createView(),
            'stats' => $stats,
            'namesByKey' => $namesByKey,
        ]);
    }

    #[Route('/records', name: 'app_profile_records', methods: ['GET'])]
    public function records(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /** @var Utilisateur $user */
        $user = $this->getUser();

        // Fonction interne pour récupérer le meilleur score d’un jeu
        $getBestRecord = function(string $gameKey) use ($em, $user) {
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

            $meta = json_decode($best->getMetaJson() ?? '[]', true) ?: [];

            return [
                'partie' => $best,
                'grid'   => $meta['grid'] ?? null,
                'wins'   => $meta['wins'] ?? [],
            ];
        };

        // Récupération des records
        $recordSlots = $getBestRecord('slots');
        $recordDice  = $getBestRecord('dice');

        return $this->render('profile/records.html.twig', [
            'recordSlots' => $recordSlots,
            'recordDice'  => $recordDice,
        ]);
    }

}
