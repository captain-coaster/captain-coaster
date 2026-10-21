<?php

declare(strict_types=1);

namespace App\Service\Summary;

use App\Entity\Coaster;
use App\Entity\CoasterSummary;
use App\Entity\SummaryFeedback;
use App\Repository\CoasterSummaryRepository;
use App\Repository\RiddenCoasterRepository;
use App\Service\Ai\AiCallFailed;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Stores a freshly generated summary in place of the coaster's current one in that language,
 * and tells when one is due. Each language's summary is independent of the others.
 */
class CoasterSummaryWriter
{
    public function __construct(
        private readonly CoasterSummaryGenerator $generator,
        private readonly CoasterSummaryRepository $coasterSummaryRepository,
        private readonly RiddenCoasterRepository $riddenCoasterRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws NotEnoughReviews
     * @throws AiCallFailed
     */
    public function write(Coaster $coaster, string $language): GeneratedSummary
    {
        $generated = $this->generator->generate($coaster, $language);

        $summary = $this->coasterSummaryRepository->findOneBy(['coaster' => $coaster, 'language' => $language]);
        if (null === $summary) {
            $summary = new CoasterSummary();
            $summary->setCoaster($coaster);
            $summary->setLanguage($language);
            // Gedmo's on:'change' strategy only updates existing rows, not new inserts
            $summary->setRegeneratedAt(new \DateTime());
        }

        $summary->setSummary($generated->summary);
        $summary->setMetaDescription($generated->metaDescription);
        $summary->setDynamicPros($generated->pros);
        $summary->setDynamicCons($generated->cons);
        $summary->setReviewsAnalyzed($generated->reviewsAnalyzed);
        $summary->setAiModel($generated->ai->model->value);

        // Votes were about the previous text.
        $summary->setPositiveVotes(0);
        $summary->setNegativeVotes(0);
        $summary->setFeedbackRatio(0.0);
        if (null !== $summary->getId()) {
            $this->entityManager->createQueryBuilder()
                ->delete(SummaryFeedback::class, 'sf')
                ->where('sf.summary = :summary')
                ->setParameter('summary', $summary->getId())
                ->getQuery()
                ->execute();
        }

        $this->entityManager->persist($summary);
        $this->entityManager->flush();

        $this->coasterSummaryRepository->clearCacheFor($coaster, $language);

        return $generated;
    }

    /**
     * Due when there is none yet, or when enough new same-language reviews came since. No
     * time-based expiry: a summary with nothing new to say keeps its votes. The admin
     * "regenerate" action covers a changed status or context without new reviews.
     */
    public function isDue(Coaster $coaster, string $language): bool
    {
        $summary = $this->coasterSummaryRepository->findOneBy(['coaster' => $coaster, 'language' => $language]);
        if (null === $summary) {
            return true;
        }

        $analyzedCount = $summary->getReviewsAnalyzed();
        $newReviews = $this->riddenCoasterRepository->countCoasterReviewsWithTextByLanguage($coaster, $language) - $analyzedCount;

        return $newReviews >= max(CoasterSummaryGenerator::MIN_REVIEWS_REQUIRED, (int) ($analyzedCount * 0.2));
    }
}
