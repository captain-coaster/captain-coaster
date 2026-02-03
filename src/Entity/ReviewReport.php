<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ReviewReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: ReviewReportRepository::class)]
#[ORM\UniqueConstraint(name: 'user_review_report_unique', columns: ['user_id', 'review_id'])]
#[ORM\Table(options: ['collate' => 'utf8mb4_unicode_ci', 'charset' => 'utf8mb4'])]
class ReviewReport
{
    public const REASON_INAPPROPRIATE = 'inappropriate';
    public const REASON_SPAM = 'spam';

    public const REASONS = [
        self::REASON_INAPPROPRIATE,
        self::REASON_SPAM,
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_REVIEW_DELETED = 'review_deleted';
    public const STATUS_USER_BANNED = 'user_banned';
    public const STATUS_NO_ACTION = 'no_action';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_REVIEW_DELETED,
        self::STATUS_USER_BANNED,
        self::STATUS_NO_ACTION,
    ];

    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
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

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $resolvedAt = null;

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

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $reason): self
    {
        if (!\in_array($reason, self::REASONS, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid reason "%s". Valid reasons are: %s', $reason, implode(', ', self::REASONS)));
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (!\in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid status "%s". Valid statuses are: %s', $status, implode(', ', self::STATUSES)));
        }

        $this->status = $status;

        // Auto-set resolved flag based on status
        $this->resolved = self::STATUS_PENDING !== $status;
        if ($this->resolved && null === $this->resolvedAt) {
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

    /** Get the review content, either from the stored snapshot or the live review */
    public function getDisplayContent(): string
    {
        // First check stored snapshot
        if ($this->reviewContent) {
            return $this->reviewContent;
        }

        // If review still exists, get its content (may be null for rating-only)
        if ($this->review) {
            return $this->review->getReview() ?? '';
        }

        // Review was deleted - status column already indicates this
        return '';
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
