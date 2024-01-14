<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use App\Form\Type\ReviewType;
use App\Repository\RiddenCoasterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/reviews')]
class ReviewController extends AbstractController
{
    /**
     * Show a list of reviews.
     *
     * @throws NonUniqueResultException
     */
    #[Route(path: '/{page}', name: 'review_list', requirements: ['page' => '\d+'], methods: ['GET'])]
    public function listAction(
        Request $request,
        RiddenCoasterRepository $riddenCoasterRepository,
        PaginatorInterface $paginator,
        int $page = 1
    ): Response {
        try {
            $pagination = $paginator->paginate(
                $riddenCoasterRepository->findAll($request->getLocale()),
                $page,
                10
            );
        } catch (\UnexpectedValueException) {
            throw new BadRequestHttpException();
        }

        return $this->render(
            'Review/list.html.twig',
            ['reviews' => $pagination]
        );
    }

    /** Create or update a review. */
    #[Route(path: '/coasters/{id}/form', name: 'review_form', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted('rate', 'coaster', statusCode: 403)]
    public function newAction(
        Request $request,
        Coaster $coaster,
        EntityManagerInterface $em,
        RiddenCoasterRepository $riddenCoasterRepository
    ): Response {
        $review = $riddenCoasterRepository->findOneBy(
            ['coaster' => $coaster, 'user' => $this->getUser()]
        );

        if (!$review instanceof RiddenCoaster) {
            $review = new RiddenCoaster();
            $review->setCoaster($coaster);
            $review->setUser($this->getUser());
            $review->setLanguage($request->getLocale());
        }

        $form = $this->createForm(
            ReviewType::class,
            $review,
            [
                'locales' => $this->getParameter('app_locales_array'),
            ]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($review);
            $em->flush();

            return $this->redirectToRoute('bdd_show_coaster', ['slug' => $coaster->getSlug()]);
        }

        return $this->render(
            'Review/form.html.twig',
            [
                'form' => $form,
                'coaster' => $coaster,
            ]
        );
    }
}
