<?php

namespace App\Controller;

use App\Entity\Coaster;
use App\Entity\Manufacturer;
use App\Entity\Park;
use App\Entity\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class MapsController
 * @package App\Controller
 */
#[Route(path: '/map')]
class MapsController extends AbstractController
{
    /**
     * Map of all coasters, with filters
     *
     *
     */
    #[Route(path: '/', name: 'map_index', methods: ['GET'])]
    public function indexAction(Request $request): \Symfony\Component\HttpFoundation\Response
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
                'markers' => json_encode($this->getMarkers($initialFilters), JSON_THROW_ON_ERROR),
                'filters' => $initialFilters,
                'filtersForm' => $this->getFiltersForm(),
                'parkId' => $parkId,
            ]
        );
    }

    /**
     * Map of coasters ridden by a user
     */
    #[Route(path: '/users/{id}', name: 'map_user', methods: ['GET'])]
    public function userMapAction(User $user): \Symfony\Component\HttpFoundation\Response
    {
        if (!$user->isEnabled()) {
            throw new NotFoundHttpException();
        }

        $initialFilters = [
            "ridden" => "on",
            "user" => $user->getId(),
        ];

        return $this->render(
            'Maps/userMap.html.twig',
            [
                'markers' => json_encode($this->getMarkers($initialFilters), JSON_THROW_ON_ERROR),
                'filters' => $initialFilters,
                'filtersForm' => $this->getFiltersForm(),
            ]
        );
    }

    /**
     * Returns json data with markers filtered
     *
     * @return JsonResponse
     */
    #[Route(path: '/markers', name: 'map_markers_ajax', methods: ['GET'], condition: 'request.isXmlHttpRequest()', options: ['expose' => true])]
    public function markersAction(Request $request)
    {
        return new JsonResponse($this->getMarkers($request->get('filters', [])));
    }

    /**
     * Get coasters data (when user clicks on a marker)
     */
    #[Route(path: '/parks/{id}/coasters', name: 'map_coasters_ajax', methods: ['GET'], condition: 'request.isXmlHttpRequest()', options: ['expose' => true])]
    public function getCoastersAction(Request $request, Park $park): \Symfony\Component\HttpFoundation\Response
    {
        $filters = $request->get('filters', []);

        $coasters = $this->getDoctrine()->getRepository(Coaster::class)->getCoastersForMap(
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
            ->getRepository(Coaster::class)
            ->getFilteredMarkers($filters);
    }

    /**
     * Get data to display filter form (mainly <select> data)
     *
     * @return array
     */
    private function getFiltersForm()
    {
        return ['manufacturer' => $this->getDoctrine()
            ->getRepository(Manufacturer::class)
            ->findBy([], ["name" => "asc"]), 'openingDate' => $this->getDoctrine()
            ->getRepository(Coaster::class)
            ->getDistinctOpeningYears()];
    }
}
