<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Coaster;
use App\Entity\Image;
use App\Entity\LikedImage;
use App\Form\Type\ImageUploadType;
use App\Repository\CoasterRepository;
use App\Repository\CoasterSummaryRepository;
use App\Repository\RiddenCoasterRepository;
use App\Service\ImageManager;
use App\Service\SummaryFeedbackService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: '/coasters')]
class CoasterController extends BaseController
{
    /** Redirects to index */
    #[Route(path: '/', name: 'coaster_index', methods: ['GET'])]
    public function index(): RedirectResponse
    {
        return $this->redirectToRoute('default_index');
    }

    /** Uploads an image for a coaster */
    #[Route(path: '/{slug}/images/upload', name: 'coaster_images_upload', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function imageUpload(
        Request $request,
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Coaster $coaster,
        TranslatorInterface $translator,
        EntityManagerInterface $em,
        ImageManager $imageManager
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
            // Check for duplicate
            if ($imageManager->isDuplicate($image->getFile())) {
                $this->addFlash('warning', $translator->trans('image_upload.form.duplicate'));

                return $this->redirectToRoute(
                    'coaster_images_upload',
                    ['slug' => $coaster->getSlug()]
                );
            }

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
    public function ajaxLoadImages(EntityManagerInterface $em, #[MapEntity(mapping: ['slug' => 'slug'])] Coaster $coaster, int $imageNumber = 8): Response
    {
        $userLikes = [];
        if (($user = $this->getUser()) instanceof UserInterface) {
            $userLikes = $em
                ->getRepository(LikedImage::class)
                ->findUserLikes($user)
                ->getSingleColumnResult();
        }

        return $this->render(
            'Coaster/_image_panel.html.twig',
            [
                'userLikes' => $userLikes,
                'coaster' => $coaster,
                'number' => $imageNumber,
            ]
        );
    }

    /** Async loads AI summary for a coaster */
    #[Route(
        path: '/{slug}/summary/ajax',
        name: 'coaster_summary_ajax_load',
        options: ['expose' => true],
        methods: ['GET'],
        condition: 'request.isXmlHttpRequest()'
    )]
    public function ajaxLoadSummary(
        Request $request,
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Coaster $coaster,
        CoasterSummaryRepository $coasterSummaryRepository,
        SummaryFeedbackService $summaryFeedbackService
    ): Response {
        $user = $this->getUser();
        $coasterSummary = $coasterSummaryRepository->findByCoasterAndLanguage($coaster, $request->getLocale());

        // Get user's current feedback state for the summary
        $userFeedbackState = null;
        if ($coasterSummary) {
            $ipAddress = $request->getClientIp() ?? '127.0.0.1';
            $userFeedbackState = $summaryFeedbackService->getUserFeedbackState($coasterSummary, $user, $ipAddress);
        }

        return $this->render(
            'Coaster/_ai_summary.html.twig',
            [
                'coasterSummary' => $coasterSummary,
                'userFeedbackState' => $userFeedbackState,
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
    public function ajaxLoadReviews(Request $request, RiddenCoasterRepository $riddenCoasterRepository, PaginatorInterface $paginator, #[MapEntity(mapping: ['slug' => 'slug'])] Coaster $coaster, int $page = 1): Response
    {
        $user = $this->getUser();
        $displayReviewsInAllLanguages = true;
        if (null !== $user) {
            $displayReviewsInAllLanguages = $this->getUser()->isDisplayReviewsInAllLanguages();
        }

        $pagination = $paginator->paginate(
            $riddenCoasterRepository->getCoasterReviews($coaster, $request->getLocale(), $displayReviewsInAllLanguages),
            $page,
            25
        );

        return $this->render(
            'Coaster/_review_panel.html.twig',
            [
                'reviews' => $pagination,
                'coaster' => $coaster,
                'displayReviewsInAllLanguages' => $displayReviewsInAllLanguages,
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
        RiddenCoasterRepository $riddenCoasterRepository,
        CoasterRepository $coasterRepository
    ): Response {
        $rating = null;
        $user = null;
        if ($this->isGranted('ROLE_USER')) {
            $user = $this->getUser();
            $rating = $riddenCoasterRepository->findOneBy(
                ['coaster' => $coaster, 'user' => $user]
            );
        }

        return $this->render(
            'Coaster/show.html.twig',
            [
                'countRatings' => $riddenCoasterRepository->getRatingStatsForCoaster($coaster),
                'coaster' => $coaster,
                'rating' => $rating,
                'user' => $user,
                'coasters' => $coasterRepository->findAllCoastersInPark($coaster->getPark()),
            ]
        );
    }

    /** Redirect old urls to above */
    #[Route(path: '/{slug}', name: 'redirect_coaster_show', options: ['expose' => true], methods: ['GET'])]
    public function redirectCoaster(#[MapEntity(mapping: ['slug' => 'slug'])] Coaster $coaster): RedirectResponse
    {
        return $this->redirectToRoute('show_coaster', [
            'id' => $coaster->getId(),
            'slug' => $coaster->getSlug(),
        ], 301);
    }
}
