<?php

declare(strict_types=1);

namespace App\Tests\Validator\Constraints;

use App\Validator\Constraints\BlockedWordList;
use PHPUnit\Framework\TestCase;

class BlockedWordListTest extends TestCase
{
    /** @return iterable<string, array{0: string}> */
    public static function blockedTextProvider(): iterable
    {
        yield 'fuck' => ['This ride is fuck ugly'];
        yield 'fucking as intensifier (still blocked, deliberate)' => ['This coaster is fucking amazing'];
        yield 'fucker' => ['What a fucker of a restraint'];
        yield 'fucked' => ['My back got fucked up'];
        yield 'putain positive tone (still blocked, deliberate)' => ['Putain que ce coaster est genial'];
        yield 'goddamn' => ['This is a goddamn good ride'];
        yield 'goddamned' => ['The goddamned queue was endless'];
        yield 'bitch' => ['Stop being a bitch about the wait time'];
        yield 'bitches' => ['These operators are bitches'];
        yield 'connard' => ["tu es qu'un connard elias"];
        yield 'connarde' => ['quelle connarde'];
        yield 'connards' => ['bande de connards'];
        yield 'asshole' => ['The designer is an asshole'];
        yield 'assholes' => ['They are all assholes'];
        yield 'salope' => ['Sale salope'];
        yield 'uppercase FUCK' => ['FUCK this ride is bad'];
        yield 'mixed case PuTaIn' => ['PuTaIn ce virage'];
    }

    /** @dataProvider blockedTextProvider */
    public function testMatchesReturnsTrueForBlockedWords(string $text): void
    {
        $this->assertTrue(BlockedWordList::matches($text));
    }

    /** @return iterable<string, array{0: string}> */
    public static function allowedTextProvider(): iterable
    {
        yield 'shit' => ['This ride is shit'];
        yield 'damn' => ['Damn that drop is intense'];
        yield 'hell' => ['What the hell was that'];
        yield 'ass' => ['My ass hurts after this ride'];
        yield 'crap' => ['This queue is crap'];
        yield 'merde' => ["c'est de la merde"];
        yield 'merdique' => ['un coaster merdique'];
        yield 'bordel' => ['quel bordel cette file'];
        yield 'clean english review' => ['This coaster is absolutely amazing, best drop ever'];
        yield 'clean french review' => ['Ce coaster est vraiment incroyable, la meilleure chute'];
        yield 'empty string' => [''];
    }

    /** @dataProvider allowedTextProvider */
    public function testMatchesReturnsFalseForAllowedWords(string $text): void
    {
        $this->assertFalse(BlockedWordList::matches($text));
    }

    /** @return iterable<string, array{0: string}> */
    public static function falsePositiveTrapProvider(): iterable
    {
        yield 'french "en retard" (late), not the ableist slur' => ['La théma est en retard sur tout le reste du parc'];
        yield 'spanish "con" (with), substring of connard-style words' => ['Atracción vertiginosa, con un lanzamiento potente'];
        yield 'english "con" as in pros and cons' => ['The biggest con of this coaster is the rattle'];
        yield 'french "salopette" (dungarees), starts with salope' => ["j'ai mis ma salopette pour la piscine"];
    }

    /** @dataProvider falsePositiveTrapProvider */
    public function testMatchesReturnsFalseForKnownFalsePositiveTraps(string $text): void
    {
        $this->assertFalse(BlockedWordList::matches($text));
    }

    /**
     * Regression test for the whole-branch review finding: preg_match()
     * with the /u modifier returns false (not 0) on malformed UTF-8, and
     * `1 === preg_match(...)` treats false the same as "no match" — a
     * silent bypass. Malformed UTF-8 must fail closed (be treated as
     * blocked) instead.
     */
    public function testMatchesReturnsTrueForMalformedUtf8(): void
    {
        // Lone continuation byte (0x80): invalid as a standalone UTF-8
        // sequence, guaranteed to make preg_match() return false under /u.
        $malformed = "This ride is great \x80\xFF";

        $this->assertTrue(BlockedWordList::matches($malformed));
    }
}
