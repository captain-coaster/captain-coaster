<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Park;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ParkController.
 */
class ParkController extends AbstractController
{
    /**
     * Show park details.
     */
    #[Route(path: '/parks/{slug}', name: 'park_show', methods: ['GET'], options: ['expose' => true])]
    public function showAction(Park $park): \Symfony\Component\HttpFoundation\Response
    {
        $closestParks = $this->getDoctrine()
            ->getRepository(Park::class)
            ->getClosestParks($park, 80, 300);

        return $this->render('Park/show.html.twig', ['park' => $park, 'closestParks' => $closestParks]);
    }
}
