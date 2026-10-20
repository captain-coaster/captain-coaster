<?php

declare(strict_types=1);

namespace App\Service\Moderation;

use App\Entity\RiddenCoaster;
use App\Service\Ai\AiTask;
use App\Service\Ai\BedrockService;
use App\Service\Ai\PromptNameSanitizer;
use Psr\Log\LoggerInterface;

/**
 * Analyzes a single review for moderation flags (toxicity/spam/troll/off-topic)
 * and detects its language, via a single Bedrock call.
 */
class ReviewModerationService
{
    /** Strict mode: the answer always matches it, so it is the only validation. */
    private const OUTPUT_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'language' => ['type' => 'string', 'pattern' => '^[a-z]{2}$', 'description' => 'ISO 639-1 code of the review text'],
            'category' => ['type' => 'string', 'enum' => ['ok', 'toxic', 'spam', 'troll', 'offtopic', 'not_ridden', 'other']],
            'confidence' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
            'explanation' => ['type' => ['string', 'null']],
        ],
        'required' => ['language', 'category', 'confidence', 'explanation'],
        'additionalProperties' => false,
    ];

    /**
     * Written from the decisions moderators made on past AI reports. "high" confidence means the
     * verdict is applied without waiting for a moderator, so the prompt asks for it sparingly.
     */
    private const INSTRUCTIONS = <<<'PROMPT'
        You moderate reviews on Captain Coaster, where roller coaster enthusiasts review the coasters they have ridden. Analyze the review in the user message.

        Return its language (ISO 639-1), one category, your confidence, and, unless the category is "ok", a one-sentence explanation in English for the moderator.

        The one question: would a reasonable moderator hide this text? Most reviews are fine. A review only has to tell something about the rider's experience of this coaster, however short, harsh, enthusiastic, sarcastic or badly written. Read irony and jokes for what they mean: sarcastic praise of a bad ride is a genuine negative opinion. A very short text is fine when it still says something about the ride.

        Hide it when it is:
        - "spam": not a review at all — credit logging ("+1", "<username> has ridden this ride"), ads, or a copied fact sheet about the coaster.
        - "troll": a joke or nonsense with no view of the ride behind it.
        - "toxic": heavy profanity, even masked or in a positive review ("f**king brilliant"); profanity that is the whole verdict or adds nothing ("this ride is shit", "crappy", "Sucked", "c'est de la merde"); sexual content; gratuitous meanness; an insult or demeaning remark aimed at people — staff, guests, companions, named persons, or a group by body, origin or identity. A factual remark about a group's behavior is fine ("people from X tend to cut the line"). Mild swearing in a real review, jabs at "enthusiasts" in general, and harsh but constructive or joking criticism of the ride or its designers are fine.
        - "offtopic": about something else than riding it — accidents or injuries of others, messages to the site, unrelated stories.
        - "not_ridden": the reviewer says they never rode it. Partial riding (another seat, without the launch) is fine.
        - "other": clearly problematic but none of the above.

        Confidence: "high" only when any moderator would agree. When reasonable moderators could disagree — harshness that could be a joke or gratuitous, a jab at another guest, a short text whose meaning is unclear — say "medium" or "low", whatever the category.
        PROMPT;

    /**
     * Injected via Symfony/MonologBundle's parameter-name channel autowiring:
     * a constructor argument named "$moderationLogger" resolves to the
     * "moderation" monolog channel (see config/packages/monolog.yaml),
     * which has its own handler outside the main fingers_crossed buffer so
     * these warning-level records reach disk in prod instead of being
     * silently discarded.
     */
    public function __construct(
        private BedrockService $bedrockService,
        private LoggerInterface $moderationLogger
    ) {
    }

    private function reviewIdForLogging(RiddenCoaster $review): int|string
    {
        try {
            return $review->getId();
        } catch (\TypeError) {
            return 'unpersisted';
        }
    }

    /**
     * @return array{language: string, category: string, confidence: string, explanation: ?string}|null
     *                                                                                                  null means the caller should skip this review and let the next cron run retry it
     */
    public function analyze(RiddenCoaster $review): ?array
    {
        $reviewId = $this->reviewIdForLogging($review);
        $response = $this->bedrockService->invoke(
            AiTask::ReviewModeration,
            self::INSTRUCTIONS,
            $this->buildInput($review),
            self::OUTPUT_SCHEMA,
            subjectId: \is_int($reviewId) ? $reviewId : null,
        );

        if (!$response['success']) {
            $this->moderationLogger->error('Review moderation Bedrock call failed', [
                'review_id' => $reviewId,
                'error' => $response['error'],
                'error_code' => $response['error_code'],
                'metadata' => $response['metadata'],
            ]);

            return null;
        }

        $data = $response['data'];

        return [
            'language' => (string) $data['language'],
            'category' => (string) $data['category'],
            'confidence' => (string) $data['confidence'],
            'explanation' => \is_string($data['explanation']) ? $data['explanation'] : null,
        ];
    }

    private function buildInput(RiddenCoaster $review): string
    {
        $sanitizedCoasterName = PromptNameSanitizer::sanitize($review->getCoaster()->getName());

        return "<review>\nCoaster: {$sanitizedCoasterName}\nRating: {$review->getValue()}/5\nText: {$review->getReview()}\n</review>";
    }
}
