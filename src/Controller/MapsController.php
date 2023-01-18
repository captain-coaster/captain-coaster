<?php

namespace App\Controller;

use App\Entity\Park;
use App\Entity\User;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class MapsController
 * @package App\Controller
 * @Route("/map")
 */
class MapsController extends AbstractController
{
    /**
     * Map of all coasters, with filters
     *
     * @param Request $request
     * @Route("/", name="map_index", methods={"GET"})
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(Request $request)
    {
        $initialFilters = ["status" => "on"];
        $parkId = "";

        $slug = $request->query->get("parkslug");
        if($slug) {
            $park = $this->getDoctrine()->getRepository(Park::class)->findOneBy(['slug' => $slug]);
            if(!empty($park)) {
                $parkId = $park->getId();
            }
        }

        return $this->render(
            'Maps/index.html.twig',
            [
                'markers' => json_encode($this->getMarkers($initialFilters)),
                'filters' => $initialFilters,
                'filtersForm' => $this->getFiltersForm(),
                'parkId' => $parkId,
                'meta_description' => 'map_index.description',
            ]
        );
    }

    /**
     * Map of coasters ridden by a user
     *
     * @param User $user
     * @return Response
     *
     * @Route("/users/{id}", name="map_user", methods={"GET"})
     */
    public function userMapAction(User $user)
    {
        $initialFilters = [
            "ridden" => "on",
            "user" => $user->getId(),
        ];

        return $this->render(
            'Maps/index.html.twig',
            [
                'markers' => json_encode($this->getMarkers($initialFilters)),
                'filters' => $initialFilters,
                'filtersForm' => $this->getFiltersForm(),
                'parkId' => '',
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
     *     methods={"GET"},
     *     condition="request.isXmlHttpRequest()",
     *     options = {"expose" = true}
     * )
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
     *     methods={"GET"},
     *     condition="request.isXmlHttpRequest()",
     *     options = {"expose" = true}
     *     )
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
