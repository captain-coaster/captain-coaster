<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Park;
use App\Entity\User;
use App\Repository\CoasterRepository;
use App\Repository\ParkRepository;
use App\Service\FilterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/map')]
class MapsController extends AbstractController
{
    public function __construct(
        private readonly CoasterRepository $coasterRepository,
        private readonly ParkRepository $parkRepository,
        private readonly FilterService $filterService
    ) {
    }

    /** Map of all coasters, with initial filters. */
    #[Route(path: '/', name: 'map_index', methods: ['GET'])]
    public function indexAction(Request $request): Response
    {
        $parkslug = $request->get('parkslug');
        $parkId = '';

        // Get filters from URL or default to status=on
        $filters = $request->query->all('filters') ?: ['status' => 'on'];

        if ($parkslug) {
            $park = $this->parkRepository->findOneBy(['slug' => $parkslug]);
            if (!empty($park)) {
                $parkId = $park->getId();
                if (\count($park->getOpenedCoasters()) < 1) {
                    unset($filters['status']);
                }
            }
        }

        // Validate and authorize filters
        $validatedFilters = $this->filterService->validateAndAuthorize(
            $filters,
            'map',
            $this->getUser()
        );

        return $this->render(
            'Maps/index.html.twig',
            [
                'markers' => $this->getMarkers($validatedFilters),
                'filters' => $validatedFilters,
                'filtersForm' => $this->filterService->getFilterData(),
                'parkId' => $parkId,
                'meta_description' => 'map_index.description',
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

        // Validate and authorize initial filters
        $validatedFilters = $this->filterService->validateAndAuthorize(
            $initialFilters,
            'map',
            $this->getUser()
        );

        return $this->render(
            'Maps/index.html.twig',
            [
                'markers' => $this->getMarkers($validatedFilters),
                'filters' => $validatedFilters,
                'filtersForm' => $this->filterService->getFilterData(),
                'parkId' => '',
            ]
        );
    }

    /** Returns json data with markers filtered. */
    #[Route(path: '/markers', name: 'map_markers_ajax', options: ['expose' => true], methods: ['GET'], condition: 'request.isXmlHttpRequest()')]
    public function markersAction(#[MapQueryParameter] array $filters = []): JsonResponse
    {
        try {
            // Validate and authorize filters
            $validatedFilters = $this->filterService->validateAndAuthorize(
                $filters,
                'map',
                $this->getUser()
            );

            return new JsonResponse($this->getMarkers($validatedFilters));
        } catch (AccessDeniedHttpException $e) {
            throw $e;
        }
    }

    /**
     * Get coasters in a park (when user clicks on a marker).
     * Uses MapQueryParameter to properly handle filters[key] format from the form.
     */
    #[Route(path: '/parks/{id}/coasters', name: 'map_coasters_ajax', options: ['expose' => true], methods: ['GET'], condition: 'request.isXmlHttpRequest()')]
    public function getCoastersAction(Park $park, #[MapQueryParameter] array $filters = []): Response
    {
        try {
            // Validate and authorize filters
            $validatedFilters = $this->filterService->validateAndAuthorize(
                $filters,
                'map',
                $this->getUser()
            );

            $coasters = $this->coasterRepository->findForPark($park, $validatedFilters);

            return $this->render(
                'Maps/_map_popup.html.twig',
                ['coasters' => $coasters]
            );
        } catch (AccessDeniedHttpException $e) {
            throw $e;
        }
    }

    private function getMarkers(array $filters = []): array
    {
        return $this->coasterRepository->findForMap($filters);
    }
}
