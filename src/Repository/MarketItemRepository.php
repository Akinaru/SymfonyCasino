<?php

namespace App\Repository;

use App\Entity\MarketItem;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MarketItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MarketItem::class);
    }

    /** @return MarketItem[] */
    public function findOnMarket(): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.owner IS NULL')
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()->getResult();
    }

    /** @return MarketItem[] */
    public function findByOwner(Utilisateur $owner): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.owner = :o')
            ->setParameter('o', $owner)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()->getResult();
    }
}
