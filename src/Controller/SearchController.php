<?php

namespace App\Controller;

use App\Entity\Coaster;
use App\Entity\Manufacturer;
use App\Service\SearchService;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class SearchController.
 */
#[Route(path: '/search')]
class SearchController extends AbstractController
{
    final public const CACHE_AUTOCOMPLETE = 'main_autocomplete';

    /**
     * @var PaginatorInterface
     */
    protected $paginator;

    /**
     * RankingController constructor.
     */
    public function __construct(PaginatorInterface $paginator)
    {
        $this->paginator = $paginator;
    }

    #[Route(path: '/', name: 'search_index', methods: ['GET'])]
    public function indexAction(): Response
    {
        return $this->render(
            'Search/index.html.twig',
            [
                'filters' => ['status' => 'on'],
                'filtersForm' => $this->getFiltersForm(),
            ]
        );
    }

    #[Route(path: '/coasters', name: 'search_coasters_ajax', methods: ['GET'], options: ['expose' => true], condition: 'request.isXmlHttpRequest()')]
    public function searchAction(Request $request): Response
    {
        $filters = $request->get('filters', []);
        $page = $request->get('page', 1);

        return $this->render(
            'Search/results.html.twig',
            [
                'coasters' => $this->getCoasters($filters, $page),
            ]
        );
    }

    /**
     * All data for main search service.
     *
     * @return JsonResponse
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    #[Route(path: '/main.json', name: 'ajax_main_search', methods: ['GET'], options: ['expose' => true], condition: 'request.isXmlHttpRequest()')]
    public function ajaxMainSearch(SearchService $searchService)
    {
        $cache = new FilesystemAdapter();
        $searchItems = $cache->getItem(self::CACHE_AUTOCOMPLETE);

        if (!$searchItems->isHit()) {
            $searchItems->set($searchService->getAutocompleteValues());
            $searchItems->expiresAfter(\DateInterval::createFromDateString('24 hours'));
            $cache->save($searchItems);
        }

        $response = new JsonResponse($searchItems->get());
        $response->setEncodingOptions(JSON_UNESCAPED_UNICODE);

        $response->setPublic();
        $response->setMaxAge(600);

        return $response;
    }

    private function getCoasters(array $filters = [], int $page = 1): PaginationInterface
    {
        $query = $this->getDoctrine()
            ->getRepository(Coaster::class)
            ->getSearchCoasters($filters);

        try {
            return $this->paginator->paginate(
                $query,
                $page,
                20,
                ['wrap-queries' => true]
            );
        } catch (\UnexpectedValueException) {
            throw new BadRequestHttpException();
        }
    }

    /**
     * Get data to display filter form (mainly <select> data).
     *
     * @return array
     */
    private function getFiltersForm()
    {
        return ['manufacturer' => $this->getDoctrine()
            ->getRepository(Manufacturer::class)
            ->findBy([], ['name' => 'asc']), 'openingDate' => $this->getDoctrine()
            ->getRepository(Coaster::class)
            ->getDistinctOpeningYears()];
    }
}
