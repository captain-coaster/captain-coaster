<?php

namespace App\Controller;

use App\Entity\Liste;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

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
     * @param PaginatorInterface $paginator
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function listAction(Request $request, PaginatorInterface $paginator)
    {
        $em = $this->getDoctrine()->getManager();
        $query = $em->getRepository(Liste::class)->findAllTops();

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
