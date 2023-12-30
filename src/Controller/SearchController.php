<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\CoasterRepository;
use App\Repository\ManufacturerRepository;
use App\Service\SearchService;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/search')]
class SearchController extends AbstractController
{
    final public const CACHE_AUTOCOMPLETE = 'main_autocomplete';

    protected PaginatorInterface $paginator;

    public function __construct(PaginatorInterface $paginator)
    {
        $this->paginator = $paginator;
    }

    #[Route(path: '/', name: 'search_index', methods: ['GET'])]
    public function indexAction(ManufacturerRepository $manufacturerRepository, CoasterRepository $coasterRepository): Response
    {
        return $this->render(
            'Search/index.html.twig',
            [
                'filters' => ['status' => 'on'],
                'filtersForm' => [
                    'manufacturer' => $manufacturerRepository->findBy([], ['name' => 'asc']),
                    'openingDate' => $coasterRepository->getDistinctOpeningYears(),
                ],
            ]
        );
    }

    #[Route(path: '/coasters', name: 'search_coasters_ajax', options: ['expose' => true], methods: ['GET'], condition: 'request.isXmlHttpRequest()')]
    public function searchAction(
        CoasterRepository $coasterRepository,
        #[MapQueryParameter]
        array $filters = [],
        #[MapQueryParameter]
        int $page = 1
    ): Response {
        try {
            $pagination = $this->paginator->paginate(
                $coasterRepository->getSearchCoasters($filters),
                $page,
                20,
                ['wrap-queries' => true]
            );
        } catch (\Exception) {
            throw new BadRequestHttpException();
        }

        return $this->render('Search/results.html.twig', ['coasters' => $pagination]);
    }

    /**
     * All data for main search service.
     *
     * @throws InvalidArgumentException
     */
    #[Route(path: '/main.json', name: 'ajax_main_search', options: ['expose' => true], methods: ['GET'], condition: 'request.isXmlHttpRequest()')]
    public function ajaxMainSearch(SearchService $searchService): JsonResponse
    {
        $cache = new FilesystemAdapter();
        $searchItems = $cache->getItem(self::CACHE_AUTOCOMPLETE);

        if (!$searchItems->isHit()) {
            $searchItems->set($searchService->getAutocompleteValues());
            $searchItems->expiresAfter(\DateInterval::createFromDateString('24 hours'));
            $cache->save($searchItems);
        }

        $response = new JsonResponse($searchItems->get());
        $response->setEncodingOptions(\JSON_UNESCAPED_UNICODE);

        $response->setPublic();
        $response->setMaxAge(600);

        return $response;
    }
}
