<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Image;
use App\Service\PictureUrlSigner;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

/**
 * @extends AbstractCrudController<Image>
 */
class ImageCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly PictureUrlSigner $pictureUrlSigner,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Image::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Picture')
            ->setEntityLabelInPlural('Pictures')
            ->setSearchFields(['id', 'coaster.name', 'uploader.displayName', 'filename', 'credit'])
            ->setDefaultSort(['enabled' => 'ASC', 'updatedAt' => 'DESC'])
            ->showEntityActionsInlined()
            ->setPaginatorPageSize(20);
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
            ->add(EntityFilter::new('uploader')->autocomplete())
            ->add(EntityFilter::new('coaster')->autocomplete())
            ->add('credit')
            ->add('enabled')
            ->add('watermarked')
            ->add('likeCounter');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('uploader')->autocomplete(),
            AssociationField::new('coaster')->autocomplete(),
            TextField::new('credit'),
            // Value returned by formatValue() is already an absolute (signed) URL, so
            // EasyAdmin's ImageConfigurator passes it through untouched instead of
            // prepending a basePath (it special-cases http(s)://-prefixed values).
            ImageField::new('filename', 'Image')
                ->formatValue(fn (mixed $value, Image $entity): string => $this->pictureUrlSigner->sign($entity->getFilename(), 1440, 1440, 'jpg'))
                ->onlyOnIndex(),
            BooleanField::new('enabled'),
            TextField::new('filename')->hideOnIndex()->setFormTypeOption('disabled', 'disabled'),
            BooleanField::new('watermarked')->onlyWhenUpdating()->setFormTypeOption('disabled', 'disabled'),
            IntegerField::new('likeCounter')->hideOnIndex(),
            DateTimeField::new('createdAt')->onlyWhenUpdating()->setFormTypeOption('disabled', 'disabled'),
            DateTimeField::new('updatedAt')->onlyWhenUpdating()->setFormTypeOption('disabled', 'disabled'),
        ];
    }
}
