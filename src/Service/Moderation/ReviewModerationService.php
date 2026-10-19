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

    private const INSTRUCTIONS = <<<'PROMPT'
        You are a content moderator for a roller coaster review website. Analyze the review given in the user message.

        <task>
        1. Detect the language of the review text and return its ISO 639-1 code (e.g. "en", "fr", "ja", "sv").
        2. Classify the review into exactly one category:
           - "ok": on-topic opinion about the ride itself — any ride characteristic: sensations, look, operations, etc. Light profanity (e.g. "crap", "sucks", "shit", "damn") is fine as part of a genuine review.
           - "toxic": hostility or insults aimed at people (staff, management, reviewers) — never the ride itself. A blunt or harshly negative opinion about the ride (e.g. "Horrendous", "this thing is a disaster, no interest at all") is "ok", not toxic, as long as it targets no person and contains no real profanity — bluntness or brevity alone is not toxicity. Light profanity is only ok as part of a genuine on-topic review of the ride — without real ride content behind it, light profanity is toxic. Heavy profanity (e.g. "fuck"/"fucking", slurs, graphic/extreme language) is always toxic, regardless of context.
           - "spam": promotional content, gibberish, or repeated/copy-pasted text.
           - "troll": bad-faith, disruptive, or nonsensical content with no genuine opinion behind it. Jokes and exaggerated humor are "ok", NOT troll, if they express a real (if hyperbolic) sentiment about the ride — e.g. "life is short, let's not make it shorter by riding this" genuinely means the ride feels intense/scary, so classify it "ok". Only use troll when there is no real view behind the text, or it is meant to disrupt/mislead.
           - "offtopic": not about the ride experience or general park experience. Includes: formal complaints or legal/financial grievances with management, incidents unrelated to the normal ride experience (e.g. a fall, an altercation with other guests, an evacuation). Does NOT include physical reactions to the ride itself — aches, bruises, motion sickness, fear — even when described as an injury or in strong language; those are core review content, not offtopic. Also does not include staff/service quality opinions or ride mechanics/experience descriptions.
           - "not_ridden": the reviewer explicitly states they have not ridden the coaster (e.g. "I never got to ride this"). Do not infer this from tone, brevity, appearance-only comments, or anticipation of a future visit — a terse or exterior-only comment is still a real (if thin) opinion, not evidence the reviewer skipped the ride. When it's ambiguous, do not flag.
           - "other": clearly problematic but doesn't fit the categories above.
        3. Rate your confidence in the category as "low", "medium", or "high".
        4. If category is not "ok", give a one-sentence explanation in English for a human moderator. If category is "ok", explanation must be null.
        </task>
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
