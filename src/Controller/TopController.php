<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Coaster;
use App\Entity\Top;
use App\Form\Type\TopDetailsType;
use App\Form\Type\TopType;
use App\Repository\TopRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/tops')]
class TopController extends AbstractController
{
    /**
     * Create a new top.
     */
    #[Route(path: '/new', name: 'top_new', methods: ['GET', 'POST'])]
    public function newAction(Request $request, EntityManagerInterface $em, TopRepository $topRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $top = new Top();
        $mainTop = $topRepository->findOneBy(['user' => $this->getUser(), 'main' => true]);

        // Very first top, redirect to main top edit
        if (!$mainTop instanceof Top) {
            $top->setName('Top Coasters');
            $top->setMain(true);
            $top->setUser($this->getUser());

            $em->persist($top);
            $em->flush();

            return $this->redirectToRoute('top_edit', ['id' => $top->getId()]);
        }

        // Else go to form to create a custom top
        $form = $this->createForm(TopDetailsType::class, $top);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $top->setMain(false);
            $top->setUser($this->getUser());

            $em->persist($top);
            $em->flush();

            return $this->redirectToRoute('top_edit', ['id' => $top->getId()]);
        }

        return $this->render('Top/edit-details.html.twig', ['form' => $form, 'create' => true]);
    }

    /**
     * Displays all tops.
     */
    #[Route(path: '/', name: 'top_list', methods: ['GET'])]
    public function list(PaginatorInterface $paginator, EntityManagerInterface $em, #[MapQueryParameter] int $page = 1): Response
    {
        try {
            $pagination = $paginator->paginate(
                $em->getRepository(Top::class)->findAllTops(),
                $page,
                9,
                ['wrap-queries' => true]
            );
        } catch (\UnexpectedValueException) {
            throw new BadRequestHttpException();
        }

        return $this->render(
            'Top/list.html.twig',
            [
                'tops' => $pagination,
            ]
        );
    }

    /**
     * Displays a top.
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    #[Route(path: '/{id}', name: 'top_show', methods: ['GET'])]
    public function show(Top $top, EntityManagerInterface $em): Response
    {
        return $this->render(
            'Top/show.html.twig',
            [
                'top' => $em->getRepository(Top::class)->getTopWithData($top),
            ]
        );
    }

    /**
     * Edits a top.
     *
     * @throws \Exception
     */
    #[Route(path: '/{id}/edit', name: 'top_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Top $top, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('edit', $top);

        $originalCoasters = new ArrayCollection();
        foreach ($top->getTopCoasters() as $coaster) {
            $originalCoasters->add($coaster);
        }

        /** @var Form $form */
        $form = $this->createForm(TopType::class, $top);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($originalCoasters as $coaster) {
                if (!$top->getTopCoasters()->contains($coaster)) {
                    $em->remove($coaster);
                }
            }

            // need to update manually because only TopCoaster changes
            $top->setUpdatedAt(new \DateTime());
            $em->persist($top);
            $em->flush();

            return $this->redirectToRoute('top_show', ['id' => $top->getId()]);
        }

        return $this->render(
            'Top/edit.html.twig',
            [
                'form' => $form,
                'topName' => $top->getName(),
            ]
        );
    }

    /**
     * Edits details of a top (name, type).
     */
    #[Route(path: '/{id}/edit-details', name: 'top_edit_details', methods: ['GET', 'POST'])]
    public function editDetails(Request $request, Top $top, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('edit-details', $top);

        /** @var Form $form */
        $form = $this->createForm(TopDetailsType::class, $top);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($top);
            $em->flush();

            return $this->redirectToRoute('top_show', ['id' => $top->getId()]);
        }

        return $this->render('Top/edit-details.html.twig', ['form' => $form, 'create' => false]);
    }

    /**
     * Deletes a top.
     */
    #[Route(path: '/{id}/delete', name: 'top_delete', methods: ['GET'])]
    public function delete(Top $top, EntityManagerInterface $em): RedirectResponse
    {
        $this->denyAccessUnlessGranted('delete', $top);

        $em->remove($top);
        $em->flush();

        return $this->redirectToRoute('top_list');
    }

    /**
     * Ajax route for autocomplete search (search "q" parameter).
     */
    #[Route(path: '/search/coasters.json', name: 'top_ajax_search', options: ['expose' => true], methods: ['GET'], condition: 'request.isXmlHttpRequest()')]
    public function ajaxSearch(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$request->get('q')) {
            return new JsonResponse([]);
        }

        return new JsonResponse(
            [
                'items' => $em->getRepository(Coaster::class)->suggestCoasterForTop(
                    $request->get('q'),
                    $this->getUser()
                ),
            ]
        );
    }
}
