<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\VocabularyGuide;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class VocabularyGuideCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return VocabularyGuide::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('language')->setHelp('Language code (en, fr, es, de)');
        yield CodeEditorField::new('content')
            ->setLanguage('markdown')
            ->setHelp('Edit the vocabulary guide content. Use markdown format with sections: CRITICAL RULES, PRESERVE, TRANSLATE');
        yield IntegerField::new('reviewsAnalyzed')->onlyOnIndex();
        yield DateTimeField::new('createdAt')->onlyOnIndex();
        yield DateTimeField::new('updatedAt')->onlyOnIndex();
    }
}
