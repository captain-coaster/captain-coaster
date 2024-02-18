<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Coaster;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CoasterCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Coaster::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Coaster')
            ->setEntityLabelInPlural('Coasters')
            ->setSearchFields(['id', 'name', 'park.name', 'manufacturer.name'])
            ->setDefaultSort(['updatedAt' => 'DESC'])
            ->showEntityActionsInlined()
            ->setPaginatorPageSize(50);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('name')
            ->add('park')
            ->add('materialType')
            ->add('seatingType')
            ->add('manufacturer')
            ->add('restraint')
            ->add('launchs')
            ->add('speed')
            ->add('height')
            ->add('length')
            ->add('inversionsNumber')
            ->add('kiddie')
            ->add('vr')
            ->add('indoor')
            ->add('status')
            ->add('enabled');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('name'),
            TextField::new('slug')->onlyWhenUpdating()->setFormTypeOption('disabled', 'disabled'),
            AssociationField::new('park'),
            AssociationField::new('materialType')->hideOnIndex(),
            AssociationField::new('seatingType')->hideOnIndex(),
            AssociationField::new('model')->hideOnIndex(),
            AssociationField::new('manufacturer'),
            AssociationField::new('restraint')->hideOnIndex(),
            AssociationField::new('launchs')->hideOnIndex(),
            TextField::new('youtubeId')->hideOnIndex(),
            AssociationField::new('ratings')->onlyOnIndex(),
            IntegerField::new('speed')->hideOnIndex(),
            IntegerField::new('height')->hideOnIndex(),
            IntegerField::new('length')->hideOnIndex(),
            IntegerField::new('inversionsNumber')->hideOnIndex(),
            BooleanField::new('kiddie')->hideOnIndex(),
            BooleanField::new('vr')->hideOnIndex(),
            BooleanField::new('indoor')->hideOnIndex(),
            AssociationField::new('status')->hideOnIndex(),
            DateField::new('openingDate')->hideOnIndex(),
            DateField::new('closingDate')->hideOnIndex(),
            IntegerField::new('price')->hideOnIndex(),
            AssociationField::new('currency')->hideOnIndex(),
            BooleanField::new('enabled'),
            DateTimeField::new('createdAt')->hideOnIndex()->setFormTypeOption('disabled', 'disabled'),
            DateTimeField::new('updatedAt')->setFormTypeOption('disabled', 'disabled'),
        ];
    }
}
