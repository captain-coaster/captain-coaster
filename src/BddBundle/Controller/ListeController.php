<?php

namespace BddBundle\Controller;

use BddBundle\Entity\Liste;
use BddBundle\Entity\ListeCoaster;
use BddBundle\Form\Type\ListeType;
use BddBundle\Service\ImageService;
use Doctrine\Common\Collections\ArrayCollection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ListeController
 * @package BddBundle\Controller
 * @Route("/list")
 */
class ListeController extends Controller
{
    /**
     * @Route("/{id}/edit", name="liste_edit")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_USER')")
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
            ]
        );
    }

    /**
     *
     * @Route("/me", name="liste_me")
     * @Method({"GET"})
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function userMainAction()
    {
        $user = $this->getUser();

        $liste = $this
            ->getDoctrine()
            ->getRepository('BddBundle:Liste')
            ->findOneBy(['user' => $user]);

        if (!$liste instanceof Liste) {
            return $this->redirectToRoute('liste_create');
        }

        return $this->showAction($liste);
    }

    /**
     * @Route("/create", name="liste_create")
     * @Method({"GET"})
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function createAction()
    {
        $liste = new Liste();
        $liste->setName('Top Coasters');
        $liste->setType('topcoasters');
        $liste->setUser($this->getUser());

        $em = $this->getDoctrine()->getManager();
        $em->persist($liste);
        $em->flush();

        return $this->redirectToRoute('liste_edit', ['id' => $liste->getId()]);
    }

    /**
     *
     * @Route("/{id}", name="liste_show")
     * @Method({"GET"})
     * @param Liste $liste
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showAction(Liste $liste)
    {
        $ids = [];
        /** @var ListeCoaster $listeCoaster */
        foreach ($liste->getListeCoasters() as $listeCoaster) {
            $ids[] = $listeCoaster->getCoaster()->getId();
        }

        $imageUrls = $this->get(ImageService::class)->getMultipleImagesUrl($ids);

        return $this->render(
            'BddBundle:Liste:show.html.twig',
            [
                'liste' => $liste,
                'images' => $imageUrls,
            ]
        );
    }
}
