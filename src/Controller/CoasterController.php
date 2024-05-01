<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\Hydrator\ColumnHydrator;
use App\Entity\Coaster;
use App\Entity\Image;
use App\Entity\LikedImage;
use App\Form\Type\ImageUploadType;
use App\Repository\RiddenCoasterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: '/coasters')]
class CoasterController extends AbstractController
{
    /** Redirects to index */
    #[Route(path: '/', name: 'coaster_index', methods: ['GET'])]
    public function index(): RedirectResponse
    {
        return $this->redirectToRoute('bdd_index');
    }

    /** Uploads an image for a coaster */
    #[Route(path: '/{slug}/images/upload', name: 'coaster_images_upload', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function imageUpload(
        Request $request,
        Coaster $coaster,
        TranslatorInterface $translator,
        EntityManagerInterface $em
    ): Response {
        $image = new Image();
        $image->setCoaster($coaster);
        $image->setWatermarked(true);
        $image->setCredit($this->getUser()->getDisplayName());
        $image->setUploader($this->getUser());

        /** @var Form $form */
        $form = $this->createForm(ImageUploadType::class, $image);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($image);
            $em->flush();

            $this->addFlash('success', $translator->trans('image_upload.form.success'));

            return $this->redirectToRoute(
                'coaster_images_upload',
                ['slug' => $coaster->getSlug()]
            );
        }

        return $this->render(
            'Coaster/image-upload.html.twig',
            [
                'form' => $form,
                'coaster' => $coaster,
            ]
        );
    }

    /** Async loads images for a coaster */
    #[Route(
        path: '/{slug}/images/ajax/{imageNumber}',
        name: 'coaster_images_ajax_load',
        options: ['expose' => true],
        methods: ['GET'],
        condition: 'request.isXmlHttpRequest()'
    )]
    public function ajaxLoadImages(EntityManagerInterface $em, Coaster $coaster, int $imageNumber = 8): Response
    {
        $userLikes = [];
        if (($user = $this->getUser()) instanceof UserInterface) {
            $em->getConfiguration()->addCustomHydrationMode(
                'COLUMN_HYDRATOR',
                ColumnHydrator::class
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

    /** Async loads reviews for a coaster */
    #[Route(
        path: '/{slug}/reviews/ajax/{page}',
        name: 'coaster_reviews_ajax_load',
        options: ['expose' => true],
        methods: ['GET'],
        condition: 'request.isXmlHttpRequest()'
    )]
    public function ajaxLoadReviews(Request $request, RiddenCoasterRepository $riddenCoasterRepository, PaginatorInterface $paginator, Coaster $coaster, int $page = 1): Response
    {
        $pagination = $paginator->paginate(
            $riddenCoasterRepository->getReviews($coaster, $request->getLocale()),
            $page,
            100
        );

        return $this->render(
            'Coaster/reviews-ajax.html.twig',
            [
                'reviews' => $pagination,
                'coaster' => $coaster,
            ]
        );
    }

    /** Keep redirection for a while */
    #[Route(path: '/ranking/{page}', name: 'coaster_ranking', requirements: ['page' => '\d+'], methods: ['GET'])]
    public function showRankingAction(int $page = 1): RedirectResponse
    {
        return $this->redirectToRoute('ranking_index', ['page' => $page], 301);
    }

    /** Show details of a coaster */
    #[Route(path: '/{id}/{slug}', name: 'show_coaster', options: ['expose' => true], methods: ['GET'])]
    public function showAction(
        Request $request,
        Coaster $coaster,
        RiddenCoasterRepository $riddenCoasterRepository
    ): Response {
        $rating = null;
        $user = null;
        if ($this->isGranted('ROLE_USER')) {
            $user = $this->getUser();
            $rating = $riddenCoasterRepository->findOneBy(
                ['coaster' => $coaster, 'user' => $user]
            );
        }

        $countRatings = $riddenCoasterRepository->getRatingStatsForCoaster($coaster);

        return $this->render(
            'Coaster/show.html.twig',
            [
                'countRatings' => $countRatings,
                'coaster' => $coaster,
                'reviews' => $riddenCoasterRepository->getReviews($coaster, $request->getLocale()),
                'rating' => $rating,
                'user' => $user,
            ]
        );
    }

    /** Redirect old urls to above */
    #[Route(path: '/{slug}', name: 'redirect_coaster_show', options: ['expose' => true], methods: ['GET'])]
    public function redirectCoaster(Coaster $coaster): RedirectResponse
    {
        return $this->redirectToRoute('show_coaster', [
            'id' => $coaster->getId(),
            'slug' => $coaster->getSlug(),
        ], 301);
    }
}
