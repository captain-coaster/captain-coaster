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
    public const REASON_OFFENSIVE = 'offensive';
    public const REASON_INAPPROPRIATE = 'inappropriate';
    public const REASON_INCORRECT = 'incorrect';
    public const REASON_SPAM = 'spam';
    public const REASON_OTHER = 'other';

    public const REASONS = [
        self::REASON_OFFENSIVE,
        self::REASON_INAPPROPRIATE,
        self::REASON_INCORRECT,
        self::REASON_SPAM,
        self::REASON_OTHER,
    ];

    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: RiddenCoaster::class, inversedBy: 'reports')]
    #[ORM\JoinColumn(name: 'review_id', nullable: false, onDelete: 'CASCADE')]
    private ?RiddenCoaster $review = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $reason;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $resolved = false;

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
}
