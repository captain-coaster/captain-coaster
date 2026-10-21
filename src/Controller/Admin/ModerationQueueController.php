<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ImageReport;
use App\Entity\ReviewReport;
use App\Repository\ImageReportRepository;
use App\Repository\ReviewReportRepository;
use App\Service\PictureUrlSigner;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Every photo and review waiting for a moderator, in one list, oldest first, with what the AI
 * or the member said and the actions at hand. Below it, what the AI hid on its own this week.
 * The actions are the report CRUD controllers' own, sent back here (?queue=1).
 */
#[IsGranted('ROLE_MODERATOR')]
class ModerationQueueController extends AbstractController
{
    public const ROUTE = 'admin_moderation_queue';

    // EasyAdmin styles .btn-danger alone as an invisible button: destructive ones are secondary in red.
    private const REVIEW_ACTIONS = [
        'publish' => ['Publish', 'btn-primary', null],
        'hide' => ['Hide', 'btn-secondary', null],
        'deleteReview' => ['Delete', 'btn-secondary text-danger', 'Delete this rating and its review?'],
        'ban' => ['Ban', 'btn-secondary text-danger', 'Ban this member?'],
    ];

    private const IMAGE_ACTIONS = [
        'approve' => ['Publish', 'btn-primary', null],
        'reject' => ['Delete', 'btn-secondary text-danger', 'Delete this photo?'],
    ];

    public function __construct(
        private readonly ReviewReportRepository $reviewReportRepository,
        private readonly ImageReportRepository $imageReportRepository,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly PictureUrlSigner $pictureUrlSigner,
    ) {
    }

    /** Through the dashboard, so the page gets EasyAdmin's layout and menu. */
    public static function url(AdminUrlGenerator $adminUrlGenerator): string
    {
        return $adminUrlGenerator->unsetAll()->setRoute(self::ROUTE)->generateUrl();
    }

    #[Route('/team/moderation', name: self::ROUTE)]
    public function index(): Response
    {
        $items = [
            ...array_map($this->reviewItem(...), $this->reviewReportRepository->findPending()),
            ...array_map($this->imageItem(...), $this->imageReportRepository->findPending()),
        ];
        usort($items, static fn (array $a, array $b): int => $a['createdAt'] <=> $b['createdAt']);

        return $this->render('admin/moderation_queue.html.twig', [
            'items' => $items,
            'autoHidden' => array_map($this->reviewItem(...), $this->reviewReportRepository->findAutoHiddenSince(new \DateTimeImmutable('-7 days'))),
        ]);
    }

    /** @return array<string, mixed> */
    private function reviewItem(ReviewReport $report): array
    {
        $review = $report->getReview();

        return [
            'type' => 'review',
            'id' => $report->getId(),
            'createdAt' => $report->getCreatedAt(),
            'coaster' => $report->getDisplayCoasterName(),
            'rating' => $report->getDisplayRating(),
            'author' => $report->getDisplayReviewerName(),
            'authorUrl' => null !== $report->getDisplayReviewerId() ? $this->adminUrlGenerator->unsetAll()->setController(UserCrudController::class)->setAction('detail')->setEntityId($report->getDisplayReviewerId())->generateUrl() : null,
            'text' => $report->getDisplayContent(),
            'visible' => $review?->isReviewEnabled() ?? false,
            'reason' => $report->getReason(),
            'reporter' => $report->getUser()?->getDisplayName(),
            'explanation' => $report->getAiExplanation(),
            'image' => null,
            'actions' => null === $review ? [] : $this->actions(ReviewReportCrudController::class, ReviewReportCrudController::CSRF_TOKEN_ID, $report->getId(), self::REVIEW_ACTIONS),
        ];
    }

    /** @return array<string, mixed> */
    private function imageItem(ImageReport $report): array
    {
        $image = $report->getImage();

        return [
            'type' => 'photo',
            'id' => $report->getId(),
            'createdAt' => $report->getCreatedAt(),
            'coaster' => $report->getCoasterName(),
            'rating' => null,
            'author' => null,
            'authorUrl' => null,
            'text' => null,
            'visible' => false,
            'reason' => implode(', ', $report->getCategories()),
            'reporter' => null,
            'explanation' => $report->getAiExplanation(),
            // 1440x1440 is an inside-fit size: the whole photo, letterboxed.
            'image' => null !== $image ? $this->pictureUrlSigner->signImage($image, 1440, 1440, 'jpg') : null,
            'actions' => $this->actions(ImageReportCrudController::class, ImageReportCrudController::CSRF_TOKEN_ID, $report->getId(), self::IMAGE_ACTIONS),
        ];
    }

    /**
     * @param class-string                                  $crudController
     * @param array<string, array{string, string, ?string}> $actions        CRUD action name => [label, button class, confirmation]
     *
     * @return list<array{label: string, class: string, confirm: ?string, url: string}>
     */
    private function actions(string $crudController, string $tokenId, int $reportId, array $actions): array
    {
        $token = $this->csrfTokenManager->getToken($tokenId)->getValue();
        $buttons = [];
        foreach ($actions as $action => [$label, $class, $confirm]) {
            $buttons[] = [
                'label' => $label,
                'class' => $class,
                'confirm' => $confirm,
                'url' => $this->adminUrlGenerator->unsetAll()
                    ->setController($crudController)
                    ->setAction($action.'Action')
                    ->setEntityId($reportId)
                    ->set('token', $token)
                    ->set('queue', 1)
                    ->generateUrl(),
            ];
        }

        return $buttons;
    }
}
