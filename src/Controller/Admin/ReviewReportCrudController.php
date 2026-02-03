<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ReviewReport;
use App\Repository\ReviewReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\ActionGroup;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @extends AbstractCrudController<ReviewReport>
 */
class ReviewReportCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly EntityManagerInterface $entityManager,
        private readonly ReviewReportRepository $reportRepository
    ) {
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
            ->setSearchFields(['id', 'user.displayName', 'reviewContent', 'reason'])
            ->setDefaultSort(['resolved' => 'ASC', 'createdAt' => 'DESC'])
            ->showEntityActionsInlined()
            ->setPaginatorPageSize(25);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('user')->autocomplete())
            ->add('reason')
            ->add('status');
    }

    public function configureActions(Actions $actions): Actions
    {
        // Create moderation actions dropdown for pending reports
        $moderationActions = ActionGroup::new('moderation', 'Actions')
            ->setIcon('fa fa-cog')
            ->asPrimaryActionGroup()
            ->displayIf(static fn ($entity) => ReviewReport::STATUS_PENDING === $entity->getStatus());

        // Add view report action (available to all)
        $moderationActions->addAction(
            Action::new('viewReport', 'View Report', 'fa fa-file-text')
                ->linkToCrudAction('detail')
                ->addCssClass('text-info')
        );

        // Add admin-only actions
        if ($this->isGranted('ROLE_ADMIN')) {
            $moderationActions
                ->addAction(
                    Action::new('deleteReview', 'Delete Review', 'fa fa-trash')
                        ->linkToCrudAction('deleteReviewAction')
                        ->addCssClass('text-danger')
                        ->displayIf(static fn ($entity) => null !== $entity->getReview())
                )
                ->addAction(
                    Action::new('disableUser', 'Ban User', 'fa fa-user-slash')
                        ->linkToCrudAction('disableUserAction')
                        ->addCssClass('text-warning')
                );
        }

        // Add action available to all moderators
        $moderationActions->addAction(
            Action::new('doNothing', 'No Action', 'fa fa-check')
                ->linkToCrudAction('doNothingAction')
                ->addCssClass('text-success')
        );

        // Create actions dropdown for processed reports
        $processedActions = ActionGroup::new('processed', 'Actions')
            ->setIcon('fa fa-cog')
            ->asDefaultActionGroup()
            ->displayIf(static fn ($entity) => ReviewReport::STATUS_PENDING !== $entity->getStatus());

        // Add view report action for processed reports
        $processedActions->addAction(
            Action::new('viewProcessedReport', 'View Report', 'fa fa-file-text')
                ->linkToCrudAction('detail')
                ->addCssClass('text-info')
        );

        // Add view review action if review still exists
        $processedActions->addAction(
            Action::new('viewReview', 'View Review', 'fa fa-eye')
                ->linkToUrl(function ($entity) {
                    if (!$entity || !$entity->getReview()) {
                        return $this->adminUrlGenerator->setController(self::class)->setAction('index')->generateUrl();
                    }

                    return $this->adminUrlGenerator
                        ->setController(ReviewCrudController::class)
                        ->setAction(Action::DETAIL)
                        ->setEntityId($entity->getReview()->getId())
                        ->generateUrl();
                })
                ->displayIf(static fn ($entity) => null !== $entity->getReview())
        );

        return $actions
            ->add(Crud::PAGE_INDEX, $moderationActions)
            ->add(Crud::PAGE_INDEX, $processedActions)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->disable(Action::NEW);
    }

    public function configureFields(string $pageName): iterable
    {
        $fields = [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('user', 'Reporter')
                ->autocomplete()
                ->formatValue(fn ($value, $entity) => $entity?->getUser()?->getDisplayName() ?? '[Deleted]'),
            TextField::new('displayReviewerName', 'Reviewer')
                ->hideOnForm()
                ->formatValue(function ($value, $entity) {
                    $name = $entity?->getDisplayReviewerName();
                    if (!$name) {
                        return '';
                    }
                    $userId = $entity->getDisplayReviewerId();
                    if (!$userId) {
                        return $name;
                    }
                    $url = $this->adminUrlGenerator
                        ->setController(UserCrudController::class)
                        ->setAction(Action::DETAIL)
                        ->setEntityId($userId)
                        ->generateUrl();

                    return \sprintf('<a href="%s">%s</a>', $url, htmlspecialchars($name));
                })
                ->renderAsHtml(),
            TextField::new('displayCoasterName', 'Coaster')
                ->hideOnForm()
                ->formatValue(fn ($value, $entity) => $entity?->getDisplayCoasterName() ?? '[Unknown]'),
            ChoiceField::new('reason')
                ->setChoices([
                    'Inappropriate language' => ReviewReport::REASON_INAPPROPRIATE,
                    'Spam' => ReviewReport::REASON_SPAM,
                ])
                ->renderAsBadges([
                    ReviewReport::REASON_INAPPROPRIATE => 'warning',
                    ReviewReport::REASON_SPAM => 'danger',
                ]),
            NumberField::new('displayRating', 'Rating')
                ->hideOnForm()
                ->setNumDecimals(1)
                ->formatValue(fn ($value, $entity) => $entity?->getDisplayRating() ? $entity->getDisplayRating().'/5' : '-'),
        ];

        // Show different content fields based on page
        if (Crud::PAGE_INDEX === $pageName) {
            $fields[] = TextareaField::new('displayContent', 'Reported Content')
                ->hideOnForm()
                ->formatValue(function ($value, $entity) {
                    if (!$entity) {
                        return '[No content]';
                    }
                    $content = $entity->getDisplayContent();
                    // Truncate for index view
                    if (\strlen($content) > 100) {
                        return substr($content, 0, 100).'...';
                    }

                    return $content;
                });
        } else {
            $fields[] = TextareaField::new('displayContent', 'Reported Content')
                ->hideOnForm()
                ->formatValue(fn ($value, $entity) => $entity?->getDisplayContent() ?? '[No content]');
        }

        $fields[] = ChoiceField::new('status')
            ->setChoices([
                'Pending' => ReviewReport::STATUS_PENDING,
                'Review Deleted' => ReviewReport::STATUS_REVIEW_DELETED,
                'User Banned' => ReviewReport::STATUS_USER_BANNED,
                'No Action' => ReviewReport::STATUS_NO_ACTION,
            ])
            ->renderAsBadges([
                ReviewReport::STATUS_PENDING => 'warning',
                ReviewReport::STATUS_REVIEW_DELETED => 'danger',
                ReviewReport::STATUS_USER_BANNED => 'dark',
                ReviewReport::STATUS_NO_ACTION => 'success',
            ])
            ->setDisabled();

        $fields[] = DateTimeField::new('createdAt', 'Reported At')
            ->setFormat('MMM d, yyyy HH:mm')
            ->setDisabled();

        if (Crud::PAGE_DETAIL === $pageName || Crud::PAGE_EDIT === $pageName) {
            $fields[] = DateTimeField::new('resolvedAt', 'Resolved At')
                ->setFormat('MMM d, yyyy HH:mm')
                ->setDisabled();
        }

        return $fields;
    }

    #[IsGranted('ROLE_ADMIN')]
    public function deleteReviewAction(AdminContext $context): Response
    {
        /** @var ReviewReport $contextReport */
        $contextReport = $context->getEntity()->getInstance();

        // Re-fetch the entity to ensure it's managed
        $reviewReport = $this->entityManager->find(ReviewReport::class, $contextReport->getId());
        if (!$reviewReport) {
            $this->addFlash('error', 'Report not found.');

            return $this->redirectToIndex();
        }

        if (ReviewReport::STATUS_PENDING !== $reviewReport->getStatus()) {
            $this->addFlash('error', 'This report has already been processed.');

            return $this->redirectToIndex();
        }

        $review = $reviewReport->getReview();
        if (!$review) {
            $this->addFlash('error', 'The review has already been deleted.');

            return $this->redirectToIndex();
        }

        // Store review info before deletion
        $coasterName = $review->getCoaster()->getName() ?? 'Unknown';
        $reviewerName = $review->getUser()->getDisplayName() ?? 'Unknown';

        // Mark all pending reports for this review as review deleted
        $relatedReports = $this->reportRepository->findPendingReportsForReview($review);
        foreach ($relatedReports as $report) {
            $report->setStatus(ReviewReport::STATUS_REVIEW_DELETED);
        }

        // Delete the review
        $this->entityManager->remove($review);
        $this->entityManager->flush();

        $this->addFlash('success', \sprintf(
            'Review by %s for %s has been deleted and %d report(s) marked as review deleted.',
            $reviewerName,
            $coasterName,
            \count($relatedReports)
        ));

        return $this->redirectToIndex();
    }

    #[IsGranted('ROLE_ADMIN')]
    public function disableUserAction(AdminContext $context): Response
    {
        /** @var ReviewReport $contextReport */
        $contextReport = $context->getEntity()->getInstance();

        // Re-fetch the entity to ensure it's managed
        $reviewReport = $this->entityManager->find(ReviewReport::class, $contextReport->getId());
        if (!$reviewReport) {
            $this->addFlash('error', 'Report not found.');

            return $this->redirectToIndex();
        }

        if (ReviewReport::STATUS_PENDING !== $reviewReport->getStatus()) {
            $this->addFlash('error', 'This report has already been processed.');

            return $this->redirectToIndex();
        }

        $review = $reviewReport->getReview();
        if (!$review) {
            $this->addFlash('error', 'The associated review no longer exists.');

            return $this->redirectToIndex();
        }

        $user = $review->getUser();

        if (!$user->isEnabled()) {
            $this->addFlash('warning', \sprintf('User %s is already disabled.', $user->getDisplayName()));
        } else {
            // Disable the user - UserListener handles related cleanup
            $user->setEnabled(false);
            $this->addFlash('success', \sprintf('User %s has been disabled.', $user->getDisplayName()));
        }

        // Mark all pending reports for this review as user banned
        $relatedReports = $this->reportRepository->findPendingReportsForReview($review);
        foreach ($relatedReports as $report) {
            $report->setStatus(ReviewReport::STATUS_USER_BANNED);
        }
        $this->entityManager->flush();

        return $this->redirectToIndex();
    }

    public function doNothingAction(AdminContext $context): Response
    {
        /** @var ReviewReport $contextReport */
        $contextReport = $context->getEntity()->getInstance();

        // Re-fetch the entity to ensure it's managed
        $reviewReport = $this->entityManager->find(ReviewReport::class, $contextReport->getId());
        if (!$reviewReport) {
            $this->addFlash('error', 'Report not found.');

            return $this->redirectToIndex();
        }

        if (ReviewReport::STATUS_PENDING !== $reviewReport->getStatus()) {
            $this->addFlash('error', 'This report has already been processed.');

            return $this->redirectToIndex();
        }

        // Mark all pending reports for this review as no action
        $review = $reviewReport->getReview();
        if ($review) {
            $relatedReports = $this->reportRepository->findPendingReportsForReview($review);
            foreach ($relatedReports as $report) {
                $report->setStatus(ReviewReport::STATUS_NO_ACTION);
            }
        } else {
            // Review was deleted, just mark this report
            $reviewReport->setStatus(ReviewReport::STATUS_NO_ACTION);
        }
        $this->entityManager->flush();

        $this->addFlash('info', 'Report(s) marked as no action taken.');

        return $this->redirectToIndex();
    }

    private function redirectToIndex(): RedirectResponse
    {
        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl();

        return new RedirectResponse($url);
    }
}
