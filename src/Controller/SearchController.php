<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\SearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/search')]
class SearchController extends AbstractController
{
    /** Modern API search endpoint for real-time search suggestions. */
    #[Route(path: '/api', name: 'api_search', methods: ['GET'])]
    public function apiSearch(
        Request $request,
        SearchService $searchService
    ): JsonResponse {
        $query = trim($request->query->get('q', ''));

        // Validate query parameter
        if (empty($query)) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Query parameter "q" is required',
                'code' => 'MISSING_QUERY',
            ], 400);
        }

        if (\strlen($query) < 2) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Query must be at least 2 characters long',
                'code' => 'QUERY_TOO_SHORT',
            ], 400);
        }

        if (\strlen($query) > 100) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Query must be less than 100 characters',
                'code' => 'QUERY_TOO_LONG',
            ], 400);
        }

        try {
            $limit = min(5, max(1, (int) $request->query->get('limit', '5')));
            $searchResponse = $searchService->searchAll($query, $limit);

            $response = new JsonResponse($searchResponse->toArray());
            $response->setEncodingOptions(\JSON_UNESCAPED_UNICODE);

            // Set cache headers for performance
            $response->setPublic();
            $response->setMaxAge(300); // 5 minutes

            return $response;
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Search temporarily unavailable',
                'code' => 'SEARCH_ERROR',
            ], 500);
        }
    }

    #[Route(path: '/', name: 'search_index', options: ['expose' => true], methods: ['GET'])]
    public function search(Request $request, SearchService $searchService): Response
    {
        $query = $request->query->get('query');
        $page = max(1, (int) $request->query->get('page', '1'));
        $type = $request->query->get('type', 'all');

        // Validate type filter
        if (!\in_array($type, ['all', 'coaster', 'park', 'user'], true)) {
            $type = 'all';
        }

        // If no query or query too short, show empty search page
        if (empty($query) || \strlen($query) < 2) {
            return $this->render('Search/index.html.twig', [
                'query' => $query,
                'results' => null,
                'pagination' => null,
                'totalResults' => 0,
                'countByType' => ['all' => 0, 'coaster' => 0, 'park' => 0, 'user' => 0],
                'activeType' => $type,
            ]);
        }

        try {
            // Get unified search results with pagination
            $searchResults = $searchService->searchAllWithPagination($query, $page, 20);

            // Filter by type if specified
            $filteredResults = $searchResults['results'];
            if ('all' !== $type) {
                $filteredResults = array_values(array_filter(
                    $searchResults['results'],
                    fn ($result) => $result['entity_type'] === $type
                ));
            }

            return $this->render('Search/index.html.twig', [
                'query' => $query,
                'results' => $filteredResults,
                'pagination' => $searchResults['pagination'],
                'totalResults' => $searchResults['totalResults'],
                'countByType' => $searchResults['countByType'],
                'currentPage' => $page,
                'hasMore' => $searchResults['hasMore'],
                'activeType' => $type,
            ]);
        } catch (\Exception $e) {
            // Log error with full details for debugging
            error_log('Search error: '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());
            error_log('Stack trace: '.$e->getTraceAsString());

            // Re-throw in dev mode to see the actual error
            if ('dev' === $this->getParameter('kernel.environment')) {
                throw $e;
            }

            return $this->render('Search/index.html.twig', [
                'query' => $query,
                'results' => [],
                'pagination' => null,
                'totalResults' => 0,
                'countByType' => ['all' => 0, 'coaster' => 0, 'park' => 0, 'user' => 0],
                'activeType' => $type,
                'error' => 'Search temporarily unavailable',
            ]);
        }
    }
}
