<?php

declare(strict_types=1);

namespace App\Service\Summary;

use App\Entity\Coaster;
use App\Repository\RiddenCoasterRepository;
use App\Service\Ai\AiCallFailed;
use App\Service\Ai\AiClient;
use App\Service\Ai\AiModel;
use App\Service\Ai\AiTask;
use Psr\Log\LoggerInterface;

/**
 * Writes a coaster's summary in one language from its reviews, without storing it:
 * CoasterSummaryWriter stores it, CompareSummaryModelsCommand only shows it.
 */
class CoasterSummaryGenerator
{
    /** Reviews counted across all languages: is there enough to analyze at all. */
    public const MIN_REVIEWS_REQUIRED = 20;

    /**
     * Same-language reviews, whatever the backfill: below this, the summary would be mostly other
     * languages translated rather than this audience's view.
     */
    public const MIN_NATIVE_REVIEWS_REQUIRED = 5;

    private const MAX_REVIEWS_FOR_ANALYSIS = 600;

    /**
     * Below this many same-language reviews, the sample is filled in with reviews in other
     * languages, so thin-language coasters still get a representative one.
     */
    private const REPRESENTATIVE_SAMPLE_FLOOR = 100;

    public function __construct(
        private readonly RiddenCoasterRepository $riddenCoasterRepository,
        private readonly AiClient $aiClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param bool $preview not recorded against the coaster in ai_call, so it never counts as its summary's cost
     *
     * @throws NotEnoughReviews
     * @throws AiCallFailed
     */
    public function generate(Coaster $coaster, string $language, ?AiModel $model = null, bool $preview = false): GeneratedSummary
    {
        $nativeReviewCount = $this->riddenCoasterRepository->countCoasterReviewsWithTextByLanguage($coaster, $language);
        if ($nativeReviewCount < self::MIN_NATIVE_REVIEWS_REQUIRED) {
            throw new NotEnoughReviews($nativeReviewCount);
        }

        $totalReviewCount = $this->riddenCoasterRepository->countAllReviewsWithText($coaster);
        if ($totalReviewCount < self::MIN_REVIEWS_REQUIRED) {
            throw new NotEnoughReviews($totalReviewCount);
        }

        $reviews = $this->riddenCoasterRepository->getCoasterReviewsWithTextByLanguage($coaster, $language, self::MAX_REVIEWS_FOR_ANALYSIS);
        $reviewsAnalyzed = \count($reviews);
        if ($reviewsAnalyzed < self::REPRESENTATIVE_SAMPLE_FLOOR) {
            $reviews = [...$reviews, ...$this->riddenCoasterRepository->getCoasterReviewsWithTextExcludingLanguage($coaster, $language, self::REPRESENTATIVE_SAMPLE_FLOOR - $reviewsAnalyzed)];
        }

        $result = $this->aiClient->run(
            AiTask::CoasterSummary,
            SummaryPrompt::instructions($language),
            SummaryPrompt::input($coaster, $reviews, $language),
            SummaryPrompt::OUTPUT_SCHEMA,
            subjectId: $preview ? null : $coaster->getId(),
            language: $language,
            model: $model,
        );

        $summary = trim((string) $result->data['summary']);
        if ('' === $summary) {
            $this->logger->error('Summary answer is empty', ['coaster_id' => $coaster->getId(), 'language' => $language]);

            throw new AiCallFailed(AiTask::CoasterSummary, 'EmptySummary', 'the summary is empty');
        }

        // A column of 255: a longer answer is cut on a word, though the prompt asks for ~120.
        $metaDescription = mb_strimwidth(trim((string) $result->data['meta_description']), 0, 255, '…');

        return new GeneratedSummary(
            $summary,
            '' !== $metaDescription ? $metaDescription : null,
            array_values(array_map(strval(...), (array) $result->data['pros'])),
            array_values(array_map(strval(...), (array) $result->data['cons'])),
            $reviewsAnalyzed,
            \count($reviews),
            $result,
        );
    }
}
