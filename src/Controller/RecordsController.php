<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\RecordsService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RecordsController extends BaseController
{
    /**
     * World records page — top 3 coasters per category (tallest, longest, fastest,
     * most inversions). Filterable by continent.
     */
    #[Route(path: '/records', name: 'records_index', methods: ['GET'])]
    public function index(Request $request, RecordsService $recordsService): Response
    {
        $pills = $recordsService->getContinentPills();
        $allowedSlugs = array_filter(array_column($pills, 'slug'));

        $continentSlug = $request->query->getString('continent');
        $continentSlug = '' !== $continentSlug && \in_array($continentSlug, $allowedSlugs, true)
            ? $continentSlug
            : null;

        return $this->render('Records/index.html.twig', [
            'records' => $recordsService->getRecords($continentSlug),
            'continentPills' => $pills,
            'currentContinent' => $continentSlug,
        ]);
    }
}
