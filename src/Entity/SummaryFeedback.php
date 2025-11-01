<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Entity representing user feedback on AI-generated coaster summaries.
 *
 * Tracks thumbs up/down votes from both authenticated and anonymous users.
 * Prevents duplicate votes through unique constraints on user/IP combinations.
 */
#[ORM\Entity(repositoryClass: 'App\Repository\SummaryFeedbackRepository')]
#[ORM\Table(name: 'summary_feedback')]
#[ORM\UniqueConstraint(name: 'unique_user_feedback', columns: ['summary_id', 'user_id'])]
#[ORM\UniqueConstraint(name: 'unique_anonymous_feedback', columns: ['summary_id', 'ip_address'])]
#[ORM\Index(columns: ['summary_id', 'is_positive'], name: 'idx_summary_feedback')]
class SummaryFeedback
{
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CoasterSummary::class, inversedBy: 'feedbacks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CoasterSummary $summary = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $ipAddress = '';

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isPositive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeInterface $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSummary(): ?CoasterSummary
    {
        return $this->summary;
    }

    public function setSummary(?CoasterSummary $summary): static
    {
        $this->summary = $summary;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function isPositive(): bool
    {
        return $this->isPositive;
    }

    public function setIsPositive(bool $isPositive): static
    {
        $this->isPositive = $isPositive;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}