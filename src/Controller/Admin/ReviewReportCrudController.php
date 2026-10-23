<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ReviewReport;
use App\Entity\RiddenCoaster;
use App\Repository\ReviewReportRepository;
use App\Service\Moderation\ReviewRule;
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
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @extends AbstractCrudController<ReviewReport>
 */
#[IsGranted('ROLE_MODERATOR')]
class ReviewReportCrudController extends AbstractCrudController
{
    public const CSRF_TOKEN_ID = 'review_report_moderation';

    private const REASON_LABELS = [
        'Not a review' => ReviewRule::NotAReview->value,
        'Attack on people' => ReviewRule::Attack->value,
        'Sexual' => ReviewRule::Sexual->value,
        'Not ridden' => ReviewRule::NotRidden->value,
        'Harmful off-topic' => ReviewRule::HarmfulOfftopic->value,
        'Gray zone' => ReviewRule::GrayZone->value,
        'Analysis failed' => ReviewReport::REASON_ANALYSIS_FAILED,
        'Heavy profanity' => ReviewReport::REASON_BLOCKED_WORD,
        'Spam (member)' => ReviewReport::REASON_SPAM,
        'Toxic (member)' => ReviewReport::REASON_TOXIC,
        'Troll (old)' => 'troll',
        'Off-topic (old)' => 'offtopic',
        'Other (old)' => 'other',
        'Uncertain (old)' => 'uncertain',
    ];

    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly EntityManagerInterface $entityManager,
        private readonly ReviewReportRepository $reportRepository,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    private function actionUrl(int $entityId, string $crudAction): string
    {
        return $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction($crudAction)
            ->setEntityId($entityId)
            ->set('token', $this->csrfTokenManager->getToken(self::CSRF_TOKEN_ID)->getValue())
            ->generateUrl();
    }

