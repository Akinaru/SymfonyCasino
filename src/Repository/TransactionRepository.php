<?php
// ...
namespace App\Repository;

use App\Entity\Transaction;
use App\Entity\Utilisateur;
use App\Enum\TransactionType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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
     * @param int $limit
     * @param int $offset
     * @return Transaction[]
     */
    public function searchUserTransactionsPaginated(
        Utilisateur $user,
        ?string $gameKey,
        array $types,
        int $limit,
        int $offset
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.utilisateur = :u')->setParameter('u', $user)
            ->orderBy('t.cree_le', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        if ($gameKey !== null && $gameKey !== '') {
            $qb->andWhere('t.game_key = :g')->setParameter('g', $gameKey);
        }
        if (!empty($types)) {
            $qb->andWhere('t.type IN (:types)')->setParameter('types', $types);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param Utilisateur $user
     * @param string|null $gameKey
     * @param TransactionType[] $types
     */
    public function countUserTransactions(
        Utilisateur $user,
        ?string $gameKey,
        array $types
    ): int {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.utilisateur = :u')->setParameter('u', $user);

        if ($gameKey !== null && $gameKey !== '') {
            $qb->andWhere('t.game_key = :g')->setParameter('g', $gameKey);
        }
        if (!empty($types)) {
            $qb->andWhere('t.type IN (:types)')->setParameter('types', $types);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
