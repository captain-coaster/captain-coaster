<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Top;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

/**
 * @extends AbstractCrudController<Top>
 */
class TopCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Top::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Top')
            ->setEntityLabelInPlural('Tops')
            ->setSearchFields(['id', 'name', 'user.displayName'])
            ->setDefaultSort(['updatedAt' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('user')->autocomplete())
            ->add('main')
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
            TextField::new('name'),
            AssociationField::new('user')->autocomplete(),
            BooleanField::new('main'),
            AssociationField::new('topCoasters')->onlyOnIndex(),
            DateTimeField::new('createdAt')->onlyWhenUpdating()->setFormTypeOption('disabled', 'disabled'),
            DateTimeField::new('updatedAt')->hideWhenCreating()->setFormTypeOption('disabled', 'disabled'),
        ];
    }
}
