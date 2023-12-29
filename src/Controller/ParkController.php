<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Park;
use App\Repository\CoasterRepository;
use App\Repository\ParkRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ParkController extends AbstractController
{
    /** Show park details. */
    #[Route(path: '/parks/{slug}', name: 'park_show', options: ['expose' => true], methods: ['GET'])]
    public function showAction(ParkRepository $parkRepository, Park $park, Request $request, CoasterRepository $coasterRepository): Response
    {
        $is_imperial = $coasterRepository->isImperial($request->getLocale());
        if ($is_imperial) {

            foreach ($park->getCoasters() as $coaster) {
                $coasterRepository->transformStatsToImperial($coaster);
            }
        }

        return $this->render(
            'Park/show.html.twig',
            ['park' => $park, 'closestParks' => $parkRepository->getClosestParks($park, 80, 300), 'is_imperial' => $is_imperial]
        );
    }
}
