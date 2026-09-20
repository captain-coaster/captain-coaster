<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ImageReport;
use App\Service\PictureUrlSigner;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\ActionGroup;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @extends AbstractCrudController<ImageReport>
 */
#[IsGranted('ROLE_MODERATOR')]
class ImageReportCrudController extends AbstractCrudController
{
    private const CSRF_TOKEN_ID = 'image_report_moderation';

    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly EntityManagerInterface $entityManager,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly PictureUrlSigner $pictureUrlSigner,
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
        return ImageReport::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Image Report')
            ->setEntityLabelInPlural('Image Reports')
            ->setSearchFields(['id', 'coasterName', 'imageFilename'])
            ->setDefaultSort(['resolved' => 'ASC', 'createdAt' => 'DESC'])
            ->showEntityActionsInlined()
            ->setPaginatorPageSize(25);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('status');
    }

    public function configureActions(Actions $actions): Actions
    {
        $moderationActions = ActionGroup::new('moderation', 'Actions')
            ->setIcon('fa fa-cog')
            ->asPrimaryActionGroup()
            ->displayIf(static fn ($entity) => ImageReport::STATUS_PENDING === $entity->getStatus());

        $moderationActions
            ->addAction(
                Action::new('viewReport', 'View Report', 'fa fa-file-text')
                    ->linkToCrudAction('detail')
                    ->addCssClass('text-info')
            )
            ->addAction(
                Action::new('approve', 'Approve', 'fa fa-check')
                    ->linkToUrl(fn ($entity) => $this->actionUrl($entity->getId(), 'approveAction'))
                    ->renderAsForm()
                    ->addCssClass('text-success')
            )
            ->addAction(
                Action::new('reject', 'Reject (delete photo)', 'fa fa-trash')
                    ->linkToUrl(fn ($entity) => $this->actionUrl($entity->getId(), 'rejectAction'))
                    ->renderAsForm()
                    ->addCssClass('text-danger')
            );

        $processedActions = ActionGroup::new('processed', 'Actions')
            ->setIcon('fa fa-cog')
            ->asDefaultActionGroup()
            ->displayIf(static fn ($entity) => ImageReport::STATUS_PENDING !== $entity->getStatus());

        $processedActions->addAction(
            Action::new('viewProcessedReport', 'View Report', 'fa fa-file-text')
                ->linkToCrudAction('detail')
                ->addCssClass('text-info')
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
        ];

        if (Crud::PAGE_INDEX === $pageName) {
            // 1440x1440 is an inside-fit size (whole photo, letterboxed); 480x300 is a cover crop.
            $fields[] = ImageField::new('imageFilename', 'Photo')
                ->setTemplatePath('admin/field/photo.html.twig')
                ->formatValue(fn (mixed $value, ImageReport $entity): string => $this->pictureUrlSigner->sign($entity->getImageFilename() ?? '', 1440, 1440, 'jpg'));
        }

        $fields[] = TextField::new('coasterName', 'Coaster');
        $fields[] = ArrayField::new('categories');

        $fields[] = ChoiceField::new('status')
            ->setChoices([
                'Pending' => ImageReport::STATUS_PENDING,
                'Approved' => ImageReport::STATUS_APPROVED,
                'Rejected' => ImageReport::STATUS_REJECTED,
            ])
            ->renderAsBadges([
                ImageReport::STATUS_PENDING => 'warning',
                ImageReport::STATUS_APPROVED => 'success',
                ImageReport::STATUS_REJECTED => 'danger',
            ])
            ->setDisabled();

        $fields[] = DateTimeField::new('createdAt')->setDisabled();

        if (Crud::PAGE_DETAIL === $pageName) {
            $fields[] = TextField::new('aiConfidence', 'AI Confidence');
            $fields[] = TextareaField::new('aiExplanation', 'AI Explanation');
            $fields[] = DateTimeField::new('resolvedAt')->setDisabled();
        }

        return $fields;
    }

    public function approveAction(AdminContext $context): Response
    {
        $this->assertCsrfTokenIsValid($context);

        $report = $this->findPendingReport($context);
        if (null === $report) {
            return $this->redirectToIndex();
        }

        $image = $report->getImage();
        if (null === $image) {
            $this->addFlash('error', 'The underlying photo no longer exists.');

            return $this->redirectToIndex();
        }

        $image->setEnabled(true);
        $report->setStatus(ImageReport::STATUS_APPROVED);
        $this->entityManager->flush();

        $this->addFlash('success', 'Photo approved and published.');

        return $this->redirectToIndex();
    }

    public function rejectAction(AdminContext $context): Response
    {
        $this->assertCsrfTokenIsValid($context);

        $report = $this->findPendingReport($context);
        if (null === $report) {
            return $this->redirectToIndex();
        }

        $image = $report->getImage();
        if (null !== $image) {
            $this->entityManager->remove($image);
        }
        $report->setStatus(ImageReport::STATUS_REJECTED);
        $this->entityManager->flush();

        $this->addFlash('success', 'Photo rejected and deleted.');

        return $this->redirectToIndex();
    }

    private function assertCsrfTokenIsValid(AdminContext $context): void
    {
        if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $context->getRequest()->query->get('token'))) {
            throw new InvalidCsrfTokenException();
        }
    }

    private function findPendingReport(AdminContext $context): ?ImageReport
    {
        /** @var ImageReport $contextReport */
        $contextReport = $context->getEntity()->getInstance();

        // Re-fetch by id to ensure it's managed, matching ReviewReportCrudController's pattern.
        $report = $this->entityManager->find(ImageReport::class, $contextReport->getId());

        if (!$report instanceof ImageReport || ImageReport::STATUS_PENDING !== $report->getStatus()) {
            $this->addFlash('error', 'This report has already been processed.');

            return null;
        }

        return $report;
    }

    private function redirectToIndex(): RedirectResponse
    {
        $url = $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl();

        return $this->redirect($url);
    }
}
