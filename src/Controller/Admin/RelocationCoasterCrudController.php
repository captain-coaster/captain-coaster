<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\RelocationCoaster;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

class RelocationCoasterCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return RelocationCoaster::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('coaster')
                ->autocomplete()
                ->setColumns('col-12'),
            IntegerField::new('position')->setColumns('col-12'),
        ];
    }
}
