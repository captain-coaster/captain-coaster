<?php

namespace App\Controller;

use App\Entity\Liste;
use App\Entity\User;
use App\Form\Type\ListeCustomType;
use App\Form\Type\ListeType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ListeController
 *
 * @package App\Controller
 * @Route("/lists")
 */
class ListeController extends Controller
{
    /**
     * Displays all lists
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Route("/", name="liste_list")
     * @Method({"GET"})
     */
    public function listAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $query = $em->getRepository('App:Liste')->findAllCustomLists();

        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $query,
            $request->get('page', 1),
            9,
            ['wrap-queries' => true]
        );

        return $this->render(
            'Liste/list.html.twig',
            [
                'listes' => $pagination,
            ]
        );
    }

    /**
     * Creates a new custom list
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     *
     * @Route("/new", name="liste_new")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_USER')")
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
            'Liste/new.html.twig',
            [
                'form' => $form->createView(),
            ]
        );
    }

    /**
     * Edits details of a list (name...)
     *
     * @param Request $request
     * @param Liste $liste
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     *
     * @Route("/{id}/edit-details", name="liste_edit_details")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_USER')")
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
            'Liste/edit-details.html.twig',
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
     * @Method({"GET"}).
     * @Security("is_granted('ROLE_USER')")
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
     * @param Request $request
     * @param Liste $liste
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     *
     * @Route("/{id}/edit", name="liste_edit")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_USER')")
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

            // need to update manually because only ListeCoaster changes
            $liste->setUpdatedAt(new \DateTime());
            $em->persist($liste);
            $em->flush();

            return $this->redirectToRoute('liste_show', ['id' => $liste->getId()]);
        }

        return $this->render(
            'Liste/edit.html.twig',
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
     * @Security("is_granted('ROLE_USER')")
     */
    public function mainListAction()
    {
        $user = $this->getUser();

        $liste = $this
            ->getDoctrine()
            ->getRepository('App:Liste')
            ->findOneBy(['user' => $user]);

        if (!$liste instanceof Liste) {
            return $this->redirectToRoute('liste_create');
        }

        return $this->redirectToRoute('liste_show', ['id' => $liste->getId()]);
    }

    /**
     * Deletes a custom list
     *
     * @param Liste $liste
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     *
     * @Route("/{id}/delete", name="liste_delete")
     * @Method({"GET"})
     * @Security("is_granted('ROLE_USER')")
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
     * @param EntityManagerInterface $em
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     *
     * @Route("/{id}", name="liste_show")
     * @Method({"GET"})
     */
    public function showAction(Liste $liste, EntityManagerInterface $em)
    {
        try {
            $liste = $em->getRepository('App:Liste')->getListeWithData($liste);
        } catch (NoResultException $e) {
            // if we cannot get Liste with all data, $liste is still defined
        }

        return $this->render(
            'Liste/show.html.twig',
            [
                'liste' => $liste,
            ]
        );
    }

    /**
     * Ajax route for autocomplete search (search "q" parameter)
     *
     * @param Request $request
     * @param EntityManagerInterface $em
     * @return JsonResponse
     *
     * @Route(
     *     "/search/coasters.json",
     *     name="coaster_search_json",
     *     options = {"expose" = true},
     *     condition="request.isXmlHttpRequest()"
     * )
     * @Method({"GET"})
     * @Security("is_granted('ROLE_USER')")
     */
    public function ajaxSearchAction(Request $request, EntityManagerInterface $em)
    {
        if (!$request->get("q")) {
            return new JsonResponse([]);
        }

        return new JsonResponse(
            [
                "items" => $em->getRepository('App:Coaster')->suggestCoasterForListe(
                    $request->get("q"),
                    $this->getUser()
                ),
            ]
        );
    }
}
