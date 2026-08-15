<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Top;
use App\Entity\User;
use App\Form\Type\ProfileSettingsForm;
use App\Repository\ImageRepository;
use App\Repository\RiddenCoasterRepository;
use App\Repository\TopCoasterRepository;
use App\Service\AccountDeletionService;
use App\Service\ProfilePictureManager;
use App\Service\StatService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProfileController extends BaseController
{
    /** Show my profile. */
    #[Route(path: '/profile', name: 'profile', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(
        StatService $statService,
        ImageRepository $imageRepository,
        RiddenCoasterRepository $riddenCoasterRepository,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('Profile/index.html.twig', [
            'user' => $user,
            'stats' => $statService->getProfileStats($user),
            'images_counter' => $imageRepository->countUserEnabledImages($user),
            'recentActivity' => $riddenCoasterRepository->findRecentActivity($user, 6),
        ]);
    }

    /** Redirect to new route profile. */
    #[Route(path: '/me', name: 'profile_redirect', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function redirectMeToProfile(): Response
    {
        return $this->redirectToRoute('profile');
    }

    /** Legacy ratings route — folded into My Coasters (rated tab). */
    #[Route(path: '/profile/ratings/{page}', name: 'profile_ratings', requirements: ['page' => '\d+'], defaults: ['page' => 1], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function ratingsAction(): Response
    {
        return $this->redirectToRoute('profile_my_coasters', ['tab' => 'rated'], Response::HTTP_MOVED_PERMANENTLY);
    }

    /** Show my journey (chronological timeline). */
    #[Route(path: '/profile/journey', name: 'profile_journey', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function journey(
        Request $request,
        RiddenCoasterRepository $riddenCoasterRepository,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $yearParam = $request->query->get('year');
        $showAll = 'all' === $yearParam;
        $yearFilter = (!$showAll && null !== $yearParam) ? (int) $yearParam : null;

        $availableYears = $riddenCoasterRepository->findAvailableRideYears($user);

        // Default to most recent year to avoid loading all rides at once
        if (!$showAll && null === $yearFilter && [] !== $availableYears) {
            return $this->redirectToRoute('profile_journey', ['year' => $availableYears[0]]);
        }

        $riddenCoasters = $riddenCoasterRepository->findByUserForJourney($user, $yearFilter);
        $milestones = $riddenCoasterRepository->findMilestoneCoasterIds($user);
        $stats = $riddenCoasterRepository->getJourneyStats($user);

        $byYear = [];
        $undated = [];

        foreach ($riddenCoasters as $rc) {
            $firstRiddenAt = $rc->getFirstRiddenAt();
            if (null !== $firstRiddenAt) {
                $byYear[(int) $firstRiddenAt->format('Y')][] = $rc;
            } else {
                $undated[] = $rc;
            }
        }

        krsort($byYear);

        return $this->render('Profile/journey.html.twig', [
            'byYear' => $byYear,
            'undated' => $undated,
            'stats' => $stats,
            'availableYears' => $availableYears,
            'yearFilter' => $yearFilter,
            'showAll' => $showAll,
            'milestones' => $milestones,
        ]);
    }

    /** Show my coasters (rated, ridden, wishlist). */
    #[Route(path: '/profile/my-coasters', name: 'profile_my_coasters', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function myCoasters(
        Request $request,
        RiddenCoasterRepository $riddenCoasterRepository,
        TopCoasterRepository $topCoasterRepository,
        PaginatorInterface $paginator,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $tab = $request->query->getString('tab', 'all');
        $search = trim($request->query->getString('q'));
        // Ridden-only entries have no rating and no meaningful "recent" order, so default them to ride date.
        $sort = $request->query->getString('sort', 'ridden' === $tab ? 'date' : 'recent');
        $page = $request->query->getInt('page', 1);

        if ('' === $search) {
            $search = null;
        }

        // Counts for tabs
        $ratedCount = (int) (clone $riddenCoasterRepository->findRatedByUser($user))
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();
        $riddenOnlyCount = (int) (clone $riddenCoasterRepository->findRiddenOnlyByUser($user))
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();
        $wishlistCount = $topCoasterRepository->countBucketByUser($user);
        $allCount = $ratedCount + $riddenOnlyCount;

        // Build query based on tab
        $isWishlistTab = 'wishlist' === $tab;

        if ($isWishlistTab) {
            $qb = $topCoasterRepository->createQueryBuilder('w')
                ->join('w.coaster', 'c')
                ->join('c.park', 'p')
                ->join('w.top', 't')
                ->where('t.user = :user')
                ->andWhere('t.type = :bucket')
                ->setParameter('user', $user)
                ->setParameter('bucket', Top::TYPE_BUCKET);

            if (null !== $search) {
                $qb->andWhere('c.name LIKE :search OR p.name LIKE :search')
                    ->setParameter('search', '%'.addcslashes($search, '%_\\').'%');
            }

            // Sorting for the bucket list (newest added = highest position)
            match ($sort) {
                'name' => $qb->orderBy('c.name', 'ASC'),
                default => $qb->orderBy('w.position', 'DESC'),
            };
        } else {
            $qb = match ($tab) {
                'rated' => $riddenCoasterRepository->findRatedByUser($user),
                'ridden' => $riddenCoasterRepository->findRiddenOnlyByUser($user),
                default => $riddenCoasterRepository->getUserRatings($user),
            };

            if (null !== $search) {
                $qb->andWhere('c.name LIKE :search OR p.name LIKE :search')
                    ->setParameter('search', '%'.addcslashes($search, '%_\\').'%');
            }

            // Sorting for ridden coasters
            match ($sort) {
                'rating' => $qb->orderBy('r.rating', 'DESC'),
                'date' => $qb->orderBy('r.firstRiddenAt', 'DESC'),
                'name' => $qb->orderBy('c.name', 'ASC'),
                default => $qb->orderBy('r.updatedAt', 'DESC'),
            };
        }

        // Ordering is applied above; point KNP's sort param at an unused name so it
        // doesn't try to interpret our `sort` value (recent/rating/date/name) as a column.
        $results = $paginator->paginate($qb, $page, 30, [
            'sortFieldParameterName' => '_disabled',
        ]);

        return $this->render('Profile/my_coasters.html.twig', [
            'results' => $results,
            'tab' => $tab,
            'search' => $search ?? '',
            'sort' => $sort,
            'allCount' => $allCount,
            'ratedCount' => $ratedCount,
            'riddenOnlyCount' => $riddenOnlyCount,
            'wishlistCount' => $wishlistCount,
            'isWishlistTab' => $isWishlistTab,
        ]);
    }

    /** Show my settings. */
    #[Route(path: '/profile/settings', name: 'profile_settings', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function settings(
        Request $request,
        EntityManagerInterface $em,
        ProfilePictureManager $profilePictureManager,
        TranslatorInterface $translator
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ProfileSettingsForm::class, $user, [
            'can_change_name' => $user->canChangeName(),
            'locales' => $this->getParameter('app_locales_array'),
            'translator' => $translator,
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$user->canChangeName()) {
                $originalData = $em->getUnitOfWork()->getOriginalEntityData($user);
                $nameChanged = ($user->getFirstName() !== ($originalData['firstName'] ?? null))
                    || ($user->getLastName() !== ($originalData['lastName'] ?? null));

                if ($nameChanged) {
                    return $this->redirectToRoute('profile_settings');
                }
            }

            // Handle profile picture upload
            $profilePictureFile = $form->get('profilePicture')->getData();
            if ($profilePictureFile instanceof UploadedFile) {
                $filename = $profilePictureManager->uploadProfilePicture($profilePictureFile, $user);
                if ($filename) {
                    $user->setProfilePicture($filename);
                }
            }

            $this->addFlash('success', $translator->trans('profile.settings.updated_success'));

            $em->persist($user);
            $em->flush();

            return $this->redirectToRoute('profile_settings');
        }

        return $this->render('Profile/settings.html.twig', [
            'form' => $form,
            'user' => $user,
            'canChangeName' => $user->canChangeName(),
        ]);
    }

    /** Delete account. */
    #[Route(path: '/profile/delete-account', name: 'profile_delete_account', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function deleteAccount(
        Request $request,
        AccountDeletionService $accountDeletionService,
        TranslatorInterface $translator
    ): Response {
        $token = $request->request->getString('_csrf_token');

        if (!$this->isCsrfTokenValid('delete_account', $token)) {
            $this->addFlash('error', $translator->trans('profile.delete_account.invalid_token'));

            return $this->redirectToRoute('profile_settings');
        }

        /** @var User $user */
        $user = $this->getUser();

        $accountDeletionService->scheduleAccountDeletion($user);

        return $this->redirectToRoute('logout');
    }
}
