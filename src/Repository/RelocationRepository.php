<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Coaster;
use App\Entity\Relocation;
use App\Entity\RelocationCoaster;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RelocationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Relocation::class);
    }

    public function findAnotherRelocation(Coaster $coaster)
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('rc')
            ->from(RelocationCoaster::class, 'rc')
            ->innerJoin('rc.relocation', 'r')
            ->where('rc.coaster = :coaster')
            ->setParameter('coaster', $coaster)
            ->getQuery()
            ->getResult();
    }
}
