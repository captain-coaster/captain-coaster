<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\PromptNameSanitizer;
use PHPUnit\Framework\TestCase;

class PromptNameSanitizerTest extends TestCase
{
    public function testPreservesAccentedCharacters(): void
    {
        $this->assertSame('Titánide', PromptNameSanitizer::sanitize('Titánide'));
    }

    public function testStripsInjectionCharacters(): void
    {
        $sanitized = PromptNameSanitizer::sanitize('Test<script>alert("xss")</script>Coaster');

        $this->assertSame('TestscriptalertxssscriptCoaster', $sanitized);
    }

    public function testFallsBackToOriginalStringOnInvalidUtf8(): void
    {
        // preg_replace with the /u modifier returns null (not the input) on malformed
        // UTF-8, instead of throwing - this must not silently blank the name.
        $invalidUtf8 = "Vol\xB1ge";

        $this->assertSame($invalidUtf8, PromptNameSanitizer::sanitize($invalidUtf8));
    }
}
