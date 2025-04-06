<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ReviewReport;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

class ReviewReportCrudController extends AbstractCrudController
{
    private $adminUrlGenerator;

    public function __construct(AdminUrlGenerator $adminUrlGenerator)
    {
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public static function getEntityFqcn(): string
    {
        return ReviewReport::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Review Report')
            ->setEntityLabelInPlural('Review Reports')
            ->setSearchFields(['id', 'user.displayName', 'review.review', 'reason'])
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('user')
            ->add('review')
            ->add('reason')
            ->add('resolved');
    }

    public function configureActions(Actions $actions): Actions
    {
        $viewReviewAction = Action::new('viewReview', 'View Review')
            ->linkToUrl(function ($entity) {
                if (!$entity || !$entity->getReview()) {
                    return $this->adminUrlGenerator->setController(self::class)->setAction('index')->generateUrl();
                }

                return $this->adminUrlGenerator
                    ->setController(ReviewCrudController::class)
                    ->setAction('detail')
                    ->setEntityId($entity->getReview()->getId())
                    ->generateUrl();
            })
            ->displayIf(static fn ($entity) => null !== $entity->getReview());

        return $actions
            ->add(Crud::PAGE_INDEX, $viewReviewAction)
            ->add(Crud::PAGE_DETAIL, $viewReviewAction)
            ->disable(Action::NEW);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('user')->autocomplete(),
            TextField::new('coasterName', 'Coaster')
                ->hideOnForm()
                // Make sure it's visible on the index page
                ->formatValue(fn ($value, $entity) => $entity->getReview() ? $entity->getReview()->getCoaster()->getName() : 'Unknown'),
            ChoiceField::new('reason')
                ->setChoices([
                    'Offensive content' => ReviewReport::REASON_OFFENSIVE,
                    'Inappropriate language' => ReviewReport::REASON_INAPPROPRIATE,
                    'Incorrect information' => ReviewReport::REASON_INCORRECT,
                    'Spam' => ReviewReport::REASON_SPAM,
                    'Other' => ReviewReport::REASON_OTHER,
                ]),
            TextareaField::new('review.review', 'Reported Content')
                ->hideOnIndex()
                ->formatValue(function ($value, $entity) {
                    if (!$entity->getReview() || !$entity->getReview()->getReview()) {
                        return 'No content';
                    }

                    $content = $entity->getReview()->getReview();
                    if (\strlen($content) > 100) {
                        $content = substr($content, 0, 100).'...';
                    }

                    return $content;
                }),
            BooleanField::new('resolved'),
            DateTimeField::new('createdAt')->setDisabled(),
            DateTimeField::new('resolvedAt')->setDisabled(),
        ];
    }
}
