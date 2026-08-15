<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * UI-only mockups for the rating experience redesign.
 * No DB, no entities, no business logic. Hardcoded fake data only.
 *
 * Routes are dev-only (kernel.environment = "dev").
 */
#[Route(path: '/mockup/rating', name: 'mockup_rating_')]
class MockupController extends AbstractController
{
    /** Index — links to all mockups. */
    #[Route(path: '', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('Mockup/Rating/index.html.twig', [
            'states' => $this->states(),
        ]);
    }

    /** Coaster show — widget + journal in 5 states. */
    #[Route(path: '/coaster/{state}', name: 'coaster', methods: ['GET'], requirements: ['state' => 'unknown|wishlist|ridden|rated|anonymous'])]
    public function coaster(string $state): Response
    {
        return $this->render('Mockup/Rating/coaster_show.html.twig', [
            'state' => $state,
            'coaster' => $this->fakeCoaster(),
            'rating' => $this->fakeRatingFor($state),
            'reviews' => $this->fakeReviews(),
            'myReview' => $this->fakeMyReview($state),
            'states' => $this->states(),
        ]);
    }

    /** Reviews section only — to compare layouts. */
    #[Route(path: '/reviews/{state}', name: 'reviews', methods: ['GET'], requirements: ['state' => 'with_my_review|without_my_review'])]
    public function reviews(string $state): Response
    {
        return $this->render('Mockup/Rating/reviews_section.html.twig', [
            'state' => $state,
            'coaster' => $this->fakeCoaster(),
            'reviews' => $this->fakeReviews(),
            'myReview' => 'with_my_review' === $state ? $this->fakeMyReview('rated') : null,
        ]);
    }

    /** Journey page — chronological journal with milestones. */
    #[Route(path: '/journey', name: 'journey', methods: ['GET'])]
    public function journey(): Response
    {
        return $this->render('Mockup/Rating/journey.html.twig', [
            'years' => $this->fakeJourney(),
        ]);
    }

    /** Park show — compact widget per coaster row. */
    #[Route(path: '/park', name: 'park', methods: ['GET'])]
    public function park(): Response
    {
        return $this->render('Mockup/Rating/park_show.html.twig', [
            'coasters' => $this->fakeParkCoasters(),
        ]);
    }

    /** Profile/ratings = "mes notes" — list with badges and tabs. */
    #[Route(path: '/my-ratings', name: 'my_ratings', methods: ['GET'])]
    public function myRatings(): Response
    {
        return $this->render('Mockup/Rating/my_ratings.html.twig', [
            'rows' => $this->fakeMyRatings(),
        ]);
    }

    /** @return array<string, string> */
    private function states(): array
    {
        return [
            'anonymous' => 'Anonymous (logged out)',
            'unknown' => 'Unknown (logged, nothing posted)',
            'wishlist' => 'Wishlist',
            'ridden' => 'Ridden, no rating',
            'rated' => 'Rated (with date + re-ride)',
        ];
    }

    /** @return array{id: int, slug: string, name: string, park: string, country: string, image: string|null, score: float, rank: int} */
    private function fakeCoaster(): array
    {
        return [
            'id' => 1,
            'slug' => 'steel-vengeance',
            'name' => 'Steel Vengeance',
            'park' => 'Cedar Point',
            'country' => 'USA',
            'image' => null, // gradient fallback in template
            'score' => 95.4,
            'rank' => 2,
        ];
    }

    /** @return array<string, mixed>|null */
    private function fakeRatingFor(string $state): ?array
    {
        return match ($state) {
            'wishlist' => [
                'isWishlist' => true,
                'value' => null,
                'firstRiddenAt' => null,
                'lastRiddenAt' => null,
                'rideCount' => 0,
                'addedToWishlistAt' => '2026-03-14',
            ],
            'ridden' => [
                'isWishlist' => false,
                'value' => null,
                'firstRiddenAt' => '12 juil 2024',
                'lastRiddenAt' => null,
                'rideCount' => 1,
            ],
            'rated' => [
                'isWishlist' => false,
                'value' => 4.5,
                'firstRiddenAt' => '12 juil 2024',
                'lastRiddenAt' => '8 mai 2026',
                'rideCount' => 3,
            ],
            default => null,
        };
    }

    /** @return list<array<string, mixed>> */
    private function fakeReviews(): array
    {
        return [
            [
                'id' => 101,
                'user' => ['name' => 'Pierre L.', 'avatar' => null, 'slug' => 'pierre-l'],
                'value' => 5.0,
                'review' => 'Probablement le meilleur coaster que j\'ai jamais ridé. Airtime de fou, layout incroyable, première ligne mandatory.',
                'pros' => ['airtime', 'layout'],
                'cons' => [],
                'updatedAt' => '2026-04-22',
                'upvotes' => 18,
            ],
            [
                'id' => 102,
                'user' => ['name' => 'Sarah K.', 'avatar' => null, 'slug' => 'sarah-k'],
                'value' => 4.5,
                'review' => 'Fantastique de bout en bout. Quelques secousses dans les inversions mais ça reste une référence absolue.',
                'pros' => ['intensity'],
                'cons' => ['roughness'],
                'updatedAt' => '2026-04-15',
                'upvotes' => 12,
            ],
            [
                'id' => 103,
                'user' => ['name' => 'Marco D.', 'avatar' => null, 'slug' => 'marco-d'],
                'value' => 5.0,
                'review' => 'Il faut absolument le faire de nuit. L\'expérience est totalement transformée.',
                'pros' => ['theme', 'airtime'],
                'cons' => [],
                'updatedAt' => '2026-04-10',
                'upvotes' => 9,
            ],
            [
                'id' => 104,
                'user' => ['name' => 'Léa T.', 'avatar' => null, 'slug' => 'lea-t'],
                'value' => 4.0,
                'review' => 'Très bon coaster mais pas mon préféré du parc. Je préfère Maverick personnellement.',
                'pros' => [],
                'cons' => [],
                'updatedAt' => '2026-04-02',
                'upvotes' => 4,
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    private function fakeMyReview(string $state): ?array
    {
        if ('rated' !== $state) {
            return null;
        }

        return [
            'id' => 999,
            'user' => ['name' => 'Florian (toi)', 'avatar' => null, 'slug' => 'me', 'isMe' => true],
            'value' => 4.5,
            'review' => 'Je l\'ai fait 3 fois en 2 ans. La file ne désempère pas mais ça vaut largement le coup. Quelques rides un peu fatigants vers la fin.',
            'pros' => ['airtime', 'layout'],
            'cons' => [],
            'updatedAt' => '2026-05-08',
            'upvotes' => 7,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function fakeJourney(): array
    {
        // Milestones use 'sublabel' = exact ride name so the template can attach them to the right ride bubble.
        return [
            [
                'year' => 2026,
                'count' => 8,
                'milestones' => [
                    ['icon' => 'tabler:trophy', 'label' => '250e coaster 🎉', 'sublabel' => 'Steel Vengeance', 'color' => 'cc-warm'],
                ],
                'rides' => [
                    ['name' => 'Steel Vengeance', 'park' => 'Cedar Point', 'value' => 4.5, 'date' => '2026-05-08', 'isReride' => true],
                    ['name' => 'Voltron Nevera', 'park' => 'Europa-Park', 'value' => 5.0, 'date' => '2026-04-12', 'isReride' => false],
                    ['name' => 'Hyperion', 'park' => 'Energylandia', 'value' => 4.5, 'date' => '2026-03-20', 'isReride' => false],
                ],
            ],
            [
                'year' => 2024,
                'count' => 42,
                'milestones' => [
                    ['icon' => 'tabler:bolt', 'label' => '1er +200 km/h', 'sublabel' => 'Top Thrill 2', 'color' => 'cc-blue'],
                    ['icon' => 'tabler:building-factory-2', 'label' => '1er Intamin', 'sublabel' => 'Maverick', 'color' => 'green'],
                    ['icon' => 'tabler:flag', 'label' => '1er pays : USA 🇺🇸', 'sublabel' => 'Steel Vengeance', 'color' => 'cc-warm'],
                ],
                'rides' => [
                    ['name' => 'Top Thrill 2', 'park' => 'Cedar Point', 'value' => 4.0, 'date' => '2024-08-14', 'isReride' => false],
                    ['name' => 'Maverick', 'park' => 'Cedar Point', 'value' => 5.0, 'date' => '2024-07-12', 'isReride' => false],
                    ['name' => 'Steel Vengeance', 'park' => 'Cedar Point', 'value' => 5.0, 'date' => '2024-07-12', 'isReride' => false],
                ],
            ],
            [
                'year' => 2023,
                'count' => 28,
                'milestones' => [
                    ['icon' => 'tabler:trophy', 'label' => '100e coaster 🎉', 'sublabel' => 'Wodan', 'color' => 'cc-warm'],
                ],
                'rides' => [
                    ['name' => 'Wodan', 'park' => 'Europa-Park', 'value' => 4.0, 'date' => '2023-06-24', 'isReride' => false],
                    ['name' => 'Silver Star', 'park' => 'Europa-Park', 'value' => 3.5, 'date' => '2023-06-24', 'isReride' => false],
                ],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function fakeParkCoasters(): array
    {
        return [
            ['name' => 'Steel Vengeance', 'manufacturer' => 'RMC', 'year' => 2018, 'score' => 95.4, 'rank' => 2, 'state' => 'rated', 'value' => 4.5],
            ['name' => 'Maverick', 'manufacturer' => 'Intamin', 'year' => 2007, 'score' => 89.1, 'rank' => 12, 'state' => 'rated', 'value' => 5.0],
            ['name' => 'Top Thrill 2', 'manufacturer' => 'Zamperla', 'year' => 2024, 'score' => 78.3, 'rank' => 47, 'state' => 'ridden', 'value' => null],
            ['name' => 'Magnum XL-200', 'manufacturer' => 'Arrow', 'year' => 1989, 'score' => 65.2, 'rank' => 124, 'state' => 'wishlist', 'value' => null],
            ['name' => 'Millennium Force', 'manufacturer' => 'Intamin', 'year' => 2000, 'score' => 88.7, 'rank' => 14, 'state' => 'unknown', 'value' => null],
            ['name' => 'Iron Dragon', 'manufacturer' => 'Arrow', 'year' => 1987, 'score' => 42.1, 'rank' => 312, 'state' => 'unknown', 'value' => null],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function fakeMyRatings(): array
    {
        return [
            ['name' => 'Steel Vengeance', 'park' => 'Cedar Point', 'state' => 'rated', 'value' => 4.5, 'date' => '2024-07-12', 'rideCount' => 3, 'statusColor' => 'green'],
            ['name' => 'Voltron Nevera', 'park' => 'Europa-Park', 'state' => 'rated', 'value' => 5.0, 'date' => '2026-04-12', 'rideCount' => 1, 'statusColor' => 'green'],
            ['name' => 'Top Thrill 2', 'park' => 'Cedar Point', 'state' => 'ridden', 'value' => null, 'date' => '2024-08-14', 'rideCount' => 1, 'statusColor' => 'green'],
            ['name' => 'Hyperion', 'park' => 'Energylandia', 'state' => 'rated', 'value' => 4.5, 'date' => '2026-03-20', 'rideCount' => 2, 'statusColor' => 'green'],
            ['name' => 'Magnum XL-200', 'park' => 'Cedar Point', 'state' => 'wishlist', 'value' => null, 'date' => null, 'rideCount' => 0, 'statusColor' => 'green'],
            ['name' => 'Kingda Ka', 'park' => 'Six Flags Great Adventure', 'state' => 'rated', 'value' => 4.0, 'date' => '2019-08-22', 'rideCount' => 1, 'statusColor' => 'red'],
        ];
    }
}