    public static function getEntityFqcn(): string
    {
        return ReviewReport::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Report')
            ->setEntityLabelInPlural('Reports')
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
            ->displayIf(static fn ($entity) => $entity->isActionable());

        // Add view report action (available to all)
        $moderationActions->addAction(
            Action::new('viewReport', 'View Report', 'fa fa-file-text')
                ->linkToCrudAction('detail')
                ->addCssClass('text-info')
        );

        // Writes need POST + CSRF token since they're reached via a dropdown link, not a real
        // form submission (see actionUrl()). The four actions of the review policy.
        foreach ([
            ['publish', 'Publish', 'fa fa-check', 'text-success'],
            ['hide', 'Hide (rating kept)', 'fa fa-eye-slash', 'text-warning'],
            ['deleteReview', 'Delete rating and review', 'fa fa-trash', 'text-danger'],
            ['ban', 'Ban user', 'fa fa-user-slash', 'text-danger'],
        ] as [$name, $label, $icon, $class]) {
            $moderationActions->addAction(
                Action::new($name, $label, $icon)
                    ->linkToUrl(fn ($entity) => $this->actionUrl($entity->getId(), $name.'Action'))
                    ->renderAsForm()
                    ->addCssClass($class)
                    ->displayIf(static fn ($entity) => null !== $entity->getReview())
            );
        }

        // Create actions dropdown for processed reports
        $processedActions = ActionGroup::new('processed', 'Actions')
            ->setIcon('fa fa-cog')
            ->asDefaultActionGroup()
            ->displayIf(static fn ($entity) => !$entity->isActionable());

        // Add view report action for processed reports
        $processedActions->addAction(
            Action::new('viewProcessedReport', 'View Report', 'fa fa-file-text')
                ->linkToCrudAction('detail')
                ->addCssClass('text-info')
        );

        // A moderator's hide can be undone.
        $processedActions->addAction(
            Action::new('republish', 'Publish', 'fa fa-check')
                ->linkToUrl(fn ($entity) => $this->actionUrl($entity->getId(), 'publishAction'))
                ->renderAsForm()
                ->addCssClass('text-success')
                ->displayIf(static fn ($entity) => ReviewReport::STATUS_HIDDEN === $entity->getStatus() && null !== $entity->getReview())
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
            // Deleting a pending report would leave its review hidden with no way back to the queue.
            ->disable(Action::NEW, Action::DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        $fields = [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('user', 'Reporter')
                ->autocomplete()
                ->formatValue(static function ($value, $entity) {
                    if (!$entity instanceof ReviewReport) {
                        return '[Deleted]';
                    }

                    return $entity->getUser()?->getDisplayName() ?? '🤖 AI moderation';
                }),
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
                ->formatValue(static fn ($value, $entity) => $entity?->getDisplayCoasterName() ?? '[Unknown]'),
            ChoiceField::new('reason')
                ->setChoices(self::REASON_LABELS)
                ->renderAsBadges([
                    ReviewRule::Attack->value => 'danger',
                    ReviewRule::Sexual->value => 'danger',
                    ReviewRule::NotAReview->value => 'dark',
                    ReviewRule::NotRidden->value => 'info',
                    ReviewRule::GrayZone->value => 'warning',
                    ReviewReport::REASON_BLOCKED_WORD => 'dark',
                ] + array_fill_keys(ReviewReport::reasons(), 'secondary')),
            NumberField::new('displayRating', 'Rating')
                ->hideOnForm()
                ->setNumDecimals(1)
                ->formatValue(static fn ($value, $entity) => $entity?->getDisplayRating() ? $entity->getDisplayRating().'/5' : '-'),
        ];

        // Show different content fields based on page
        if (Crud::PAGE_INDEX === $pageName) {
            $fields[] = TextareaField::new('displayContent', 'Reported Content')
                ->hideOnForm()
                ->formatValue(static function ($value, $entity) {
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
                ->formatValue(static fn ($value, $entity) => $entity?->getDisplayContent() ?? '[No content]');
        }

        $fields[] = ChoiceField::new('status')
            ->setChoices([
                'Pending' => ReviewReport::STATUS_PENDING,
                'Published' => ReviewReport::STATUS_APPROVED,
                'Hidden' => ReviewReport::STATUS_HIDDEN,
                'Deleted' => ReviewReport::STATUS_REJECTED,
                'Deleted by author' => ReviewReport::STATUS_AUTHOR_DELETED,
                'User banned' => ReviewReport::STATUS_USER_BANNED,
                'Auto-hidden' => ReviewReport::STATUS_AUTO_HIDDEN,
                'Superseded (edited)' => ReviewReport::STATUS_SUPERSEDED,
                'Text cleared (old)' => ReviewReport::STATUS_REVIEW_TEXT_CLEARED,
            ])
            ->renderAsBadges([
                ReviewReport::STATUS_PENDING => 'warning',
                ReviewReport::STATUS_APPROVED => 'success',
                ReviewReport::STATUS_HIDDEN => 'warning',
                ReviewReport::STATUS_REJECTED => 'danger',
                ReviewReport::STATUS_AUTHOR_DELETED => 'light',
                ReviewReport::STATUS_REVIEW_TEXT_CLEARED => 'light',
                ReviewReport::STATUS_USER_BANNED => 'dark',
                ReviewReport::STATUS_AUTO_HIDDEN => 'secondary',
                ReviewReport::STATUS_SUPERSEDED => 'light',
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

        if (Crud::PAGE_DETAIL === $pageName) {
            $fields[] = TextField::new('aiConfidence', 'AI Confidence')
                ->setDisabled()
                ->formatValue(static fn ($value) => $value ?? '—');
            $fields[] = TextareaField::new('reporterComment', 'Reporter comment')
                ->hideOnForm()
                ->formatValue(static fn ($value) => $value ?? '—');
            $fields[] = TextareaField::new('aiExplanation', 'AI Explanation')
                ->hideOnForm()
                ->formatValue(static fn ($value) => $value ?? '—');
        }

        return $fields;
    }

    public function publishAction(AdminContext $context): Response
    {
        return $this->resolve($context, ReviewReport::STATUS_APPROVED, 'Review published.', static fn (RiddenCoaster $review) => $review->setReviewEnabled(true));
    }

    public function hideAction(AdminContext $context): Response
    {
        return $this->resolve($context, ReviewReport::STATUS_HIDDEN, 'Review hidden; its rating stays.', static fn (RiddenCoaster $review) => $review->setReviewEnabled(false));
    }

    public function deleteReviewAction(AdminContext $context): Response
    {
        return $this->resolve($context, ReviewReport::STATUS_REJECTED, 'Rating and review deleted.', fn (RiddenCoaster $review) => $this->entityManager->remove($review));
    }

    /** Disables the account (UserListener records the ban); the review itself is left as it is. */
    public function banAction(AdminContext $context): Response
    {
        return $this->resolve($context, ReviewReport::STATUS_USER_BANNED, 'User banned.', static fn (RiddenCoaster $review) => $review->getUser()->setEnabled(false));
    }

    /**
     * Applies a moderator's decision to the reported review and resolves every open report on it.
     *
     * @param callable(RiddenCoaster): mixed $apply
     */
    private function resolve(AdminContext $context, string $status, string $done, callable $apply): Response
    {
        if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $context->getRequest()->query->get('token'))) {
            throw new InvalidCsrfTokenException();
        }

        $report = $this->entityManager->find(ReviewReport::class, $context->getEntity()->getPrimaryKeyValue());
        // A moderator's hide can be undone by publishing; every other decision is final.
        $republishes = ReviewReport::STATUS_APPROVED === $status && ReviewReport::STATUS_HIDDEN === $report?->getStatus();
        if (null === $report || !($report->isActionable() || $republishes)) {
            $this->addFlash('error', 'This report has already been processed.');

            return $this->redirectToIndex();
        }

        $review = $report->getReview();
        if (null === $review) {
            // Its author deleted it meanwhile: nothing left to act on.
            $report->setStatus(ReviewReport::STATUS_AUTHOR_DELETED);
            $this->entityManager->flush();
            $this->addFlash('info', 'The review was already deleted; report closed.');

            return $this->redirectToIndex();
        }

        foreach ($this->reportRepository->findActionableReportsForReview($review) as $related) {
            $related->setStatus($status);
        }
        $report->setStatus($status);
        $apply($review);
        $this->entityManager->flush();

        $this->addFlash('success', $done);

        return $this->redirectToIndex();
    }

    /** Back to the moderation queue when the action came from it. */
    private function redirectToIndex(): RedirectResponse
    {
        $url = $this->container->get('request_stack')->getCurrentRequest()?->query->getBoolean('queue')
            ? ModerationQueueController::url($this->adminUrlGenerator)
            : $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl();

        return new RedirectResponse($url);
    }
}
