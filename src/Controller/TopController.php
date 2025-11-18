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
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/tops')]
class TopController extends BaseController
{
    /** Create a new top. */
    #[Route(path: '/new', name: 'top_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function newAction(Request $request, EntityManagerInterface $em, TopRepository $topRepository): Response
    {
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

    /** Displays all tops. */
    #[Route(path: '/', name: 'top_list', methods: ['GET'])]
    public function list(PaginatorInterface $paginator, EntityManagerInterface $em, #[MapQueryParameter] int $page = 1): Response
    {
        try {
            $pagination = $paginator->paginate($em->getRepository(Top::class)->findAllTops(), $page, 9, ['wrap-queries' => true]);
        } catch (\UnexpectedValueException) {
            throw new BadRequestHttpException();
        }

        return $this->render('Top/list.html.twig', [
            'tops' => $pagination,
        ]);
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
        return $this->render('Top/show.html.twig', [
            'top' => $em->getRepository(Top::class)->getTopWithData($top),
        ]);
    }

    /**
     * Edits a top.
     *
     * @throws \Exception
     */
    #[Route(path: '/{id}/edit', name: 'top_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted('edit', 'top', statusCode: 403)]
    public function edit(Request $request, Top $top, EntityManagerInterface $em): Response
    {
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

        return $this->render('Top/edit.html.twig', [
            'form' => $form,
            'topName' => $top->getName(),
        ]);
    }

    /** Edits details of a top (name, type). */
    #[Route(path: '/{id}/edit-details', name: 'top_edit_details', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted('edit-details', 'top', statusCode: 403)]
    public function editDetails(Request $request, Top $top, EntityManagerInterface $em): Response
    {
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

    /** Deletes a top. */
    #[Route(path: '/{id}/delete', name: 'top_delete', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted('delete', 'top', statusCode: 403)]
    public function delete(Top $top, EntityManagerInterface $em): RedirectResponse
    {
        $em->remove($top);
        $em->flush();

        return $this->redirectToRoute('top_list');
    }

    /** Ajax route for autocomplete search (search "q" parameter). */
    #[Route(path: '/search/coasters.json', name: 'top_ajax_search', options: ['expose' => true], methods: ['GET'], condition: 'request.isXmlHttpRequest()')]
    #[IsGranted('ROLE_USER')]
    public function ajaxSearch(Request $request, EntityManagerInterface $em): JsonResponse
    {
        if (!$request->get('q')) {
            return new JsonResponse([]);
        }

        return new JsonResponse([
            'items' => $em->getRepository(Coaster::class)->suggestCoasterForTop($request->get('q'), $this->getUser()),
        ]);
    }

    /** Auto-save positions for drag and drop reordering. */
    #[Route(path: '/{id}/auto-save', name: 'top_auto_save', methods: ['POST'], condition: 'request.isXmlHttpRequest()')]
    #[IsGranted('ROLE_USER')]
    #[IsGranted('edit', 'top', statusCode: 403)]
    public function autoSave(Request $request, Top $top, EntityManagerInterface $em): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['positions']) || !\is_array($data['positions'])) {
                throw new BadRequestHttpException('Invalid positions data');
            }

            $positions = $data['positions'];

            $positionCoasterIds = array_map('intval', array_keys($positions));
            
            // Build lookup map for existing TopCoasters (O(1) lookups)
            $existingTopCoasters = [];
            foreach ($top->getTopCoasters() as $topCoaster) {
                $existingTopCoasters[$topCoaster->getCoaster()->getId()] = $topCoaster;
            }
            
            // 1. Remove TopCoasters no longer in positions
            foreach ($existingTopCoasters as $coasterId => $topCoaster) {
                if (!in_array($coasterId, $positionCoasterIds, true)) {
                    $top->removeTopCoaster($topCoaster);
                    $em->remove($topCoaster);
                    unset($existingTopCoasters[$coasterId]);
                }
            }
            
            // 2. Batch load new coasters (single query)
            $newCoasterIds = array_diff($positionCoasterIds, array_keys($existingTopCoasters));
            $newCoasters = [];
            if (!empty($newCoasterIds)) {
                $newCoasters = $em->getRepository(Coaster::class)
                    ->createQueryBuilder('c')
                    ->where('c.id IN (:ids)')
                    ->setParameter('ids', $newCoasterIds)
                    ->getQuery()
                    ->getResult();
                $newCoasters = array_column($newCoasters, null, 'id');
            }
            
            // 3. Update positions and create new TopCoasters
            foreach ($positions as $coasterId => $position) {
                $coasterId = (int) $coasterId;
                $newPosition = (int) $position;
                
                if ($newPosition <= 0) continue;
                
                if (isset($existingTopCoasters[$coasterId])) {
                    // Update existing
                    $existingTopCoasters[$coasterId]->setPosition($newPosition);
                } elseif (isset($newCoasters[$coasterId])) {
                    // Create new
                    $topCoaster = new \App\Entity\TopCoaster();
                    $topCoaster->setTop($top);
                    $topCoaster->setCoaster($newCoasters[$coasterId]);
                    $topCoaster->setPosition($newPosition);
                    $top->addTopCoaster($topCoaster);
                    $em->persist($topCoaster);
                }
            }

            // Update the top's modified date
            $top->setUpdatedAt(new \DateTime());

            $em->flush();

            return new JsonResponse([
                'status' => 'success',
                'message' => 'Positions updated successfully',
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
