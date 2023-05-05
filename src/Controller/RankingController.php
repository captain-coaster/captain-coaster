<?php

namespace App\Controller;

use App\Entity\Coaster;
use App\Entity\Continent;
use App\Entity\Country;
use App\Entity\Manufacturer;
use App\Entity\MaterialType;
use App\Entity\Model;
use App\Entity\Ranking;
use App\Entity\SeatingType;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class RankingController
 * @package App\Controller
 */
#[Route(path: '/ranking')]
class RankingController extends AbstractController
{
    final public const COASTERS_PER_PAGE = 20;

    /**
     * @var PaginatorInterface
     */
    protected $paginator;

    /**
     * RankingController constructor.
     */
    public function __construct(PaginatorInterface $paginator)
    {
        $this->paginator = $paginator;
    }

    /**
     * Show ranking of best coasters
     *
     * @throws \Exception
     */
    #[Route(path: '/', name: 'ranking_index', methods: ['GET'])]
    public function indexAction(EntityManagerInterface $em): Response
    {
        return $this->render(
            'Ranking/index.html.twig',
            [
                'ranking' => $em->getRepository(Ranking::class)->findCurrent(),
                'previousRanking' => $em->getRepository(Ranking::class)->findPrevious(),
                'filtersForm' => $this->getFiltersForm(),
            ]
        );
    }

    /**
     *
     * @throws \Exception
     */
    #[Route(path: '/coasters', name: 'ranking_search_async', methods: ['GET'], options: ['expose' => true], condition: 'request.isXmlHttpRequest()')]
    public function searchAsyncAction(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $filters = $request->get('filters', []);
        $page = $request->get('page', 1);

        return $this->render(
            'Ranking/results.html.twig',
            [
                'coasters' => $this->getCoasters($filters, $page),
                // array_filter removes empty filters e.g. ['continent' => '']
                'filtered' => (array) array_filter($filters, "strlen") !== [],
                'firstRank' => self::COASTERS_PER_PAGE * ($page - 1) + 1,
            ]
        );
    }

    /**
     * Learn more on the ranking
     */
    #[Route(path: '/learn-more', name: 'ranking_learn_more', methods: ['GET'])]
    public function learnMore(): \Symfony\Component\HttpFoundation\Response
    {
        return $this->render('Ranking/learn_more.html.twig');
    }

    /**
     * @throws \Exception
     */
    private function getCoasters(array $filters = [], int $page = 1): PaginationInterface
    {
        try {
            return $this->paginator->paginate(
                $this->getDoctrine()->getRepository(Ranking::class)->findCoastersRanked($filters),
                $page,
                self::COASTERS_PER_PAGE
            );
        } catch (\UnexpectedValueException) {
            throw new BadRequestHttpException();
        }
    }

    /**
     * Get data to display filter form (mainly <select> data)
     *
     * @return array
     */
    private function getFiltersForm()
    {
        $cache = new FilesystemAdapter();
        $filtersForm = $cache->getItem('ranking_filters_form');

        if (!$filtersForm->isHit()) {
            $data = [];
            $data['continent'] = $this->getDoctrine()
                ->getRepository(Continent::class)
                ->findBy([], ["name" => "asc"]);

            $data['country'] = $this->getDoctrine()
                ->getRepository(Country::class)
                ->findBy([], ["name" => "asc"]);

            $data['materialType'] = $this->getDoctrine()
                ->getRepository(MaterialType::class)
                ->findBy([], ["name" => "asc"]);

            $data['seatingType'] = $this->getDoctrine()
                ->getRepository(SeatingType::class)
                ->findBy([], ["name" => "asc"]);

            $data['model'] = $this->getDoctrine()
                ->getRepository(Model::class)
                ->findBy([], ['name' => 'asc']);

            $data['manufacturer'] = $this->getDoctrine()
                ->getRepository(Manufacturer::class)
                ->findBy([], ["name" => "asc"]);

            $data['openingDate'] = $this->getDoctrine()
                ->getRepository(Coaster::class)
                ->getDistinctOpeningYears();

            $filtersForm->set($data);
            $filtersForm->expiresAfter(\DateInterval::createFromDateString('7 days'));
            $cache->save($filtersForm);
        }

        return $filtersForm->get();
    }
}
