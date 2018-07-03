<?php

namespace BddBundle\Controller;

use BddBundle\Entity\Coaster;
use BddBundle\Entity\Image;
use BddBundle\Form\Type\CommonCoasterType;
use BddBundle\Form\Type\ImageUploadType;
use BddBundle\Form\Type\RelocationCoasterType;
use BddBundle\Service\ImageService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class CoasterController
 * @package BddBundle\Controller
 * @Route("/coasters")
 */
class CoasterController extends Controller
{
    CONST NUMBER_RANKING = 20;

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

        return $this->redirectToRoute('bdd_show_coaster', ['slug' => $coaster->getSlug()]);
    }

    /**
     * Create a new coaster
     *
     * @Route("/new", name="bdd_new_coaster")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_CONTRIBUTOR')")
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function newAction(Request $request)
    {
        $coaster = new Coaster();

        /** @var Form $form */
        $form = $this->createForm(CommonCoasterType::class, $coaster);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($coaster);
            $em->flush();

            return $this->redirectToRoute('bdd_show_coaster', ['slug' => $coaster->getSlug()]);
        }

        return $this->render(
            'BddBundle:Coaster:create.html.twig',
            [
                'form' => $form->createView(),
            ]
        );
    }

    /**
     * Edit a coaster
     *
     * @Route("/{slug}/edit", name="coaster_edit")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_CONTRIBUTOR')")
     *
     * @param Request $request
     * @param Coaster $coaster
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function editAction(Request $request, Coaster $coaster)
    {
        /** @var Form $form */
        $form = $this->createForm(CommonCoasterType::class, $coaster);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($coaster);
            $em->flush();

            return $this->redirectToRoute('bdd_show_coaster', ['slug' => $coaster->getSlug()]);
        }

        return $this->render(
            'BddBundle:Coaster:create.html.twig',
            [
                'form' => $form->createView(),
            ]
        );
    }

    /**
     * Uploads an image for a coaster
     *
     * @Route("/{slug}/images/upload", name="coaster_images_upload")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_CONTRIBUTOR')")
     *
     * @param Request $request
     * @param Coaster $coaster
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function imageUpload(Request $request, Coaster $coaster)
    {
        $image = new Image();
        $image->setCoaster($coaster);

        /** @var Form $form */
        $form = $this->createForm(ImageUploadType::class, $image);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($image);
            $em->flush();

            $this->addFlash('info', 'Image uploaded !');

            return $this->redirectToRoute('coaster_images_upload', ['slug' => $coaster->getSlug()]);
        }

        return $this->render(
            'BddBundle:Coaster:image-upload.html.twig',
            [
                'form' => $form->createView(),
            ]
        );
    }

    /**
     * Relocate a coaster
     *
     * @Route("/reloc", name="coaster_reloc")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_CONTRIBUTOR')")
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function relocAction(Request $request)
    {
        $coaster = new Coaster();

        /** @var Form $form */
        $form = $this->createForm(RelocationCoasterType::class, $coaster);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($coaster);
            $em->flush();

            return $this->redirectToRoute('bdd_show_coaster', ['slug' => $coaster->getSlug()]);
        }

        return $this->render(
            'BddBundle:Coaster:create.html.twig',
            [
                'form' => $form->createView(),
            ]
        );
    }


    /**
     * Show ranking of best coasters
     *
     * @param int $page
     * @param ImageService $imageService
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/ranking/{page}", name="coaster_ranking", requirements={"page" = "\d+"})
     * @Method({"GET"})
     */
    public function showRankingAction($page = 1, ImageService $imageService)
    {
        $query = $this->getDoctrine()
            ->getRepository('BddBundle:Coaster')
            ->findByRanking();

        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $query,
            $page,
            self::NUMBER_RANKING
        );

        $ids = [];

        /** @var Coaster $coaster */
        foreach ($pagination as $coaster) {
            $ids[] = $coaster->getId();
        }

        $imageUrls = $imageService->getMultipleImagesUrl($ids);

        $nextRankingDate = new \DateTime('first day of next month midnight 1 minute');
        if ($nextRankingDate->diff(new \DateTime('now'), true)->format('%h') < 1) {
            $nextRankingDate = null;
        }

        $em = $this->get('doctrine.orm.default_entity_manager');
        $ranking = $em->getRepository('BddBundle:Ranking')->findCurrent();

        return $this->render(
            '@Bdd/Coaster/ranking.html.twig',
            [
                'coasters' => $pagination,
                'images' => $imageUrls,
                'rankingDate' => new \DateTime('first day of this month midnight'),
                'nextRankingDate' => $nextRankingDate,
                'ranking' => $ranking,
            ]
        );
    }

    /**
     * Show details of a coaster
     *
     * @Route("/{slug}", name="bdd_show_coaster", options = {"expose" = true})
     * @Method({"GET"})
     * @param Request $request
     * @param Coaster $coaster
     * @param ImageService $imageService
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showAction(Request $request, Coaster $coaster, ImageService $imageService)
    {
        // Display images from file system
        $imageUrls = $imageService->getCoasterImagesUrl($coaster->getId());

        // Load reviews
        $reviews = $this->getDoctrine()
            ->getRepository('BddBundle:RiddenCoaster')
            ->getReviews($coaster, $request->getLocale());

        $rating = null;
        if ($this->isGranted('ROLE_USER')) {
            $em = $this->getDoctrine()->getManager();
            $rating = $em->getRepository('BddBundle:RiddenCoaster')->findOneBy(
                ['coaster' => $coaster->getId(), 'user' => $this->getUser()->getId()]
            );
        }

        return $this->render(
            'BddBundle:Coaster:show.html.twig',
            [
                'coaster' => $coaster,
                'images' => $imageUrls,
                'reviews' => $reviews,
                'rating' => $rating,
            ]
        );
    }

    /**
     * Ajax route for autocomplete search
     * (search "q" parameter)
     *
     * @Route(
     *     "/search/coasters.json",
     *     name="coaster_search_json",
     *     options = {"expose" = true},
     *     condition="request.isXmlHttpRequest()"
     * )
     * @Method({"GET"})
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function ajaxSearchAction(Request $request)
    {
        $q = $request->get("q");

        $em = $this->get('doctrine.orm.default_entity_manager');

        $result = ["items" => $em->getRepository('BddBundle:Coaster')->searchByName($q)];

        return new JsonResponse($result);
    }
}
