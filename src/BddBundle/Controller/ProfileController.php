<?php

namespace BddBundle\Controller;

use BddBundle\Entity\User;
use BddBundle\Form\Type\ProfileType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;

class ProfileController extends Controller
{
    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/me", name="me")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_USER')")
     */
    public function meAction(Request $request)
    {
        $user = $this->getUser();

        /** @var Form $form */
        $form = $this->createForm(
            ProfileType::class,
            $user,
            [
                'firstname' => $user->getFirstname(),
                'lastname' => $user->getLastName(),
            ]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($user);
            $em->flush();

            return $this->redirectToRoute('me');
        }

        return $this->render(
            'BddBundle:Profile:me.html.twig',
            [
                'user' => $this->getUser(),
                'form' => $form->createView(),
            ]
        );
    }

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
