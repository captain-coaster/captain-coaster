<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Coaster;
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
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin')]
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
        yield MenuItem::linkToCrud('Coaster', 'fas fa-sleigh', Coaster::class);
        yield MenuItem::linkToCrud('Continent', 'fa fa-globe', Continent::class);
        yield MenuItem::linkToCrud('Country', 'fa fa-flag-usa', Country::class);
        yield MenuItem::linkToCrud('Currency', 'fa fa-euro-sign', Currency::class);
        yield MenuItem::linkToCrud('Pictures', 'fas fa-image', Image::class);
        yield MenuItem::linkToCrud('Launch', 'fas fa-wind', Launch::class);
        yield MenuItem::linkToCrud('Manufacturer', 'fas fa-industry', Manufacturer::class);
        yield MenuItem::linkToCrud('Material Type', 'fas fa-cubes', MaterialType::class);
        yield MenuItem::linkToCrud('Model', 'fas fa-copyright', Model::class);
        yield MenuItem::linkToCrud('Park', 'fas fa-dharmachakra', Park::class);
        yield MenuItem::linkToCrud('Ranking History', 'fas fa-trophy', RankingHistory::class);
        yield MenuItem::linkToCrud('Restraint', 'fas fa-lock', Restraint::class);
        yield MenuItem::linkToCrud('Review', 'fa fa-comment-dots', RiddenCoaster::class);
        yield MenuItem::linkToCrud('Review Reports', 'fa fa-flag', ReviewReport::class);
        yield MenuItem::linkToCrud('Seating Type', 'fa fa-chair', SeatingType::class);
        yield MenuItem::linkToCrud('Status', 'fa fa-toggle-on', Status::class);
        yield MenuItem::linkToCrud('Tag', 'fa fa-tag', Tag::class);
        yield MenuItem::linkToCrud('Top', 'fa fa-list-ol', Top::class);
        yield MenuItem::linkToCrud('User', 'fas fa-users', User::class);
    }
}
