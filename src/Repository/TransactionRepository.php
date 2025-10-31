<?php
// src/Repository/TransactionRepository.php
namespace App\Repository;

use App\Entity\Transaction;
use App\Entity\Utilisateur;
use App\Enum\TransactionType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /**
     * @param Utilisateur $user
     * @param string|null $gameKey
     * @param TransactionType[] $types
     * @return Transaction[]
     */
    public function searchUserTransactions(Utilisateur $user, ?string $gameKey, array $types = []): array
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.utilisateur = :u')
            ->setParameter('u', $user)
            ->orderBy('t.cree_le', 'DESC');

        if ($gameKey !== null && $gameKey !== '') {
            $qb->andWhere('t.game_key = :g')
                ->setParameter('g', $gameKey);
        }

        if (!empty($types)) {
            $qb->andWhere('t.type IN (:types)')
                ->setParameter('types', $types);
        }

        return $qb->getQuery()->getResult();
    }
}
