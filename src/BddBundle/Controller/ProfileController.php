<?php

namespace BddBundle\Controller;

use BddBundle\Entity\User;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class ProfileController extends Controller
{
    /**
     * @param int $page
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Route("/me/ratings/{page}", name="me_ratings", requirements={"page" = "\d+"})
     * @Method({"GET"})
     * @Security("is_granted('ROLE_USER')")
     */
    public function ratingsAction($page = 1)
    {
        /** @var User $user */
        $user = $this->getUser();
        $dql = 'SELECT r FROM BddBundle:RatingCoaster r 
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
                'defaultSortFieldName' => 'r.updatedAt',
                'defaultSortDirection' => 'desc',
            ]
        );

        return $this->render(
            'BddBundle:Profile:ratings.html.twig',
            [
                'ratings' => $pagination,
            ]
        );
    }
}
