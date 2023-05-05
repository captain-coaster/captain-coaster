<?php

namespace App\Controller;

use App\Entity\Coaster;
use App\Entity\Top;
use App\Form\Type\TopDetailsType;
use App\Form\Type\TopType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class TopController
 * @package App\Controller
 * @Route("/tops")
 */
class TopController extends AbstractController
{
    /**
     * Creates a new top
     *
     * @param Request $request
     * @param EntityManagerInterface $em
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|Response
     *
     * @Route("/new", name="top_new", methods={"GET", "POST"})
     */
    public function newAction(Request $request, EntityManagerInterface $em)
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $top = new Top();
        $mainTop = $em->getRepository(Top::class)->findOneBy(['user' => $this->getUser(), 'main' => true]);

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

            $em = $this->getDoctrine()->getManager();
            $em->persist($top);
            $em->flush();

            return $this->redirectToRoute('top_edit', ['id' => $top->getId()]);
        }

        return $this->render('Top/edit-details.html.twig', ['form' => $form->createView(), 'create' => true]);
    }

    /**
     * Displays all tops
     *
     * @Route("/", name="top_list", methods={"GET"})
     */
    public function list(Request $request, PaginatorInterface $paginator, EntityManagerInterface $em): Response
    {
        try {
            $pagination = $paginator->paginate(
                $em->getRepository(Top::class)->findAllTops(),
                $request->get('page', 1),
                9,
                ['wrap-queries' => true]
            );
        } catch (\UnexpectedValueException $e) {
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
     * Displays a top
     *
     * @param Top $top
     * @param EntityManagerInterface $em
     * @return Response
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @Route("/{id}", name="top_show", methods={"GET"})
     */
    public function show(Top $top, EntityManagerInterface $em)
    {
        return $this->render(
            'Top/show.html.twig',
            [
                'top' => $em->getRepository(Top::class)->getTopWithData($top),
            ]
        );
    }

    /**
     * Edits a top
     *
     * @param Request $request
     * @param Top $top
     * @param EntityManagerInterface $em
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|Response
     *
     * @throws \Exception
     * @Route("/{id}/edit", name="top_edit", methods={"GET", "POST"})
     */
    public function edit(Request $request, Top $top, EntityManagerInterface $em)
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
                if (false === $top->getTopCoasters()->contains($coaster)) {
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
                'form' => $form->createView(),
                'topName' => $top->getName(),
            ]
        );
    }

    /**
     * Edits details of a top (name, type)
     *
     * @param Request $request
     * @param Top $top
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|Response
     *
     * @Route("/{id}/edit-details", name="top_edit_details", methods={"GET", "POST"})
     */
    public function editDetails(Request $request, Top $top)
    {
        $this->denyAccessUnlessGranted('edit-details', $top);

        /** @var Form $form */
        $form = $this->createForm(TopDetailsType::class, $top);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($top);
            $em->flush();

            return $this->redirectToRoute('top_show', ['id' => $top->getId()]);
        }

        return $this->render('Top/edit-details.html.twig', ['form' => $form->createView(), 'create' => false]);
    }

    /**
     * Deletes a top
     *
     * @param Top $top
     * @param EntityManagerInterface $em
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     *
     * @Route("/{id}/delete", name="top_delete", methods={"GET"})
     */
    public function delete(Top $top, EntityManagerInterface $em)
    {
        $this->denyAccessUnlessGranted('delete', $top);

        $em->remove($top);
        $em->flush();

        return $this->redirectToRoute('top_list');
    }

    /**
     * Ajax route for autocomplete search (search "q" parameter)
     *
     * @param Request $request
     * @param EntityManagerInterface $em
     * @return JsonResponse
     *
     * @Route(
     *     "/search/coasters.json",
     *     name="top_ajax_search",
     *     methods={"GET"},
     *     options = {"expose" = true},
     *     condition="request.isXmlHttpRequest()"
     * )
     */
    public function ajaxSearch(Request $request, EntityManagerInterface $em)
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
