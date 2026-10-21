<?php

declare(strict_types=1);

namespace App\Tests\Service\Summary;

use App\Entity\Coaster;
use App\Entity\CoasterSummary;
use App\Repository\CoasterSummaryRepository;
use App\Repository\RiddenCoasterRepository;
use App\Service\Ai\AiCallFailed;
use App\Service\Ai\AiModel;
use App\Service\Ai\AiTask;
use App\Service\Summary\CoasterSummaryGenerator;
use App\Service\Summary\CoasterSummaryWriter;
use App\Service\Summary\GeneratedSummary;
use App\Service\Summary\NotEnoughReviews;
use App\Tests\AiStub;
use App\Tests\EntityId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CoasterSummaryWriterTest extends TestCase
{
    private CoasterSummaryGenerator&MockObject $generator;
    private CoasterSummaryRepository&MockObject $coasterSummaryRepository;
    private RiddenCoasterRepository&MockObject $riddenCoasterRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private CoasterSummaryWriter $writer;
    private Coaster $coaster;

    protected function setUp(): void
    {
        $this->generator = $this->createMock(CoasterSummaryGenerator::class);
        $this->coasterSummaryRepository = $this->createMock(CoasterSummaryRepository::class);
        $this->riddenCoasterRepository = $this->createMock(RiddenCoasterRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->writer = new CoasterSummaryWriter($this->generator, $this->coasterSummaryRepository, $this->riddenCoasterRepository, $this->entityManager);

        $this->coaster = new Coaster();
        $this->coaster->setName('Test Coaster');
    }

    public function testNewSummaryIsStoredWithTheModelThatWroteIt(): void
    {
        $this->generator->method('generate')->with($this->coaster, 'fr')->willReturn($this->generated(reviewsAnalyzed: 30, model: AiModel::Gpt6Luna));
        $this->coasterSummaryRepository->method('findOneBy')->with(['coaster' => $this->coaster, 'language' => 'fr'])->willReturn(null);
        $this->entityManager->expects($this->never())->method('createQueryBuilder');
        $stored = null;
        $this->entityManager->expects($this->once())->method('persist')->willReturnCallback(static function (CoasterSummary $summary) use (&$stored): void {
            $stored = $summary;
        });
        $this->entityManager->expects($this->once())->method('flush');
        $this->coasterSummaryRepository->expects($this->once())->method('clearCacheFor')->with($this->coaster, 'fr');

        $this->writer->write($this->coaster, 'fr');

        $this->assertInstanceOf(CoasterSummary::class, $stored);
        $this->assertSame($this->coaster, $stored->getCoaster());
        $this->assertSame('fr', $stored->getLanguage());
        $this->assertSame('New summary', $stored->getSummary());
        $this->assertSame('Riders love its airtime.', $stored->getMetaDescription());
        $this->assertSame(['Pro'], $stored->getDynamicPros());
        $this->assertSame(['Con'], $stored->getDynamicCons());
        $this->assertSame(30, $stored->getReviewsAnalyzed());
        $this->assertSame('gpt-6-luna', $stored->getAiModel());
    }

    /** Votes were about the previous text. */
    public function testRegeneratingResetsVotesAndClearsFeedback(): void
    {
        $existing = EntityId::set(new CoasterSummary(), 7);
        $existing->setCoaster($this->coaster);
        $existing->setLanguage('en');
        $existing->setSummary('Old summary');
        $existing->setPositiveVotes(5);
        $existing->setNegativeVotes(2);
        $existing->setFeedbackRatio(0.714);
        $this->generator->method('generate')->willReturn($this->generated());
        $this->coasterSummaryRepository->method('findOneBy')->willReturn($existing);

        $query = $this->createMock(Query::class);
        $query->expects($this->once())->method('execute');
        $builder = $this->createMock(QueryBuilder::class);
        $builder->method('delete')->willReturnSelf();
        $builder->method('where')->willReturnSelf();
        $builder->expects($this->once())->method('setParameter')->with('summary', 7)->willReturnSelf();
        $builder->method('getQuery')->willReturn($query);
        $this->entityManager->expects($this->once())->method('createQueryBuilder')->willReturn($builder);
        $this->entityManager->expects($this->once())->method('persist')->with($existing);
        $this->coasterSummaryRepository->expects($this->once())->method('clearCacheFor')->with($this->coaster, 'en');

        $this->writer->write($this->coaster, 'en');

        $this->assertSame('New summary', $existing->getSummary());
        $this->assertSame(0, $existing->getPositiveVotes());
        $this->assertSame(0, $existing->getNegativeVotes());
        $this->assertSame(0.0, $existing->getFeedbackRatio());
    }

    public function testNothingIsStoredWhenThereAreNotEnoughReviews(): void
    {
        $this->generator->method('generate')->willThrowException(new NotEnoughReviews(3));
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $this->expectException(NotEnoughReviews::class);

        $this->writer->write($this->coaster, 'en');
    }

    public function testNothingIsStoredWhenTheAiCallFails(): void
    {
        $this->generator->method('generate')->willThrowException(AiStub::failure(AiTask::CoasterSummary));
        $this->entityManager->expects($this->never())->method('persist');
        $this->coasterSummaryRepository->expects($this->never())->method('clearCacheFor');

        $this->expectException(AiCallFailed::class);

        $this->writer->write($this->coaster, 'en');
    }

    public function testDueWhenNoSummaryExistsYet(): void
    {
        $this->coasterSummaryRepository->method('findOneBy')->with(['coaster' => $this->coaster, 'language' => 'de'])->willReturn(null);

        $this->assertTrue($this->writer->isDue($this->coaster, 'de'));
    }

    public function testDueOnceTwentyPercentMoreReviewsCameWithAFloorOfTwenty(): void
    {
        $this->coasterSummaryRepository->method('findOneBy')->willReturn($this->summaryAnalyzing(200));
        $this->riddenCoasterRepository->method('countCoasterReviewsWithTextByLanguage')->willReturnOnConsecutiveCalls(239, 240);

        $this->assertFalse($this->writer->isDue($this->coaster, 'en'));
        $this->assertTrue($this->writer->isDue($this->coaster, 'en'));
    }

    public function testSmallSummariesNeedTwentyNewReviews(): void
    {
        $this->coasterSummaryRepository->method('findOneBy')->willReturn($this->summaryAnalyzing(25));
        $this->riddenCoasterRepository->method('countCoasterReviewsWithTextByLanguage')->willReturnOnConsecutiveCalls(44, 45);

        $this->assertFalse($this->writer->isDue($this->coaster, 'en'));
        $this->assertTrue($this->writer->isDue($this->coaster, 'en'));
    }

    private function summaryAnalyzing(int $reviewsAnalyzed): CoasterSummary
    {
        return new CoasterSummary()->setReviewsAnalyzed($reviewsAnalyzed);
    }

    private function generated(int $reviewsAnalyzed = 25, AiModel $model = AiModel::Gpt6Luna): GeneratedSummary
    {
        return new GeneratedSummary('New summary', 'Riders love its airtime.', ['Pro'], ['Con'], $reviewsAnalyzed, $reviewsAnalyzed, AiStub::result([], $model));
    }
}
