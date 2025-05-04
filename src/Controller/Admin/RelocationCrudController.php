<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Relocation;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;

class RelocationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Relocation::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Relocation')
            ->setEntityLabelInPlural('Relocations')
            ->setSearchFields(['id', 'coasters.coaster.name', 'coasters.coaster.park.name'])
            ->setDefaultSort(['updatedAt' => 'DESC'])
            ->showEntityActionsInlined()
            ->setPaginatorPageSize(50);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            CollectionField::new('coasters')
                ->useEntryCrudForm(RelocationCoasterCrudController::class)
                ->renderExpanded(),
            DateTimeField::new('createdAt')->hideOnIndex()->setFormTypeOption('disabled', 'disabled'),
            DateTimeField::new('updatedAt')->setFormTypeOption('disabled', 'disabled'),
        ];
    }
}
