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

    public function __construct(
        private BedrockService $bedrockService,
        private LoggerInterface $logger
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
     *               null means the caller should skip this review and let the next cron run retry it
     */
    public function analyze(RiddenCoaster $review): ?array
    {
        $prompt = $this->buildPrompt($review);
        $response = $this->bedrockService->invokeModel($prompt, self::MODEL_KEY, self::MAX_TOKENS, 0.3);

        if (!$response['success']) {
            $this->logger->error('Review moderation Bedrock call failed', [
                'review_id' => $this->reviewIdForLogging($review),
                'error' => $response['error'] ?? 'Unknown error',
                'error_code' => $response['error_code'] ?? null,
                'metadata' => $response['metadata'],
            ]);

            return null;
        }

        $parsed = $this->parseResponse($response['content'] ?? '');

        if (null === $parsed) {
            $this->logger->warning('Review moderation response could not be parsed', [
                'review_id' => $this->reviewIdForLogging($review),
                'response_content' => substr($response['content'] ?? '', 0, 500),
                'metadata' => $response['metadata'],
            ]);
        }

        return $parsed;
    }

    private function buildPrompt(RiddenCoaster $review): string
    {
        $sanitizedCoasterName = preg_replace('/[^\w\s-]/', '', $review->getCoaster()->getName());

        $prompt = "You are a content moderator for a roller coaster review website. Analyze the following review and respond with strict JSON only.\n\n";
        $prompt .= "<review>\n";
        $prompt .= "Coaster: {$sanitizedCoasterName}\n";
        $prompt .= "Rating: {$review->getValue()}/5\n";
        $prompt .= "Text: {$review->getReview()}\n";
        $prompt .= "</review>\n\n";
        $prompt .= "<task>\n";
        $prompt .= "1. Detect the language of the review text and return its ISO 639-1 code (e.g. \"en\", \"fr\", \"ja\", \"sv\").\n";
        $prompt .= "2. Classify the review into exactly one category:\n";
        $prompt .= "   - \"ok\": on-topic, and if it contains mild profanity it also contains substantive opinion about the ride (e.g. \"the theming is crap but the airtime is amazing\" is \"ok\").\n";
        $prompt .= "   - \"toxic\": pure insults or hostility with no substantive content (e.g. \"i fucking hate this coaster\", \"this sucks\").\n";
        $prompt .= "   - \"spam\": promotional content, gibberish, or repeated/copy-pasted text.\n";
        $prompt .= "   - \"troll\": deliberately provocative, absurd, or bad-faith content, not a genuine review.\n";
        $prompt .= "   - \"offtopic\": not actually about the ride experience.\n";
        $prompt .= "   - \"not_ridden\": the reviewer indicates they never actually rode the coaster, either explicitly (states they couldn't ride, e.g. physical restriction or closure) or implicitly (only comments on appearance/anticipation, no actual ride experience described).\n";
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

    /**
     * @return array{language: string, category: string, confidence: ?string, explanation: ?string}|null
     */
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

            $explanation = $json['explanation'] ?? null;

            return [
                'language' => strtolower(trim($json['language'])),
                'category' => $json['category'],
                'confidence' => $confidence,
                'explanation' => \is_string($explanation) ? trim($explanation) : null,
            ];
        } catch (\JsonException) {
            return null;
        }
    }
}
