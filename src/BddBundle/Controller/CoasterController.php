<?php

namespace BddBundle\Controller;

use BddBundle\Entity\Coaster;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;


/**
 * Class CoasterController
 * @package BddBundle\Controller
 */
class CoasterController extends Controller
{
    /**
     * @Route("/coaster/{slug}", name="bdd_show_coaster")
     * @Method({"GET"})
     *
     * @param Coaster $coaster
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showAction(Coaster $coaster)
    {
        return $this->render(
            'BddBundle:Coaster:coaster.html.twig',
            array(
                'coaster' => $coaster,
            )
        );
    }

}
