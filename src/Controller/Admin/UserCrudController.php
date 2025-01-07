<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('User')
            ->setEntityLabelInPlural('Users')
            ->setSearchFields(['id', 'firstName', 'lastName', 'displayName', 'email'])
            ->setDefaultSort(['lastLogin' => 'DESC'])
            ->showEntityActionsInlined()
            ->setPaginatorPageSize(50);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable('new')
            ->setPermission(Action::DELETE, 'ROLE_SUPER_ADMIN');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('firstName')
            ->add('lastName')
            ->add('email')
            ->add('enabled')
            ->add('createdAt')
            ->add('updatedAt')
            ->add('lastLogin');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('firstName')->setFormTypeOption('disabled', 'disabled'),
            TextField::new('lastName')->setFormTypeOption('disabled', 'disabled'),
            TextField::new('displayName')->hideOnIndex(),
            ArrayField::new('roles')->setPermission('ROLE_SUPER_ADMIN'),
            TextField::new('slug')->onlyWhenUpdating()->setFormTypeOption('disabled', 'disabled'),
            TextField::new('email'),
            TextField::new('facebookId')->hideOnIndex(),
            TextField::new('googleId')->hideOnIndex(),
            AssociationField::new('ratings')->onlyOnIndex(),
            AssociationField::new('tops')->onlyOnIndex(),
            AssociationField::new('images')->onlyOnIndex(),
            AssociationField::new('homePark')->hideOnIndex(),
            TextField::new('preferredLocale')->hideOnIndex(),
            BooleanField::new('enabled'),
            BooleanField::new('emailNotification')->hideOnIndex(),
            BooleanField::new('addTodayDateWhenRating')->hideOnIndex(),
            TextField::new('apiKey')->hideOnIndex()->setFormTypeOption('disabled', 'disabled'),
            TextField::new('ipAddress')->hideOnIndex()->setFormTypeOption('disabled', 'disabled'),
            DateTimeField::new('lastLogin')->setFormTypeOption('disabled', 'disabled'),
            DateTimeField::new('createdAt')->hideOnIndex()->setFormTypeOption('disabled', 'disabled'),
            DateTimeField::new('updatedAt')->hideOnIndex()->setFormTypeOption('disabled', 'disabled'),
        ];
    }
}
