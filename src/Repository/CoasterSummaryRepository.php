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

    /**
     * Find summaries with poor feedback ratios.
     *
     * @param float $maxRatio Maximum feedback ratio threshold (e.g., 0.3 for 30%)
     * @param int   $minVotes Minimum number of votes required to consider the ratio
     *
     * @return array<int> Array of coaster IDs with poor feedback
     */
    public function findCoasterIdsWithPoorFeedback(float $maxRatio, int $minVotes): array
    {
        $result = $this->createQueryBuilder('cs')
            ->select('IDENTITY(cs.coaster) as coasterId')
            ->where('cs.feedbackRatio <= :maxRatio')
            ->andWhere('(cs.positiveVotes + cs.negativeVotes) >= :minVotes')
            ->setParameter('maxRatio', $maxRatio)
            ->setParameter('minVotes', $minVotes)
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'coasterId');
    }
}
