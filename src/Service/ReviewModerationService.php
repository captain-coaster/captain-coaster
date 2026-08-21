<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\RiddenCoaster;
use Psr\Log\LoggerInterface;

/**
 * Analyzes a single review for moderation flags (toxicity/spam/troll/off-topic)
 * and detects its language, via a single Bedrock call.
 */
class ReviewModerationService
{
    private const MODEL_KEY = 'gpt-5.6-luna';
    private const MAX_TOKENS = 500;

    private const VALID_CATEGORIES = ['ok', 'toxic', 'spam', 'troll', 'offtopic', 'not_ridden', 'other'];
    private const VALID_CONFIDENCE_LEVELS = ['low', 'medium', 'high'];

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
     * @return array{language: string, category: string, confidence: ?string, explanation: ?string}|null
     *                                                                                                   null means the caller should skip this review and let the next cron run retry it
     */
    public function analyze(RiddenCoaster $review): ?array
    {
        $prompt = $this->buildPrompt($review);
        $response = $this->bedrockService->invokeModel($prompt, self::MODEL_KEY, self::MAX_TOKENS, 0.3);

        if (!$response['success']) {
            $this->moderationLogger->error('Review moderation Bedrock call failed', [
                'review_id' => $this->reviewIdForLogging($review),
                'error' => $response['error'] ?? 'Unknown error',
                'error_code' => $response['error_code'] ?? null,
                'metadata' => $response['metadata'],
            ]);

            return null;
        }

        $parsed = $this->parseResponse($response['content'] ?? '');

        if (null === $parsed) {
            $this->moderationLogger->warning('Review moderation response could not be parsed', [
                'review_id' => $this->reviewIdForLogging($review),
                'response_content' => substr($response['content'] ?? '', 0, 500),
                'metadata' => $response['metadata'],
            ]);
        }

        return $parsed;
    }

    private function buildPrompt(RiddenCoaster $review): string
    {
        // \w alone is ASCII-only and silently mangles accented coaster names - \p{L}/\p{N}
        // with /u keep any Unicode letter/digit.
        $sanitizedCoasterName = preg_replace('/[^\p{L}\p{N}\s\-]/u', '', $review->getCoaster()->getName());

        $prompt = "You are a content moderator for a roller coaster review website. Analyze the following review and respond with strict JSON only.\n\n";
        $prompt .= "<review>\n";
        $prompt .= "Coaster: {$sanitizedCoasterName}\n";
        $prompt .= "Rating: {$review->getValue()}/5\n";
        $prompt .= "Text: {$review->getReview()}\n";
        $prompt .= "</review>\n\n";
        $prompt .= "<task>\n";
        $prompt .= "1. Detect the language of the review text and return its ISO 639-1 code (e.g. \"en\", \"fr\", \"ja\", \"sv\").\n";
        $prompt .= "2. Classify the review into exactly one category:\n";
        $prompt .= "   - \"ok\": on-topic opinion about the ride itself — any ride characteristic: sensations, look, operations, etc. Light profanity (e.g. \"crap\", \"sucks\", \"shit\", \"damn\") is fine as part of a genuine review.\n";
        $prompt .= "   - \"toxic\": hostility or insults aimed at people (staff, management, reviewers) — never the ride itself. A blunt or harshly negative opinion about the ride (e.g. \"Horrendous\", \"this thing is a disaster, no interest at all\") is \"ok\", not toxic, as long as it targets no person and contains no real profanity — bluntness or brevity alone is not toxicity. Light profanity is only ok as part of a genuine on-topic review of the ride — without real ride content behind it, light profanity is toxic. Heavy profanity (e.g. \"fuck\"/\"fucking\", slurs, graphic/extreme language) is always toxic, regardless of context.\n";
        $prompt .= "   - \"spam\": promotional content, gibberish, or repeated/copy-pasted text.\n";
        $prompt .= "   - \"troll\": bad-faith, disruptive, or nonsensical content with no genuine opinion behind it. Jokes and exaggerated humor are \"ok\", NOT troll, if they express a real (if hyperbolic) sentiment about the ride — e.g. \"life is short, let's not make it shorter by riding this\" genuinely means the ride feels intense/scary, so classify it \"ok\". Only use troll when there is no real view behind the text, or it is meant to disrupt/mislead.\n";
        $prompt .= "   - \"offtopic\": not about the ride experience or general park experience. Includes: formal complaints or legal/financial grievances with management, incidents unrelated to the normal ride experience (e.g. a fall, an altercation with other guests, an evacuation). Does NOT include physical reactions to the ride itself — aches, bruises, motion sickness, fear — even when described as an injury or in strong language; those are core review content, not offtopic. Also does not include staff/service quality opinions or ride mechanics/experience descriptions.\n";
        $prompt .= "   - \"not_ridden\": the reviewer explicitly states they have not ridden the coaster (e.g. \"I never got to ride this\"). Do not infer this from tone, brevity, appearance-only comments, or anticipation of a future visit — a terse or exterior-only comment is still a real (if thin) opinion, not evidence the reviewer skipped the ride. When it's ambiguous, do not flag.\n";
        $prompt .= "   - \"other\": clearly problematic but doesn't fit the categories above.\n";
        $prompt .= "3. Rate your confidence in the category as \"low\", \"medium\", or \"high\".\n";
        $prompt .= "4. If category is not \"ok\", give a one-sentence explanation in English for a human moderator. If category is \"ok\", explanation must be null.\n";
        $prompt .= "</task>\n\n";
        $prompt .= "<output_format>\n";
        $prompt .= "Respond with valid JSON in this exact format:\n";
        $prompt .= "{\n";
        $prompt .= "  \"language\": \"en\",\n";
        $prompt .= "  \"category\": \"ok\",\n";
        $prompt .= "  \"confidence\": \"high\",\n";
        $prompt .= "  \"explanation\": null\n";
        $prompt .= "}\n";
        $prompt .= "Ensure your response is valid JSON that can be parsed directly.\n";
        $prompt .= '</output_format>';

        return $prompt;
    }

    /** @return array{language: string, category: string, confidence: ?string, explanation: ?string}|null */
    private function parseResponse(string $response): ?array
    {
        try {
            $cleaned = preg_replace('/<reasoning>.*?<\/reasoning>\s*/s', '', $response);
            $cleaned = trim($cleaned);

            if (!preg_match('/\{.*\}/s', $cleaned, $matches)) {
                return null;
            }

            $json = json_decode($matches[0], true, 10, \JSON_THROW_ON_ERROR);

            if (!\is_array($json) || !isset($json['language'], $json['category']) || !\is_string($json['language']) || !\is_string($json['category'])) {
                return null;
            }

            if (!\in_array($json['category'], self::VALID_CATEGORIES, true)) {
                return null;
            }

            $confidence = $json['confidence'] ?? null;
            if (!\is_string($confidence) || !\in_array($confidence, self::VALID_CONFIDENCE_LEVELS, true)) {
                $confidence = null;
            }

            $language = strtolower(trim($json['language']));
            if (!preg_match('/^[a-z]{2}$/', $language)) {
                return null;
            }

            $explanation = $json['explanation'] ?? null;

            return [
                'language' => $language,
                'category' => $json['category'],
                'confidence' => $confidence,
                'explanation' => \is_string($explanation) ? trim($explanation) : null,
            ];
        } catch (\JsonException) {
            return null;
        }
    }
}
