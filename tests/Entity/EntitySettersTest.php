<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Coaster;
use App\Entity\Launch;
use App\Entity\RiddenCoaster;
use App\Entity\Status;
use PHPUnit\Framework\TestCase;

class EntitySettersTest extends TestCase
{
    public function testLaunchSetters(): void
    {
        $launch = new Launch();
        
        $launch->setName('LSM Launch');
        $this->assertSame('LSM Launch', $launch->getName());
        
        $launch->setSlug('lsm-launch');
        $this->assertSame('lsm-launch', $launch->getSlug());
        
        // Test empty string values
        $launch->setName('');
        $this->assertSame('', $launch->getName());
    }

    public function testStatusSetters(): void
    {
        $status = new Status();
        
        $status->setName('Operating');
        $this->assertSame('Operating', $status->getName());
        
        $status->setSlug('operating');
        $this->assertSame('operating', $status->getSlug());
        
        $status->setType('open');
        $this->assertSame('open', $status->getType());
        
        // Test empty string values
        $status->setType('');
        $this->assertSame('', $status->getType());
    }

    public function testCoasterSetters(): void
    {
        $coaster = new Coaster();
        
        $coaster->setVideo('abc123');
        $this->assertSame('abc123', $coaster->getVideo());
        
        $coaster->setPrice(50);
        $this->assertSame(50, $coaster->getPrice());
        
        $coaster->setRank(1);
        $this->assertSame(1, $coaster->getRank());
        
        // Test null values
        $coaster->setVideo(null);
        $this->assertNull($coaster->getVideo());
        
        $coaster->setPrice(null);
        $this->assertNull($coaster->getPrice());
        
        $coaster->setRank(null);
        $this->assertNull($coaster->getRank());
    }

    public function testRiddenCoasterSetters(): void
    {
        $riddenCoaster = new RiddenCoaster();
        
        $riddenCoaster->setReview('Great ride!');
        $this->assertSame('Great ride!', $riddenCoaster->getReview());
        
        $riddenCoaster->setLanguage('en');
        $this->assertSame('en', $riddenCoaster->getLanguage());
        
        // Test null values
        $riddenCoaster->setReview(null);
        $this->assertNull($riddenCoaster->getReview());
    }
}
