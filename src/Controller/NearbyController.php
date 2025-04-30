<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\CoasterRepository;
use App\Repository\ManufacturerRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/nearby')]
class NearbyController extends AbstractController
{
    protected PaginatorInterface $paginator;

    public function __construct(PaginatorInterface $paginator)
    {
        $this->paginator = $paginator;
    }

    #[Route(path: '/', name: 'nearby_index', methods: ['GET'])]
    public function indexAction(ManufacturerRepository $manufacturerRepository, CoasterRepository $coasterRepository): Response
    {
        return $this->render(
            'Nearby/index.html.twig',
            [
                'filters' => ['status' => 'on'],
                'filtersForm' => [
                    'manufacturer' => $manufacturerRepository->findBy([], ['name' => 'asc']),
                    'openingDate' => $coasterRepository->getDistinctOpeningYears(),
                ],
            ]
        );
    }

    #[Route(path: '/coasters', name: 'nearby_coasters_ajax', options: ['expose' => true], methods: ['GET'], condition: 'request.isXmlHttpRequest()')]
    public function searchAction(
        CoasterRepository $coasterRepository,
        #[MapQueryParameter]
        array $filters = [],
        #[MapQueryParameter]
        int $page = 1
    ): Response {
        try {
            $pagination = $this->paginator->paginate(
                $coasterRepository->getNearbyCoasters($filters),
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
