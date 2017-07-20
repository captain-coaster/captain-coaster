<?php

namespace BddBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

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
            array(
                'markers' => json_encode($this->getMarkers()),
            )
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
}
