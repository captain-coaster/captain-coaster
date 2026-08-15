<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Image;
use App\Entity\LikedImage;
use App\Entity\User;
use App\Repository\ImageRepository;
use App\Repository\RiddenCoasterRepository;
use App\Repository\TopLikeRepository;
use App\Repository\TopRepository;
use App\Service\StatService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Class UserController.
 */
#[Route(path: '/users')]
class UserController extends BaseController
{
    /** Show all user's ratings. */
    #[Route(path: '/{id}/ratings/{page}', name: 'user_ratings', requirements: ['page' => '\d+'], methods: ['GET'])]
    public function listRatingsAction(
        RiddenCoasterRepository $riddenCoasterRepository,
        PaginatorInterface $paginator,
        User $user,
        int $page = 1
    ): Response {
        if (!$user->isEnabled()) {
            throw new NotFoundHttpException();
        }

        try {
            $pagination = $paginator->paginate(
                $riddenCoasterRepository->getUserRatings($user),
                $page,
                30,
                [
                    'defaultSortFieldName' => 'r.firstRiddenAt',
                    'defaultSortDirection' => 'desc',
                ]
            );
        } catch (\UnexpectedValueException) {
            throw new BadRequestHttpException();
        }

        return $this->render(
            'User/list_ratings.html.twig',
            [
                'ratings' => $pagination,
                'user' => $user,
            ]
        );
    }

    /** Show all user's reviews. */
    #[Route(path: '/{id}/reviews/{page}', name: 'user_reviews', requirements: ['page' => '\d+'], methods: ['GET'])]
    public function listReviews(
        RiddenCoasterRepository $riddenCoasterRepository,
        PaginatorInterface $paginator,
        User $user,
        int $page = 1
    ): Response {
        if (!$user->isEnabled()) {
            throw new NotFoundHttpException();
        }

        try {
            $pagination = $paginator->paginate(
                $riddenCoasterRepository->getUserReviews($user),
                $page,
                30,
                [
                    'wrap-queries' => true,
                    'defaultSortFieldName' => 'r.updatedAt',
                    'defaultSortDirection' => 'desc',
                ]
            );
        } catch (\UnexpectedValueException) {
            throw new BadRequestHttpException();
        }

        return $this->render(
            'User/list_reviews.html.twig',
            [
                'reviews' => $pagination,
                'user' => $user,
            ]
        );
    }

    /** Show all of a user's lists, split into Top Coasters / Bucket List / Custom Lists. */
    #[Route(path: '/{id}/tops', name: 'user_tops', methods: ['GET'])]
    public function listTops(User $user, TopRepository $topRepository, TopLikeRepository $likeRepository): Response
    {
        if (!$user->isEnabled()) {
            throw new NotFoundHttpException();
        }

        $tops = $topRepository->findAllByUser($user);
        $isOwnProfile = $this->getUser() === $user;

        $topCoasters = null;
        $bucketList = null;
        $customLists = [];

        foreach ($tops as $top) {
            if ($top->isRanking()) {
                $topCoasters = $top;
            } elseif ($top->isBucket()) {
                $bucketList = $top;
            } else {
                // Hide private custom lists from non-owners.
                if (!$isOwnProfile && !$top->isPublic()) {
                    continue;
                }
                $customLists[] = $top;
            }
        }

        $currentUser = $this->getUser();
        $likedIds = $currentUser instanceof User ? $likeRepository->findLikedTopIds($currentUser) : [];

        return $this->render(
            'User/tops.html.twig',
            [
                'user' => $user,
                'isOwnProfile' => $isOwnProfile,
                'topCoasters' => $topCoasters,
                'bucketList' => $bucketList,
                'customLists' => $customLists,
                'likedIds' => $likedIds,
            ]
        );
    }

    /** Show all user's pictures. */
    #[Route(path: '/{id}/pictures', name: 'user_pictures', requirements: ['page' => '\d+'], methods: ['GET'])]
    public function picturesAction(Request $request, User $user, EntityManagerInterface $em, PaginatorInterface $paginator): Response
    {
        if (!$user->isEnabled()) {
            throw new NotFoundHttpException();
        }

        try {
            $pagination = $paginator->paginate(
                $em->getRepository(Image::class)->findUserImages($user),
                $request->query->getInt('page', 1),
                30,
                [
                    'wrap-queries' => true,
                    'defaultSortFieldName' => 'i.likeCounter',
                    'defaultSortDirection' => 'desc',
                ]
            );
        } catch (\UnexpectedValueException) {
            throw new BadRequestHttpException();
        }

        $userLikes = [];
        if (($loggedInUser = $this->getUser()) instanceof UserInterface) {
            $userLikes = $em
                ->getRepository(LikedImage::class)
                ->findUserLikes($loggedInUser)
                ->getSingleColumnResult();
        }

        return $this->render(
            'User/images.html.twig',
            [
                'images' => $pagination,
                'user' => $user,
                'userLikes' => $userLikes,
            ]
        );
    }

    /** Display a user. */
    #[Route(path: '/{slug}', name: 'user_show', options: ['expose' => true], methods: ['GET'])]
    public function showAction(#[MapEntity(mapping: ['slug' => 'slug'])] User $user, StatService $statService, ImageRepository $imageRepository, RiddenCoasterRepository $riddenCoasterRepository): Response
    {
        if (!$user->isEnabled()) {
            throw new NotFoundHttpException();
        }

        return $this->render(
            'User/show.html.twig',
            [
                'user' => $user,
                'stats' => $statService->getProfileStats($user),
                'images_counter' => $imageRepository->countUserEnabledImages($user),
                'recentActivity' => $riddenCoasterRepository->findRecentActivity($user, 6),
            ]
        );
    }

    #[Route(path: '/{id}/profile', name: 'user_profile', methods: ['GET'])]
    public function permalinkProfile(User $user): RedirectResponse
    {
        return $this->redirectToRoute('user_show', ['slug' => $user->getSlug()]);
    }
}
