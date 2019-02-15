<?php

namespace App\Controller;

use App\Entity\Image;
use App\Entity\LikedImage;
use App\Entity\Liste;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use App\Service\StatService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
     * @param PaginatorInterface $paginator
     * @param int $page
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Route("/{page}", name="user_list", requirements={"page" = "\d+"}, methods={"GET"})
     */
    public function listAction(PaginatorInterface $paginator, $page = 1)
    {
        $users = $this
            ->getDoctrine()
            ->getRepository(User::class)
            ->getUserList();

        $pagination = $paginator->paginate($users, $page, 21);

        return $this->render('User/list.html.twig', ['users' => $pagination]);
    }

    /**
     * Show all user's ratings
     *
     * @param PaginatorInterface $paginator
     * @param User $user
     * @param int $page
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Route("/{id}/ratings/{page}", name="user_ratings", requirements={"page" = "\d+"}, methods={"GET"})
     */
    public function listRatingsAction(PaginatorInterface $paginator, User $user, $page = 1)
    {
        $query = $this
            ->getDoctrine()
            ->getRepository(RiddenCoaster::class)
            ->getUserRatings($user);

        $pagination = $paginator->paginate(
            $query,
            $page,
            30,
            [
                'wrap-queries' => true,
                'defaultSortFieldName' => 'r.riddenAt',
                'defaultSortDirection' => 'desc',
            ]
        );

        return $this->render(
            'User/list_ratings.html.twig',
            [
                'ratings' => $pagination,
                'user' => $user,
            ]
        );
    }

    /**
     * Show all user's lists
     *
     * @Route("/{id}/lists", name="user_lists", methods={"GET"})
     *
     * @param User $user
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function listsAction(User $user)
    {
        $listes = $this
            ->getDoctrine()
            ->getRepository(Liste::class)
            ->findAllByUser($user);

        return $this->render(
            'User/lists.html.twig',
            [
                'listes' => $listes,
                'user' => $user,
            ]
        );
    }

    /**
     * Show all user's pictures
     *
     * @Route("/{id}/pictures", name="user_pictures", requirements={"page" = "\d+"}, methods={"GET"})
     *
     * @param Request $request
     * @param User $user
     * @param EntityManagerInterface $em
     * @param PaginatorInterface $paginator
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function picturesAction(
        Request $request,
        User $user,
        EntityManagerInterface $em,
        PaginatorInterface $paginator
    ) {
        $page = $request->get('page', 1);
        $query = $em
            ->getRepository(Image::class)
            ->findUserImages($user);

        $pagination = $paginator->paginate(
            $query,
            $page,
            30,
            [
                'wrap-queries' => true,
                'defaultSortFieldName' => 'i.likeCounter',
                'defaultSortDirection' => 'desc',
            ]
        );

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
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/{slug}", name="user_show", methods={"GET"}, options={"expose" = true})
     */
    public function showAction(User $user, StatService $statService)
    {

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
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Route("/{id}/profile", name="user_profile", methods={"GET"})
     */
    public function permalinkProfile(User $user)
    {
        return $this->redirectToRoute('user_show', ['slug' => $user->getSlug()]);
    }
}
