<?php

namespace BddBundle\Controller;

use BddBundle\Entity\Coaster;
use BddBundle\Entity\User;
use BddBundle\Form\Type\CoasterType;
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

        return $this->showAction($coaster);
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
     * @Route("/{slug}/edit", name="bdd_edit_coaster")
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
     * Show ranking of best coasters
     *
     * @Route("/ranking", name="coaster_ranking")
     * @Method({"GET"})
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showRankingAction()
    {
        $coasters = $this->getDoctrine()
            ->getRepository('BddBundle:Coaster')
            ->findBy([], ['averageRating' => 'desc'], self::NUMBER_RANKING);

        $ids = [];

        foreach ($coasters as $coaster) {
            $ids[] = $coaster->getId();
        }

        $imageUrls = $this->get(ImageService::class)->getMultipleImagesUrl($ids);

        return $this->render(
            '@Bdd/Coaster/ranking.html.twig',
            ['coasters' => $coasters, 'images' => $imageUrls]
        );
    }

    /**
     * Show details of a coaster
     *
     * @Route("/{slug}", name="bdd_show_coaster", options = {"expose" = true})
     * @Method({"GET"})
     *
     * @param Coaster $coaster
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showAction(Coaster $coaster)
    {
        // Display images from file system
        $imageUrls = $this->get(ImageService::class)->getCoasterImagesUrl($coaster->getId());

        // Load reviews
        $reviews = $this->getDoctrine()
            ->getRepository('BddBundle:RiddenCoaster')
            ->getReviews($coaster->getId());

        return $this->render(
            'BddBundle:Coaster:show.html.twig',
            array(
                'coaster' => $coaster,
                'images' => $imageUrls,
                'reviews' => $reviews
            )
        );
    }

    /**
     * Ajax route for autocomplete search
     *
     * @Route(
     *     "/search/all.json",
     *     name="coaster_search_all_json",
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

    /**
     * Ajax route to add a coaster to wishlist
     *
     * @Route(
     *     "/{id}/wishlist/add",
     *     name="bdd_coaster_add_wishlist",
     *     options = {"expose" = true},
     * )
     * @Method({"GET"})
     * @Security("is_granted('ROLE_USER')")
     *
     * @param Coaster $coaster
     * @return JsonResponse
     */

    public function ajaxWishListAction(Coaster $coaster)
    {
        /** @var User $user */
        $user = $this->getUser();

        $user->addWishCoaster($coaster);
        $em = $this->get('doctrine.orm.default_entity_manager');
        $em->persist($user);
        $em->flush();

        return new JsonResponse(['state' => 'success']);
    }
}
