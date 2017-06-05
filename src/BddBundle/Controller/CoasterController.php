<?php

namespace BddBundle\Controller;

use BddBundle\Entity\Coaster;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;


/**
 * Class CoasterController
 * @package BddBundle\Controller
 */
class CoasterController extends Controller
{
    /**
     * @Route("/coaster/new", name="bdd_new_coaster")
     * @Method({"GET", "POST"})
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function newAction(Request $request)
    {
        $coaster = new Coaster();

        /** @var Form $form */
        $form = $this->createFormBuilder($coaster)
            ->add('name', TextType::class)
            ->add('save', SubmitType::class, array('label' => 'Create Coaster'))
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $coaster = $form->getData();
        }

        return $this->render(
            'BddBundle:Coaster:new.html.twig',
            array(
                'form' => $form->createView(),
            )
        );
    }

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
            'BddBundle:Coaster:show.html.twig',
            array(
                'coaster' => $coaster,
            )
        );
    }
}
