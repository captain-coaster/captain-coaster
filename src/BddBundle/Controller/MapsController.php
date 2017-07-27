<?php

namespace BddBundle\Controller;

use BddBundle\Entity\Park;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
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
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/", name="map_index")
     * @Method({"GET"})
     * @Security("is_granted('ROLE_PREVIEW_FEATURE')")
     */
    public function indexAction()
    {
        return $this->render(
            '@Bdd/Maps/index.html.twig',
            [
                'markers' => json_encode($this->getMarkers()),
                'filters' => $this->getFilters(),
            ]
        );
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @Route("/markers", name="map_markers_ajax", condition="request.isXmlHttpRequest()")
     * @Method({"GET"})
     * @Security("is_granted('ROLE_PREVIEW_FEATURE')")
     */
    public function markersAction(Request $request)
    {
        $user = $this->getUser();
        $filters = $request->get('filters', []);

        return new JsonResponse($this->getMarkers($filters, $user));
    }

    /**
     * @param Request $request
     * @param Park $park
     * @return Response
     * @Route("/parks/{id}/coasters", name="map_coasters_ajax", condition="request.isXmlHttpRequest()")
     * @Method({"GET"})
     * @Security("is_granted('ROLE_PREVIEW_FEATURE')")
     */
    public function getCoastersAction(Request $request, Park $park)
    {
        $user = $this->getUser();
        $filters = $request->get('filters', []);

        $coasters = $this->getDoctrine()->getRepository('BddBundle:Coaster')->getCoastersForMap(
            $park,
            $filters,
            $user
        );

        return $this->render('@Bdd/Maps/listCoasters.html.twig', ['coasters' => $coasters]);
    }

    /**
     * @param array $filters
     * @param null $user
     * @return array
     */
    private function getMarkers($filters = [], $user = null)
    {
        return $this->getDoctrine()
            ->getRepository('BddBundle:Coaster')
            ->getFilteredMarkers($filters, $user);
    }

    /**
     *
     * @return array
     */
    private function getFilters()
    {
        return $this->getDoctrine()
            ->getRepository('BddBundle:Manufacturer')
            ->findBy([], ["name" => "asc"]);
    }
}
