<?php

namespace BddBundle\Controller;

use BddBundle\Entity\User;
use BddBundle\Service\StatService;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class UserController
 * @package BddBundle\Controller
 * @Route("/users")
 */
class UserController extends Controller
{
    /**
     * List all users
     *
     * @param int $page
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Route("/{page}", name="user_list", requirements={"page" = "\d+"})
     * @Method({"GET"})
     */
    public function listAction($page = 1)
    {
        $users = $this
            ->getDoctrine()
            ->getRepository('BddBundle:User')
            ->getUserList();

        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate($users, $page, 21);

        return $this->render('BddBundle:User:list.html.twig', ['users' => $pagination]);
    }

    /**
     * Show all user's ratings
     *
     * @param User $user
     * @param int $page
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Route("/{id}/ratings/{page}", name="user_ratings", requirements={"page" = "\d+"})
     * @Method({"GET"})
     */
    public function listRatingsAction(User $user, $page = 1)
    {
        $query = $this
            ->get('doctrine.orm.entity_manager')
            ->getRepository('BddBundle:RiddenCoaster')
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
            'BddBundle:User:list_ratings.html.twig',
            [
                'ratings' => $pagination,
                'user' => $user,
            ]
        );
    }

    /**
     * Show all user's lists
     *
     * @Route("/{id}/lists", name="user_lists")
     * @Method({"GET"})
     *
     * @param User $user
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function listsAction(User $user)
    {
        $listes = $this
            ->get('doctrine.orm.entity_manager')
            ->getRepository('BddBundle:Liste')
            ->findAllByUser($user);

        return $this->render(
            'BddBundle:User:lists.html.twig',
            [
                'listes' => $listes,
                'user' => $user,
            ]
        );
    }

    /**
     * Show all user's pictures
     *
     * @Route("/{id}/pictures", name="user_pictures", requirements={"page" = "\d+"})
     * @Method({"GET"})
     *
     * @param User $user
     * @param EntityManagerInterface $em
     * @param int $page
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function picturesAction(Request $request, User $user, EntityManagerInterface $em)
    {
        $page = $request->get('page', 1);
        $query = $em
            ->getRepository('BddBundle:Image')
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
                'BddBundle\Doctrine\Hydrator\ColumnHydrator'
            );
            $userLikes = $em
                ->getRepository('BddBundle:LikedImage')
                ->findUserLikes($loggedInUser)
                ->getResult('COLUMN_HYDRATOR');
        }

        return $this->render(
            'BddBundle:User:images.html.twig',
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
     * @Route("/{slug}", name="user_show", options={"expose" = true})
     * @Method({"GET"})
     */
    public function showAction(User $user, StatService $statService)
    {

        return $this->render(
            'BddBundle:User:show.html.twig',
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
     * @Route("/{id}/profile", name="user_profile")
     * @Method({"GET"})
     *
     * @deprecated
     */
    public function deprecatedShowAction(User $user)
    {
        return $this->redirectToRoute('user_show', ['slug' => $user->getSlug()]);
    }
}
