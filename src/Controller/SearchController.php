<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\CoasterRepository;
use App\Repository\ManufacturerRepository;
use App\Repository\ParkRepository;
use App\Repository\UserRepository;
use App\Service\SearchService;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/search')]
class SearchController extends AbstractController
{
    protected PaginatorInterface $paginator;
    final public const string CACHE_AUTOCOMPLETE = 'main_autocomplete';

    public function __construct(PaginatorInterface $paginator)
    {
        $this->paginator = $paginator;
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

    #[Route(path: '/', name: 'search_index', options: ['expose' => true], methods: ['GET'])]
    public function search(Request $request, CoasterRepository $coasterRepository, ManufacturerRepository $manufacturerRepository): Response
    {
        $query = $request->query->get('query');

        return $this->render(
            'Search/index.html.twig',
            [
                'query' => $query,
            ]
        );
    }

    #[Route(path: '/coasters', name: 'search_coasters_ajax', options: ['expose' => true], methods: ['GET'], condition: 'request.isXmlHttpRequest()')]
    public function searchCoastersAction(
        CoasterRepository $coasterRepository,
        #[MapQueryParameter]
        string $query,
        #[MapQueryParameter]
        int $page = 1
    ): Response {
        try {
            $pagination = $this->paginator->paginate(
                $coasterRepository->getSearchCoasters($query),
                $page,
                10,
                ['wrap-queries' => true]
            );
        } catch (\Exception) {
            throw new BadRequestHttpException();
        }

        return $this->render('Search/coasters_results.html.twig', ['coasters' => $pagination]);
    }

    #[Route(path: '/parks', name: 'search_parks_ajax', options: ['expose' => true], methods: ['GET'], condition: 'request.isXmlHttpRequest()')]
    public function searchParksAction(
        ParkRepository $parkRepository,
        #[MapQueryParameter]
        string $query,
        #[MapQueryParameter]
        int $page = 1
    ): Response {
        try {
            $pagination = $this->paginator->paginate(
                $parkRepository->getSearchParks($query),
                $page,
                10,
                ['wrap-queries' => true]
            );
        } catch (\Exception) {
            throw new BadRequestHttpException();
        }

        return $this->render('Search/parks_results.html.twig', ['parks' => $pagination]);
    }

    #[Route(path: '/users', name: 'search_users_ajax', options: ['expose' => true], methods: ['GET'], condition: 'request.isXmlHttpRequest()')]
    public function searchUsersAction(
        UserRepository $userRepository,
        #[MapQueryParameter]
        string $query,
        #[MapQueryParameter]
        int $page = 1
    ): Response {
        try {
            $pagination = $this->paginator->paginate(
                $userRepository->getSearchUsers($query),
                $page,
                12,
                ['wrap-queries' => true]
            );
        } catch (\Exception) {
            throw new BadRequestHttpException();
        }

        return $this->render('Search/users_results.html.twig', ['users' => $pagination]);
    }
}
