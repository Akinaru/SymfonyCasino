<?php
// src/Manager/TransactionManager.php
namespace App\Manager;

use App\Entity\Partie;
use App\Entity\Transaction;
use App\Entity\Utilisateur;
use App\Enum\TransactionType;
use Doctrine\ORM\EntityManagerInterface;

final class TransactionManager
{
    public function __construct(private EntityManagerInterface $em) {}

    public function debit(Utilisateur $user, int $amount, string $gameKey, ?Partie $partie = null, ?\DateTimeImmutable $at = null): Transaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Debit amount must be > 0');
        }
        $at ??= new \DateTimeImmutable();

        $before = $user->getBalance();
        if ($before < $amount) {
            throw new \RuntimeException('Insufficient balance.');
        }

        $user->setBalance($before - $amount);
        $user->setWagger(($user->getWagger() ?? 0) + $amount);

        $tx = (new Transaction())
            ->setUtilisateur($user)
            ->setPartie($partie)
            ->setGameKey($gameKey)
            ->setType(TransactionType::MISE)
            ->setMontant(-$amount)
            ->setSoldeAvant($before)
            ->setSoldeApres($user->getBalance())
            ->setCreeLe($at);

        $this->em->persist($user);
        $this->em->persist($tx);

        return $tx;
    }

    public function credit(Utilisateur $user, int $amount, string $gameKey, ?Partie $partie = null, ?\DateTimeImmutable $at = null): ?Transaction
    {
        if ($amount <= 0) {
            return null;
        }
        $at ??= new \DateTimeImmutable();

        $before = $user->getBalance();
        $user->setBalance($before + $amount);

        $tx = (new Transaction())
            ->setUtilisateur($user)
            ->setPartie($partie)
            ->setGameKey($gameKey)
            ->setType(TransactionType::GAIN)
            ->setMontant($amount)
            ->setSoldeAvant($before)
            ->setSoldeApres($user->getBalance())
            ->setCreeLe($at);

        $this->em->persist($user);
        $this->em->persist($tx);

        return $tx;
    }
}
