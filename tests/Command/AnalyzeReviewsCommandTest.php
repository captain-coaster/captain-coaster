<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\AnalyzeReviewsCommand;
use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use App\Message\AnalyzeReviewMessage;
use App\Repository\RiddenCoasterRepository;
use App\Service\Ai\AiTask;
use App\Service\Moderation\ReviewModerationService;
use App\Service\Moderation\ReviewRule;
use App\Service\Moderation\ReviewVerdict;
use App\Tests\AiStub;
use App\Tests\EntityId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class AnalyzeReviewsCommandTest extends TestCase
{
    private RiddenCoasterRepository&MockObject $riddenCoasterRepository;
    private ReviewModerationService&MockObject $moderationService;
    private MessageBusInterface&MockObject $messageBus;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->riddenCoasterRepository = $this->createMock(RiddenCoasterRepository::class);
        $this->moderationService = $this->createMock(ReviewModerationService::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $command = new AnalyzeReviewsCommand(
            $this->riddenCoasterRepository,
            $this->moderationService,
            $this->messageBus,
        );

        $application = new Application();
        $application->addCommand($command);

        $this->commandTester = new CommandTester($command);
    }

    private function createPersistedReview(int $id, string $text): RiddenCoaster
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        $review = new RiddenCoaster();
        $review->setCoaster($coaster);
        $review->setValue(3.0);
        $review->setReview($text);

        EntityId::set($review, $id);

        $user = EntityId::set(new User(), 1000 + $id);
        $user->setDisplayName('Jane Doe');
        $review->setUser($user);

        return $review;
    }

    public function testTextModePrintsTheRuleWithoutTouchingTheRepository(): void
    {
        $this->riddenCoasterRepository->expects($this->never())->method('findPendingAnalysis');
        $this->moderationService->expects($this->once())->method('analyze')
            ->willReturn(new ReviewVerdict('en', ReviewRule::Attack, 'Insults the staff.'));

        $this->commandTester->execute(['--text' => 'the operator is an idiot', '--rating' => '1']);

        $this->assertStringContainsString('attack (hide, en): Insults the staff.', $this->commandTester->getDisplay());
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testTextModeReportsAFailedCall(): void
    {
        $this->moderationService->method('analyze')->willThrowException(AiStub::failure(AiTask::ReviewModeration));

        $this->commandTester->execute(['--text' => 'a review']);

        $this->assertStringContainsString('ThrottlingException', $this->commandTester->getDisplay());
        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    public function testEvalCountsOutcomesAndListsDisagreements(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'golden');
        file_put_contents($file, "expected\trating\tcoaster\ttext\npublish\t5\tTaron\tGreat launch\nhide\t1\tTaron\t+1\npublish\t2\tTaron\tthe staff were brutes\n");

        $this->moderationService->method('analyze')->willReturnCallback(static fn (RiddenCoaster $review): ReviewVerdict => match ($review->getReview()) {
            '+1' => new ReviewVerdict('en', ReviewRule::NotAReview, 'Credit log.'),
            'the staff were brutes' => new ReviewVerdict('en', ReviewRule::Attack, 'Insults the staff.'),
            default => new ReviewVerdict('en', ReviewRule::None, null),
        });

        $this->commandTester->execute(['--eval' => $file]);
        unlink($file);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Wrongly hidden: 1. Missed hides: 0. Disagreements: 1 of 3.', $output);
        $this->assertStringContainsString('the staff were brutes', $output);
    }

    public function testDefaultModeSendsPendingReviewsToModeration(): void
    {
        $this->riddenCoasterRepository->expects($this->once())
            ->method('findPendingAnalysis')
            ->with(20)
            ->willReturn([$this->createPersistedReview(1, 'a'), $this->createPersistedReview(2, 'b')]);
        $this->moderationService->expects($this->never())->method('analyze');

        $dispatched = [];
        $this->messageBus->method('dispatch')->willReturnCallback(static function (object $message) use (&$dispatched): Envelope {
            $dispatched[] = $message;

            return new Envelope($message);
        });

        $this->commandTester->execute(['--limit' => 20]);

        $this->assertEquals([new AnalyzeReviewMessage(1), new AnalyzeReviewMessage(2)], $dispatched);
        $this->assertStringContainsString('Sent 2 review(s)', $this->commandTester->getDisplay());
    }
}
