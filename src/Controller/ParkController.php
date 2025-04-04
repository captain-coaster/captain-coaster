<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Park;
use App\Repository\CoasterRepository;
use App\Repository\ParkRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[\Symfony\Component\Routing\Attribute\Route(path: '/parks')]
class ParkController extends AbstractController
{
    /** Redirects to index */
    #[\Symfony\Component\Routing\Attribute\Route(path: '/', name: 'park_index', methods: ['GET'])]
    public function index(): RedirectResponse
    {
        return $this->redirectToRoute('bdd_index');
    }

    /** Show park details. */
    #[Route(path: '/{id}/{slug}', name: 'park_show', options: ['expose' => true], methods: ['GET'])]
    public function showAction(
        ParkRepository $parkRepository,
        Park $park,
        CoasterRepository $coasterRepository
    ): Response {
        return $this->render(
            'Park/show.html.twig',
            [
                'park' => $park,
                'coasters' => $coasterRepository->findAllCoastersInPark($park),
                'closestParks' => $parkRepository->getClosestParks($park, 80, 300),
            ]
        );
    }

    /** Redirect old urls to above */
    #[\Symfony\Component\Routing\Attribute\Route(path: '/{slug}', name: 'redirect_park_show', options: ['expose' => true], methods: ['GET'])]
    public function redirectCoaster(Park $park): RedirectResponse
    {
        return $this->redirectToRoute('park_show', [
            'id' => $park->getId(),
            'slug' => $park->getSlug(),
        ], 301);
    }
}
