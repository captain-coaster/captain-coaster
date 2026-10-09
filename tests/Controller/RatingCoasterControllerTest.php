<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\RatingCoasterController;
use App\DTO\PartialDate;
use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use App\Repository\RiddenCoasterRepository;
use App\Validator\Constraints\ValidRideDateValidator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\ContainerConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RatingCoasterControllerTest extends TestCase
{
    private RatingCoasterController $controller;
    private User $user;
    private Coaster $coaster;
    private RiddenCoasterRepository&MockObject $repository;
    private EntityManagerInterface&MockObject $em;
    private CsrfTokenManagerInterface&MockObject $csrf;
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->user = new User();
        $this->coaster = new Coaster();

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($this->user, 'main'));
        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);

        $this->controller = new RatingCoasterController();
        $this->controller->setContainer($container);

        $this->repository = $this->createMock(RiddenCoasterRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $this->csrf->method('isTokenValid')->willReturn(true);

        // UniqueEntity needs a real EntityManager: stubbed, it is not under test.
        $noopValidator = new class extends ConstraintValidator {
            public function validate(mixed $value, Constraint $constraint): void
            {
            }
        };
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->setConstraintValidatorFactory(new ContainerConstraintValidatorFactory(new ServiceLocator([
                ValidRideDateValidator::class => static fn (): ValidRideDateValidator => new ValidRideDateValidator(),
                'doctrine.orm.validator.unique' => static fn () => $noopValidator,
            ])))
            ->getValidator();
    }

    public function testRatingChangeSucceedsWhenTheStoredRideDateIsInvalid(): void
    {
        $this->coaster->setClosing(PartialDate::fromString('2020-06-30'));
        $rating = $this->existingRating(new \DateTime('2023-08-15'));
        $this->em->expects($this->once())->method('flush');

        $response = $this->edit(['value' => '4.5']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(4.5, $rating->getValue());
    }

    public function testAddTodayLeavesTheDateEmptyOnAClosedCoaster(): void
    {
        $this->coaster->setClosing(PartialDate::fromString('2020-06-30'));
        $this->user->setAddTodayDateWhenRating(true);
        $persisted = $this->expectPersistedRating();

        $response = $this->edit(['value' => '4']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull($persisted()->getRiddenAt());
    }

    public function testAddTodaySetsTheDateOnAnOperatingCoaster(): void
    {
        $this->user->setAddTodayDateWhenRating(true);
        $persisted = $this->expectPersistedRating();

        $this->edit(['value' => '4']);

        $this->assertSame(date('Y-m-d'), $persisted()->getRiddenAt()?->format('Y-m-d'));
    }

    public function testAnInvalidRideDateIsRefused(): void
    {
        $this->coaster->setClosing(PartialDate::fromString('2020-06-30'));
        $this->existingRating(null);
        $this->em->expects($this->never())->method('flush');

        $response = $this->edit(['riddenAt' => '2023-08-15']);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('ride_date.after_closing', $this->decode($response)['message']);
    }

    public function testAnUnparsableRideDateIsRefused(): void
    {
        $this->existingRating(null);
        $this->em->expects($this->never())->method('flush');

        $response = $this->edit(['riddenAt' => 'not a date']);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('ride_date.invalid', $this->decode($response)['message']);
    }

    /** An offset would pass the future check as an instant and store the next day. */
    public function testARideDateWithATimeOrAnOffsetIsRefused(): void
    {
        $this->existingRating(null);
        $this->em->expects($this->never())->method('flush');

        $response = $this->edit(['riddenAt' => date('Y-m-d', strtotime('+1 day')).'T00:00:00+14:00']);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function testAddTodaySetsTheDateOnTheClosingDay(): void
    {
        $this->coaster->setClosing(PartialDate::fromString(date('Y-m-d')));
        $this->user->setAddTodayDateWhenRating(true);
        $persisted = $this->expectPersistedRating();

        $this->edit(['value' => '4']);

        $this->assertSame(date('Y-m-d'), $persisted()->getRiddenAt()?->format('Y-m-d'));
    }

    public function testAnInvalidRatingValueIsRefused(): void
    {
        $this->existingRating(null);
        $this->em->expects($this->never())->method('flush');

        $response = $this->edit(['value' => '7']);

        $this->assertSame(422, $response->getStatusCode());
    }

    private function existingRating(?\DateTime $riddenAt): RiddenCoaster
    {
        $rating = new RiddenCoaster();
        $rating->setUser($this->user);
        $rating->setCoaster($this->coaster);
        $rating->setValue(3.0);
        $rating->setRiddenAt($riddenAt);
        self::setId($rating);
        $this->repository->method('findOneBy')->willReturn($rating);

        return $rating;
    }

    /** @return \Closure(): RiddenCoaster */
    private function expectPersistedRating(): \Closure
    {
        $persisted = null;
        $this->repository->method('findOneBy')->willReturn(null);
        $this->em->expects($this->once())->method('persist')
            ->willReturnCallback(function (RiddenCoaster $rating) use (&$persisted): void {
                $persisted = $rating;
                self::setId($rating);
            });

        return static function () use (&$persisted): RiddenCoaster {
            self::assertInstanceOf(RiddenCoaster::class, $persisted);

            return $persisted;
        };
    }

    private static function setId(RiddenCoaster $rating): void
    {
        new \ReflectionProperty(RiddenCoaster::class, 'id')->setValue($rating, 1);
    }

    /** @param array<string, string> $data */
    private function edit(array $data): JsonResponse
    {
        $request = new Request(request: $data + ['_token' => 'token']);

        return $this->controller->editAction($request, $this->coaster, $this->em, $this->repository, $this->validator, $this->csrf, new IdentityTranslator());
    }

    /** @return array<string, mixed> */
    private function decode(JsonResponse $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
