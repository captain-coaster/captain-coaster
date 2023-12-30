<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\RiddenCoaster;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ReviewCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return RiddenCoaster::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Review')
            ->setEntityLabelInPlural('Reviews')
            ->setSearchFields(['id', 'coaster.name', 'user.displayName', 'review'])
            ->setDefaultSort(['updatedAt' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('user')
            ->add('coaster')
            ->add('value')
            ->add('updatedAt');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable('new');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('user'),
            AssociationField::new('coaster'),
            DateField::new('riddenAt')->hideOnIndex(),
            NumberField::new('value'),
            AssociationField::new('pros')->hideOnIndex(),
            AssociationField::new('cons')->hideOnIndex(),
            TextField::new('language')->hideOnIndex(),
            TextareaField::new('review')->setMaxLength(Crud::PAGE_DETAIL === $pageName ? 1024 : 50),
            IntegerField::new('like')->hideOnIndex(),
            IntegerField::new('dislike')->hideOnIndex(),
            DateTimeField::new('createdAt')->onlyWhenUpdating()->setFormTypeOption('disabled', 'disabled'),
            DateTimeField::new('updatedAt')->hideWhenCreating()->setFormTypeOption('disabled', 'disabled'),
        ];
    }
}
