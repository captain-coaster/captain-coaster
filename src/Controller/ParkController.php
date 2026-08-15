<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Coaster;
use App\Entity\Park;
use App\Entity\User;
use App\Repository\CoasterRepository;
use App\Repository\ParkRepository;
use App\Repository\RiddenCoasterRepository;
use App\Repository\TopCoasterRepository;
use App\Service\RatingService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[\Symfony\Component\Routing\Attribute\Route(path: '/parks')]
class ParkController extends AbstractController
{
    /** Redirects to index */
    #[\Symfony\Component\Routing\Attribute\Route(path: '/', name: 'park_index', methods: ['GET'])]
    public function index(): RedirectResponse
    {
        return $this->redirectToRoute('default_index');
    }

    /** Show park details. */
    #[Route(path: '/{id}/{slug}', name: 'park_show', options: ['expose' => true], methods: ['GET'])]
    public function showAction(
        ParkRepository $parkRepository,
        Park $park,
        CoasterRepository $coasterRepository,
        RiddenCoasterRepository $riddenCoasterRepository,
        TopCoasterRepository $topCoasterRepository,
        RatingService $ratingService,
    ): Response {
        $coasters = $coasterRepository->findAllCoastersInPark($park);

        // Compute park statistics
        $stats = $this->computeParkStats($coasters);

        // Group coasters into sections
        $groupedCoasters = $this->groupCoasters($coasters);

        // Personal data for authenticated users
        $riddenCoasters = [];
        $wishlists = [];
        $parkStats = null;

        /** @var User|null $user */
        $user = $this->getUser();
        if ($user instanceof User) {
            foreach ($riddenCoasterRepository->findByUserAndPark($user, $park) as $rc) {
                $id = $rc->getCoaster()->getId();
                if (null !== $id) {
                    $riddenCoasters[$id] = $rc;
                }
            }
            $wishlists = $topCoasterRepository->findBucketByUserAndPark($user, $park);
            $parkStats = $ratingService->getParkStatsForUser($user, $park);
        }

        return $this->render(
            'Park/show.html.twig',
            [
                'park' => $park,
                'coasters' => $coasters,
                'groupedCoasters' => $groupedCoasters,
                'closestParks' => $parkRepository->getClosestParks($park, 80, 300),
                'stats' => $stats,
                'riddenCoasters' => $riddenCoasters,
                'wishlists' => $wishlists,
                'parkStats' => $parkStats,
            ]
        );
    }

    /**
     * Compute useful statistics for the park page.
     *
     * @param array<Coaster> $coasters
     *
     * @return array<string, mixed>
     */
    private function computeParkStats(array $coasters): array
    {
        $operating = 0;
        $kiddies = 0;
        $totalRatings = 0;
        $rankedCoasters = [];
        $topManufacturers = [];

        foreach ($coasters as $coaster) {
            $status = $coaster->getStatus();
            if (null !== $status && 1 === $status->getId()) {
                ++$operating;
                if ($coaster->isKiddie()) {
                    ++$kiddies;
                }
            }
            $totalRatings += $coaster->getTotalRatings();

            if (null !== $coaster->getRank()) {
                $rankedCoasters[] = $coaster;
            }

            $manufacturer = $coaster->getManufacturer();
            if (null !== $manufacturer) {
                $name = $manufacturer->getName();
                $topManufacturers[$name] = ($topManufacturers[$name] ?? 0) + 1;
            }
        }

        arsort($topManufacturers);

        // Best ranked = lowest rank number (#1 is best)
        $bestRanked = null;
        foreach ($rankedCoasters as $coaster) {
            if (null === $bestRanked || $coaster->getRank() < $bestRanked->getRank()) {
                $bestRanked = $coaster;
            }
        }

        return [
            'operating' => $operating,
            'kiddies' => $kiddies,
            'totalCoasters' => \count($coasters),
            'totalRatings' => $totalRatings,
            'rankedCount' => \count($rankedCoasters),
            'bestRanked' => $bestRanked,
            'topManufacturer' => array_key_first($topManufacturers),
        ];
    }

    /** Redirect old urls to above */
    #[\Symfony\Component\Routing\Attribute\Route(path: '/{slug}', name: 'redirect_park_show', options: ['expose' => true], methods: ['GET'])]
    public function redirectPark(#[MapEntity(mapping: ['slug' => 'slug'])] Park $park): RedirectResponse
    {
        return $this->redirectToRoute('park_show', [
            'id' => $park->getId(),
            'slug' => $park->getSlug(),
        ], 301);
    }

    /**
     * Group coasters into display sections:
     *   - soon    : rumored / announced / in construction
     *   - new     : soft opening OR operating and opened this year
     *   - main    : operating (not new) + closed temporarily
     *   - legacy  : retracked / relocated / closed definitely
     *
     * @param array<Coaster> $coasters
     *
     * @return array{soon: array<Coaster>, new: array<Coaster>, main: array<Coaster>, legacy: array<Coaster>}
     */
    private function groupCoasters(array $coasters): array
    {
        $soon = [];
        $new = [];
        $main = [];
        $legacy = [];

        $currentYear = (int) date('Y');

        // Status IDs from the database
        $soonIds = [10, 6, 3];    // rumored, announced, construction
        $legacyIds = [8, 4, 2];   // retracked, relocated, closed definitely
        $softOpeningId = 11;
        $operatingId = 1;
        $closedTemporarilyId = 9;

        foreach ($coasters as $coaster) {
            $status = $coaster->getStatus();
            if (null === $status) {
                $main[] = $coaster;
                continue;
            }

            $statusId = $status->getId();

            if (\in_array($statusId, $soonIds, true)) {
                $soon[] = $coaster;
            } elseif ($softOpeningId === $statusId) {
                $new[] = $coaster;
            } elseif ($operatingId === $statusId) {
                $openingDate = $coaster->getOpeningDate();
                if (null !== $openingDate && (int) $openingDate->format('Y') === $currentYear) {
                    $new[] = $coaster;
                } else {
                    $main[] = $coaster;
                }
            } elseif ($closedTemporarilyId === $statusId) {
                $main[] = $coaster;
            } elseif (\in_array($statusId, $legacyIds, true)) {
                $legacy[] = $coaster;
            } else {
                $main[] = $coaster;
            }
        }

        return [
            'soon' => $soon,
            'new' => $new,
            'main' => $main,
            'legacy' => $legacy,
        ];
    }
}
