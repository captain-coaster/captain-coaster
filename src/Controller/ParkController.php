<?php

namespace App\Controller;

use App\Entity\Park;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 * Class ParkController
 * @package App\Controller
 */
class ParkController extends Controller
{
    /**
     * Show park details
     *
     * @param Park $park
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/parks/{slug}", name="park_show", options = {"expose" = true})
     * @Method({"GET"})
     */
    public function showAction(Park $park)
    {
        return $this->render('Park/show.html.twig', ['park' => $park]);
    }
}
