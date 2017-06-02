<?php

namespace BddBundle\Controller;

use BddBundle\Entity\Coaster;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

class CoasterController extends Controller
{
    /**
     * @Route("/coaster/{slug}", name="bdd_show_coaster")
     */
    public function showAction(Coaster $coaster)
    {
        dump($coaster);
        exit;

        return $this->render('BddBundle:Coaster:coaster.html.twig', array(
            // ...
        ));
    }

}
