<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SummaryFeedback;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for SummaryFeedback entity.
 *
 * @extends ServiceEntityRepository<SummaryFeedback>
 */
class SummaryFeedbackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SummaryFeedback::class);
    }
}