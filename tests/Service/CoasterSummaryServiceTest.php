<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Coaster;
use App\Entity\CoasterSummary;
use App\Entity\VocabularyGuide;
use App\Repository\RiddenCoasterRepository;
use App\Repository\VocabularyGuideRepository;
use App\Service\BedrockService;
use App\Service\CoasterSummaryService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for CoasterSummaryService translation functionality.
 */
class CoasterSummaryServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private RiddenCoasterRepository&MockObject $riddenCoasterRepository;
    private VocabularyGuideRepository&MockObject $vocabularyGuideRepository;
    private BedrockService&MockObject $bedrockService;
    private LoggerInterface&MockObject $logger;
    private CoasterSummaryService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->riddenCoasterRepository = $this->createMock(RiddenCoasterRepository::class);
        $this->vocabularyGuideRepository = $this->createMock(VocabularyGuideRepository::class);
        $this->bedrockService = $this->createMock(BedrockService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new CoasterSummaryService(
            $this->entityManager,
            $this->riddenCoasterRepository,
            $this->vocabularyGuideRepository,
            $this->bedrockService,
            $this->logger
        );
    }

    public function testTranslateSummaryRejectsNonEnglishSource(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        $sourceSummary = new CoasterSummary();
        $sourceSummary->setCoaster($coaster);
        $sourceSummary->setLanguage('fr'); // Non-English source
        $sourceSummary->setSummary('Test summary');

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Cannot translate non-English summary', $this->anything());

        $result = $this->service->translateSummary($sourceSummary, 'de');

        $this->assertNull($result['summary']);
        $this->assertSame('source_not_english', $result['reason']);
    }

    public function testTranslateSummaryRejectsMissingVocabularyGuide(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        $sourceSummary = new CoasterSummary();
        $sourceSummary->setCoaster($coaster);
        $sourceSummary->setLanguage('en');
        $sourceSummary->setSummary('Test summary');

        $this->vocabularyGuideRepository
            ->expects($this->once())
            ->method('findByLanguage')
            ->with('fr')
            ->willReturn(null);

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('No vocabulary guide for target language', $this->anything());

        $result = $this->service->translateSummary($sourceSummary, 'fr');

        $this->assertNull($result['summary']);
        $this->assertSame('missing_vocabulary_guide', $result['reason']);
    }

    public function testTranslateSummaryHandlesAiError(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        $sourceSummary = new CoasterSummary();
        $sourceSummary->setCoaster($coaster);
        $sourceSummary->setLanguage('en');
        $sourceSummary->setSummary('Test summary');
        $sourceSummary->setDynamicPros(['Great speed', 'Smooth ride']);
        $sourceSummary->setDynamicCons(['Long queue']);

        $vocabularyGuide = new VocabularyGuide();
        $vocabularyGuide->setLanguage('es');
        $vocabularyGuide->setContent('Spanish vocabulary guide');

        $this->vocabularyGuideRepository
            ->expects($this->once())
            ->method('findByLanguage')
            ->with('es')
            ->willReturn($vocabularyGuide);

        $this->riddenCoasterRepository
            ->expects($this->once())
            ->method('getCoasterReviewsWithTextByLanguage')
            ->with($coaster, 'es', 10)
            ->willReturn([]);

        $this->bedrockService
            ->expects($this->once())
            ->method('invokeModel')
            ->willReturn([
                'success' => false,
                'error' => 'API error',
                'metadata' => ['cost_usd' => 0.01],
            ]);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Translation failed', $this->anything());

        $result = $this->service->translateSummary($sourceSummary, 'es');

        $this->assertNull($result['summary']);
        $this->assertSame('ai_error', $result['reason']);
        $this->assertArrayHasKey('metadata', $result);
    }

    public function testTranslateSummarySuccessfullyCreatesTranslation(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        $sourceSummary = new CoasterSummary();
        $sourceSummary->setCoaster($coaster);
        $sourceSummary->setLanguage('en');
        $sourceSummary->setSummary('This is a great roller coaster.');
        $sourceSummary->setDynamicPros(['Great speed', 'Smooth ride']);
        $sourceSummary->setDynamicCons(['Long queue']);
        $sourceSummary->setReviewsAnalyzed(50);

        $vocabularyGuide = new VocabularyGuide();
        $vocabularyGuide->setLanguage('de');
        $vocabularyGuide->setContent('German vocabulary guide');

        $this->vocabularyGuideRepository
            ->expects($this->once())
            ->method('findByLanguage')
            ->with('de')
            ->willReturn($vocabularyGuide);

        $this->riddenCoasterRepository
            ->expects($this->once())
            ->method('getCoasterReviewsWithTextByLanguage')
            ->with($coaster, 'de', 10)
            ->willReturn([]);

        $aiResponse = json_encode([
            'summary' => 'Das ist eine großartige Achterbahn.',
            'pros' => ['Hohe Geschwindigkeit', 'Sanfte Fahrt'],
            'cons' => ['Lange Warteschlange'],
        ]);

        $this->bedrockService
            ->expects($this->once())
            ->method('invokeModel')
            ->willReturn([
                'success' => true,
                'content' => $aiResponse,
                'metadata' => ['cost_usd' => 0.02],
            ]);

        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['coaster' => $coaster, 'language' => 'de'])
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(CoasterSummary::class)
            ->willReturn($repository);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(CoasterSummary::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->translateSummary($sourceSummary, 'de');

        $this->assertInstanceOf(CoasterSummary::class, $result['summary']);
        $this->assertSame('de', $result['summary']->getLanguage());
        $this->assertSame('Das ist eine großartige Achterbahn.', $result['summary']->getSummary());
        $this->assertSame(['Hohe Geschwindigkeit', 'Sanfte Fahrt'], $result['summary']->getDynamicPros());
        $this->assertSame(['Lange Warteschlange'], $result['summary']->getDynamicCons());
        $this->assertSame(50, $result['summary']->getReviewsAnalyzed());
        $this->assertArrayHasKey('metadata', $result);
    }

    public function testShouldUpdateTranslationUsesCorrectLogic(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        // Mock repository to return null (no existing translation)
        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['coaster' => $coaster, 'language' => 'fr'])
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(CoasterSummary::class)
            ->willReturn($repository);

        $result = $this->service->shouldUpdateTranslation($coaster, 'fr');

        $this->assertTrue($result);
    }
}
