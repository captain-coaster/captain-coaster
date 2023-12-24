<?php

namespace App\Controller;

use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use App\Form\Type\ReviewType;
use Doctrine\ORM\NonUniqueResultException;
use Knp\Component\Pager\PaginatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

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
     * @throws NonUniqueResultException
     */
    public function listAction(Request $request, PaginatorInterface $paginator, int $page = 1): Response
    {
        $query = $this->getDoctrine()
            ->getRepository(RiddenCoaster::class)
            ->findAll($request->getLocale());

        try {
            $pagination = $paginator->paginate(
                $query,
                $page,
                10
            );
        } catch (\UnexpectedValueException $e) {
            throw new BadRequestHttpException();
        }

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
     * @return Response
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
