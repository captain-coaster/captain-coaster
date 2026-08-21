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
        $invalidUtf8 = "Vol\xB1ge";

        $this->assertSame($invalidUtf8, PromptNameSanitizer::sanitize($invalidUtf8));
    }
}
