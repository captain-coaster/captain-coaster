<?php

namespace BddBundle\Controller;

use BddBundle\Service\SearchService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class SearchController
 * @package BddBundle\Controller
 * @Route("/search")
 */
class SearchController extends Controller
{
    CONST CACHE_AUTOCOMPLETE = 'main_autocomplete';

    /**
     * @Route("/", name="search_index")
     * @Method({"GET"})
     */
    public function indexAction()
    {
        return $this->render(
            'BddBundle:Search:index.html.twig',
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
     *     options = {"expose" = true},
     *     condition="request.isXmlHttpRequest()"
     * )
     * @Method({"GET"})
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function searchAction(Request $request)
    {
        $filters = $request->get('filters', []);
        $page = $request->get('page', 1);

        return $this->render(
            'BddBundle:Search:results.html.twig',
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
     *     options = {"expose" = true},
     *     condition="request.isXmlHttpRequest()"
     * )
     * @Method({"GET"})
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
            ->getRepository('BddBundle:Coaster')
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
            ->getRepository('BddBundle:Manufacturer')
            ->findBy([], ["name" => "asc"]);

        $filtersForm['openingDate'] = $this->getDoctrine()
            ->getRepository('BddBundle:Coaster')
            ->getDistinctOpeningYears();

        return $filtersForm;
    }
}
