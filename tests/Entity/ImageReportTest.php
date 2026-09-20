<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\ImageReport;
use PHPUnit\Framework\TestCase;

class ImageReportTest extends TestCase
{
    public function testValidCategoriesAreAccepted(): void
    {
        $report = new ImageReport();
        $report->setCategories([ImageReport::CATEGORY_RETOUCHED, ImageReport::CATEGORY_PEOPLE_SUBJECT]);

        $this->assertSame([ImageReport::CATEGORY_RETOUCHED, ImageReport::CATEGORY_PEOPLE_SUBJECT], $report->getCategories());
    }

    public function testSettingAnUnknownCategoryThrows(): void
    {
        $report = new ImageReport();

        $this->expectException(\InvalidArgumentException::class);
        $report->setCategories(['not-a-real-category']);
    }

    public function testDefaultsToPendingAndUnresolved(): void
    {
        $report = new ImageReport();

        $this->assertSame(ImageReport::STATUS_PENDING, $report->getStatus());
        $this->assertFalse($report->isResolved());
        $this->assertNull($report->getResolvedAt());
    }

    public function testSettingANonPendingStatusMarksItResolvedAndStampsResolvedAt(): void
    {
        $report = new ImageReport();
        $report->setStatus(ImageReport::STATUS_APPROVED);

        $this->assertTrue($report->isResolved());
        $this->assertNotNull($report->getResolvedAt());
    }

    public function testSettingAnUnknownStatusThrows(): void
    {
        $report = new ImageReport();

        $this->expectException(\InvalidArgumentException::class);
        $report->setStatus('not-a-real-status');
    }

    public function testAiConfidenceAndExplanationAreNullableAndSettable(): void
    {
        $report = new ImageReport();

        $this->assertNull($report->getAiConfidence());
        $this->assertNull($report->getAiExplanation());

        $report->setAiConfidence('high');
        $report->setAiExplanation('Logo overlay in corner.');

        $this->assertSame('high', $report->getAiConfidence());
        $this->assertSame('Logo overlay in corner.', $report->getAiExplanation());
    }

    public function testImageIsNullableForWhenTheUnderlyingPhotoWasDeleted(): void
    {
        $report = new ImageReport();
        $report->setImage(null);

        $this->assertNull($report->getImage());
    }
}
