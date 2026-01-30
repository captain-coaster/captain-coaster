<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Coaster;
use App\Entity\CoasterSummary;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CoasterSummary>
 */
class CoasterSummaryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CoasterSummary::class);
    }

    /**
     * Find a summary by coaster and language.
     * Works with the new ManyToOne schema with unique constraint on (coaster_id, language).
     */
    public function findByCoasterAndLanguage(Coaster $coaster, string $language): ?CoasterSummary
    {
        return $this->findOneBy(['coaster' => $coaster, 'language' => $language]);
    }

    /**
     * Find coasters that have summaries in a specific language.
     * Used for translate-only mode to load coasters with existing English summaries.
     *
     * @param string   $language Language code (e.g., 'en')
     * @param int|null $limit    Optional limit on results
     *
     * @return array<Coaster> Array of coaster entities ordered by ID
     */
    public function findCoastersWithSummaries(string $language, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('cs')
            ->select('c')
            ->join('cs.coaster', 'c')
            ->where('cs.language = :language')
            ->orderBy('c.id', 'ASC')
            ->setParameter('language', $language);

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find coasters that have summaries with negative votes above the specified threshold.
     * Used for force-bad-reviews mode to regenerate poorly rated summaries.
     *
     * @param int      $downvoteThreshold Minimum number of negative votes
     * @param int|null $limit             Optional limit on results
     *
     * @return array<Coaster> Array of coaster entities ordered by ID
     */
    public function findCoastersWithBadReviews(int $downvoteThreshold, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('cs')
            ->select('c')
            ->join('cs.coaster', 'c')
            ->where('cs.negativeVotes >= :threshold')
            ->orderBy('c.id', 'ASC')
            ->setParameter('threshold', $downvoteThreshold);

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }
}
