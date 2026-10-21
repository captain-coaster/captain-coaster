<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\CompareSummaryModelsCommand;
use App\Entity\Coaster;
use App\Entity\CoasterSummary;
use App\Repository\CoasterRepository;
use App\Repository\CoasterSummaryRepository;
use App\Service\Ai\AiModel;
use App\Service\Ai\AiTask;
use App\Service\Summary\CoasterSummaryGenerator;
use App\Service\Summary\GeneratedSummary;
use App\Service\Summary\NotEnoughReviews;
use App\Tests\AiStub;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Previews only: the generator is never asked to record against the coaster.
 */
class CompareSummaryModelsCommandTest extends TestCase
{
    private CoasterRepository&MockObject $coasterRepository;
    private CoasterSummaryRepository&MockObject $coasterSummaryRepository;
    private CoasterSummaryGenerator&MockObject $generator;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->coasterRepository = $this->createMock(CoasterRepository::class);
        $this->coasterSummaryRepository = $this->createMock(CoasterSummaryRepository::class);
        $this->generator = $this->createMock(CoasterSummaryGenerator::class);

        $command = new CompareSummaryModelsCommand($this->coasterRepository, $this->coasterSummaryRepository, $this->generator);
        new Application()->addCommand($command);

        $this->commandTester = new CommandTester($command);
    }

    private function preview(int $reviewsAnalyzed = 50, int $reviewsSent = 50, AiModel $model = AiModel::Gpt6Luna): GeneratedSummary
    {
        return new GeneratedSummary('A preview summary.', 'Riders love it.', ['fast'], ['rough'], $reviewsAnalyzed, $reviewsSent, AiStub::result([], $model, 0.0005));
    }

    private function findsCoaster(?CoasterSummary $stored = null): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');
        $this->coasterRepository->method('find')->willReturn($coaster);
        $this->coasterSummaryRepository->method('findByCoasterAndLanguage')->willReturn($stored);
    }

    public function testCoasterNotFoundReturnsFailure(): void
    {
        $this->coasterRepository->method('find')->willReturn(null);

        $this->commandTester->execute(['coaster-id' => '999']);

        $this->assertStringContainsString('Coaster not found', $this->commandTester->getDisplay());
        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    public function testEveryPreviewIsGeneratedAsAPreview(): void
    {
        $this->findsCoaster();
        $this->generator->expects($this->exactly(2))->method('generate')
            ->with($this->anything(), 'en', $this->anything(), true)
            ->willReturn($this->preview());

        $this->commandTester->execute(['coaster-id' => '1']);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('A preview summary.', $this->commandTester->getDisplay());
    }

    public function testNoStoredSummaryShowsNote(): void
    {
        $this->findsCoaster();
        $this->generator->method('generate')->willReturn($this->preview());

        $this->commandTester->execute(['coaster-id' => '1']);

        $this->assertStringContainsString('No stored summary', $this->commandTester->getDisplay());
    }

    public function testStoredSummaryIsDisplayed(): void
    {
        $stored = new CoasterSummary();
        $stored->setSummary('Stored summary text.');
        $stored->setPositiveVotes(5);
        $stored->setNegativeVotes(1);
        $this->findsCoaster($stored);
        $this->generator->method('generate')->willReturn($this->preview());

        $this->commandTester->execute(['coaster-id' => '1']);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Stored summary text.', $output);
        $this->assertStringContainsString('👍 5', $output);
        $this->assertStringContainsString('👎 1', $output);
    }

    public function testNotEnoughReviewsShowsWarningInsteadOfSummary(): void
    {
        $this->findsCoaster();
        $this->generator->method('generate')->willThrowException(new NotEnoughReviews(5));

        $this->commandTester->execute(['coaster-id' => '1']);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('No preview generated', $output);
        $this->assertStringContainsString('only 5 reviews', $output);
    }

    public function testAFailedCallShowsWarning(): void
    {
        $this->findsCoaster();
        $this->generator->method('generate')->willThrowException(AiStub::failure(AiTask::CoasterSummary));

        $this->commandTester->execute(['coaster-id' => '1']);

        $this->assertStringContainsString('ThrottlingException', $this->commandTester->getDisplay());
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testBackfillIsShownInReviewsLabel(): void
    {
        $this->findsCoaster();
        $this->generator->method('generate')->willReturn($this->preview(30, 100));

        $this->commandTester->execute(['coaster-id' => '1', '--language' => 'fr']);

        $this->assertStringContainsString('30 same-language + 70 backfilled = 100 total', $this->commandTester->getDisplay());
    }

    /** @return iterable<string, array{array<string, string>}> */
    public static function unknownModelProvider(): iterable
    {
        yield 'candidate' => [['--candidate-model' => 'gpt-oss-120b']];
        yield 'baseline' => [['--baseline-model' => 'not-a-model']];
    }

    /** @param array<string, string> $options */
    #[DataProvider('unknownModelProvider')]
    public function testUnknownModelKeyFailsWithoutPreviewing(array $options): void
    {
        $this->coasterRepository->method('find')->willReturn(new Coaster());
        $this->generator->expects($this->never())->method('generate');

        $this->commandTester->execute(['coaster-id' => '1'] + $options);

        $this->assertSame(1, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Unknown model', $output);
        $this->assertStringContainsString('gpt-6-luna', $output);
    }

    public function testDefaultModelsAreTheTaskModelAndGpt6Luna(): void
    {
        $this->findsCoaster();

        $requested = [];
        $this->generator->method('generate')->willReturnCallback(function (Coaster $coaster, string $language, ?AiModel $model) use (&$requested): GeneratedSummary {
            $requested[] = $model;

            return $this->preview();
        });

        $this->commandTester->execute(['coaster-id' => '1']);

        $this->assertSame([AiTask::CoasterSummary->model(), AiModel::Gpt6Luna], $requested);
    }
}
