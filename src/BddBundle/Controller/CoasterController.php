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
     * @Route("/coaster/create", name="bdd_create_coaster")
     * @Method({"GET", "POST"})
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function createAction(Request $request)
    {
        $coaster = new Coaster();

        /** @var Form $form */
        $form = $this->createForm(CoasterType::class, $coaster);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $coaster = $form->getData();
            dump($coaster);
            exit;
        }

        return $this->render(
            'BddBundle:Coaster:create.html.twig',
            array(
                'form' => $form->createView(),
            )
        );
    }

    /**
     * @Route("/coaster/{slug}", name="bdd_show_coaster", options = {"expose" = true})
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
     * @Route(
     *     "/coaster/ajax/search/all",
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
