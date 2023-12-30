<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Coaster;
use App\Entity\Continent;
use App\Entity\Country;
use App\Entity\Manufacturer;
use App\Entity\MaterialType;
use App\Entity\Model;
use App\Entity\SeatingType;
use App\Repository\RankingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/ranking')]
class RankingController extends AbstractController
{
    final public const COASTERS_PER_PAGE = 20;

    public function __construct(
        private readonly PaginatorInterface $paginator,
        private readonly RankingRepository $rankingRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Show ranking of best coasters.
     *
     * @throws InvalidArgumentException
     */
    #[Route(path: '/', name: 'ranking_index', methods: ['GET'])]
    public function indexAction(): Response
    {
        return $this->render(
            'ranking/index.html.twig',
            [
                'ranking' => $this->rankingRepository->findCurrent(),
                'previousRanking' => $this->rankingRepository->findPrevious(),
                'filtersForm' => $this->getFiltersForm(),
            ]
        );
    }

    /**
     * Get data to display filter form (mainly <select> data).
     *
     * @throws InvalidArgumentException
     */
    private function getFiltersForm(): array
    {
        $cache = new FilesystemAdapter();
        $filtersForm = $cache->getItem('ranking_filters_form');

        if (!$filtersForm->isHit()) {
            $data = [];
            $data['continent'] = $this->em->getRepository(Continent::class)->findBy([], ['name' => 'asc']);
            $data['country'] = $this->em->getRepository(Country::class)->findBy([], ['name' => 'asc']);
            $data['materialType'] = $this->em->getRepository(MaterialType::class)->findBy([], ['name' => 'asc']);
            $data['seatingType'] = $this->em->getRepository(SeatingType::class)->findBy([], ['name' => 'asc']);
            $data['model'] = $this->em->getRepository(Model::class)->findBy([], ['name' => 'asc']);
            $data['manufacturer'] = $this->em->getRepository(Manufacturer::class)->findBy([], ['name' => 'asc']);
            $data['openingDate'] = $this->em->getRepository(Coaster::class)->getDistinctOpeningYears();

            $filtersForm->set($data);
            $filtersForm->expiresAfter(\DateInterval::createFromDateString('7 days'));
            $cache->save($filtersForm);
        }

        return $filtersForm->get();
    }

    /** @throws \Exception */
    #[Route(
        path: '/coasters',
        name: 'ranking_search_async',
        options: ['expose' => true],
        methods: ['GET'],
        condition: 'request.isXmlHttpRequest()'
    )]
    public function searchAsyncAction(#[MapQueryParameter] array $filters = [], #[MapQueryParameter] int $page = 1): Response
    {
        try {
            $pagination = $this->paginator->paginate(
                $this->rankingRepository->findCoastersRanked($filters),
                $page,
                self::COASTERS_PER_PAGE
            );
        } catch (\Exception) {
            throw new BadRequestHttpException();
        }

        return $this->render(
            'ranking/results.html.twig',
            [
                'coasters' => $pagination,
                // array_filter removes empty filters e.g. ['continent' => '']
                'filtered' => [] !== array_filter($filters, 'strlen'),
                'firstRank' => self::COASTERS_PER_PAGE * ($page - 1) + 1,
            ]
        );
    }

    /** Learn more on the ranking. */
    #[Route(path: '/learn-more', name: 'ranking_learn_more', methods: ['GET'])]
    public function learnMore(): Response
    {
        return $this->render('ranking/learn_more.html.twig');
    }
}
