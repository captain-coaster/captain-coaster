<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Coaster;
use App\Entity\Park;
use App\Repository\CoasterRepository;
use App\Repository\ParkRepository;
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
        CoasterRepository $coasterRepository
    ): Response {
        $coasters = $coasterRepository->findAllCoastersInPark($park);

        // Compute park statistics
        $stats = $this->computeParkStats($coasters);

        return $this->render(
            'Park/show.html.twig',
            [
                'park' => $park,
                'coasters' => $coasters,
                'closestParks' => $parkRepository->getClosestParks($park, 80, 300),
                'stats' => $stats,
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

        return [
            'operating' => $operating,
            'kiddies' => $kiddies,
            'totalCoasters' => \count($coasters),
            'totalRatings' => $totalRatings,
            'rankedCount' => \count($rankedCoasters),
            'bestRanked' => $rankedCoasters[0] ?? null,
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
}
