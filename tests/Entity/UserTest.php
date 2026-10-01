<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    /**
     * @param array<string> $submitted
     * @param list<string>  $expected
     */
    #[DataProvider('preferredReviewLanguages')]
    public function testPreferredReviewLanguagesAreStoredAsAList(array $submitted, array $expected): void
    {
        $user = new User()->setPreferredReviewLanguages($submitted);

        // assertSame compares keys too
        $this->assertSame($expected, $user->getPreferredReviewLanguages());
    }

    /** @return iterable<string, array{array<string>, list<string>}> */
    public static function preferredReviewLanguages(): iterable
    {
        yield 'one language kept' => [[1 => 'fr'], ['fr']];
        yield 'gap in the keys' => [[0 => 'en', 2 => 'es'], ['en', 'es']];
        yield 'already a list' => [['en', 'fr'], ['en', 'fr']];
        yield 'empty' => [[], []];
    }
}
