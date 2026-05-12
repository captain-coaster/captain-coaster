<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\CoasterRepository;
use App\Repository\RankingRepository;
use App\Service\FilterService;
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
        private readonly CoasterRepository $coasterRepository
    ) {
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

        return $this->render(
            'Ranking/index.html.twig',
            [
                'ranking' => $this->rankingRepository->findCurrent(),
                'previousRanking' => $this->rankingRepository->findPrevious(),
                'filtersForm' => $this->filterService->getFilterData(),
                'filters' => $filters,
                'coasters' => $pagination,
                'filtered' => [] !== array_diff_key($validatedFilters, ['user' => null]),
                'firstRank' => self::COASTERS_PER_PAGE * ($page - 1) + 1,
            ]
        );
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
                'firstRank' => self::COASTERS_PER_PAGE * ($page - 1) + 1,
            ]
        );
    }

    /** Learn more on the ranking. */
    #[Route(path: '/learn-more', name: 'ranking_learn_more', methods: ['GET'])]
    public function learnMore(): Response
    {
        return $this->render('Ranking/learn_more.html.twig');
    }
}
