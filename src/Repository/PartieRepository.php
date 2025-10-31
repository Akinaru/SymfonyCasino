<?php
// src/Repository/PartieRepository.php

namespace App\Repository;

use App\Entity\Partie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PartieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Partie::class);
    }

    /**
     * @return Partie[]
     */
    public function findLastSlotWins(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.game_key = :g')->setParameter('g', 'slots')
            ->andWhere('p.gain > 0')
            ->orderBy('p.fin_le', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
