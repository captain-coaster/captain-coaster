<?php

namespace BddBundle\Controller;

use BddBundle\Entity\Coaster;
use BddBundle\Form\Type\CoasterType;
use BddBundle\Service\ImageService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * Class CoasterController
 * @package BddBundle\Controller
 */
class CoasterController extends Controller
{
    /**
     * @Route("/coaster/create", name="bdd_create_coaster")
     * @Method({"GET", "POST"})
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function createAction(Request $request)
    {
        $coaster = new Coaster();

        /** @var Form $form */
        $form = $this->createForm(CoasterType::class, $coaster);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $coaster = $form->getData();
            dump($coaster);
            exit;
        }

        return $this->render(
            'BddBundle:Coaster:create.html.twig',
            array(
                'form' => $form->createView(),
            )
        );
    }

    /**
     * @Route("/coaster/{slug}", name="bdd_show_coaster", options = {"expose" = true})
     * @Method({"GET"})
     *
     * @param Coaster $coaster
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showAction(Coaster $coaster)
    {
        $imageUrls = $this->get(ImageService::class)->getCoasterImagesUrl($coaster->getId());

        return $this->render(
            'BddBundle:Coaster:show.html.twig',
            array(
                'coaster' => $coaster,
                'images' => $imageUrls,
            )
        );
    }

    /**
     * @Route("/coaster/ajax/search/all", name="bdd_ajax_search_all_coaster", options = {"expose" = true})
     * @Method({"GET"})
     *
     */
    public function ajaxSearchAction()
    {
        $em = $this->get('doctrine.orm.default_entity_manager');
        $qb = $em->createQueryBuilder();

//        $qb->select('c.name, c.slug')
//            ->from('BddBundle:Coaster', 'c')
//            ->innerJoin('c.park', 'p', 'WITH', 'c.park = p.id')
//            ->where(
//                $qb->expr()->orX(
//                    $qb->expr()->like('c.name', '?1'),
//                    $qb->expr()->like('p.name', '?1')
//                )
//            )
//            ->orderBy('c.name', 'ASC')
//            ->setMaxResults(5)
//            ->setParameter(1, '%'.$term.'%');

        $qb->select('c.name, c.slug')
            ->from('BddBundle:Coaster', 'c')
            ->innerJoin('c.park', 'p', 'WITH', 'c.park = p.id');

        $result = $qb->getQuery()->getArrayResult();

        return new JsonResponse($result);
    }
}
