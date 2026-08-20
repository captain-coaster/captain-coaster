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
     * Find coasters that have a summary in a specific language with negative votes at or
     * above the specified threshold. Used by `app:generate-coaster-summaries --min-downvotes`
     * to bulk-regenerate poorly rated summaries - scoped to one language so a downvoted EN
     * summary doesn't also trigger regenerating an unrelated, perfectly good FR summary on
     * the same coaster. A threshold of 0 matches every coaster with a summary in the
     * language (negativeVotes is never negative), which is how --min-downvotes=0 resets
     * every existing summary regardless of votes.
     *
     * @param string   $language          Language code (e.g., 'en')
     * @param int      $downvoteThreshold Minimum number of negative votes
     * @param int|null $limit             Optional limit on results
     *
     * @return array<Coaster> Array of coaster entities ordered by ID
     */
    public function findCoastersWithBadReviews(string $language, int $downvoteThreshold, ?int $limit = null): array
    {
        $subQuery = $this->createQueryBuilder('cs')
            ->select('IDENTITY(cs.coaster)')
            ->where('cs.language = :language')
            ->andWhere('cs.negativeVotes >= :threshold');

        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('c')
            ->from(Coaster::class, 'c')
            ->where($this->getEntityManager()->createQueryBuilder()->expr()->in('c.id', $subQuery->getDQL()))
            ->orderBy('c.id', 'ASC')
            ->setParameter('language', $language)
            ->setParameter('threshold', $downvoteThreshold);

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }
}
