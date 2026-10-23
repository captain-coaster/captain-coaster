<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ReviewReportRepository;
use App\Service\Moderation\ReviewRule;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: ReviewReportRepository::class)]
#[ORM\UniqueConstraint(name: 'user_review_report_unique', columns: ['user_id', 'review_id'])]
#[ORM\Table(options: ['collate' => 'utf8mb4_unicode_ci', 'charset' => 'utf8mb4'])]
class ReviewReport
{
    // What members report: mostly fake ratings, which the AI cannot see.
    public const REASON_NOT_RIDDEN = 'not_ridden';
    public const REASON_SPAM = 'spam';
    public const REASON_TOXIC = 'toxic';
    public const MEMBER_REASONS = [self::REASON_NOT_RIDDEN, self::REASON_SPAM, self::REASON_TOXIC];

    // What the AI reports: a rule of the review policy (ReviewRule), or its own failure.
    public const REASON_ANALYSIS_FAILED = 'analysis_failed';
    // Heavy profanity in a review published before the word list (app:reviews:hide-blocked-words).
    public const REASON_BLOCKED_WORD = 'blocked_word';

    // Only in older reports, from AI categories before the review policy.
    public const LEGACY_REASONS = ['troll', 'offtopic', 'other', 'uncertain'];

    public const STATUS_PENDING = 'pending';
    // Published by a moderator (or kept, for a published gray zone).
    public const STATUS_APPROVED = 'approved';
    // Hidden by a moderator: the rating stays.
    public const STATUS_HIDDEN = 'hidden';
    // Deleted by a moderator, rating and text.
    public const STATUS_REJECTED = 'rejected';
    // Its author deleted the review before a moderator acted on it.
    public const STATUS_AUTHOR_DELETED = 'author_deleted';
    public const STATUS_USER_BANNED = 'user_banned';
    // Hidden by the AI, without a moderator: resolved, kept for spot checks.
    public const STATUS_AUTO_HIDDEN = 'auto_hidden';
    // Its author edited the text: the report was about a version that no longer exists.
    public const STATUS_SUPERSEDED = 'superseded';
    // Only in older reports: the text was cleared and the rating kept.
    public const STATUS_REVIEW_TEXT_CLEARED = 'review_text_cleared';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_HIDDEN,
        self::STATUS_REJECTED,
        self::STATUS_AUTHOR_DELETED,
        self::STATUS_USER_BANNED,
        self::STATUS_AUTO_HIDDEN,
        self::STATUS_SUPERSEDED,
        self::STATUS_REVIEW_TEXT_CLEARED,
    ];

    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: RiddenCoaster::class, inversedBy: 'reports')]
    #[ORM\JoinColumn(name: 'review_id', nullable: true, onDelete: 'SET NULL')]
    private ?RiddenCoaster $review = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reviewContent = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $coasterName = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $reviewerName = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $reviewerId = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $ratingValue = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $reason;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $resolved = false;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => self::STATUS_PENDING])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $resolvedAt = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
    private ?string $aiConfidence = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $aiExplanation = null;

    // Why the member reports it, in their words; empty on reports from before it was asked.
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reporterComment = null;

    /** An AI moderation report, with the review's state at that time. */
    public static function fromAi(RiddenCoaster $review, ReviewRule|string $reason, ?string $aiExplanation): self
    {
        return new self()
            ->setReview($review)
            ->setReason($reason instanceof ReviewRule ? $reason->value : $reason)
            ->setReviewContent($review->getReview())
            ->setCoasterName($review->getCoaster()->getName())
            ->setReviewerName($review->getUser()->getDisplayName())
            ->setReviewerId($review->getUser()->getId())
            ->setRatingValue($review->getValue())
            ->setAiExplanation($aiExplanation);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getReview(): ?RiddenCoaster
    {
        return $this->review;
    }

    public function setReview(?RiddenCoaster $review): self
    {
        $this->review = $review;

        return $this;
    }

    /** @return list<string> */
    public static function reasons(): array
    {
        $rules = array_map(static fn (ReviewRule $rule): string => $rule->value, array_filter(ReviewRule::cases(), static fn (ReviewRule $rule): bool => ReviewRule::None !== $rule));

        return array_values(array_unique([...self::MEMBER_REASONS, ...$rules, self::REASON_ANALYSIS_FAILED, self::REASON_BLOCKED_WORD, ...self::LEGACY_REASONS]));
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $reason): self
    {
        if (!\in_array($reason, self::reasons(), true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid reason "%s". Valid reasons are: %s', $reason, implode(', ', self::reasons())));
        }

        $this->reason = $reason;

        return $this;
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    public function setResolved(bool $resolved): self
    {
        $this->resolved = $resolved;

        if ($resolved && null === $this->resolvedAt) {
            $this->resolvedAt = new \DateTime();
        }

        return $this;
    }

    /** Pending, or auto-hidden: a moderator can still overturn what the AI did. */
    public function isActionable(): bool
    {
        return \in_array($this->status, [self::STATUS_PENDING, self::STATUS_AUTO_HIDDEN], true);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (!\in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid status "%s". Valid statuses are: %s', $status, implode(', ', self::STATUSES)));
        }

        // A moderator acting on what the AI hid resolves it again, at their time.
        $overturnsAi = self::STATUS_AUTO_HIDDEN === $this->status && self::STATUS_AUTO_HIDDEN !== $status;
        $this->status = $status;

        // Auto-set resolved flag based on status
        $this->resolved = self::STATUS_PENDING !== $status;
        if ($this->resolved && (null === $this->resolvedAt || $overturnsAi)) {
            $this->resolvedAt = new \DateTime();
        }

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getResolvedAt(): ?\DateTimeInterface
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?\DateTimeInterface $resolvedAt): self
    {
        $this->resolvedAt = $resolvedAt;

        return $this;
    }

    public function getAiConfidence(): ?string
    {
        return $this->aiConfidence;
    }

    public function setAiConfidence(?string $aiConfidence): self
    {
        $this->aiConfidence = $aiConfidence;

        return $this;
    }

    public function getReporterComment(): ?string
    {
        return $this->reporterComment;
    }

    public function setReporterComment(?string $reporterComment): self
    {
        $this->reporterComment = $reporterComment;

        return $this;
    }

    public function getAiExplanation(): ?string
    {
        return $this->aiExplanation;
    }

    public function setAiExplanation(?string $aiExplanation): self
    {
        $this->aiExplanation = $aiExplanation;

        return $this;
    }

    public function getReviewContent(): ?string
    {
        return $this->reviewContent;
    }

    public function setReviewContent(?string $reviewContent): self
    {
        $this->reviewContent = $reviewContent;

        return $this;
    }

    public function getCoasterName(): ?string
    {
        return $this->coasterName;
    }

    public function setCoasterName(?string $coasterName): self
    {
        $this->coasterName = $coasterName;

        return $this;
    }

    public function getReviewerName(): ?string
    {
        return $this->reviewerName;
    }

    public function setReviewerName(?string $reviewerName): self
    {
        $this->reviewerName = $reviewerName;

        return $this;
    }

    public function getReviewerId(): ?int
    {
        return $this->reviewerId;
    }

    public function setReviewerId(?int $reviewerId): self
    {
        $this->reviewerId = $reviewerId;

        return $this;
    }

    public function getRatingValue(): ?float
    {
        return $this->ratingValue;
    }

    public function setRatingValue(?float $ratingValue): self
    {
        $this->ratingValue = $ratingValue;

        return $this;
    }

    /** To act on, the review as it is now; once settled, the text the report was about. */
    public function getDisplayContent(): string
    {
        $live = $this->review?->getReview();

        return ($this->isActionable() ? $live ?? $this->reviewContent : $this->reviewContent ?? $live) ?? '';
    }

    /** Get the rating value, either from the stored snapshot or the live review */
    public function getDisplayRating(): ?float
    {
        if ($this->ratingValue) {
            return $this->ratingValue;
        }

        return $this->review?->getValue();
    }

    /** Get the coaster name, either from the stored snapshot or the live review */
    public function getDisplayCoasterName(): string
    {
        if ($this->coasterName) {
            return $this->coasterName;
        }

        return $this->review?->getCoaster()?->getName() ?? '';
    }

    /** Get the reviewer name, either from the stored snapshot or the live review */
    public function getDisplayReviewerName(): string
    {
        if ($this->reviewerName) {
            return $this->reviewerName;
        }

        return $this->review?->getUser()?->getDisplayName() ?? '';
    }

    /** Get the reviewer ID, either from the stored snapshot or the live review */
    public function getDisplayReviewerId(): ?int
    {
        if ($this->reviewerId) {
            return $this->reviewerId;
        }

        return $this->review?->getUser()?->getId();
    }
}
