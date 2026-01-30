<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SeatingType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeatingType>
 */
class SeatingTypeRepository extends ServiceEntityRepository
{
    use FilterableRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SeatingType::class);
    }
}
