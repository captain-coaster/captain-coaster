<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Park;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class ParkCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Park::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Park')
            ->setEntityLabelInPlural('Parks')
            ->setSearchFields(['id', 'name', 'country.name'])
            ->setDefaultSort(['updatedAt' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->setPermission(Action::DELETE, 'ROLE_ADMIN');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('name')
            ->add(EntityFilter::new('country')->autocomplete());
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('name'),
            ArrayField::new('formerNames')->hideOnIndex(),
            TextField::new('slug')->onlyWhenUpdating()->setFormTypeOption('disabled', 'disabled'),
            NumberField::new('latitude')->hideOnIndex(),
            NumberField::new('longitude')->hideOnIndex(),
            AssociationField::new('country'),
            AssociationField::new('coasters'),
            BooleanField::new('enabled'),
            DateTimeField::new('createdAt')->onlyWhenUpdating()->setFormTypeOption('disabled', 'disabled'),
            DateTimeField::new('updatedAt')->hideWhenCreating()->setFormTypeOption('disabled', 'disabled'),
        ];
    }
}
