<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\ReviewReport;
use PHPUnit\Framework\TestCase;

class ReviewReportTest extends TestCase
{
    public function testAiOnlyReasonsAreAccepted(): void
    {
        $report = new ReviewReport();

        foreach ([ReviewReport::REASON_TROLL, ReviewReport::REASON_TOXIC, ReviewReport::REASON_OFFTOPIC, ReviewReport::REASON_NOT_RIDDEN, ReviewReport::REASON_OTHER] as $reason) {
            $report->setReason($reason);
            $this->assertSame($reason, $report->getReason());
        }
    }

    public function testUserCanBeNullForSystemGeneratedReports(): void
    {
        $report = new ReviewReport();
        $report->setUser(null);

        $this->assertNull($report->getUser());
    }

    public function testAiConfidenceAndExplanationAreNullableAndSettable(): void
    {
        $report = new ReviewReport();

        $this->assertNull($report->getAiConfidence());
        $this->assertNull($report->getAiExplanation());

        $report->setAiConfidence('high');
        $report->setAiExplanation('Pure insult, no substance.');

        $this->assertSame('high', $report->getAiConfidence());
        $this->assertSame('Pure insult, no substance.', $report->getAiExplanation());
    }
}
