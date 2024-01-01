<?php

declare(strict_types=1);

namespace App\Controller;

use App\Doctrine\Hydrator\ColumnHydrator;
use App\Entity\Image;
use App\Entity\LikedImage;
use App\Entity\User;
use App\Repository\RiddenCoasterRepository;
use App\Repository\TopRepository;
use App\Repository\UserRepository;
use App\Service\StatService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
class UserController extends AbstractController
{
    /** List all users. */
    #[Route(path: '/{page}', name: 'user_list', requirements: ['page' => '\d+'], methods: ['GET'])]
    public function listAction(UserRepository $userRepository, PaginatorInterface $paginator, int $page = 1): Response
    {
        return $this->render(
            'User/list.html.twig',
            ['users' => $paginator->paginate(
                $userRepository->getAllUsersWithTotalRatingsQuery(),
                $page,
                21
            )]
        );
    }

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
                    'defaultSortFieldName' => 'r.riddenAt',
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
            'Review/list.html.twig',
            [
                'reviews' => $pagination,
                'user' => $user,
            ]
        );
    }

    /** Show all user's top. */
    #[Route(path: '/{id}/tops', name: 'user_tops', methods: ['GET'])]
    public function listTops(User $user, TopRepository $topRepository): Response
    {
        if (!$user->isEnabled()) {
            throw new NotFoundHttpException();
        }

        return $this->render(
            'User/tops.html.twig',
            [
                'tops' => $topRepository->findAllByUser($user),
                'user' => $user,
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
            $em->getConfiguration()->addCustomHydrationMode(
                'COLUMN_HYDRATOR',
                ColumnHydrator::class
            );
            $userLikes = $em
                ->getRepository(LikedImage::class)
                ->findUserLikes($loggedInUser)
                ->getResult('COLUMN_HYDRATOR');
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
    public function showAction(User $user, StatService $statService): Response
    {
        if (!$user->isEnabled()) {
            throw new NotFoundHttpException();
        }

        return $this->render(
            'User/show.html.twig',
            [
                'user' => $user,
                'stats' => $statService->getUserStats($user),
            ]
        );
    }

    #[Route(path: '/{id}/profile', name: 'user_profile', methods: ['GET'])]
    public function permalinkProfile(User $user): RedirectResponse
    {
        return $this->redirectToRoute('user_show', ['slug' => $user->getSlug()]);
    }
}
