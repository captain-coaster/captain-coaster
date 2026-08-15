<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Coaster;
use App\Entity\Top;
use App\Entity\TopCoaster;
use App\Entity\User;
use App\Form\Type\TopDetailsType;
use App\Repository\TopLikeRepository;
use App\Repository\TopRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Knp\Component\Pager\PaginatorInterface;
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
    /** Create a new custom list. */
    #[Route(path: '/new', name: 'top_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function newAction(Request $request, EntityManagerInterface $em): Response
    {
        $top = new Top();
        $top->setType(Top::TYPE_CUSTOM);

        $form = $this->createForm(TopDetailsType::class, $top);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $top->setUser($this->getUser());

            $em->persist($top);
            $em->flush();

            return $this->redirectToRoute('top_edit', ['id' => $top->getId()]);
        }

        return $this->render('Top/new.html.twig', ['form' => $form]);
    }

    /**
     * Explore public lists (Top Coasters + Bucket Lists + Custom Lists).
     * Returns a card fragment on XHR for infinite scroll.
     */
    #[Route(path: '/', name: 'top_explore', methods: ['GET'])]
    public function explore(
        Request $request,
        PaginatorInterface $paginator,
        EntityManagerInterface $em,
        TopLikeRepository $likeRepository,
        #[MapQueryParameter]
        int $page = 1,
        #[MapQueryParameter]
        ?string $type = null,
        #[MapQueryParameter]
        ?string $q = null,
    ): Response {
        $type = $this->normaliseExploreType($type);
        $q = null !== $q ? trim($q) : null;
        if ('' === $q) {
            $q = null;
        }

        /** @var TopRepository $repo */
        $repo = $em->getRepository(Top::class);

        try {
            $pagination = $paginator->paginate($repo->findPublicTops($type, $q), $page, 12, ['wrap-queries' => true]);
        } catch (\UnexpectedValueException) {
            throw new BadRequestHttpException();
        }

        $likedIds = ($this->getUser() instanceof User) ? $likeRepository->findLikedTopIds($this->getUser()) : [];

        if ($request->isXmlHttpRequest()) {
            return $this->render('Top/_explore_cards.html.twig', [
                'tops' => $pagination,
                'type' => $type,
                'q' => $q,
                'likedIds' => $likedIds,
            ]);
        }

        return $this->render('Top/explore.html.twig', [
            'tops' => $pagination,
            'type' => $type,
            'q' => $q,
            'counts' => $repo->countPublicTopsByType(),
            'likedIds' => $likedIds,
        ]);
    }

    private function normaliseExploreType(?string $type): ?string
    {
        if (null === $type || '' === $type || 'all' === $type) {
            return null;
        }

        return match ($type) {
            'top_coaster', Top::TYPE_RANKING => Top::TYPE_RANKING,
            'bucket', Top::TYPE_BUCKET => Top::TYPE_BUCKET,
            'custom', Top::TYPE_CUSTOM => Top::TYPE_CUSTOM,
            default => null,
        };
    }

    /**
     * Displays a top.
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    #[Route(path: '/{id}', name: 'top_show', methods: ['GET'])]
    #[IsGranted('view', 'top', statusCode: 403)]
    public function show(Top $top, EntityManagerInterface $em, TopLikeRepository $likeRepository): Response
    {
        $userHasLiked = false;
        if ($top->isCustom() && $this->getUser() instanceof User) {
            $userHasLiked = null !== $likeRepository->findOneByUserAndTop($this->getUser(), $top);
        }

        return $this->render('Top/show.html.twig', [
            'top' => $em->getRepository(Top::class)->getTopWithData($top),
            'userHasLiked' => $userHasLiked,
        ]);
    }

    /**
     * Edits a top. All persistence happens through the auto-save endpoint.
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    #[Route(path: '/{id}/edit', name: 'top_edit', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted('edit', 'top', statusCode: 403)]
    public function edit(Top $top, EntityManagerInterface $em): Response
    {
        return $this->render('Top/edit.html.twig', [
            'top' => $em->getRepository(Top::class)->getTopWithData($top),
        ]);
    }

    /** Toggle a custom list's public/private flag. */
    #[Route(path: '/{id}/visibility', name: 'top_visibility', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted('edit-details', 'top', statusCode: 403)]
    public function visibility(Request $request, Top $top, EntityManagerInterface $em): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('top_visibility'.$top->getId(), (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token');
        }

        $top->setIsPublic(!$top->isPublic());
        $top->setUpdatedAt(new \DateTime());
        $em->flush();

        return $this->redirectToRoute('top_edit', ['id' => $top->getId()]);
    }

    /** Rename a custom list (inline from the editor). */
    #[Route(path: '/{id}/rename', name: 'top_rename', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted('edit-details', 'top', statusCode: 403)]
    public function rename(Request $request, Top $top, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('top_rename'.$top->getId(), (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token');
        }

        $name = trim($request->request->getString('name'));
        if ('' !== $name) {
            $top->setName($name);
            $top->setUpdatedAt(new \DateTime());
            $em->flush();
        }

        return $this->redirectToRoute('top_edit', ['id' => $top->getId()]);
    }

    /** Deletes a top. */
    #[Route(path: '/{id}/delete', name: 'top_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted('delete', 'top', statusCode: 403)]
    public function delete(Request $request, Top $top, EntityManagerInterface $em): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('top_delete'.$top->getId(), (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token');
        }

        $ownerId = $top->getUser()->getId();
        $em->remove($top);
        $em->flush();

        return $this->redirectToRoute('user_tops', ['id' => $ownerId]);
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
                if (!\in_array($coasterId, $positionCoasterIds, true)) {
                    $top->removeTopCoaster($topCoaster);
                    $em->remove($topCoaster);
                    unset($existingTopCoasters[$coasterId]);
                }
            }

            // 2. Batch load new coasters (single query)
            $newCoasterIds = array_diff($positionCoasterIds, array_keys($existingTopCoasters));
            $newCoasters = [];
            if (!empty($newCoasterIds)) {
                $loadedCoasters = $em->getRepository(Coaster::class)
                    ->createQueryBuilder('c')
                    ->where('c.id IN (:ids)')
                    ->setParameter('ids', $newCoasterIds)
                    ->getQuery()
                    ->getResult();

                // Build array indexed by coaster ID
                foreach ($loadedCoasters as $coaster) {
                    $newCoasters[$coaster->getId()] = $coaster;
                }
            }

            // 3. Update positions and create new TopCoasters
            foreach ($positions as $coasterId => $position) {
                $coasterId = (int) $coasterId;
                $newPosition = (int) $position;

                if ($newPosition <= 0) {
                    continue;
                }

                if (isset($existingTopCoasters[$coasterId])) {
                    // Update existing
                    $existingTopCoasters[$coasterId]->setPosition($newPosition);
                } elseif (isset($newCoasters[$coasterId])) {
                    // Create new
                    $topCoaster = new TopCoaster();
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

            // Refresh the entity to get accurate count
            $em->refresh($top);

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
