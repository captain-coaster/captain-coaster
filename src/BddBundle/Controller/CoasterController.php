<?php

namespace BddBundle\Controller;

use BddBundle\Entity\Coaster;
use BddBundle\Entity\Image;
use BddBundle\Form\Type\ImageUploadType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Class CoasterController
 * @package BddBundle\Controller
 * @Route("/coasters")
 */
class CoasterController extends Controller
{
    /**
     * Redirects to index
     *
     * @Route("/", name="bdd_index_coaster")
     * @Method({"GET"})
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction()
    {
        return $this->redirectToRoute('bdd_index');
    }

    /**
     * Uploads an image for a coaster
     *
     * @Route("/{slug}/images/upload", name="coaster_images_upload")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_USER')")
     *
     * @param Request $request
     * @param Coaster $coaster
     * @param TranslatorInterface $translator
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function imageUpload(Request $request, Coaster $coaster, TranslatorInterface $translator)
    {
//        $this->denyAccessUnlessGranted('upload', $coaster);

        $image = new Image();
        $image->setCoaster($coaster);
        $image->setWatermarked(true);
        $image->setCredit($this->getUser()->getDisplayName());
        $image->setUploader($this->getUser());

        /** @var Form $form */
        $form = $this->createForm(ImageUploadType::class, $image);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($image);
            $em->flush();

            $this->addFlash('success', $translator->trans('image_upload.form.success'));

            return $this->redirectToRoute('coaster_images_upload', ['slug' => $coaster->getSlug()]);
        }

        return $this->render(
            'BddBundle:Coaster:image-upload.html.twig',
            [
                'form' => $form->createView(),
                'coaster' => $coaster,
            ]
        );
    }

    /**
     * Async loads images for a coaster
     *
     * @Route(
     *     "/{slug}/images/ajax/{imageNumber}",
     *     name="coaster_images_ajax_load",
     *     options = {"expose" = true},
     *     condition="request.isXmlHttpRequest()"
     * )
     * @Method({"GET"})
     *
     * @param Coaster $coaster
     * @param int $imageNumber
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function ajaxLoadImages(Coaster $coaster, int $imageNumber = 8)
    {
        return $this->render(
            'BddBundle:Coaster:image-ajax.html.twig',
            [
                'coaster' => $coaster,
                'number' => $imageNumber,
            ]
        );
    }

    /**
     * Keep redirection for a while
     *
     * @param int $page
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/ranking/{page}", name="coaster_ranking", requirements={"page" = "\d+"})
     * @Method({"GET"})
     */
    public function showRankingAction($page = 1)
    {
        return $this->redirectToRoute('ranking_index', ['page' => $page], 301);
    }

    /**
     * Show details of a coaster
     *
     * @Route("/{slug}", name="bdd_show_coaster", options = {"expose" = true})
     * @Method({"GET"})
     * @param Request $request
     * @param Coaster $coaster
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showAction(Request $request, Coaster $coaster)
    {
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
                'reviews' => $reviews,
                'rating' => $rating,
            ]
        );
    }
}
