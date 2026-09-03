<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Coaster;
use App\Entity\CoasterSummary;
use App\Entity\Continent;
use App\Entity\Country;
use App\Entity\Currency;
use App\Entity\Image;
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
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends AbstractDashboardController
{
    public function __construct(private readonly AdminUrlGenerator $adminUrlGenerator)
    {
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
        // yield MenuItem::linkToDashboard('Dashboard', 'fa fa-dashboard');
        yield MenuItem::subMenu('Content', 'fas fa-sleigh')->setSubItems([
            MenuItem::linkToCrud('Coaster', 'fas fa-sleigh', Coaster::class),
            MenuItem::linkToCrud('Park', 'fas fa-dharmachakra', Park::class),
            MenuItem::linkToCrud('Manufacturer', 'fas fa-industry', Manufacturer::class),
            MenuItem::linkToCrud('Model', 'fas fa-copyright', Model::class),
            MenuItem::linkToCrud('Material Type', 'fas fa-cubes', MaterialType::class),
            MenuItem::linkToCrud('Launch', 'fas fa-wind', Launch::class),
            MenuItem::linkToCrud('Restraint', 'fas fa-lock', Restraint::class),
            MenuItem::linkToCrud('Seating Type', 'fa fa-chair', SeatingType::class),
            MenuItem::linkToCrud('Status', 'fa fa-toggle-on', Status::class),
            MenuItem::linkToCrud('Continent', 'fa fa-globe', Continent::class),
            MenuItem::linkToCrud('Country', 'fa fa-flag-usa', Country::class),
            MenuItem::linkToCrud('Currency', 'fa fa-euro-sign', Currency::class),
            MenuItem::linkToCrud('Pictures', 'fas fa-image', Image::class),
        ]);

        yield MenuItem::subMenu('Moderation', 'fa fa-shield-alt')->setPermission('ROLE_MODERATOR')->setSubItems([
            MenuItem::linkToCrud('User', 'fas fa-users', User::class),
            MenuItem::linkToCrud('Review', 'fa fa-comment-dots', RiddenCoaster::class),
            MenuItem::linkToCrud('Reports', 'fa fa-flag', ReviewReport::class),
            MenuItem::linkToCrud('Tag', 'fa fa-tag', Tag::class),
            MenuItem::linkToCrud('AI Summaries', 'fas fa-robot', CoasterSummary::class),
        ]);

        yield MenuItem::subMenu('Administration', 'fa fa-user-shield')->setPermission('ROLE_ADMIN')->setSubItems([
            MenuItem::linkToCrud('Top', 'fa fa-list-ol', Top::class),
            MenuItem::linkToCrud('Ranking History', 'fas fa-trophy', RankingHistory::class),
        ]);
    }
}
