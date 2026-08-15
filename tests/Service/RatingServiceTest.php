<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\RiddenCoaster;
use App\Repository\RiddenCoasterRepository;
use App\Service\RatingService;
use App\Validator\Constraints\ValidRideDate;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RatingServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private RiddenCoasterRepository&MockObject $repo;
    private ValidatorInterface&MockObject $validator;
    private TranslatorInterface&MockObject $translator;
    private CacheItemPoolInterface&MockObject $cache;
    private RatingService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->repo = $this->createMock(RiddenCoasterRepository::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->service = new RatingService(
            $this->em, $this->repo, $this->validator, $this->translator, $this->cache
        );
    }

    public function testUpdateLastRiddenAtPersistsWhenValid(): void
    {
        $rc = new RiddenCoaster();
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        $this->em->expects($this->once())->method('flush');

        $date = new \DateTime('2026-05-28');
        $result = $this->service->updateLastRiddenAt($rc, $date);

        $this->assertNull($result);
        $this->assertEquals($date, $rc->getLastRiddenAt());
    }

    public function testUpdateLastRiddenAtReturnsErrorAndRefreshesWhenInvalid(): void
    {
        $rc = new RiddenCoaster();
        $violations = $this->createMock(ConstraintViolationListInterface::class);
        $violations->method('count')->willReturn(1);
        $violation = $this->createMock(\Symfony\Component\Validator\ConstraintViolationInterface::class);
        $violation->method('getMessage')->willReturn('ride_date.future');
        $violations->method('offsetGet')->with(0)->willReturn($violation);
        $this->validator->method('validate')->willReturn($violations);
        $this->translator->method('trans')->willReturn('Date is in the future');
        $this->em->expects($this->once())->method('refresh')->with($rc);
        $this->em->expects($this->never())->method('flush');

        $result = $this->service->updateLastRiddenAt($rc, new \DateTime('2999-01-01'));

        $this->assertSame('Date is in the future', $result);
    }

    public function testSetRideCountPersistsValidCount(): void
    {
        $rc = new RiddenCoaster();
        $rc->setLastRiddenAt(new \DateTime('2026-05-28'));
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->setRideCount($rc, 34);

        $this->assertNull($result);
        $this->assertSame(34, $rc->getRideCount());
        $this->assertNotNull($rc->getLastRiddenAt());
    }

    public function testSetRideCountOfOneClearsLastRiddenAt(): void
    {
        $rc = new RiddenCoaster();
        $rc->setLastRiddenAt(new \DateTime('2026-05-28'));
        $this->em->expects($this->once())->method('flush');

        $this->service->setRideCount($rc, 1);

        $this->assertSame(1, $rc->getRideCount());
        $this->assertNull($rc->getLastRiddenAt());
    }

    public function testSetRideCountRejectsBelowOne(): void
    {
        $rc = new RiddenCoaster();
        $rc->setRideCount(3);
        $this->translator->method('trans')->willReturn('Invalid count');
        $this->em->expects($this->never())->method('flush');

        $result = $this->service->setRideCount($rc, 0);

        $this->assertSame('Invalid count', $result);
        $this->assertSame(3, $rc->getRideCount());
    }
}
