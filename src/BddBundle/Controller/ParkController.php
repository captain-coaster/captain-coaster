<?php

namespace BddBundle\Controller;

use BddBundle\Entity\Coaster;
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
     * Create a new park.
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Route("/parks/new", name="park_new")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_CONTRIBUTOR')")
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

            return $this->redirectToRoute('park_show', ['slug' => $park->getSlug()]);
        }

        return $this->render(
            'BddBundle:Park:new.html.twig',
            [
                'form' => $form->createView(),
            ]
        );
    }

    /**
     * Edit a park.
     *
     * @param Request $request
     * @param Park $park
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Route("/parks/{slug}/edit", name="park_edit")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_CONTRIBUTOR')")
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

            return $this->redirectToRoute('park_show', ['slug' => $park->getSlug()]);
        }

        return $this->render(
            'BddBundle:Park:new.html.twig',
            [
                'form' => $form->createView(),
            ]
        );
    }

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
        $ids = [];
        /** @var Coaster $coaster */
        foreach ($park->getCoasters() as $coaster) {
            $ids[] = $coaster->getId();
        }

        return $this->render(
            'BddBundle:Park:show.html.twig',
            [
                'park' => $park,
            ]
        );
    }
}
