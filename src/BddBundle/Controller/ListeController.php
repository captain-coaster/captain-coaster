<?php

namespace BddBundle\Controller;

use BddBundle\Entity\Liste;
use BddBundle\Entity\ListeCoaster;
use BddBundle\Form\Type\ListeCustomType;
use BddBundle\Form\Type\ListeType;
use Doctrine\Common\Collections\ArrayCollection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ListeController
 *
 * @package BddBundle\Controller
 * @Route("/lists")
 */
class ListeController extends Controller
{
    /**
     * Displays all lists
     *
     * @Route("/", name="liste_list")
     * @Method({"GET"})
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function listAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $query = $em->getRepository('BddBundle:Liste')->findAllCustomLists();

        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $query,
            $request->get('page', 1),
            9,
            ['wrap-queries' => true]
        );

        return $this->render(
            'BddBundle:Liste:list.html.twig',
            [
                'listes' => $pagination,
            ]
        );
    }

    /**
     * Creates a new custom list
     *
     * @Route("/new", name="liste_new")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_USER')")
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function newAction(Request $request)
    {
        $liste = new Liste();

        /** @var Form $form */
        $form = $this->createForm(ListeCustomType::class, $liste);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $liste->setMain(false);
            $liste->setUser($this->getUser());

            $em = $this->getDoctrine()->getManager();
            $em->persist($liste);
            $em->flush();

            return $this->redirectToRoute('liste_edit', ['id' => $liste->getId()]);
        }

        return $this->render(
            'BddBundle:Liste:new.html.twig',
            [
                'form' => $form->createView(),
            ]
        );
    }

    /**
     * Edits details of a list (name...)
     *
     * @Route("/{id}/edit-details", name="liste_edit_details")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_USER')")
     *
     * @param Request $request
     * @param Liste $liste
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function editDetailsAction(Request $request, Liste $liste)
    {
        $this->denyAccessUnlessGranted('edit-details', $liste);

        /** @var Form $form */
        $form = $this->createForm(ListeCustomType::class, $liste);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em = $this->getDoctrine()->getManager();
            $em->persist($liste);
            $em->flush();

            return $this->redirectToRoute('liste_show', ['id' => $liste->getId()]);
        }

        return $this->render(
            'BddBundle:Liste:edit-details.html.twig',
            [
                'form' => $form->createView(),
            ]
        );
    }

    /**
     * Create new main user's list
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Route("/create", name="liste_create")
     * @Method({"GET"})
     */
    public function createAction()
    {
        $liste = new Liste();
        $liste->setName('Top Coasters');
        $liste->setType('main');
        $liste->setMain(true);
        $liste->setUser($this->getUser());

        $em = $this->getDoctrine()->getManager();
        $em->persist($liste);
        $em->flush();

        return $this->redirectToRoute('liste_edit', ['id' => $liste->getId()]);
    }

    /**
     * Edits a list
     *
     * @Route("/{id}/edit", name="liste_edit")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_USER')")
     *
     * @param Request $request
     * @param Liste $liste
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function editAction(Request $request, Liste $liste)
    {
        $this->denyAccessUnlessGranted('edit', $liste);

        $originalCoasters = new ArrayCollection();
        foreach ($liste->getListeCoasters() as $coaster) {
            $originalCoasters->add($coaster);
        }

        /** @var Form $form */
        $form = $this->createForm(ListeType::class, $liste);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em = $this->getDoctrine()->getManager();

            foreach ($originalCoasters as $coaster) {
                if (false === $liste->getListeCoasters()->contains($coaster)) {
                    $em->remove($coaster);
                }
            }

            $em->persist($liste);
            $em->flush();

            return $this->redirectToRoute('liste_show', ['id' => $liste->getId()]);
        }

        return $this->render(
            'BddBundle:Liste:edit.html.twig',
            [
                'form' => $form->createView(),
                'listName' => $liste->getName(),
            ]
        );
    }

    /**
     * Shortcut to user's personal main list
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Route("/me", name="liste_me")
     * @Method({"GET"})
     */
    public function mainListAction()
    {
        $user = $this->getUser();

        $liste = $this
            ->getDoctrine()
            ->getRepository('BddBundle:Liste')
            ->findOneBy(['user' => $user]);

        if (!$liste instanceof Liste) {
            return $this->redirectToRoute('liste_create');
        }

        return $this->redirectToRoute('liste_edit', ['id' => $liste->getId()]);
    }

    /**
     * Deletes a custom list
     *
     * @param Liste $liste
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     *
     * @Route("/{id}/delete", name="liste_delete")
     * @Method({"GET"})
     */
    public function deleteAction(Liste $liste)
    {
        $this->denyAccessUnlessGranted('delete', $liste);

        $em = $this->getDoctrine()->getManager();
        $em->remove($liste);
        $em->flush();

        return $this->redirectToRoute('me');
    }

    /**
     * Display a list
     *
     * @param Liste $liste
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/{id}", name="liste_show")
     * @Method({"GET"})
     */
    public function showAction(Liste $liste)
    {
        $ids = [];
        /** @var ListeCoaster $listeCoaster */
        foreach ($liste->getListeCoasters() as $listeCoaster) {
            $ids[] = $listeCoaster->getCoaster()->getId();
        }

        return $this->render(
            'BddBundle:Liste:show.html.twig',
            [
                'liste' => $liste,
            ]
        );
    }
}
