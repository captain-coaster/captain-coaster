<?php

namespace BddBundle\Controller;

use BddBundle\Entity\Coaster;
use BddBundle\Form\Type\CoasterType;
use BddBundle\Service\ImageService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;


/**
 * Class CoasterController
 * @package BddBundle\Controller
 */
class CoasterController extends Controller
{
    /**
     * Shows a specific coaster defined in conf
     *
     * @Route("/", name="bdd_index_coaster")
     * @Method({"GET"})
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction()
    {
        $coaster = $this
            ->getDoctrine()
            ->getRepository('BddBundle:Coaster')
            ->findOneBy(['id' => $this->getParameter('default_coaster_id')]);

        return $this->showAction($coaster);
    }

    /**
     * Create a new coaster
     *
     * @Route("/coasters/new", name="bdd_new_coaster")
     * @Method({"GET", "POST"})
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function newAction(Request $request)
    {
        $coaster = new Coaster();

        /** @var Form $form */
        $form = $this->createForm(CoasterType::class, $coaster);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($coaster);
            $em->flush();

            return $this->redirectToRoute('bdd_show_coaster', ['slug' => $coaster->getSlug()]);
        }

        return $this->render(
            'BddBundle:Coaster:create.html.twig',
            array(
                'form' => $form->createView(),
            )
        );
    }

    /**
     * Edit a coaster
     *
     * @Route("/coasters/{slug}/edit", name="bdd_edit_coaster")
     * @Method({"GET", "POST"})
     *
     * @param Request $request
     * @param Coaster $coaster
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function editAction(Request $request, Coaster $coaster)
    {
        /** @var Form $form */
        $form = $this->createForm(CoasterType::class, $coaster);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($coaster);
            $em->flush();

            return $this->redirectToRoute('bdd_show_coaster', ['slug' => $coaster->getSlug()]);
        }

        return $this->render(
            'BddBundle:Coaster:create.html.twig',
            array(
                'form' => $form->createView(),
            )
        );
    }

    /**
     * Show details of a coaster
     *
     * @Route("/coasters/{slug}", name="bdd_show_coaster", options = {"expose" = true})
     * @Method({"GET"})
     *
     * @param Coaster $coaster
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showAction(Coaster $coaster)
    {
        $imageUrls = $this->get(ImageService::class)->getCoasterImagesUrl($coaster->getId());

        return $this->render(
            'BddBundle:Coaster:show.html.twig',
            array(
                'coaster' => $coaster,
                'images' => $imageUrls,
            )
        );
    }

    /**
     * Ajax route for autocomplete search
     *
     * @Route(
     *     "/coasters/ajax/search/all",
     *     name="bdd_ajax_search_all_coaster",
     *     options = {"expose" = true},
     *     condition="request.isXmlHttpRequest()"
     * )
     * @Method({"GET"})
     *
     * @return JsonResponse
     */
    public function ajaxSearchAction()
    {
        $em = $this->get('doctrine.orm.default_entity_manager');

        return new JsonResponse(
            $em->getRepository('BddBundle:Coaster')->findAllNameAndSlug()
        );
    }
}
