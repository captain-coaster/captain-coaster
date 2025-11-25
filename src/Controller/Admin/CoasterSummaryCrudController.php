<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\CoasterSummary;
use App\Service\CoasterSummaryService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class CoasterSummaryCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly CoasterSummaryService $coasterSummaryService
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return CoasterSummary::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('AI Summary')
            ->setEntityLabelInPlural('AI Summaries')
            ->setSearchFields(['coaster.name', 'language', 'summary'])
            ->setDefaultSort(['updatedAt' => 'DESC'])
            ->showEntityActionsInlined()
            ->setPaginatorPageSize(25);
    }

    public function configureActions(Actions $actions): Actions
    {
        $regenerateAction = Action::new('regenerate', '', 'fas fa-sync-alt')
            ->linkToCrudAction('regenerateSummary')
            ->setCssClass('btn btn-warning btn-sm')
            ->displayIf(static fn (CoasterSummary $summary): bool => null !== $summary->getCoaster());

        return $actions
            ->add(Crud::PAGE_INDEX, $regenerateAction)
            ->add(Crud::PAGE_DETAIL, $regenerateAction)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW)
            ->disable(Action::EDIT)
            ->disable(Action::DELETE);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('coaster')->autocomplete())
            ->add(ChoiceFilter::new('language')->setChoices([
                'English' => 'en',
                'French' => 'fr',
                'Spanish' => 'es',
                'German' => 'de',
            ]))
            ->add(NumericFilter::new('feedbackRatio')->setLabel('Feedback Ratio'))
            ->add(NumericFilter::new('positiveVotes')->setLabel('Positive Votes'))
            ->add(NumericFilter::new('negativeVotes')->setLabel('Negative Votes'))
            ->add(DateTimeFilter::new('createdAt'))
            ->add(DateTimeFilter::new('updatedAt'));
    }

    public function configureFields(string $pageName): iterable
    {
        $fields = [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('coaster')
                ->setLabel('Coaster')
                ->formatValue(fn ($value, CoasterSummary $entity) => $entity->getCoaster()?->getName() ?? 'N/A'),
            TextField::new('language')
                ->setLabel('Language')
                ->formatValue(function ($value) {
                    return match ($value) {
                        'en' => 'English',
                        'fr' => 'French',
                        'es' => 'Spanish',
                        'de' => 'German',
                        default => $value,
                    };
                }),
        ];

        if (Crud::PAGE_INDEX === $pageName) {
            $fields = array_merge($fields, [
                NumberField::new('feedbackRatio')
                    ->setLabel('Ratio')
                    ->setNumDecimals(2)
                    ->formatValue(function ($value, CoasterSummary $entity) {
                        $ratio = $entity->getFeedbackRatio();
                        $totalVotes = $entity->getTotalVotes();

                        if ($totalVotes < 5) {
                            return \sprintf('%.1f%%', $ratio * 100);
                        }

                        $percentage = \sprintf('%.1f%%', $ratio * 100);

                        // Highlight poor ratios with sufficient votes
                        if ($ratio < 0.3) {
                            return \sprintf('<span class="badge badge-danger">%s</span>', $percentage);
                        }

                        if ($ratio < 0.5) {
                            return \sprintf('<span class="badge badge-warning">%s</span>', $percentage);
                        }

                        return \sprintf('<span class="badge badge-success">%s</span>', $percentage);
                    }),
                IntegerField::new('totalVotes')
                    ->setLabel('Votes')
                    ->formatValue(function ($value, CoasterSummary $entity) {
                        $total = $entity->getTotalVotes();
                        $positive = $entity->getPositiveVotes();
                        $negative = $entity->getNegativeVotes();

                        return \sprintf('%d (👍%d 👎%d)', $total, $positive, $negative);
                    }),
            ]);
        } else {
            $fields = array_merge($fields, [
                TextareaField::new('summary')
                    ->setLabel('AI Summary')
                    ->hideOnIndex(),
                ArrayField::new('dynamicPros')
                    ->setLabel('Highlights')
                    ->hideOnIndex(),
                ArrayField::new('dynamicCons')
                    ->setLabel('Concerns')
                    ->hideOnIndex(),
                NumberField::new('feedbackRatio')
                    ->setLabel('Feedback Ratio')
                    ->setNumDecimals(4)
                    ->formatValue(fn ($value, CoasterSummary $entity) => \sprintf('%.2f%% (%d total votes)', $entity->getFeedbackRatio() * 100, $entity->getTotalVotes())),
                IntegerField::new('positiveVotes')->setLabel('Positive Votes'),
                IntegerField::new('negativeVotes')->setLabel('Negative Votes'),
                IntegerField::new('reviewsAnalyzed')->setLabel('Reviews Analyzed'),
                DateTimeField::new('createdAt')->setLabel('Created At'),
                DateTimeField::new('updatedAt')->setLabel('Last Updated'),
            ]);
        }

        return $fields;
    }

    public function regenerateSummary(AdminContext $context): Response
    {
        /** @var CoasterSummary $summary */
        $summary = $context->getEntity()->getInstance();

        if (!$summary->getCoaster()) {
            $this->addFlash('error', 'Cannot regenerate summary: coaster not found.');

            return $this->redirectToRoute('admin');
        }

        try {
            $result = $this->coasterSummaryService->generateSummary(
                $summary->getCoaster(),
                null,
                $summary->getLanguage()
            );

            if ($result['summary']) {
                $this->addFlash('success', \sprintf(
                    'Summary regenerated successfully for %s (%s). Analyzed %d reviews.',
                    $summary->getCoaster()->getName(),
                    $summary->getLanguage(),
                    $result['summary']->getReviewsAnalyzed()
                ));
            } else {
                $this->addFlash('warning', \sprintf(
                    'Could not regenerate summary for %s (%s). Not enough reviews or AI service unavailable.',
                    $summary->getCoaster()->getName(),
                    $summary->getLanguage()
                ));
            }
        } catch (\Exception $e) {
            $this->addFlash('error', \sprintf(
                'Error regenerating summary for %s: %s',
                $summary->getCoaster()->getName(),
                $e->getMessage()
            ));
        }

        return new RedirectResponse($context->getReferrer() ?? $this->generateUrl('admin'));
    }
}
