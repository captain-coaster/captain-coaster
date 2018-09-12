<?php

namespace BddBundle\Controller;

use BddBundle\Entity\Park;
use BddBundle\Entity\User;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class MapsController
 * @package BddBundle\Controller
 * @Route("/map")
 */
class MapsController extends Controller
{
    /**
     * Map of all coasters, with filters
     *
     * @Route("/", name="map_index")
     * @Method({"GET"})
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction()
    {
        return $this->render(
            '@Bdd/Maps/index.html.twig',
            [
                'markers' => json_encode($this->getMarkers()),
                'filters' => $this->getFilters(["status" => "on"]),
            ]
        );
    }

    /**
     * Map of coasters ridden by a user
     *
     * @param User $user
     * @return Response
     *
     * @Route("/users/{id}", name="map_user")
     * @Method({"GET"})
     */
    public function userMapAction(User $user)
    {
        $initialFilters = [
            "ridden" => "on",
            "user" => $user->getId(),
        ];

        return $this->render(
            '@Bdd/Maps/userMap.html.twig',
            [
                'markers' => json_encode($this->getMarkers($initialFilters)),
                'filters' => $this->getFilters($initialFilters),
            ]
        );
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @Route("/markers", name="map_markers_ajax", condition="request.isXmlHttpRequest()")
     * @Method({"GET"})
     */
    public function markersAction(Request $request)
    {
        $filters = $request->get('filters', []);

        return new JsonResponse($this->getMarkers($filters));
    }

    /**
     * Display coasters when you click on a park
     *
     * @param Request $request
     * @param Park $park
     * @return Response
     * @Route("/parks/{id}/coasters", name="map_coasters_ajax", condition="request.isXmlHttpRequest()")
     * @Method({"GET"})
     */
    public function getCoastersAction(Request $request, Park $park)
    {
        $filters = $request->get('filters', []);

        $coasters = $this->getDoctrine()->getRepository('BddBundle:Coaster')->getCoastersForMap(
            $park,
            $filters
        );

        return $this->render('@Bdd/Maps/listCoasters.html.twig', ['coasters' => $coasters]);
    }

    /**
     * @param array $filters
     * @return array
     */
    private function getMarkers($filters = [])
    {
        return $this->getDoctrine()
            ->getRepository('BddBundle:Coaster')
            ->getFilteredMarkers($filters);
    }

    /**
     * @param array $initialFilters
     * @return array
     */
    private function getFilters(array $initialFilters = [])
    {
        $filters = [];

        $filters['manufacturer'] = $this->getDoctrine()
            ->getRepository('BddBundle:Manufacturer')
            ->findBy([], ["name" => "asc"]);

        $filters['openingDate'] = $this->getDoctrine()
            ->getRepository('BddBundle:Coaster')
            ->getDistinctOpeningYears();

        return array_merge($filters, $initialFilters);
    }
}
