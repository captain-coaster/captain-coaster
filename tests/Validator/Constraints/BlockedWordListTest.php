<?php

declare(strict_types=1);

namespace App\Tests\Validator\Constraints;

use App\Validator\Constraints\BlockedWordList;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BlockedWordListTest extends TestCase
{
    /** @return iterable<array{string, string}> */
    public static function blocked(): iterable
    {
        yield ['That launch is fucking great', 'fucking'];
        yield ['what a f*cking mess', 'f*cking'];
        yield ['f**k this ride', 'f**k'];
        yield ['Fck max', 'Fck'];
        yield ['fvck yes', 'fvck'];
        yield ['a motherfucker of a drop', 'fucker'];
        yield ['Eeja-f*cking-naika', 'f*cking'];
        yield ['people b!tch about it', 'b!tch'];
        yield ['c**t', 'c**t'];
        yield ['tu te fais enculer', 'enculer'];
        yield ['nique ta mère', 'nique ta mère'];
        yield ['un connard', 'connard'];
        yield ['Una puta locura', 'puta'];
        yield ['hijo de puta', 'hijo de puta'];
        yield ['Was für ein Arschloch', 'Arschloch'];
    }

    #[DataProvider('blocked')]
    public function testHeavyProfanityIsFoundAsWritten(string $text, string $word): void
    {
        $this->assertSame($word, BlockedWordList::find($text));
    }

    /** @return iterable<array{string}> */
    public static function allowed(): iterable
    {
        // Everyday swearing the review policy publishes.
        yield ['putain c\'est génial'];
        yield ['c\'est de la merde'];
        yield ['this ride is shit'];
        yield ['quel bordel'];
        yield ['Scheiße'];
        yield ['joder'];
        yield ['a goddamn genius'];
        yield ['ça nique le dos'];
        // Innocent words that contain or look like one.
        yield ['les transitions sont niquel'];
        yield ['a salopette'];
        yield ['Scunthorpe'];
        yield ['a computer dispute'];
        yield ['it will bite you'];
        yield ['a fickle ride'];
        yield ['Det var roligt men jag fick ont'];
        yield ['un con'];
        // Symbols alone are not a word.
        yield ['of... well'];
        yield ['f** that'];
        yield [''];
    }

    #[DataProvider('allowed')]
    public function testEverydaySwearingAndLookalikesPass(string $text): void
    {
        $this->assertNull(BlockedWordList::find($text));
    }

    public function testMalformedUtf8IsRefused(): void
    {
        $this->assertNotNull(BlockedWordList::find("\xB1\x31"));
    }
}
