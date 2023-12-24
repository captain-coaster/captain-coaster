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
 * @Route("/users")
 */
class UserController extends AbstractController
{
    /**
     * List all users
     *
     * @Route("/{page}", name="user_list", requirements={"page" = "\d+"}, methods={"GET"})
     */
    public function listAction(PaginatorInterface $paginator, int $page = 1): Response
    {
        try {
            $pagination = $paginator->paginate(
                $this->getDoctrine()->getRepository(User::class)->getUserList(),
                $page,
                21
            );
        } catch (\UnexpectedValueException $e) {
            throw new BadRequestHttpException();
        }

        return $this->render('User/list.html.twig', ['users' => $pagination]);
    }

    /**
     * Show all user's ratings
     *
     * @Route("/{id}/ratings/{page}", name="user_ratings", requirements={"page" = "\d+"}, methods={"GET"})
     */
    public function listRatingsAction(Request $request, PaginatorInterface $paginator, User $user, int $page = 1): Response
    {
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
        } catch (\UnexpectedValueException $e) {
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
     *
     * @Route("/{id}/reviews/{page}", name="user_reviews", requirements={"page" = "\d+"}, methods={"GET"})
     */
    public function listReviews(PaginatorInterface $paginator, User $user, int $page = 1): Response
    {
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
        } catch (\UnexpectedValueException $e) {
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
     * @Route("/{id}/tops", name="user_tops", methods={"GET"})
     *
     * @param User $user
     * @return Response
     */
    public function listTops(User $user)
    {
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
     *
     * @Route("/{id}/pictures", name="user_pictures", requirements={"page" = "\d+"}, methods={"GET"})
     */
    public function picturesAction(Request $request, User $user, EntityManagerInterface $em, PaginatorInterface $paginator): Response
    {
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
        } catch (\UnexpectedValueException $e) {
            throw new BadRequestHttpException();
        }

        $userLikes = [];
        if ($loggedInUser = $this->getUser()) {
            $em->getConfiguration()->addCustomHydrationMode(
                'COLUMN_HYDRATOR',
                'App\Doctrine\Hydrator\ColumnHydrator'
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
     *
     * @param User $user
     * @param StatService $statService
     * @return Response
     * @Route("/{slug}", name="user_show", methods={"GET"}, options={"expose" = true})
     */
    public function showAction(User $user, StatService $statService)
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
     * @param User $user
     * @return Response
     *
     * @Route("/{id}/profile", name="user_profile", methods={"GET"})
     */
    public function permalinkProfile(User $user)
    {
        return $this->redirectToRoute('user_show', ['slug' => $user->getSlug()]);
    }
}
