<?php

declare(strict_types=1);

namespace App\Tests\Config;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/** A vocabulary code missing from a locale silently shows the English label there. */
class DatabaseTranslationsTest extends TestCase
{
    #[DataProvider('locales')]
    public function testLocaleHasTheSameKeysAsEnglish(string $locale): void
    {
        $this->assertEqualsCanonicalizing(
            array_keys($this->catalog('en')),
            array_keys($this->catalog($locale)),
        );
    }

    /** @return iterable<string, array{string}> */
    public static function locales(): iterable
    {
        yield 'fr' => ['fr'];
        yield 'es' => ['es'];
        yield 'de' => ['de'];
    }

    /** @return array<string, string> */
    private function catalog(string $locale): array
    {
        return Yaml::parseFile(\sprintf('%s/translations/database+intl-icu.%s.yml', \dirname(__DIR__, 2), $locale));
    }
}
