<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Coaster;
use App\Entity\CoasterSummary;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CoasterSummaryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CoasterSummary::class);
    }

    public function findByCoasterAndLanguage(Coaster $coaster, string $language): ?CoasterSummary
    {
        return $this->findOneBy(['coaster' => $coaster, 'language' => $language]);
    }
}
