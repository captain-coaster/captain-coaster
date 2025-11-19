<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Coaster;
use App\Entity\Continent;
use App\Entity\Country;
use App\Entity\Manufacturer;
use App\Entity\MaterialType;
use App\Entity\Model;
use App\Entity\SeatingType;
use App\Repository\RankingRepository;
use App\Service\FilterService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;

#[Route(path: '/ranking')]
class RankingController extends AbstractController
{
    final public const int COASTERS_PER_PAGE = 20;

    public function __construct(
        private readonly PaginatorInterface $paginator,
        private readonly RankingRepository $rankingRepository,
        private readonly EntityManagerInterface $em,
        private readonly CacheInterface $cache,
        private readonly FilterService $filterService,
    ) {
    }

    /**
     * Show ranking of best coasters.
     *
     * @throws InvalidArgumentException
     */
    #[Route(path: '/', name: 'ranking_index', methods: ['GET'])]
    public function indexAction(): Response
    {
        return $this->render(
            'ranking/index.html.twig',
            [
                'ranking' => $this->rankingRepository->findCurrent(),
                'previousRanking' => $this->rankingRepository->findPrevious(),
                'filtersForm' => $this->filterService->getFilterData(),
            ]
        );
    }



    /** @throws \Exception */
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
            $pagination = $this->paginator->paginate(
                $this->filterService->getFilteredRanking($filters),
                $page,
                self::COASTERS_PER_PAGE
            );
        } catch (\Exception) {
            throw new BadRequestHttpException();
        }

        return $this->render(
            'ranking/results.html.twig',
            [
                'coasters' => $pagination,
                'filtered' => [] !== array_diff_key($filters, ['user' => null]),
                'firstRank' => self::COASTERS_PER_PAGE * ($page - 1) + 1,
            ]
        );
    }

    /** Learn more on the ranking. */
    #[Route(path: '/learn-more', name: 'ranking_learn_more', methods: ['GET'])]
    public function learnMore(): Response
    {
        return $this->render('ranking/learn_more.html.twig');
    }
}
