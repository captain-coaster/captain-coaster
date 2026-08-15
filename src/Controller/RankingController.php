<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\CoasterRepository;
use App\Repository\RankingRepository;
use App\Repository\RiddenCoasterRepository;
use App\Service\FilterService;
use Knp\Bundle\PaginatorBundle\Pagination\SlidingPagination;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/ranking')]
class RankingController extends AbstractController
{
    final public const int COASTERS_PER_PAGE = 20;

    public function __construct(
        private readonly PaginatorInterface $paginator,
        private readonly RankingRepository $rankingRepository,
        private readonly FilterService $filterService,
        private readonly CoasterRepository $coasterRepository,
        private readonly RiddenCoasterRepository $riddenCoasterRepository
    ) {
    }

    /**
     * Ids of ranked coasters the current user has ridden — drives the row highlight.
     *
     * @return array<int, int>
     */
    private function riddenCoasterIds(): array
    {
        $user = $this->getUser();

        return $user instanceof User ? $this->riddenCoasterRepository->findRankedRiddenCoasterIds($user) : [];
    }

    /**
     * Show ranking of best coasters.
     *
     * @param array<string, mixed> $filters
     *
     * @throws InvalidArgumentException
     */
    #[Route(path: '/', name: 'ranking_index', methods: ['GET'])]
    public function indexAction(#[MapQueryParameter] array $filters = [], #[MapQueryParameter] int $page = 1): Response
    {
        $validatedFilters = $this->filterService->validateAndAuthorize(
            $filters,
            'ranking',
            $this->getUser()
        );

        $pagination = $this->paginator->paginate(
            $this->coasterRepository->findForRanking($validatedFilters),
            $page,
            self::COASTERS_PER_PAGE
        );
        // Pagination links must target the public route, not this action — and the
        // async endpoint below renders the same partial under an XHR-only route.
        if ($pagination instanceof SlidingPagination) {
            $pagination->setUsedRoute('ranking_index');
        }

        return $this->render(
            'Ranking/index.html.twig',
            [
                'ranking' => $this->rankingRepository->findCurrent(),
                'filtersForm' => $this->filterService->getFilterData(),
                'filters' => $filters,
                'coasters' => $pagination,
                'filtered' => [] !== array_diff_key($validatedFilters, ['user' => null]),
                'riddenIds' => $this->riddenCoasterIds(),
                'top100' => $this->top100Progress(),
            ]
        );
    }

    /**
     * Top-100 progress for the current user: how many of the non-demolished
     * top-100 cohort they've ridden, plus a count of demolished top-100 coasters
     * they rode before closure. Null when not logged in.
     *
     * @return array{ridden: int, total: int, defunctRidden: int}|null
     */
    private function top100Progress(): ?array
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return null;
        }

        $cohort = $this->coasterRepository->findTop100CohortBounds();

        return [
            'ridden' => $this->riddenCoasterRepository->countRiddenInTop100Cohort($user, $cohort['cutoffRank']),
            'total' => $cohort['size'],
            'defunctRidden' => $this->riddenCoasterRepository->countRiddenDefunctTop100($user),
        ];
    }

    /** @param array<string, mixed> $filters */
    #[Route(
        path: '/coasters',
        name: 'ranking_search_async',
        options: ['expose' => true],
        methods: ['GET'],
        condition: 'request.isXmlHttpRequest()'
    )]
    public function searchAsyncAction(#[MapQueryParameter] array $filters = [], #[MapQueryParameter] int $page = 1): Response
    {
        try {
            // Validate and authorize filters
            $validatedFilters = $this->filterService->validateAndAuthorize(
                $filters,
                'ranking',
                $this->getUser()
            );

            $pagination = $this->paginator->paginate(
                $this->coasterRepository->findForRanking($validatedFilters),
                $page,
                self::COASTERS_PER_PAGE
            );
            if ($pagination instanceof SlidingPagination) {
                $pagination->setUsedRoute('ranking_index');
            }
        } catch (AccessDeniedHttpException $e) {
            throw $e;
        } catch (\Exception) {
            throw new BadRequestHttpException();
        }

        return $this->render(
            'Ranking/results.html.twig',
            [
                'coasters' => $pagination,
                'filtered' => [] !== array_diff_key($validatedFilters, ['user' => null]),
                'riddenIds' => $this->riddenCoasterIds(),
            ]
        );
    }

    /** Learn more on the ranking. */
    #[Route(path: '/learn-more', name: 'ranking_learn_more', methods: ['GET'])]
    public function learnMore(): Response
    {
        return $this->render('Ranking/learn_more.html.twig', [
            'ranking' => $this->rankingRepository->findCurrent(),
            'previousRanking' => $this->rankingRepository->findPrevious(),
        ]);
    }
}
