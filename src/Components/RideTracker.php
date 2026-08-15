<?php

declare(strict_types=1);

namespace App\Components;

use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use App\Repository\RiddenCoasterRepository;
use App\Service\RatingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class RideTracker extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp]
    public int $coasterId;

    /** @var array<array{rating: float, count: int}> Community rating stats from getRatingStatsForCoaster(). */
    #[LiveProp]
    public array $countRatings = [];

    #[LiveProp(writable: true)]
    public bool $editingCount = false;

    /** Writable model props used to capture date/count inputs before submitting. */
    #[LiveProp(writable: true)]
    public string $firstDateInput = '';

    #[LiveProp(writable: true)]
    public string $lastDateInput = '';

    #[LiveProp(writable: true)]
    public int $rideCountInput = 1;

    /** Transient inline error to surface a validation message. */
    public ?string $error = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RatingService $ratingService,
        private readonly RiddenCoasterRepository $riddenCoasterRepository,
    ) {
    }

    public function getCoaster(): Coaster
    {
        $coaster = $this->em->getRepository(Coaster::class)->find($this->coasterId);
        if (!$coaster instanceof Coaster) {
            throw $this->createNotFoundException();
        }

        return $coaster;
    }

    public function getRiddenCoaster(): ?RiddenCoaster
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return null;
        }

        return $this->riddenCoasterRepository->findOneBy([
            'coaster' => $this->getCoaster(), 'user' => $user,
        ]);
    }

    public function isRidden(): bool
    {
        return null !== $this->getRiddenCoaster();
    }

    #[LiveAction]
    public function markRidden(): void
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->denyAccessUnlessGranted('rate', $this->getCoaster());
        $this->ratingService->markAsRidden($user, $this->getCoaster());
    }

    #[LiveAction]
    public function removeRidden(): void
    {
        $rc = $this->getRiddenCoaster();
        if (null !== $rc) {
            $this->denyAccessUnlessGranted('delete', $rc);
            $this->ratingService->removeRidden($rc);
        }
    }

    #[LiveAction]
    public function rate(#[LiveArg] float $value): void
    {
        if (!\in_array($value, RiddenCoaster::ALLOWED_RATINGS, true)) {
            return;
        }
        /** @var User $user */
        $user = $this->getUser();
        $this->denyAccessUnlessGranted('rate', $this->getCoaster());
        $this->ratingService->setRating($user, $this->getCoaster(), $value);
    }

    #[LiveAction]
    public function clearRating(): void
    {
        $rc = $this->requireOwnedRidden();
        if (null !== $rc) {
            $this->ratingService->clearRating($rc);
        }
    }

    #[LiveAction]
    public function addReride(): void
    {
        $rc = $this->requireOwnedRidden();
        if (null !== $rc) {
            $this->ratingService->addReride($rc);
        }
    }

    #[LiveAction]
    public function removeReride(): void
    {
        $rc = $this->requireOwnedRidden();
        if (null !== $rc && $rc->getRideCount() > 1) {
            $this->ratingService->setRideCount($rc, $rc->getRideCount() - 1);
        }
    }

    #[LiveAction]
    public function setFirstDate(): void
    {
        $rc = $this->requireOwnedRidden();
        if (null !== $rc) {
            $this->error = $this->ratingService->updateFirstRiddenAt($rc, $this->parseDate($this->firstDateInput));
        }
    }

    #[LiveAction]
    public function setLastDate(): void
    {
        $rc = $this->requireOwnedRidden();
        if (null !== $rc) {
            $this->error = $this->ratingService->updateLastRiddenAt($rc, $this->parseDate($this->lastDateInput));
        }
    }

    #[LiveAction]
    public function setRideCount(): void
    {
        $rc = $this->requireOwnedRidden();
        if (null !== $rc) {
            $this->error = $this->ratingService->setRideCount($rc, $this->rideCountInput);
            $this->editingCount = false;
        }
    }

    #[LiveAction]
    public function toggleEditingCount(): void
    {
        $this->editingCount = !$this->editingCount;
        if ($this->editingCount) {
            $rc = $this->getRiddenCoaster();
            $this->rideCountInput = $rc?->getRideCount() ?? 1;
        }
    }

    private function requireOwnedRidden(): ?RiddenCoaster
    {
        $rc = $this->getRiddenCoaster();
        if (null !== $rc) {
            $this->denyAccessUnlessGranted('update', $rc);
        }

        return $rc;
    }

    private function parseDate(string $date): ?\DateTime
    {
        $date = trim($date);
        if ('' === $date) {
            return null;
        }

        return \DateTime::createFromFormat('!Y-m-d', $date) ?: null;
    }
}
