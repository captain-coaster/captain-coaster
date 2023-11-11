<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Park;
use App\Entity\User;
use App\Repository\CoasterRepository;
use App\Repository\ManufacturerRepository;
use App\Repository\ParkRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

#[Route(path: '/map')]
class MapsController extends AbstractController
{
    public function __construct(
        private readonly ManufacturerRepository $manufacturerRepository,
        private readonly CoasterRepository $coasterRepository,
        private readonly ParkRepository $parkRepository,
    ) {
    }

    /** Map of all coasters, with initial filters. */
    #[Route(path: '/', name: 'map_index', methods: ['GET'])]
    public function indexAction(string $parkslug = null): Response
    {
        $initialFilters = ['status' => 'on'];
        $parkId = '';

        if ($parkslug) {
            $park = $this->parkRepository->findOneBy(['slug' => $parkslug]);
            if (!empty($park)) {
                $parkId = $park->getId();
            }
        }

        return $this->render(
            'Maps/index.html.twig',
            [
                'markers' => $this->getMarkers($initialFilters),
                'filters' => $initialFilters,
                'filtersForm' => $this->getFiltersForm(),
                'parkId' => $parkId,
            ]
        );
    }

    /** Map of coasters ridden by a user. */
    #[Route(path: '/users/{id}', name: 'map_user', methods: ['GET'])]
    public function userMapAction(User $user): Response
    {
        if (!$user->isEnabled()) {
            throw new NotFoundHttpException();
        }

        $initialFilters = [
            'ridden' => 'on',
            'user' => $user->getId(),
        ];

        return $this->render(
            'Maps/userMap.html.twig',
            [
                'markers' => $this->getMarkers($initialFilters),
                'filters' => $initialFilters,
                'filtersForm' => $this->getFiltersForm(),
            ]
        );
    }

    /** Returns json data with markers filtered. */
    #[Route(path: '/markers', name: 'map_markers_ajax', options: ['expose' => true], methods: ['GET'], condition: 'request.isXmlHttpRequest()')]
    public function markersAction(#[MapQueryParameter] array $filters = []): JsonResponse
    {
        return new JsonResponse($this->getMarkers($filters), json: true);
    }

    /** Get coasters in a park (when user clicks on a marker). */
    #[Route(path: '/parks/{id}/coasters', name: 'map_coasters_ajax', options: ['expose' => true], methods: ['GET'], condition: 'request.isXmlHttpRequest()')]
    public function getCoastersAction(Park $park, array $filters = []): Response
    {
        return $this->render(
            'Maps/listCoasters.html.twig',
            ['coasters' => $this->coasterRepository->getCoastersForMap($park, $filters)]
        );
    }

    /** Get data to display filter form (mainly <select> data). */
    private function getFiltersForm(): array
    {
        return [
            'manufacturer' => $this->manufacturerRepository->findBy([], ['name' => 'asc']),
            'openingDate' => $this->coasterRepository->getDistinctOpeningYears(),
        ];
    }

    /** Generate array of markers, based on array of filters */
    private function getMarkers(array $filters = []): string
    {
        return (new Serializer([], [new JsonEncoder()]))->serialize(
            $this->coasterRepository->getFilteredMarkers($filters),
            'json'
        );
    }
}
