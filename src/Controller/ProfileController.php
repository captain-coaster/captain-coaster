<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\Type\ProfileType;
use App\Service\BannerMaker;
use App\Service\StatService;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;

class ProfileController extends AbstractController
{
    /**
     * @param Request $request
     * @param BannerMaker $bannerMaker
     * @param StatService $statService
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/me", name="me", methods={"GET", "POST"})
     * @Security("is_granted('ROLE_USER')")
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function meAction(Request $request, BannerMaker $bannerMaker, StatService $statService)
    {
        $user = $this->getUser();

        // @todo async
        $bannerMaker->makeBanner($user);

        /** @var Form $form */
        $form = $this->createForm(
            ProfileType::class,
            $user,
            [
                'firstname' => $user->getFirstname(),
                'lastname' => $user->getLastName(),
                'locales' => $this->getParameter('app_locales_array'),
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
            'Profile/me.html.twig',
            [
                'user' => $this->getUser(),
                'form' => $form->createView(),
                'stats' => $statService->getUserStats($user),
            ]
        );
    }

    /**
     * @param int $page
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Route("/me/ratings/{page}", name="me_ratings", requirements={"page" = "\d+"}, methods={"GET"})
     * @Security("is_granted('ROLE_USER')")
     */
    public function ratingsAction($page = 1)
    {
        /** @var User $user */
        $user = $this->getUser();

        $query = $this->get('doctrine.orm.entity_manager')
            ->getRepository('App:RiddenCoaster')
            ->getUserRatings($user);

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
            'Profile/ratings.html.twig',
            [
                'ratings' => $pagination,
            ]
        );
    }
}
