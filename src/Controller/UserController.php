<?php

namespace App\Controller;

use App\Entity\Image;
use App\Entity\LikedImage;
use App\Entity\RiddenCoaster;
use App\Entity\Top;
use App\Entity\User;
use App\Service\StatService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class UserController
 * @package App\Controller
 */
#[Route(path: '/users')]
class UserController extends AbstractController
{
    /**
     * List all users
     */
    #[Route(path: '/{page}', name: 'user_list', requirements: ['page' => '\d+'], methods: ['GET'])]
    public function listAction(PaginatorInterface $paginator, int $page = 1): Response
    {
        try {
            $pagination = $paginator->paginate(
                $this->getDoctrine()->getRepository(User::class)->getUserList(),
                $page,
                21
            );
        } catch (\UnexpectedValueException) {
            throw new BadRequestHttpException();
        }

        return $this->render('User/list.html.twig', ['users' => $pagination]);
    }

    /**
     * Show all user's ratings
     */
    #[Route(path: '/{id}/ratings/{page}', name: 'user_ratings', requirements: ['page' => '\d+'], methods: ['GET'])]
    public function listRatingsAction(PaginatorInterface $paginator, User $user, int $page = 1): Response
    {
        if (!$user->isEnabled()) {
            throw new NotFoundHttpException();
        }

        $query = $this
            ->getDoctrine()
            ->getRepository(RiddenCoaster::class)
            ->getUserRatings($user);

        try {
            $pagination = $paginator->paginate(
                $query,
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

    /**
     * Show all user's reviews
     */
    #[Route(path: '/{id}/reviews/{page}', name: 'user_reviews', requirements: ['page' => '\d+'], methods: ['GET'])]
    public function listReviews(PaginatorInterface $paginator, User $user, int $page = 1): Response
    {
        if (!$user->isEnabled()) {
            throw new NotFoundHttpException();
        }

        $query = $this
            ->getDoctrine()
            ->getRepository(RiddenCoaster::class)
            ->getUserReviews($user);

        try {
            $pagination = $paginator->paginate(
                $query,
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

    /**
     * Show all user's top
     *
     */
    #[Route(path: '/{id}/tops', name: 'user_tops', methods: ['GET'])]
    public function listTops(User $user): \Symfony\Component\HttpFoundation\Response
    {
        if (!$user->isEnabled()) {
            throw new NotFoundHttpException();
        }

        $tops = $this
            ->getDoctrine()
            ->getRepository(Top::class)
            ->findAllByUser($user);

        return $this->render(
            'User/tops.html.twig',
            [
                'tops' => $tops,
                'user' => $user,
            ]
        );
    }

    /**
     * Show all user's pictures
     */
    #[Route(path: '/{id}/pictures', name: 'user_pictures', requirements: ['page' => '\d+'], methods: ['GET'])]
    public function picturesAction(Request $request, User $user, EntityManagerInterface $em, PaginatorInterface $paginator): Response
    {
        if (!$user->isEnabled()) {
            throw new NotFoundHttpException();
        }

        try {
            $pagination = $paginator->paginate(
                $em->getRepository(Image::class)->findUserImages($user),
                $request->get('page', 1),
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
        if (($loggedInUser = $this->getUser()) instanceof \Symfony\Component\Security\Core\User\UserInterface) {
            $em->getConfiguration()->addCustomHydrationMode(
                'COLUMN_HYDRATOR',
                \App\Doctrine\Hydrator\ColumnHydrator::class
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

    /**
     * Display a user
     */
    #[Route(path: '/{slug}', name: 'user_show', methods: ['GET'], options: ['expose' => true])]
    public function showAction(User $user, StatService $statService): \Symfony\Component\HttpFoundation\Response
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

    /**
     * Permalink to user profile
     *
     * @return Response
     */
    #[Route(path: '/{id}/profile', name: 'user_profile', methods: ['GET'])]
    public function permalinkProfile(User $user): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        return $this->redirectToRoute('user_show', ['slug' => $user->getSlug()]);
    }
}
