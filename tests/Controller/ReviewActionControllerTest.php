<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\ReviewActionController;
use App\Entity\Coaster;
use App\Entity\ReviewReport;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use App\Repository\ReviewReportRepository;
use App\Repository\ReviewUpvoteRepository;
use App\Service\ReviewScoreService;
use App\Tests\EntityId;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Translation\IdentityTranslator;

class ReviewActionControllerTest extends TestCase
{
    private ReviewActionController $controller;
    private EntityManagerInterface&MockObject $entityManager;

    protected function setUp(): void
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken(EntityId::set(new User(), 1), 'main'));
        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);

        $reportRepository = $this->createMock(ReviewReportRepository::class);
        $reportRepository->method('hasUserReported')->willReturn(false);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->controller = new ReviewActionController(
            $this->entityManager,
            $this->createMock(ReviewUpvoteRepository::class),
            $reportRepository,
            $this->createMock(ReviewScoreService::class),
            new IdentityTranslator(),
        );
        $this->controller->setContainer($container);
    }

    public function testAReportKeepsTheReportersCommentTrimmed(): void
    {
        $persisted = null;
        $this->entityManager->expects($this->once())->method('persist')->willReturnCallback(static function (object $report) use (&$persisted): void {
            $persisted = $report;
        });

        $response = $this->controller->reportAction($this->request('  Copied from the park website  '), $this->review());

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertInstanceOf(ReviewReport::class, $persisted);
        $this->assertSame('Copied from the park website', $persisted->getReporterComment());
    }

    public function testALineBreakCountsAsOneCharacterAsInTheBrowser(): void
    {
        $this->entityManager->expects($this->once())->method('persist');

        $comment = str_repeat("ab\r\n", 150);
        $response = $this->controller->reportAction($this->request($comment), $this->review());

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testAReportWithoutAUsefulCommentIsRefused(): void
    {
        $this->entityManager->expects($this->never())->method('persist');

        foreach (['', '   bad    ', str_repeat('a', ReviewActionController::REPORT_COMMENT_MAX + 1)] as $comment) {
            $response = $this->controller->reportAction($this->request($comment), $this->review());

            $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
            $this->assertStringContainsString('review.report_comment_invalid', (string) $response->getContent());
        }
    }

    private function request(string $comment): Request
    {
        return new Request(request: ['reason' => ReviewReport::REASON_SPAM, 'comment' => $comment]);
    }

    private function review(): RiddenCoaster
    {
        $review = new RiddenCoaster()
            ->setCoaster(new Coaster()->setName('Taron'))
            ->setUser(EntityId::set(new User(), 2)->setDisplayName('Jane'))
            ->setValue(1.0)
            ->setReview('Great')
            ->setReviewEnabled(true);

        return EntityId::set($review, 5);
    }
}
