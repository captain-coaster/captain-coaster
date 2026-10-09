<?php

declare(strict_types=1);

namespace App\Tests\Config;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/** A key missing from a locale silently shows the English string, or the raw key, there. */
class TranslationKeysTest extends TestCase
{
    private const array LOCALES = ['en', 'fr', 'es', 'de'];
    // Legal pages, written in English and French only
    private const array PARTIAL_DOMAINS = ['policy' => ['en', 'fr']];

    #[DataProvider('catalogs')]
    public function testLocaleHasTheSameKeysAsEnglish(string $domain, string $locale): void
    {
        $english = $this->keys($domain, 'en');
        $keys = $this->keys($domain, $locale);

        $this->assertSame([], array_values(array_diff($english, $keys)), 'Keys missing from this locale');
        $this->assertSame([], array_values(array_diff($keys, $english)), 'Keys missing from English');
    }

    /** @return iterable<string, array{string, string}> */
    public static function catalogs(): iterable
    {
        foreach (self::domains() as $domain) {
            foreach (self::PARTIAL_DOMAINS[$domain] ?? self::LOCALES as $locale) {
                if ('en' !== $locale) {
                    yield $domain.'.'.$locale => [$domain, $locale];
                }
            }
        }
    }

    public function testEveryCatalogBelongsToAKnownDomainAndLocale(): void
    {
        $expected = [];
        foreach (self::domains() as $domain) {
            foreach (self::PARTIAL_DOMAINS[$domain] ?? self::LOCALES as $locale) {
                $expected[] = \sprintf('%s+intl-icu.%s.yml', $domain, $locale);
            }
        }

        $this->assertEqualsCanonicalizing($expected, array_map(basename(...), self::files()));
    }

    /** @return list<string> */
    private static function domains(): array
    {
        return array_values(array_unique(array_map(
            static fn (string $file): string => explode('+', basename($file))[0],
            self::files(),
        )));
    }

    /** @return list<string> */
    private static function files(): array
    {
        return glob(\dirname(__DIR__, 2).'/translations/*.yml') ?: [];
    }

    /** @return list<string> dotted keys */
    private function keys(string $domain, string $locale): array
    {
        return self::flatten(Yaml::parseFile(\sprintf('%s/translations/%s+intl-icu.%s.yml', \dirname(__DIR__, 2), $domain, $locale)) ?? []);
    }

    /**
     * @param array<string, mixed> $catalog
     *
     * @return list<string>
     */
    private static function flatten(array $catalog, string $prefix = ''): array
    {
        $keys = [];
        foreach ($catalog as $key => $value) {
            if (\is_array($value)) {
                array_push($keys, ...self::flatten($value, $prefix.$key.'.'));
            } else {
                $keys[] = $prefix.$key;
            }
        }

        return $keys;
    }
}
