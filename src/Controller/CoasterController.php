<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Coaster;
use App\Entity\Image;
use App\Entity\LikedImage;
use App\Form\Type\ImageUploadType;
use App\Repository\CoasterRepository;
use App\Repository\CoasterSummaryRepository;
use App\Repository\ImageRepository;
use App\Repository\RiddenCoasterRepository;
use App\Service\ImageManager;
use App\Service\ReviewLanguagePreferenceService;
use App\Service\Summary\SummaryFeedbackService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: '/coasters')]
class CoasterController extends BaseController
{
    /** Photos rendered with the page; a phone shows the first two. */
    private const int IMAGES_SHOWN = 8;

    /** Beyond this, a page number only overflows the paginator's offset. */
    private const int MAX_REVIEW_PAGE = 10000;

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
                $this->addFlash('warning', $translator->trans('image_upload.flash.duplicate'));

                return $this->redirectToRoute(
                    'coaster_images_upload',
                    ['slug' => $coaster->getSlug()]
                );
            }

            $imageManager->store($image);

            $this->addFlash('success', $translator->trans('image_upload.flash.success'));

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
    public function ajaxLoadImages(EntityManagerInterface $em, ImageRepository $imageRepository, #[MapEntity(mapping: ['slug' => 'slug'])] Coaster $coaster, int $imageNumber = 8): Response
    {
        return $this->render(
            'Coaster/_image_panel.html.twig',
            $this->imagePanel($em, $imageRepository, $coaster, $imageNumber)
        );
    }

    /** Another page or sort of a coaster's reviews, the first one is in the page.
     * @param array<string, mixed> $filters
     */
    #[Route(
        path: '/{slug}/reviews',
        name: 'coaster_reviews_ajax_load',
        options: ['expose' => true],
        methods: ['GET'],
        condition: 'request.isXmlHttpRequest()'
    )]
    public function ajaxLoadReviews(Request $request, RiddenCoasterRepository $riddenCoasterRepository, PaginatorInterface $paginator, #[MapEntity(mapping: ['slug' => 'slug'])] Coaster $coaster, ReviewLanguagePreferenceService $reviewLanguagePreferenceService, #[MapQueryParameter(options: ['min_range' => 1, 'max_range' => self::MAX_REVIEW_PAGE])] int $page = 1, #[MapQueryParameter] array $filters = []): Response
    {
        return $this->render(
            'Coaster/_review_panel.html.twig',
            $this->reviewPanel($request, $riddenCoasterRepository, $paginator, $reviewLanguagePreferenceService, $coaster, $page, $filters)
        );
    }

    /** Keep redirection for a while */
    #[Route(path: '/ranking/{page}', name: 'coaster_ranking', requirements: ['page' => '\d+'], methods: ['GET'])]
    public function showRankingAction(int $page = 1): RedirectResponse
    {
        return $this->redirectToRoute('ranking_index', ['page' => $page], 301);
    }

    /** Show details of a coaster, its summary, photos and first reviews included. */
    #[Route(path: '/{id}/{slug}', name: 'show_coaster', requirements: ['id' => '\d+'], options: ['expose' => true], methods: ['GET'])]
    public function showAction(
        Request $request,
        #[MapEntity(expr: 'repository.findForShow(id)')]
        Coaster $coaster,
        RiddenCoasterRepository $riddenCoasterRepository,
        CoasterRepository $coasterRepository,
        CoasterSummaryRepository $coasterSummaryRepository,
        SummaryFeedbackService $summaryFeedbackService,
        EntityManagerInterface $em,
        ImageRepository $imageRepository,
        PaginatorInterface $paginator,
        ReviewLanguagePreferenceService $reviewLanguagePreferenceService,
    ): Response {
        $rating = null;
        $user = null;
        if ($this->isGranted('ROLE_USER')) {
            $user = $this->getUser();
            $rating = $riddenCoasterRepository->findOneBy(
                ['coaster' => $coaster, 'user' => $user]
            );
        }

        $coasterSummary = $coasterSummaryRepository->findByCoasterAndLanguage($coaster, $request->getLocale());
        $query = $request->query->all();

        return $this->render(
            'Coaster/show.html.twig',
            [
                'countRatings' => $riddenCoasterRepository->getRatingStatsForCoaster($coaster),
                'coaster' => $coaster,
                'rating' => $rating,
                'user' => $user,
                'coasters' => $coasterRepository->findAllCoastersInPark($coaster->getPark()),
                'metaDescription' => $coasterSummary?->getMetaDescription(),
                'coasterSummary' => $coasterSummary,
                'userFeedbackState' => $coasterSummary ? $summaryFeedbackService->getUserFeedbackState($coasterSummary, $user, $request->getClientIp() ?? '127.0.0.1') : null,
                'imagePanel' => $this->imagePanel($em, $imageRepository, $coaster, self::IMAGES_SHOWN),
                // Read leniently: a malformed review page or sort in a link never breaks the coaster page.
                'reviewPanel' => $this->reviewPanel(
                    $request,
                    $riddenCoasterRepository,
                    $paginator,
                    $reviewLanguagePreferenceService,
                    $coaster,
                    filter_var($query['page'] ?? 1, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::MAX_REVIEW_PAGE]]) ?: 1,
                    \is_array($query['filters'] ?? null) ? $query['filters'] : []
                ),
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

    /** @return array<string, mixed> */
    private function imagePanel(EntityManagerInterface $em, ImageRepository $imageRepository, Coaster $coaster, int $imageNumber): array
    {
        $userLikes = [];
        if (($user = $this->getUser()) instanceof UserInterface) {
            $userLikes = $em
                ->getRepository(LikedImage::class)
                ->findUserLikesForCoaster($user, $coaster)
                ->getSingleColumnResult();
        }

        return [
            'userLikes' => $userLikes,
            'coaster' => $coaster,
            'images' => $imageRepository->findVisibleForCoaster($coaster, $imageNumber),
            'number' => $imageNumber,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    private function reviewPanel(Request $request, RiddenCoasterRepository $riddenCoasterRepository, PaginatorInterface $paginator, ReviewLanguagePreferenceService $reviewLanguagePreferenceService, Coaster $coaster, int $page, array $filters): array
    {
        $preferredReviewLanguages = $reviewLanguagePreferenceService->resolve($request);

        // The query has no to-many join, so the paginator needs no DISTINCT. Knp's
        // own sort param is off: the order comes from filters[sort] only.
        $reviews = $paginator->paginate(
            $riddenCoasterRepository->getCoasterReviews($coaster, $preferredReviewLanguages, $filters),
            $page,
            25,
            [
                'distinct' => false,
                PaginatorInterface::SORT_FIELD_PARAMETER_NAME => null,
            ]
        );
        $riddenCoasterRepository->preloadTags($reviews);

        return [
            'reviews' => $reviews,
            'coaster' => $coaster,
            'preferredReviewLanguages' => $preferredReviewLanguages,
            'filters' => $filters,
        ];
    }
}
