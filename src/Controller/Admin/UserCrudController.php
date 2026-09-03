<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @extends AbstractCrudController<User>
 */
#[IsGranted('ROLE_MODERATOR')]
class UserCrudController extends AbstractCrudController
{
    /** @param array<string> $locales */
    public function __construct(
        #[Autowire(param: 'app_locales_array')]
        private readonly array $locales,
    ) {
    }

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
            ->setPermission(Action::DELETE, 'ROLE_ADMIN');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('firstName')
            ->add('lastName')
            ->add('email')
            ->add('enabled')
            ->add('bannedAt')
            ->add('deletedAt')
            ->add('createdAt')
            ->add('updatedAt')
            ->add('lastLogin');
    }

    public function configureFields(string $pageName): iterable
    {
        // List page fields
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('displayName')->hideOnForm();
        yield TextField::new('email')->hideOnForm();
        yield AssociationField::new('ratings')->onlyOnIndex();
        yield BooleanField::new('enabled');

        // Edit page - Identity
        yield FormField::addPanel('Identity')->onlyOnForms();
        yield IdField::new('id')->onlyOnForms()->setFormTypeOption('disabled', true);
        yield TextField::new('firstName')->onlyOnForms()->setFormTypeOption('disabled', true);
        yield TextField::new('lastName')->onlyOnForms()->setFormTypeOption('disabled', true);
        yield TextField::new('displayName')->onlyOnForms();
        yield TextField::new('slug')->onlyOnForms()->setFormTypeOption('disabled', true);
        yield TextField::new('email')->onlyOnForms();
        yield TextField::new('googleId')->onlyOnForms()->setFormTypeOption('disabled', true);

        // Edit page - Activity
        yield FormField::addPanel('Activity')->onlyOnForms();
        yield AssociationField::new('homePark')->onlyOnForms()->autocomplete();

        // Edit page - Settings
        yield FormField::addPanel('Settings')->onlyOnForms();
        yield TextField::new('preferredLocale')->onlyOnForms();
        yield BooleanField::new('emailNotification')->onlyOnForms();
        yield BooleanField::new('addTodayDateWhenRating')->onlyOnForms();
        yield ChoiceField::new('preferredUnits')
            ->setChoices(['Metric' => 'metric', 'Imperial' => 'imperial'])
            ->onlyOnForms();
        yield ChoiceField::new('preferredReviewLanguages')
            ->setChoices(array_combine($this->locales, $this->locales))
            ->allowMultipleChoices()
            ->onlyOnForms();

        // Edit page - Access
        yield FormField::addPanel('Access')->onlyOnForms();
        yield ChoiceField::new('roles')
            ->setChoices([
                'Contributor' => 'ROLE_CONTRIBUTOR',
                'Moderator' => 'ROLE_MODERATOR',
                'Admin' => 'ROLE_ADMIN',
            ])
            ->allowMultipleChoices()
            ->onlyOnForms()
            ->setPermission('ROLE_ADMIN');
        yield TextField::new('apiKey')->onlyOnForms()->setFormTypeOption('disabled', true);
        yield TextField::new('ipAddress')->onlyOnForms()->setFormTypeOption('disabled', true);

        // Edit page - Dates
        yield FormField::addPanel('Dates')->onlyOnForms();
        yield DateTimeField::new('createdAt')->onlyOnForms()->setFormTypeOption('disabled', true);
        yield DateTimeField::new('updatedAt')->onlyOnForms()->setFormTypeOption('disabled', true);
        yield DateTimeField::new('lastLogin')->onlyOnForms()->setFormTypeOption('disabled', true);
        yield DateTimeField::new('nameChangedAt')->onlyOnForms()->setFormTypeOption('disabled', true);
        yield DateTimeField::new('lastApiKeyUsedAt')->onlyOnForms()->setFormTypeOption('disabled', true);
        yield DateTimeField::new('bannedAt')->onlyOnForms()->setFormTypeOption('disabled', true);
        yield DateTimeField::new('deletedAt')->onlyOnForms()->setFormTypeOption('disabled', true);
    }
}
