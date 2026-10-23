<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Entity\Coaster;
use App\Entity\CoasterSummary;
use App\Entity\Continent;
use App\Entity\Country;
use App\Entity\Currency;
use App\Entity\Image;
use App\Entity\ImageReport;
use App\Entity\Launch;
use App\Entity\Manufacturer;
use App\Entity\MaterialType;
use App\Entity\Model;
use App\Entity\Park;
use App\Entity\RankingHistory;
use App\Entity\Restraint;
use App\Entity\ReviewReport;
use App\Entity\RiddenCoaster;
use App\Entity\SeatingType;
use App\Entity\Status;
use App\Entity\Tag;
use App\Entity\Top;
use App\Entity\User;
use App\Repository\ImageReportRepository;
use App\Repository\ImageRepository;
use App\Repository\ReviewReportRepository;
use App\Repository\RiddenCoasterRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly ReviewReportRepository $reviewReportRepository,
        private readonly ImageReportRepository $imageReportRepository,
        private readonly RiddenCoasterRepository $riddenCoasterRepository,
        private readonly ImageRepository $imageRepository,
    ) {
    }

    public function index(): Response
    {
        // redirect to some CRUD controller
        $routeBuilder = $this->adminUrlGenerator;

        return $this->redirect($routeBuilder->setController(CoasterCrudController::class)->generateUrl());
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setTitle('Captain Coaster')->setFaviconPath('favicon.ico');
    }

    public function configureCrud(): Crud
    {
        return Crud::new();
    }

    public function configureMenuItems(): iterable
    {
        // Grouped by job: what contributors add, what moderators check, what the admin runs.
        $stuckBefore = new \DateTimeImmutable(ModerationQueueController::STUCK_AFTER);
        $pending = $this->reviewReportRepository->countPending() + $this->imageReportRepository->countPending()
            + $this->riddenCoasterRepository->countStuckInAnalysis($stuckBefore) + $this->imageRepository->countStuckInAnalysis($stuckBefore);
        yield MenuItem::linkToRoute('Moderation queue', 'fa fa-inbox', ModerationQueueController::ROUTE)
            ->setPermission('ROLE_MODERATOR')
            ->setBadge($pending > 0 ? $pending : null, 'warning');

        yield MenuItem::subMenu('Catalogue', 'fas fa-sleigh')->setSubItems([
            MenuItem::linkToCrud('Coasters', 'fas fa-sleigh', Coaster::class),
            MenuItem::linkToCrud('Parks', 'fas fa-dharmachakra', Park::class),
        ]);

        yield MenuItem::subMenu('Reference data', 'fa fa-book')->setSubItems([
            MenuItem::linkToCrud('Manufacturers', 'fas fa-industry', Manufacturer::class),
            MenuItem::linkToCrud('Models', 'fas fa-copyright', Model::class),
            MenuItem::linkToCrud('Material types', 'fas fa-cubes', MaterialType::class),
            MenuItem::linkToCrud('Launches', 'fas fa-wind', Launch::class),
            MenuItem::linkToCrud('Restraints', 'fas fa-lock', Restraint::class),
            MenuItem::linkToCrud('Seating types', 'fa fa-chair', SeatingType::class),
            MenuItem::linkToCrud('Statuses', 'fa fa-toggle-on', Status::class),
            MenuItem::linkToCrud('Continents', 'fa fa-globe', Continent::class),
            MenuItem::linkToCrud('Countries', 'fa fa-flag-usa', Country::class),
            MenuItem::linkToCrud('Currencies', 'fa fa-euro-sign', Currency::class),
            MenuItem::linkToCrud('Review tags', 'fa fa-tag', Tag::class)->setPermission('ROLE_MODERATOR'),
        ]);

        yield MenuItem::subMenu('Moderation', 'fa fa-shield-alt')->setPermission('ROLE_MODERATOR')->setSubItems([
            MenuItem::linkToCrud('Members', 'fas fa-users', User::class),
            MenuItem::linkToCrud('Reviews', 'fa fa-comment-dots', RiddenCoaster::class),
            MenuItem::linkToCrud('Photos', 'fas fa-image', Image::class),
            MenuItem::linkToCrud('Tops', 'fa fa-list-ol', Top::class)->setPermission('ROLE_ADMIN'),
            MenuItem::linkToCrud('AI summaries', 'fas fa-robot', CoasterSummary::class),
            MenuItem::section('History'),
            MenuItem::linkToCrud('Review reports', 'fa fa-flag', ReviewReport::class),
            MenuItem::linkToCrud('Photo reports', 'fa fa-flag', ImageReport::class),
        ]);

        yield MenuItem::linkToCrud('Ranking history', 'fas fa-trophy', RankingHistory::class)->setPermission('ROLE_ADMIN');
    }
}
