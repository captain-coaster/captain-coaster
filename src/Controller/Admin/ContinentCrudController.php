<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Continent;
use App\Form\Admin\ContinentTranslationType;
use App\Tooling\Translation\Admin\TranslationsField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ContinentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Continent::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Continent')
            ->setEntityLabelInPlural('Continents')
            ->setSearchFields(['id', 'name', 'slug'])
            ->setDefaultSort(['name' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->setPermission(Action::DELETE, 'ROLE_SUPER_ADMIN');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('name')->hideOnForm(),
            TranslationsField::new('translations')
                ->setFormTypeOption('entry_type', ContinentTranslationType::class),
            TextField::new('slug')->onlyWhenUpdating()->setFormTypeOption('disabled', 'disabled'),
        ];
    }
}
