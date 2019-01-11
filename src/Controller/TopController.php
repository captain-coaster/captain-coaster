<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class TopController
 * @package App\Controller
 * @Route("/tops")
 */
class TopController extends AbstractController
{
    /**
     * Displays all tops
     *
     * @Route("/", name="top_list", methods={"GET"})
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function listAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $query = $em->getRepository('App:Liste')->findAllTops();

        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $query,
            $request->get('page', 1),
            9,
            ['wrap-queries' => true]
        );

        return $this->render(
            'Top/list.html.twig',
            [
                'listes' => $pagination,
            ]
        );
    }
}
