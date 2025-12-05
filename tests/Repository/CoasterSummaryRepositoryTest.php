<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Coaster;
use App\Entity\CoasterSummary;
use App\Repository\CoasterSummaryRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CoasterSummaryRepository multilingual query methods.
 */
class CoasterSummaryRepositoryTest extends TestCase
{
    private CoasterSummaryRepository&MockObject $repository;

    protected function setUp(): void
    {
        // This is a minimal test to verify method signatures and basic logic
        // Integration tests would require a test database
        $this->repository = $this->getMockBuilder(CoasterSummaryRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy', 'createQueryBuilder'])
            ->getMock();
    }

    public function testFindByCoasterAndLanguageMethodExists(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        $summary = new CoasterSummary();
        $summary->setCoaster($coaster);
        $summary->setLanguage('en');

        $this->repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['coaster' => $coaster, 'language' => 'en'])
            ->willReturn($summary);

        $result = $this->repository->findByCoasterAndLanguage($coaster, 'en');

        $this->assertInstanceOf(CoasterSummary::class, $result);
        $this->assertSame('en', $result->getLanguage());
    }

    public function testFindByCoasterAndLanguageReturnsNullWhenNotFound(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        $this->repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['coaster' => $coaster, 'language' => 'fr'])
            ->willReturn(null);

        $result = $this->repository->findByCoasterAndLanguage($coaster, 'fr');

        $this->assertNull($result);
    }
}

