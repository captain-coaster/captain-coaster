<?php

namespace App\Controller;

use App\Entity\Park;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Class ParkController
 * @package App\Controller
 */
class ParkController extends AbstractController
{
    /**
     * Show park details
     *
     * @param Park $park
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/parks/{slug}", name="park_show", methods={"GET"}, options = {"expose" = true})
     */
    public function showAction(Park $park)
    {
        return $this->render('Park/show.html.twig', ['park' => $park]);
    }
}
