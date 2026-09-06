<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use App\Form\Type\ReviewType;
use App\Repository\RiddenCoasterRepository;
use App\Service\ReviewLanguagePreferenceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/reviews')]
class ReviewController extends BaseController
{
    private const int PAGE_SIZE = 10;

    /**
     * Show a list of reviews, most recent first, growing by PAGE_SIZE on
     * each "load more" -- see RiddenCoasterRepository::findAllReviews().
     */
    #[Route(path: '', name: 'review_list', methods: ['GET'])]
    public function listAction(
        Request $request,
        RiddenCoasterRepository $riddenCoasterRepository,
        ReviewLanguagePreferenceService $reviewLanguagePreferenceService,
    ): Response {
        $preferredReviewLanguages = $reviewLanguagePreferenceService->resolve($request);

        $count = max($request->query->getInt('count', self::PAGE_SIZE), self::PAGE_SIZE);
        $reviews = $riddenCoasterRepository->findAllReviews($preferredReviewLanguages, $count + 1);
        $hasMore = \count($reviews) > $count;
        $reviews = \array_slice($reviews, 0, $count);

        $riddenCoasterRepository->preloadTags($reviews);

        $template = $request->isXmlHttpRequest() ? 'Review/_review_body.html.twig' : 'Review/list.html.twig';

        return $this->render(
            $template,
            [
                'reviews' => $reviews,
                'preferredReviewLanguages' => $preferredReviewLanguages,
                'hasMore' => $hasMore,
                'nextCount' => $count + self::PAGE_SIZE,
            ]
        );
    }

    /** Old numbered-page URLs (/reviews/2) redirect to the infinite-scroll listing. */
    #[Route(path: '/{page}', name: 'review_list_legacy_page', requirements: ['page' => '\d+'], methods: ['GET'])]
    public function legacyPageRedirect(): RedirectResponse
    {
        return $this->redirectToRoute('review_list', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    /** Create or update a review. */
    #[Route(path: '/coasters/{id}/form', name: 'review_form', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted('rate', 'coaster', statusCode: 403)]
    public function newAction(
        Request $request,
        Coaster $coaster,
        EntityManagerInterface $em,
        RiddenCoasterRepository $riddenCoasterRepository
    ): Response {
        $review = $riddenCoasterRepository->findOneBy(
            ['coaster' => $coaster, 'user' => $this->getUser()]
        );

        if (!$review instanceof RiddenCoaster) {
            $review = new RiddenCoaster();
            $review->setCoaster($coaster);
            $review->setUser($this->getUser());
            $review->setLanguage($request->getLocale());
        }

        $form = $this->createForm(
            ReviewType::class,
            $review,
            [
                'locales' => $this->getParameter('app_locales_array'),
            ]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($review);
            $em->flush();

            return $this->redirectToRoute('show_coaster', ['id' => $coaster->getId(), 'slug' => $coaster->getSlug()]);
        }

        return $this->render(
            'Review/form.html.twig',
            [
                'form' => $form,
                'coaster' => $coaster,
            ]
        );
    }
}
