<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Coaster;
use App\Entity\Park;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use App\Repository\RiddenCoasterRepository;
use App\Validator\Constraints\ValidRideDate;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RatingService
{
    /** Lifetime (seconds) of the re-ride undo snapshot stored in the cache. */
    private const int UNDO_TTL = 60;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RiddenCoasterRepository $riddenCoasterRepository,
        private readonly ValidatorInterface $validator,
        private readonly TranslatorInterface $translator,
        private readonly CacheItemPoolInterface $cache
    ) {
    }

    /** Crée RiddenCoaster(rating=null, firstRiddenAt=today). */
    public function markAsRidden(User $user, Coaster $coaster): RiddenCoaster
    {
        $riddenCoaster = $this->riddenCoasterRepository->findOneBy(['coaster' => $coaster, 'user' => $user]);

        if ($riddenCoaster instanceof RiddenCoaster) {
            return $riddenCoaster;
        }

        $riddenCoaster = new RiddenCoaster();
        $riddenCoaster->setUser($user);
        $riddenCoaster->setCoaster($coaster);
        $riddenCoaster->setFirstRiddenAt(new \DateTime('today'));

        $this->entityManager->persist($riddenCoaster);
        $this->entityManager->flush();

        return $riddenCoaster;
    }

    /** Supprime RiddenCoaster + review associée. */
    public function removeRidden(RiddenCoaster $riddenCoaster): void
    {
        $this->entityManager->remove($riddenCoaster);
        $this->entityManager->flush();
    }

    /** Pose ou met à jour la note. Crée un RiddenCoaster si nécessaire (rate = tick). */
    public function setRating(User $user, Coaster $coaster, float $rating): RiddenCoaster
    {
        $riddenCoaster = $this->markAsRidden($user, $coaster);

        $riddenCoaster->setRating($rating);
        $this->entityManager->flush();

        return $riddenCoaster;
    }

    /** Supprime uniquement la note (rating=null). RiddenCoaster reste. */
    public function clearRating(RiddenCoaster $riddenCoaster): void
    {
        $riddenCoaster->setRating(null);
        $this->entityManager->flush();
    }

    /** +1 rideCount + lastRiddenAt=today. Mémorise l'état précédent (cache, TTL court) pour l'undo. */
    public function addReride(RiddenCoaster $riddenCoaster): void
    {
        $item = $this->cache->getItem($this->undoKey($riddenCoaster));
        $item->set([
            'rideCount' => $riddenCoaster->getRideCount(),
            'lastRiddenAt' => $riddenCoaster->getLastRiddenAt()?->format('Y-m-d'),
        ]);
        $item->expiresAfter(self::UNDO_TTL);
        $this->cache->save($item);

        $riddenCoaster->setRideCount($riddenCoaster->getRideCount() + 1);
        $riddenCoaster->setLastRiddenAt(new \DateTime('today'));
        $this->entityManager->flush();
    }

    /** Annule le dernier re-ride en restaurant le snapshot serveur. No-op si le snapshot a expiré. */
    public function undoReride(RiddenCoaster $riddenCoaster): void
    {
        $item = $this->cache->getItem($this->undoKey($riddenCoaster));
        if (!$item->isHit()) {
            return;
        }

        /** @var array{rideCount: int, lastRiddenAt: ?string} $snapshot */
        $snapshot = $item->get();
        $this->cache->deleteItem($this->undoKey($riddenCoaster));

        $riddenCoaster->setRideCount($snapshot['rideCount']);
        $riddenCoaster->setLastRiddenAt(
            null !== $snapshot['lastRiddenAt'] ? new \DateTime($snapshot['lastRiddenAt']) : null
        );
        $this->entityManager->flush();
    }

    /**
     * Met à jour firstRiddenAt après validation (ValidRideDate).
     *
     * @return string|null message d'erreur traduit si la date est invalide, null si OK
     */
    public function updateFirstRiddenAt(RiddenCoaster $riddenCoaster, ?\DateTime $date): ?string
    {
        $riddenCoaster->setFirstRiddenAt($date);

        $violations = $this->validator->validate($riddenCoaster, new ValidRideDate());
        if (\count($violations) > 0) {
            // Annule la modification en mémoire pour ne pas persister une date invalide plus tard.
            $this->entityManager->refresh($riddenCoaster);

            return $this->translator->trans((string) $violations[0]->getMessage(), [], 'validators');
        }

        $this->entityManager->flush();

        return null;
    }

    /**
     * Met à jour lastRiddenAt après validation (ValidRideDate).
     *
     * @return string|null message d'erreur traduit si la date est invalide, null si OK
     */
    public function updateLastRiddenAt(RiddenCoaster $riddenCoaster, ?\DateTime $date): ?string
    {
        $riddenCoaster->setLastRiddenAt($date);

        $violations = $this->validator->validate($riddenCoaster, new ValidRideDate());
        if (\count($violations) > 0) {
            $this->entityManager->refresh($riddenCoaster);

            return $this->translator->trans((string) $violations[0]->getMessage(), [], 'validators');
        }

        $this->entityManager->flush();

        return null;
    }

    /**
     * Définit le nombre total de rides (>= 1). Si 1, efface lastRiddenAt (pas de re-ride).
     *
     * @return string|null message d'erreur traduit si invalide, null si OK
     */
    public function setRideCount(RiddenCoaster $riddenCoaster, int $count): ?string
    {
        if ($count < 1) {
            return $this->translator->trans('ride_count.invalid', [], 'validators');
        }

        $riddenCoaster->setRideCount($count);
        if (1 === $count) {
            $riddenCoaster->setLastRiddenAt(null);
        }

        $this->entityManager->flush();

        return null;
    }

    /**
     * Personal stats for the park show page.
     *
     * "Active" coasters (count toward the bar total and completion):
     *   - operating (1), soft opening (11), closed temporarily (9)
     *   These are all coasters a user can currently ride or recently rode.
     *
     * "Legacy" coasters (bonus line only, not in the bar total):
     *   - retracked (8), relocated (4), closed definitely (2)
     *   These are gone for good — including them in the total would make
     *   100% completion unreachable for most users.
     *
     * Counts:
     *   - rated         : active coasters the user has rated
     *   - ridden        : active coasters the user has ridden (rated OR not)
     *   - notRated      : active coasters ridden without a rating (= ridden - rated)
     *   - notRidden     : active coasters not yet ridden (= operatingTotal - ridden)
     *   - operatingTotal: total number of active coasters in the park
     *   - legacyRidden  : permanently-gone coasters the user has ridden
     *
     * @return array{rated: int, ridden: int, notRated: int, notRidden: int, operatingTotal: int, legacyRidden: int}
     */
    public function getParkStatsForUser(User $user, Park $park): array
    {
        // operating + soft opening + closed temporarily → count in the bar
        $activeIds = [];
        // retracked + relocated + closed definitely → bonus line only
        $legacyIds = [];

        foreach ($park->getCoasters() as $coaster) {
            $status = $coaster->getStatus();
            if (null === $status) {
                continue;
            }
            $id = $status->getId();
            if (\in_array($id, [1, 11, 9], true)) {
                $activeIds[] = $coaster->getId();
            } elseif (\in_array($id, [8, 4, 2], true)) {
                $legacyIds[] = $coaster->getId();
            }
        }
        $operatingTotal = \count($activeIds);

        $rated = 0;
        $ridden = 0;
        $legacyRidden = 0;
        foreach ($this->riddenCoasterRepository->findByUserAndPark($user, $park) as $rc) {
            $coasterId = $rc->getCoaster()->getId();
            if (\in_array($coasterId, $activeIds, true)) {
                ++$ridden;
                if (null !== $rc->getRating()) {
                    ++$rated;
                }
            } elseif (\in_array($coasterId, $legacyIds, true)) {
                ++$legacyRidden;
            }
        }

        return [
            'rated' => $rated,
            'ridden' => $ridden,
            'notRated' => max(0, $ridden - $rated),
            'notRidden' => max(0, $operatingTotal - $ridden),
            'operatingTotal' => $operatingTotal,
            'legacyRidden' => $legacyRidden,
        ];
    }

    private function undoKey(RiddenCoaster $riddenCoaster): string
    {
        return \sprintf('reride_undo_%d_%d', (int) $riddenCoaster->getUser()->getId(), $riddenCoaster->getId());
    }
}
