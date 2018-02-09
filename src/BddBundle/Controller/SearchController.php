<?php

namespace BddBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class SearchController
 * @package BddBundle\Controller
 * @Route("/search")
 */
class SearchController extends Controller
{
    /**
     * @Route("/autocomplete/all.json", name="search_autocomplete_all")
     * @Method({"GET"})
     */
    public function autocompleteAll()
    {
        $searchService = $this->get('BddBundle\Service\SearchService');

        return $searchService->searchAllJson();
    }


    /**
     * @Route("/", name="search_index")
     * @Method({"GET"})
     */
    public function indexAction()
    {
        return $this->render(
            'BddBundle:Search:index.html.twig',
            [
                'filters' => [],
                'filtersForm' => $this->getFiltersForm()
            ]
        );
    }

    /**
     * @Route(
     *     "/coasters",
     *     name="search_coasters_ajax",
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
     * @param array $filters
     * @param int   $page
     * @return array
     */
    private function getCoasters($filters = [], $page = 1)
    {
        $query = $this->getDoctrine()
            ->getRepository('BddBundle:Coaster')
            ->getFilteredCoasters($filters);

        $paginator = $this->get('knp_paginator');

        return $paginator->paginate(
            $query,
            $page,
            30
        );
    }

    /**
     * @return array
     */
    private function getFiltersForm()
    {
        $filters = [];

        $filters['manufacturer'] = $this->getDoctrine()
            ->getRepository('BddBundle:Manufacturer')
            ->findBy([], ["name" => "asc"]);

        $filters['openingDate'] = $this->getDoctrine()
            ->getRepository('BddBundle:Coaster')
            ->getDistinctOpeningYears();

        return $filters;
    }
}
