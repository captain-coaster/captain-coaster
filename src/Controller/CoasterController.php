<?php

namespace App\Controller;

use App\Entity\Coaster;
use App\Entity\Image;
use App\Entity\LikedImage;
use App\Entity\RiddenCoaster;
use App\Form\Type\ImageUploadType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class CoasterController
 * @package App\Controller
 */
#[Route(path: '/coasters')]
class CoasterController extends AbstractController
{
    /**
     * Redirects to index
     *
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    #[Route(path: '/', name: 'coaster_index', methods: ['GET'])]
    public function index(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        return $this->redirectToRoute('bdd_index');
    }

    /**
     * Uploads an image for a coaster
     *
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    #[Route(path: '/{slug}/images/upload', name: 'coaster_images_upload', methods: ['GET', 'POST'])]
    public function imageUpload(Request $request, Coaster $coaster, TranslatorInterface $translator)
    {
        $this->denyAccessUnlessGranted('upload', $coaster);

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
                'form' => $form,
                'coaster' => $coaster,
            ]
        );
    }

    /**
     * Async loads images for a coaster
     *
     */
    #[Route(path: '/{slug}/images/ajax/{imageNumber}', name: 'coaster_images_ajax_load', methods: ['GET'], options: ['expose' => true], condition: 'request.isXmlHttpRequest()')]
    public function ajaxLoadImages(EntityManagerInterface $em, Coaster $coaster, int $imageNumber = 8): \Symfony\Component\HttpFoundation\Response
    {
        $userLikes = [];
        if (($user = $this->getUser()) instanceof \Symfony\Component\Security\Core\User\UserInterface) {
            $em->getConfiguration()->addCustomHydrationMode(
                'COLUMN_HYDRATOR',
                \App\Doctrine\Hydrator\ColumnHydrator::class
            );
            $userLikes = $em
                ->getRepository(LikedImage::class)
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
     */
    #[Route(path: '/ranking/{page}', name: 'coaster_ranking', requirements: ['page' => '\d+'], methods: ['GET'])]
    public function showRankingAction($page = 1): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        return $this->redirectToRoute('ranking_index', ['page' => $page], 301);
    }

    /**
     * Show details of a coaster
     */
    #[Route(path: '/{slug}', name: 'bdd_show_coaster', methods: ['GET'], options: ['expose' => true])]
    public function showAction(Request $request, Coaster $coaster): \Symfony\Component\HttpFoundation\Response
    {
        // Load reviews
        $reviews = $this->getDoctrine()
            ->getRepository(RiddenCoaster::class)
            ->getReviews($coaster, $request->getLocale());

        $rating = null;
        $user = null;
        if ($this->isGranted('ROLE_USER')) {
            $user = $this->getUser();
            $em = $this->getDoctrine()->getManager();
            $rating = $em->getRepository('App:RiddenCoaster')->findOneBy(
                ['coaster' => $coaster, 'user' => $user]
            );
        }

        return $this->render(
            'Coaster/show.html.twig',
            [
                'coaster' => $coaster,
                'reviews' => $reviews,
                'rating' => $rating,
                'user' => $user,
            ]
        );
    }
}
