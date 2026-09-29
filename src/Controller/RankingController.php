<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Ranking;
use App\Entity\User;
use App\Repository\CoasterRepository;
use App\Repository\RankingRepository;
use App\Repository\RiddenCoasterRepository;
use App\Repository\TopRepository;
use App\Service\FilterService;
use App\Service\RankingService;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/ranking')]
class RankingController extends AbstractController
{
    final public const int COASTERS_PER_PAGE = 50;

    public function __construct(
        private readonly PaginatorInterface $paginator,
        private readonly RankingRepository $rankingRepository,
        private readonly FilterService $filterService,
        private readonly CoasterRepository $coasterRepository,
        private readonly RiddenCoasterRepository $riddenCoasterRepository,
    ) {
    }

    /**
     * Show ranking of best coasters, first page server-rendered.
     *
     * @param array<string, mixed> $filters
     *
     * @throws InvalidArgumentException
     */
    #[Route(path: '/', name: 'ranking_index', methods: ['GET'])]
    public function indexAction(#[MapQueryParameter] array $filters = [], #[MapQueryParameter] int $page = 1): Response
    {
        $results = $this->results($filters, $page);
        $user = $this->getUser();
        $top100 = $user instanceof User && !$results['filtered'] && 1 === $page
            ? $this->riddenCoasterRepository->countTop100ForUser($user)
            : null;

        return $this->render('ranking/index.html.twig', $results + [
            'previousRanking' => $this->rankingRepository->findPrevious(),
            'filtersForm' => $this->filterService->getFilterData(),
            'filters' => $filters,
            'top100' => $top100,
            'nextRanking' => RankingService::nextPublication(new \DateTimeImmutable()),
        ]);
    }

    /**
     * Results only: filter changes and "load more".
     *
     * @param array<string, mixed> $filters
     */
    #[Route(
        path: '/coasters',
        name: 'ranking_search_async',
        options: ['expose' => true],
        methods: ['GET'],
        condition: 'request.isXmlHttpRequest()'
    )]
    public function searchAsyncAction(#[MapQueryParameter] array $filters = [], #[MapQueryParameter] int $page = 1): Response
    {
        return $this->render('ranking/results.html.twig', $this->results($filters, $page));
    }

    /** Learn more on the ranking. */
    #[Route(path: '/learn-more', name: 'ranking_learn_more', methods: ['GET'])]
    public function learnMore(TopRepository $topRepository): Response
    {
        $ranking = $this->rankingRepository->findCurrent();
        $duel = $ranking?->getFeaturedDuel();
        $coasters = $duel ? $this->coasterRepository->findDuel($duel['first'], $duel['second']) : null;

        $user = $this->getUser();
        $contribution = null;
        if ($user instanceof User) {
            $ratings = $this->riddenCoasterRepository->countRankedForUser($user);
            $top = $topRepository->countForRanking($user);
            $contribution = [
                'ratings' => $ratings,
                'top' => $top['top'],
                'comparisons' => RankingService::riderComparisons($ratings, $top['top'], $top['rated']),
            ];
        }

        return $this->render('ranking/learn_more.html.twig', [
            'ranking' => $ranking,
            'duel' => $duel && $coasters ? ['first' => $coasters[0], 'second' => $coasters[1]] + $duel : null,
            'contribution' => $contribution,
            'previousRanking' => $this->rankingRepository->findPrevious(),
            'history' => $this->rankingRepository->findTotalsHistory(),
        ]);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{coasters: PaginationInterface<int, mixed>, filtered: bool, firstRank: int, riddenIds: array<int, true>, ranking: ?Ranking, queryFilters: array<string, mixed>}
     */
    private function results(array $filters, int $page): array
    {
        $page = max(1, $page);
        $user = $this->getUser();

        // Filters are sanitized, not rejected: past the permission check (403), any error is a server one
        $validatedFilters = $this->filterService->validateAndAuthorize($filters, 'ranking', $user);

        $pagination = $this->paginator->paginate(
            $this->coasterRepository->findForRanking($validatedFilters),
            $page,
            self::COASTERS_PER_PAGE,
            // Every join in findForRanking() is ManyToOne/OneToOne, so it can
            // never duplicate a coaster row -- skip KnpPaginator's extra
            // "distinct id" pre-query, which exists only to guard against
            // *-to-many joins.
            [PaginatorInterface::DISTINCT => false]
        );

        $riddenIds = [];
        if ($user instanceof User) {
            $ids = array_map(static fn ($coaster) => $coaster->getId(), iterator_to_array($pagination->getItems()));
            $riddenIds = array_fill_keys($this->riddenCoasterRepository->findRiddenCoasterIds($user, $ids), true);
        }

        $queryFilters = array_diff_key($validatedFilters, ['user' => null]);

        return [
            'coasters' => $pagination,
            'filtered' => [] !== $queryFilters,
            'firstRank' => self::COASTERS_PER_PAGE * ($page - 1) + 1,
            'riddenIds' => $riddenIds,
            'ranking' => $this->rankingRepository->findCurrent(),
            // Carried by the pager links so "load more" and "jump to" keep the filters
            'queryFilters' => $queryFilters,
        ];
    }
}
