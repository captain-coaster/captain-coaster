<?php

namespace App\Controller;

use App\Entity\Park;
use App\Entity\User;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class MapsController
 * @package App\Controller
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
        $initialFilters = ["status" => "on"];

        return $this->render(
            'Maps/index.html.twig',
            [
                'markers' => json_encode($this->getMarkers($initialFilters)),
                'filters' => $initialFilters,
                'filtersForm' => $this->getFiltersForm(),
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
            'Maps/userMap.html.twig',
            [
                'markers' => json_encode($this->getMarkers($initialFilters)),
                'filters' => $initialFilters,
                'filtersForm' => $this->getFiltersForm(),
            ]
        );
    }

    /**
     * Returns json data with markers filtered
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/markers",
     *     name="map_markers_ajax",
     *     condition="request.isXmlHttpRequest()",
     *     options = {"expose" = true}
     * )
     * @Method({"GET"})
     */
    public function markersAction(Request $request)
    {
        return new JsonResponse($this->getMarkers($request->get('filters', [])));
    }

    /**
     * Get coasters data (when user clicks on a marker)
     *
     * @param Request $request
     * @param Park $park
     * @return Response
     * @Route("/parks/{id}/coasters",
     *     name="map_coasters_ajax",
     *     condition="request.isXmlHttpRequest()",
     *     options = {"expose" = true}
     *     )
     * @Method({"GET"})
     */
    public function getCoastersAction(Request $request, Park $park)
    {
        $filters = $request->get('filters', []);

        $coasters = $this->getDoctrine()->getRepository('App:Coaster')->getCoastersForMap(
            $park,
            $filters
        );

        return $this->render('Maps/listCoasters.html.twig', ['coasters' => $coasters]);
    }

    /**
     * Return array of markers, filtered
     *
     * @param array $filters
     * @return array
     */
    private function getMarkers($filters = [])
    {
        return $this->getDoctrine()
            ->getRepository('App:Coaster')
            ->getFilteredMarkers($filters);
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
