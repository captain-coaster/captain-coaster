<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Park;
use App\Repository\CoasterRepository;
use App\Repository\ParkRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ParkController extends AbstractController
{
    /** Show park details. */
    #[Route(path: '/parks/{slug}', name: 'park_show', options: ['expose' => true], methods: ['GET'])]
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
}
