<?php

namespace App\Controller;

use App\Service\SearchService;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class SearchController
 * @package App\Controller
 * @Route("/search")
 */
class SearchController extends AbstractController
{
    CONST CACHE_AUTOCOMPLETE = 'main_autocomplete';

    /**
     * @Route("/", name="search_index", methods={"GET"})
     */
    public function indexAction()
    {
        return $this->render(
            'Search/index.html.twig',
            [
                'filters' => ["status" => "on"],
                'filtersForm' => $this->getFiltersForm(),
            ]
        );
    }

    /**
     * @Route(
     *     "/coasters",
     *     name="search_coasters_ajax",
     *     methods={"GET"},
     *     options = {"expose" = true},
     *     condition="request.isXmlHttpRequest()"
     * )
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function searchAction(Request $request)
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
     * All data for main search service
     *
     * @param SearchService $searchService
     * @return JsonResponse
     * @throws \Psr\Cache\InvalidArgumentException
     *
     * @Route(
     *     "/main.json",
     *     name="ajax_main_search",
     *     methods={"GET"},
     *     options = {"expose" = true},
     *     condition="request.isXmlHttpRequest()"
     * )
     */
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
        // disable temporarily
        //$response->setMaxAge('3600');
        //$response->setPublic();

        return $response;
    }

    /**
     * @param array $filters
     * @param int $page
     * @return array
     */
    private function getCoasters($filters = [], $page = 1)
    {
        $query = $this->getDoctrine()
            ->getRepository('App:Coaster')
            ->getSearchCoasters($filters);

        $paginator = $this->get('knp_paginator');

        return $paginator->paginate(
            $query,
            $page,
            20,
            ['wrap-queries' => true]
        );
    }

    /**
     * Get data to display filter form (mainly <select> data)
     *
     * @return array
     */
    private function getFiltersForm()
    {
        $filtersForm = [];

        $filtersForm['manufacturer'] = $this->getDoctrine()
            ->getRepository('App:Manufacturer')
            ->findBy([], ["name" => "asc"]);

        $filtersForm['openingDate'] = $this->getDoctrine()
            ->getRepository('App:Coaster')
            ->getDistinctOpeningYears();

        return $filtersForm;
    }
}
