<?php

namespace BddBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class RankingController
 * @package BddBundle\Controller
 * @Route("/ranking")
 */
class RankingController extends Controller
{
    /**
     * Show ranking of best coasters
     *
     * @param EntityManagerInterface $em
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/", name="ranking_index")
     * @Method({"GET"})
     */
    public function indexAction(EntityManagerInterface $em)
    {
        $ranking = $em->getRepository('BddBundle:Ranking')->findCurrent();

        $nextRankingDate = new \DateTime('first day of next month midnight 1 minute');
        if ($nextRankingDate->diff(new \DateTime('now'), true)->format('%h') < 1) {
            $nextRankingDate = null;
        }

        return $this->render(
            '@Bdd/Ranking/index.html.twig',
            [
                'ranking' => $ranking,
                'nextRankingDate' => $nextRankingDate,
                'filtersForm' => $this->getFiltersForm(),
            ]
        );
    }

    /**
     * @Route(
     *     "/coasters",
     *     name="ranking_search_async",
     *     options = {"expose" = true},
     *     condition="request.isXmlHttpRequest()"
     * )
     * @Method({"GET"})
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function searchAsyncAction(Request $request)
    {
        $filters = $request->get('filters', []);
        $page = $request->get('page', 1);

        return $this->render(
            'BddBundle:Ranking:results.html.twig',
            [
                'coasters' => $this->getCoasters($filters, $page),
                // array_filter removes empty filters e.g. ['continent' => '']
                'filtered' => count(array_filter($filters, "strlen")) > 0,
            ]
        );
    }

    /**
     * @param array $filters
     * @param int $page
     * @return \Knp\Component\Pager\Pagination\PaginationInterface
     */
    private function getCoasters($filters = [], $page = 1)
    {
        $query = $this->getDoctrine()
            ->getRepository('BddBundle:Ranking')
            ->findCoastersRanked($filters);

        $paginator = $this->get('knp_paginator');
        return $paginator->paginate(
            $query,
            $page,
            20
        );
    }

    /**
     * Learn more on the ranking
     *
     * @Route("/learn-more", name="ranking_learn_more")
     * @Method({"GET"})
     */
    public function learnMore()
    {
        return $this->render('@Bdd/Ranking/learn_more.html.twig');
    }

    /**
     * Get data to display filter form (mainly <select> data)
     *
     * @return array
     */
    private function getFiltersForm()
    {
        $filtersForm = [];

        $filtersForm['continent'] = $this->getDoctrine()
            ->getRepository('BddBundle:Continent')
            ->findBy([], ["name" => "asc"]);

        $filtersForm['materialType'] = $this->getDoctrine()
            ->getRepository('BddBundle:MaterialType')
            ->findAll();

        return $filtersForm;
    }
}
