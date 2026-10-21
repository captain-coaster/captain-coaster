<?php

declare(strict_types=1);

namespace App\Service\Summary;

use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use App\Service\Ai\PromptNameSanitizer;

/**
 * The summary prompt: fixed instructions, one per language, so they form a cacheable prefix
 * across coasters, then the coaster's context and reviews as the per-call input.
 */
final class SummaryPrompt
{
    /** Strict mode: the answer always matches it, so it is the only validation. */
    public const OUTPUT_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'summary' => ['type' => 'string'],
            'meta_description' => ['type' => 'string'],
            'pros' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 5],
            'cons' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 5],
        ],
        'required' => ['summary', 'meta_description', 'pros', 'cons'],
        'additionalProperties' => false,
    ];

    private const LANGUAGE_NAMES = ['en' => 'English', 'fr' => 'French', 'es' => 'Spanish', 'de' => 'German'];

    public static function instructions(string $language): string
    {
        $languageName = self::LANGUAGE_NAMES[$language] ?? 'English';

        return <<<PROMPT
            You are an expert roller coaster analyst with deep knowledge of ride experiences and enthusiast terminology. Your task is to analyze the rider reviews of the coaster given in the user message and create an objective, balanced summary that helps future riders make informed decisions.

            <reading_the_reviews>
            - Use the rating distribution to gauge the consensus and set the tone. Be honest about it: don't force balance if reviews are overwhelmingly positive or negative.
            - Each review starts with the year the coaster was ridden, in brackets. If the coaster still runs, describe it as it rides today: when recent reviews report a change (new trains, retrack, reprofiling, added trims or restraints), prefer them over older reviews about the same aspect, and mention the change.
            - When the context says the coaster no longer runs as reviewed (closed, relocated, or retracked into a new ride), describe the ride as it was until it closed. Reviews of rides after it closed describe its successor or its new location, which have their own page: leave them out.
            - Some reviews may be written in other languages: read them for content and sentiment.
            </reading_the_reviews>

            <output>
            1. summary: between 3 and 5 sentences
               - Open with what sets this coaster apart, not with a statement about the reviews or the consensus ("Reviews are...", "X is overwhelmingly praised...")
               - If the coaster still runs, end with one sentence of advice from experienced riders to someone about to ride it for the first time (which row, who will love or dislike it, when to ride, whether it is worth the wait), drawn from what the reviews say
               - If it no longer runs, write it and the meta_description in the past tense, and give no advice

            2. pros / cons: the most frequently mentioned aspects (MAX 4 words each), only if raised by multiple reviewers
               - Scale the count to sentiment: highly rated -> 3-5 pros / 1-2 cons; poorly rated -> 1-2 pros / 3-5 cons; empty arrays are fine if unsupported by review content

            3. meta_description: one sentence of at most 120 characters, shown under the page title in search results
               - Say what riders love most about this coaster, or what defines it if they mostly dislike it, so that a searcher wants to read more
               - Don't repeat the coaster or park name (the title shows them), and no numbers
            </output>

            <style>
            - Write everything in natural, fluent {$languageName}, as if written by a native speaker enthusiast. When a review uses enthusiast slang or jargon that has no natural equivalent in {$languageName}, convey the sensation it describes rather than translating it word-for-word.
            - Never quote ratings, rating counts or percentages (the page shows them).
            - Never mention safety, legal, maintenance, construction or security issues.
            </style>
            PROMPT;
    }

    /** @param array<int, RiddenCoaster> $reviews */
    public static function input(Coaster $coaster, array $reviews, string $language): string
    {
        $languageName = self::LANGUAGE_NAMES[$language] ?? 'English';

        $input = "<coaster_context>\n";
        $input .= 'Coaster: '.PromptNameSanitizer::sanitize($coaster->getName())."\n";
        // Several coasters share a name (two Iron Rattlers): park and manufacturer tell them apart.
        if ($coaster->getPark()) {
            $input .= 'Park: '.PromptNameSanitizer::sanitize($coaster->getPark()->getName())."\n";
        }
        if ($coaster->getManufacturer()) {
            $input .= 'Manufacturer: '.PromptNameSanitizer::sanitize($coaster->getManufacturer()->getName())."\n";
        }

        $gone = $coaster->getStatus()?->isGone() ?? false;
        if ($coaster->getStatus()) {
            $input .= "Status: {$coaster->getStatus()->getName()}\n";
            if ($gone) {
                $closedIn = $coaster->getClosingDate() ? ' in '.$coaster->getClosingDate()->format('Y') : '';
                $input .= "No longer runs as reviewed: it closed{$closedIn}\n";
            }
        }

        if ($coaster->getAverageRating() && $coaster->getTotalRatings() > 0) {
            // Ratings are on a 0-5 scale (see RiddenCoasterRepository::updateAverageRatings())
            $ratingPercent = round(((float) $coaster->getAverageRating() / 5) * 100, 1);
            $input .= "Community Rating: {$ratingPercent}% based on {$coaster->getTotalRatings()} ratings\n";

            $distribution = self::ratingDistribution($reviews);
            $input .= "Rating Distribution:\n";
            $input .= "- Positive (4-5 stars): {$distribution['positive']}%\n";
            $input .= "- Neutral (3 stars): {$distribution['neutral']}%\n";
            $input .= "- Negative (1-2 stars): {$distribution['negative']}%\n";
        }
        $input .= 'Reviews to analyze: '.\count($reviews)."\n";
        $input .= "</coaster_context>\n\n";

        // Without individual ratings, to avoid bias.
        $input .= "<review_data>\n";
        foreach ($reviews as $review) {
            $year = ($review->getRiddenAt() ?? $review->getCreatedAt())?->format('Y') ?? '?';
            $input .= "[{$year}] {$review->getReview()}\n\n";
        }
        $input .= "</review_data>\n\n";

        // Restated after the (often very large) review data, close to generation: instructions
        // stated only before tens of thousands of tokens of reviews are easy to lose track of.
        $summaryShape = $gone
            ? 'in the past tense, without advice, leaving out reviews of rides after it closed'
            : 'ending with advice for a first-time rider';
        $input .= "Reminder: respond in {$languageName}; summary is 3-5 sentences {$summaryShape}; never quote ratings or percentages.";

        return $input;
    }

    /**
     * Share of positive (4-5), neutral (3) and negative (below 3) ratings among the reviews sent.
     *
     * @param array<int, RiddenCoaster> $reviews
     *
     * @return array{positive: float, neutral: float, negative: float}
     */
    private static function ratingDistribution(array $reviews): array
    {
        $total = \count($reviews);
        $counts = ['positive' => 0, 'neutral' => 0, 'negative' => 0];

        foreach ($reviews as $review) {
            $rating = $review->getValue();
            if (null === $rating) {
                continue;
            }
            ++$counts[$rating >= 4.0 ? 'positive' : ($rating >= 3.0 ? 'neutral' : 'negative')];
        }

        return array_map(static fn (int $count): float => 0 === $total ? 0.0 : round($count / $total * 100, 1), $counts);
    }
}
