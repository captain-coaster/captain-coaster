<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\StatService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

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
     * @param int $page
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Route("/{page}", name="user_list", requirements={"page" = "\d+"}, methods={"GET"})
     */
    public function listAction($page = 1)
    {
        $users = $this
            ->getDoctrine()
            ->getRepository('App:User')
            ->getUserList();

        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate($users, $page, 21);

        return $this->render('User/list.html.twig', ['users' => $pagination]);
    }

    /**
     * Show all user's ratings
     *
     * @param User $user
     * @param int $page
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Route("/{id}/ratings/{page}", name="user_ratings", requirements={"page" = "\d+"}, methods={"GET"})
     */
    public function listRatingsAction(User $user, $page = 1)
    {
        $query = $this
            ->get('doctrine.orm.entity_manager')
            ->getRepository('App:RiddenCoaster')
            ->getUserRatings($user);

        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $query,
            $page,
            30,
            [
                'wrap-queries' => true,
                'defaultSortFieldName' => 'r.value',
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
            ->get('doctrine.orm.entity_manager')
            ->getRepository('App:Liste')
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
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function picturesAction(Request $request, User $user, EntityManagerInterface $em)
    {
        $page = $request->get('page', 1);
        $query = $em
            ->getRepository('App:Image')
            ->findUserImages($user);

        $paginator = $this->get('knp_paginator');
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
                ->getRepository('App:LikedImage')
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
     * @throws \Doctrine\ORM\NonUniqueResultException
     *
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
     * Deprecated - Display a user
     * Need to keep it for banners links
     *
     * @param User $user
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Route("/{id}/profile", name="user_profile", methods={"GET"})
     *
     * @deprecated
     */
    public function deprecatedShowAction(User $user)
    {
        return $this->redirectToRoute('user_show', ['slug' => $user->getSlug()]);
    }
}
