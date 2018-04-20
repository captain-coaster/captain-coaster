<?php

namespace BddBundle\Controller;

use BddBundle\Entity\User;
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
     * @Route("/{id}/ratings/{page}", name="user_ratings", requirements={"page" = "\d+"})
     * @param User $user
     * @param int $page
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function listRatingsAction(User $user, $page = 1)
    {
        $query = $this
            ->get('doctrine.orm.entity_manager')
            ->getRepository('BddBundle:User')
            ->getUserRankings($user->getId());

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
     * User list
     * @Route("/{page}", name="user_list", requirements={"page" = "\d+"})
     * @Method({"GET"})
     * @param int $page
     * @return \Symfony\Component\HttpFoundation\Response
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
}
