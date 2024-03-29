<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MainTag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * MainTagRepository.
 */
class MainTagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MainTag::class);
    }
}
