<?php

declare(strict_types=1);

namespace App\Tests\Service\Summary;

use App\DTO\PartialDate;
use App\Entity\Coaster;
use App\Entity\Manufacturer;
use App\Entity\Park;
use App\Entity\RiddenCoaster;
use App\Entity\Status;
use App\Enum\DatePrecision;
use App\Service\Summary\SummaryPrompt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SummaryPromptTest extends TestCase
{
    /** The fixed text goes in the instructions, so it stays a cacheable prefix across coasters. */
    public function testFixedInstructionsAreNotRepeatedInTheInput(): void
    {
        $input = SummaryPrompt::input($this->coaster('Test Coaster'), [$this->review('Great ride')], 'en');

        $this->assertStringContainsString('You are an expert roller coaster analyst', SummaryPrompt::instructions('en'));
        $this->assertStringNotContainsString('You are an expert roller coaster analyst', $input);
    }

    #[DataProvider('provideLanguages')]
    public function testInstructionsAskForTheLanguage(string $language, string $name): void
    {
        $this->assertStringContainsString('Write everything in natural, fluent '.$name, SummaryPrompt::instructions($language));
        $this->assertStringContainsString('respond in '.$name, SummaryPrompt::input($this->coaster('Test Coaster'), [], $language));
    }

    /** @return iterable<string, array{string, string}> */
    public static function provideLanguages(): iterable
    {
        yield 'en' => ['en', 'English'];
        yield 'fr' => ['fr', 'French'];
        yield 'es' => ['es', 'Spanish'];
        yield 'de' => ['de', 'German'];
    }

    #[DataProvider('provideCoasterContexts')]
    public function testInputCarriesTheCoasterContextAndEveryReview(string $status, string $averageRating, int $totalRatings, string $expectedRating): void
    {
        $coaster = $this->coaster('Test Coaster');
        $coaster->setAverageRating($averageRating);
        $coaster->setTotalRatings($totalRatings);
        $coaster->setStatus(new Status()->setName($status));
        $reviews = [$this->review('Incredible experience!', 5.0), $this->review('Too rough for my taste', 2.0), $this->review('Good but not great', 3.0)];

        $input = SummaryPrompt::input($coaster, $reviews, 'en');

        $this->assertMatchesRegularExpression('#<coaster_context>.*Status: '.preg_quote($status, '#').'.*</coaster_context>#s', $input);
        $this->assertStringContainsString($expectedRating, $input);
        $this->assertStringContainsString('- Positive (4-5 stars): 33.3%', $input);
        $this->assertStringContainsString('- Neutral (3 stars): 33.3%', $input);
        $this->assertStringContainsString('- Negative (1-2 stars): 33.3%', $input);
        $this->assertMatchesRegularExpression('#<review_data>.*Incredible experience!.*Too rough for my taste.*Good but not great.*</review_data>#s', $input);
    }

    /** @return iterable<string, array{string, string, int, string}> */
    public static function provideCoasterContexts(): iterable
    {
        yield 'operating, top rated' => ['Operating', '5', 500, 'Community Rating: 100% based on 500 ratings'];
        yield 'closed, lowest rated' => ['Closed', '1', 20, 'Community Rating: 20% based on 20 ratings'];
        yield 'standing but not operating, decimal rating' => ['SBNO', '4.33', 137, 'Community Rating: 86.6% based on 137 ratings'];
    }

    public function testReviewsAreSentWithoutTheirRatings(): void
    {
        $input = SummaryPrompt::input($this->coaster('Test Coaster'), [$this->review('Incredible experience!', 9.5)], 'en');

        $this->assertStringContainsString('Incredible experience!', $input);
        $this->assertStringNotContainsString('9.5', $input);
    }

    public function testCoasterWithoutStatusOrRatingsHasNeitherBlock(): void
    {
        $input = SummaryPrompt::input($this->coaster('Test Coaster'), [$this->review('Decent ride')], 'en');

        $this->assertStringNotContainsString('Status:', $input);
        $this->assertStringNotContainsString('Community Rating:', $input);
        $this->assertStringNotContainsString('Rating Distribution:', $input);
        $this->assertStringContainsString('Decent ride', $input);
    }

    /** Park and manufacturer tell apart coasters sharing a name. */
    public function testInputNamesTheParkAndManufacturer(): void
    {
        $coaster = $this->coaster('Iron Rattler');
        $coaster->setPark(new Park()->setName('Six Flags Qiddiya City'));
        $coaster->setManufacturer(new Manufacturer()->setName('Vekoma'));

        $input = SummaryPrompt::input($coaster, [], 'en');

        $this->assertStringContainsString("Coaster: Iron Rattler\nPark: Six Flags Qiddiya City\nManufacturer: Vekoma\n", $input);
    }

    public function testCoasterNameIsSanitizedAgainstInjection(): void
    {
        $input = SummaryPrompt::input($this->coaster('Test<script>alert("xss")</script>Coaster'), [], 'en');

        $this->assertStringContainsString('TestscriptalertxssscriptCoaster', $input);
        $this->assertStringNotContainsString('<script>', $input);
        $this->assertStringNotContainsString('alert("xss")', $input);
    }

    public function testAccentedCoasterNamesAreKept(): void
    {
        $input = SummaryPrompt::input($this->coaster('Titánide'), [], 'fr');

        $this->assertStringContainsString('Titánide', $input);
    }

    /** A gone coaster is flagged with its closing year, so its successor's reviews are left out. */
    public function testGoneCoasterIsFlaggedWithItsClosingYear(): void
    {
        $coaster = $this->coaster('Eurosat');
        $coaster->setStatus(new Status()->setName('Retracked')->setCode(Status::RETRACKED));
        $coaster->setClosing(new PartialDate(new \DateTimeImmutable('2017-11-05'), DatePrecision::Day));

        $input = SummaryPrompt::input($coaster, [], 'en');

        $this->assertStringContainsString("Status: Retracked\nNo longer runs as reviewed: it closed in 2017\n", $input);
        $this->assertStringContainsString('in the past tense, without advice', $input);
    }

    /** Without a closing date the flag still holds, only the year is missing. */
    public function testGoneCoasterWithoutClosingDateIsFlagged(): void
    {
        $coaster = $this->coaster('Old Coaster');
        $coaster->setStatus(new Status()->setName('Definitely closed')->setCode(Status::CLOSED_DEFINITELY));

        $this->assertStringContainsString("No longer runs as reviewed: it closed\n", SummaryPrompt::input($coaster, [], 'en'));
        $this->assertStringContainsString('no longer runs as reviewed', SummaryPrompt::instructions('en'));
    }

    public function testStatusWithoutCodeIsNotTakenForGone(): void
    {
        $coaster = $this->coaster('Test Coaster');
        $coaster->setStatus(new Status()->setName('Unknown'));

        $this->assertStringNotContainsString('No longer runs', SummaryPrompt::input($coaster, [], 'en'));
    }

    public function testOperatingCoasterIsNotFlagged(): void
    {
        $coaster = $this->coaster('Test Coaster');
        $coaster->setStatus(new Status()->setName('Operating')->setCode(Status::OPERATING));

        $input = SummaryPrompt::input($coaster, [], 'en');

        $this->assertStringNotContainsString('No longer runs', $input);
        $this->assertStringContainsString('ending with advice for a first-time rider', $input);
    }

    /** Each review carries the year it was ridden, or the year it was logged when that is unknown. */
    public function testEachReviewStartsWithItsYear(): void
    {
        $ridden = $this->review('Ridden in July');
        $ridden->setRiddenAt(new \DateTime('2019-07-14'));
        $logged = $this->review('Logged in May');
        $logged->setCreatedAt(new \DateTime('2024-05-01'));

        $input = SummaryPrompt::input($this->coaster('Test Coaster'), [$ridden, $logged], 'en');

        $this->assertStringContainsString('[2019] Ridden in July', $input);
        $this->assertStringContainsString('[2024] Logged in May', $input);
    }

    private function coaster(string $name): Coaster
    {
        $coaster = new Coaster();
        $coaster->setName($name);

        return $coaster;
    }

    private function review(string $text, float $rating = 4.0): RiddenCoaster
    {
        $review = new RiddenCoaster();
        $review->setReview($text);
        $review->setValue($rating);

        return $review;
    }
}
