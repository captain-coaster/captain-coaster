<?php

namespace BddBundle\Controller;

use BddBundle\Entity\User;
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
     */
    public function listRatingsAction(User $user, $page = 1)
    {
        $dql = 'SELECT r FROM BddBundle:RiddenCoaster r 
                JOIN r.user u 
                JOIN r.coaster c 
                JOIN c.builtCoaster bc 
                JOIN bc.manufacturer m
                WHERE u.id = ?1';
        $query = $this->get('doctrine.orm.entity_manager')->createQuery($dql);
        $query->setParameter(1, $user->getId());

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
            array(
                'ratings' => $pagination,
                'user' => $user,
            )
        );
    }

    /**
     * @Route("/", name="user_list", requirements={"page" = "\d+"})
     */
    public function listAction()
    {
        $users = $this
            ->getDoctrine()
            ->getRepository('BddBundle:User')
            ->getUserRanking();

        return $this->render(
            'BddBundle:User:list.html.twig',
            array(
                'users' => $users,
            )
        );
    }
}
