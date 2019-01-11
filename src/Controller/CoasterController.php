<?php

namespace App\Controller;

use App\Entity\Coaster;
use App\Entity\Image;
use App\Form\Type\ImageUploadType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Class CoasterController
 * @package App\Controller
 * @Route("/coasters")
 */
class CoasterController extends AbstractController
{
    /**
     * Redirects to index
     *
     * @Route("/", name="bdd_index_coaster", methods={"GET"})
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
     * @Route("/{slug}/images/upload", name="coaster_images_upload", methods={"GET", "POST"})
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
            'Coaster/image-upload.html.twig',
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
     *     methods={"GET"},
     *     options = {"expose" = true},
     *     condition="request.isXmlHttpRequest()"
     * )
     *
     * @param EntityManagerInterface $em
     * @param Coaster $coaster
     * @param int $imageNumber
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function ajaxLoadImages(EntityManagerInterface $em, Coaster $coaster, int $imageNumber = 8)
    {
        $userLikes = [];
        if ($user = $this->getUser()) {
            $em->getConfiguration()->addCustomHydrationMode(
                'COLUMN_HYDRATOR',
                'App\Doctrine\Hydrator\ColumnHydrator'
            );
            $userLikes = $em
                ->getRepository('App:LikedImage')
                ->findUserLikes($user)
                ->getResult('COLUMN_HYDRATOR');
        }

        return $this->render(
            'Coaster/image-ajax.html.twig',
            [
                'userLikes' => $userLikes,
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
     * @Route("/ranking/{page}", name="coaster_ranking", requirements={"page" = "\d+"}, methods={"GET"})
     */
    public function showRankingAction($page = 1)
    {
        return $this->redirectToRoute('ranking_index', ['page' => $page], 301);
    }

    /**
     * Show details of a coaster
     *
     * @Route("/{slug}", name="bdd_show_coaster", methods={"GET"}, options = {"expose" = true})
     * @param Request $request
     * @param Coaster $coaster
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showAction(Request $request, Coaster $coaster)
    {
        // Load reviews
        $reviews = $this->getDoctrine()
            ->getRepository('App:RiddenCoaster')
            ->getReviews($coaster, $request->getLocale());

        $rating = null;
        if ($this->isGranted('ROLE_USER')) {
            $em = $this->getDoctrine()->getManager();
            $rating = $em->getRepository('App:RiddenCoaster')->findOneBy(
                ['coaster' => $coaster->getId(), 'user' => $this->getUser()->getId()]
            );
        }

        return $this->render(
            'Coaster/show.html.twig',
            [
                'coaster' => $coaster,
                'reviews' => $reviews,
                'rating' => $rating,
            ]
        );
    }
}
