<?php

namespace BddBundle\Controller;

use BddBundle\Entity\User;
use BddBundle\Service\StatService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

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
     * User's ratings
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
     * @Route("/{id}/lists", name="user_lists", requirements={"page" = "\d+"})
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
}
