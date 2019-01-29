<?php

namespace App\Controller;

use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use App\Form\Type\ReviewType;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ReviewController
 * @package App\Controller
 * @Route("/reviews")
 */
class ReviewController extends AbstractController
{
    /**
     * Show a list of reviews
     *
     * @Route("/{page}", name="review_list", requirements={"page" = "\d+"}, methods={"GET"})
     *
     * @param Request $request
     * @param PaginatorInterface $paginator
     * @param int $page
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function listAction(Request $request, PaginatorInterface $paginator, $page = 1)
    {
        $query = $this->getDoctrine()
            ->getRepository(RiddenCoaster::class)
            ->findAll($request->getLocale());

        $pagination = $paginator->paginate(
            $query,
            $page,
            10
        );

        return $this->render(
            'Review/list.html.twig',
            ['reviews' => $pagination]
        );
    }

    /**
     * Create or update a review
     *
     * @param Request $request
     * @param Coaster $coaster
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/coasters/{id}/form", name="review_form", methods={"GET", "POST"})
     * @Security("is_granted('ROLE_USER')")
     */
    public function newAction(Request $request, Coaster $coaster)
    {
        $em = $this->getDoctrine()->getManager();

        $review = $em->getRepository('App:RiddenCoaster')->findOneBy(
            ['coaster' => $coaster->getId(), 'user' => $this->getUser()->getId()]
        );

        if (!$review instanceof RiddenCoaster) {
            $review = new RiddenCoaster();
            $review->setCoaster($coaster);
            $review->setUser($this->getUser());
            $review->setLanguage($request->getLocale());
        }

        /** @var Form $form */
        $form = $this->createForm(
            ReviewType::class,
            $review,
            [
                'locales' => $this->getParameter('app_locales_array'),
            ]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($review);
            $em->flush();

            return $this->redirectToRoute('bdd_show_coaster', ['slug' => $coaster->getSlug()]);
        }

        return $this->render(
            'Review/form.html.twig',
            [
                'form' => $form->createView(),
                'coaster' => $coaster,
            ]
        );
    }
}
