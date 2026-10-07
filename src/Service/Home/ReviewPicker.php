<?php

declare(strict_types=1);

namespace App\Service\Home;

use App\Entity\RiddenCoaster;
use App\Repository\RiddenCoasterRepository;

/**
 * Picks Home's reviews by author reputation: recent, substantial reviews from authors whose reviews collected
 * votes, in the reader's languages first, then English, one per author.
 */
class ReviewPicker
{
    private const int MIN_AUTHOR_VOTES = 10;
    private const int MIN_LENGTH = 300;
    private const int WINDOW_DAYS = 30;
    private const int POOL = 60;
    private const string FALLBACK_LANGUAGE = 'en';

    public function __construct(private readonly RiddenCoasterRepository $riddenCoasterRepository)
    {
    }

    /**
     * @param array<string> $languages          the reader's review languages
     * @param list<int>     $excludedCoasterIds coasters already featured on the page
     *
     * @return list<RiddenCoaster>
     */
    public function pick(array $languages, array $excludedCoasterIds, int $limit): array
    {
        $pool = $this->riddenCoasterRepository->findRecentReviewsByAuthors(
            $this->riddenCoasterRepository->findReputedAuthorIds(self::MIN_AUTHOR_VOTES),
            new \DateTimeImmutable('today')->modify(\sprintf('-%d days', self::WINDOW_DAYS)),
            self::MIN_LENGTH,
            self::POOL,
        );

        $picked = [];
        // The pool is latest first; each pass keeps that order.
        foreach ([$languages, [self::FALLBACK_LANGUAGE]] as $accepted) {
            foreach ($pool as $review) {
                $authorId = $review->getUser()->getId();
                if (
                    isset($picked[$authorId])
                    || !\in_array($review->getLanguage(), $accepted, true)
                    || \in_array($review->getCoaster()->getId(), $excludedCoasterIds, true)
                ) {
                    continue;
                }

                $picked[$authorId] = $review;
                if (\count($picked) === $limit) {
                    break 2;
                }
            }
        }

        $reviews = array_values($picked);
        $this->riddenCoasterRepository->preloadTags($reviews, 300);

        return $reviews;
    }
}
