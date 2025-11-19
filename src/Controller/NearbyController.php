<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\FilterService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/nearby')]
class NearbyController extends AbstractController
{
    public function __construct(
        private readonly PaginatorInterface $paginator,
        private readonly FilterService $filterService
    ) {
    }

    #[Route(path: '/', name: 'nearby_index', methods: ['GET'])]
    public function indexAction(): Response
    {
        return $this->render(
            'Nearby/index.html.twig',
            [
                'filters' => ['status' => 'on'],
                'filtersForm' => $this->filterService->getFilterData(),
            ]
        );
    }

    #[Route(path: '/coasters', name: 'nearby_coasters_ajax', options: ['expose' => true], methods: ['GET'], condition: 'request.isXmlHttpRequest()')]
    public function searchAction(
        #[MapQueryParameter]
        array $filters = [],
        #[MapQueryParameter]
        int $page = 1
    ): Response {
        try {
            $pagination = $this->paginator->paginate(
                $this->filterService->getFilteredNearby($filters),
                $page,
                20,
                ['wrap-queries' => true]
            );
        } catch (\Exception) {
            throw new BadRequestHttpException();
        }

        return $this->render('Nearby/results.html.twig', ['coasters' => $pagination]);
    }
}
