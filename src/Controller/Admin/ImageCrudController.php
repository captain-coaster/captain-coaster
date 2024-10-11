<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Image;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ImageCrudController extends AbstractCrudController
{
    public function __construct(
        #[Autowire('%env(bool:PICTURES_CDN)%')]
        private string $imagesEndpoint
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
        return $actions->disable('new');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('uploader'),
            AssociationField::new('coaster'),
            TextField::new('credit'),
            ImageField::new('path', 'Image')->setBasePath($this->imagesEndpoint.'/1440x1440/')->onlyOnIndex(),
            BooleanField::new('enabled'),
            TextField::new('filename')->hideOnIndex()->setFormTypeOption('disabled', 'disabled'),
            BooleanField::new('watermarked')->onlyWhenUpdating()->setFormTypeOption('disabled', 'disabled'),
            IntegerField::new('likeCounter')->hideOnIndex(),
            DateTimeField::new('createdAt')->onlyWhenUpdating()->setFormTypeOption('disabled', 'disabled'),
            DateTimeField::new('updatedAt')->onlyWhenUpdating()->setFormTypeOption('disabled', 'disabled'),
        ];
    }
}
