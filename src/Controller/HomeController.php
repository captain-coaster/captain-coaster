<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\CoasterRepository;
use App\Repository\RankingRepository;
use App\Repository\RiddenCoasterRepository;
use App\Service\HeroService;
use App\Service\Home\CommunityFigures;
use App\Service\Home\NearbyParks;
use App\Service\Home\NextActionPicker;
use App\Service\Home\ReviewPicker;
use App\Service\LocalePreferenceService;
use App\Service\ReviewLanguagePreferenceService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Home: two pages on one URL, the visitor's (what the site knows) and the member's (what it knows about them).
 */
class HomeController extends BaseController
{
    private const int PODIUM = 3;
    private const int REVIEWS = 3;

    public function __construct(
        private readonly HeroService $heroService,
        private readonly CommunityFigures $communityFigures,
        private readonly ReviewPicker $reviewPicker,
        private readonly ReviewLanguagePreferenceService $reviewLanguagePreferenceService,
        private readonly CoasterRepository $coasterRepository,
    ) {
    }

    /**
     * The site's root, without a locale. A member's preferredLocale wins (never null, defaults to 'en'); a visitor
     * gets the cookie, else the browser's guess (LocalePreferenceService).
     */
    public function root(Request $request, LocalePreferenceService $localePreferenceService): RedirectResponse
    {
        $locale = $this->getUser()?->getPreferredLocale()
            ?? $localePreferenceService->resolveAnonymousLocale($request);

        return $this->redirectToRoute('default_index', ['_locale' => $locale], 301);
    }

    #[Route(path: '/', name: 'default_index', methods: ['GET'])]
    public function index(
        Request $request,
        RiddenCoasterRepository $riddenCoasterRepository,
        RankingRepository $rankingRepository,
        NextActionPicker $nextActionPicker,
    ): Response {
        $user = $this->getUser();
        $hero = $this->heroService->pick();
        // The top 3 is the visitor's only.
        $podium = $user instanceof User ? [] : $this->coasterRepository->findTopRanked(self::PODIUM);
        $community = $this->communityFigures->get();

        // Reviews never repeat a coaster the page already features: the Hero photo, and the visitor's top 3.
        $featured = [...array_filter([$hero['coasterId'] ?? null]), ...array_map(static fn ($coaster) => $coaster->getId(), $podium)];
        $common = [
            'hero' => $hero,
            'reviews' => $this->reviewPicker->pick($this->reviewLanguagePreferenceService->resolve($request), array_values($featured), self::REVIEWS),
            'reviewCount' => $community['reviews'],
        ];

        if (!$user instanceof User) {
            return $this->render('Home/visitor.html.twig', $common + [
                'community' => $community,
                'podium' => $podium,
                'ranking' => $rankingRepository->findCurrent(),
            ]);
        }

        $now = new \DateTimeImmutable();
        $figures = $riddenCoasterRepository->countFiguresForUser($user, (int) $now->format('Y'));

        return $this->render('Home/member.html.twig', $common + [
            'figures' => $figures,
            'year' => $now->format('Y'),
            'top100' => $figures['ridden'] > 0 ? $riddenCoasterRepository->countTop100ForUser($user)['nb_top100_operating'] : 0,
            'nextAction' => $nextActionPicker->pick($user, $figures['ridden'], $now),
        ]);
    }

    /**
     * "Parks near you", once the visitor shares a position. A POST so the position stays out of URLs and access
     * logs; nothing is stored.
     */
    #[Route(path: '/home/nearby', name: 'home_nearby', methods: ['POST'], condition: 'request.isXmlHttpRequest()')]
    public function nearby(Request $request, NearbyParks $nearbyParks): Response
    {
        $latitude = filter_var($request->request->get('latitude'), \FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($request->request->get('longitude'), \FILTER_VALIDATE_FLOAT);
        if (false === $latitude || false === $longitude || abs($latitude) > 90 || abs($longitude) > 180) {
            throw new BadRequestHttpException('Invalid position.');
        }

        $user = $this->getUser();

        $response = $this->render('Home/_nearby.html.twig', [
            'parks' => $nearbyParks->nearest($latitude, $longitude, $user instanceof User ? $user : null),
            'member' => $user instanceof User,
        ]);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
