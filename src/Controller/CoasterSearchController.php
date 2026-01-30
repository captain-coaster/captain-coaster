<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\CoasterRepository;
use App\Service\FilterService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/search-coaster')]
class CoasterSearchController extends AbstractController
{
    public function __construct(
        private readonly PaginatorInterface $paginator,
        private readonly CoasterRepository $coasterRepository,
        private readonly FilterService $filterService
    ) {
    }

    #[Route(path: '/', name: 'coaster_search_index', methods: ['GET'])]
    public function indexAction(): Response
    {
        return $this->render(
            'CoasterSearch/index.html.twig',
            [
                'filters' => [],
                'filtersForm' => $this->filterService->getFilterData(),
            ]
        );
    }

    /** @param array<string, mixed> $filters */
    #[Route(path: '/api', name: 'coaster_search_api', options: ['expose' => true], methods: ['GET'], condition: 'request.isXmlHttpRequest()')]
    public function searchAction(
        #[MapQueryParameter]
        array $filters = [],
        #[MapQueryParameter]
        int $page = 1
    ): Response {
        try {
            // Validate and authorize filters
            $validatedFilters = $this->filterService->validateAndAuthorize(
                $filters,
                'search',
                $this->getUser()
            );

            $pagination = $this->paginator->paginate(
                $this->coasterRepository->findForSearch($validatedFilters),
                $page,
                20
            );
        } catch (AccessDeniedHttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        return $this->render('CoasterSearch/results.html.twig', ['coasters' => $pagination]);
    }
}
