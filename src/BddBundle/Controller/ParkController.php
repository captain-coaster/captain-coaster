<?php

namespace BddBundle\Controller;

use BddBundle\Entity\Park;
use BddBundle\Form\Type\ParkType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;


/**
 * Class ParkController
 * @package BddBundle\Controller
 */
class ParkController extends Controller
{
    /**
     * Create a new park
     *
     * @Route("/parks/new", name="bdd_new_park")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_CONTRIBUTOR')")
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function newAction(Request $request)
    {
        $park = new Park();

        /** @var Form $form */
        $form = $this->createForm(ParkType::class, $park);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($park);
            $em->flush();

            return $this->redirectToRoute('bdd_index_coaster');
        }

        return $this->render(
            'BddBundle:Park:new.html.twig',
            array(
                'form' => $form->createView(),
            )
        );
    }

    /**
     * Edit a park
     *
     * @Route("/parks/{slug}/edit", name="bdd_edit_park")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_CONTRIBUTOR')")
     *
     * @param Request $request
     * @param Park $park
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function editAction(Request $request, Park $park)
    {
        /** @var Form $form */
        $form = $this->createForm(ParkType::class, $park);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($park);
            $em->flush();

            return $this->redirectToRoute('bdd_index_coaster');
        }

        return $this->render(
            'BddBundle:Park:new.html.twig',
            array(
                'form' => $form->createView(),
            )
        );
    }
}
