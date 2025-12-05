<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Entity representing an AI-generated summary of coaster reviews.
 *
 * Stores summaries with pros/cons lists generated from user reviews.
 * Supports multiple languages and tracks analysis metadata.
 */
#[ORM\Entity(repositoryClass: 'App\Repository\CoasterSummaryRepository')]
#[ORM\Table(name: 'coaster_summary')]
#[ORM\UniqueConstraint(name: 'unique_coaster_language', columns: ['coaster_id', 'language'])]
class CoasterSummary
{
    public function __construct()
    {
        $this->feedbacks = new ArrayCollection();
    }

    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Coaster::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Coaster $coaster = null;

    #[ORM\Column(type: Types::STRING, length: 2)]
    private string $language = 'en';

    #[ORM\Column(type: Types::TEXT)]
    private ?string $summary = null;

    #[ORM\Column(type: Types::JSON)]
    private array $dynamicPros = [];

    #[ORM\Column(type: Types::JSON)]
    private array $dynamicCons = [];

    #[ORM\Column(type: Types::INTEGER)]
    private int $reviewsAnalyzed = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'summary', targetEntity: SummaryFeedback::class, cascade: ['remove'])]
    private Collection $feedbacks;

    #[ORM\Column(type: Types::INTEGER)]
    private int $positiveVotes = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $negativeVotes = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 4)]
    private string $feedbackRatio = '0.0000';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCoaster(): ?Coaster
    {
        return $this->coaster;
    }

    public function setCoaster(Coaster $coaster): static
    {
        $this->coaster = $coaster;

        return $this;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(string $summary): static
    {
        $this->summary = $summary;

        return $this;
    }

    public function getDynamicPros(): array
    {
        return $this->dynamicPros;
    }

    public function setDynamicPros(array $dynamicPros): static
    {
        $this->dynamicPros = array_values(array_filter($dynamicPros, 'is_string'));

        return $this;
    }

    public function getDynamicCons(): array
    {
        return $this->dynamicCons;
    }

    public function setDynamicCons(array $dynamicCons): static
    {
        $this->dynamicCons = array_values(array_filter($dynamicCons, 'is_string'));

        return $this;
    }

    public function getReviewsAnalyzed(): int
    {
        return $this->reviewsAnalyzed;
    }

    public function setReviewsAnalyzed(int $reviewsAnalyzed): static
    {
        $this->reviewsAnalyzed = max(0, $reviewsAnalyzed);

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

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /** @return Collection<int, SummaryFeedback> */
    public function getFeedbacks(): Collection
    {
        return $this->feedbacks;
    }

    public function addFeedback(SummaryFeedback $feedback): static
    {
        if (!$this->feedbacks->contains($feedback)) {
            $this->feedbacks->add($feedback);
            $feedback->setSummary($this);
        }

        return $this;
    }

    public function removeFeedback(SummaryFeedback $feedback): static
    {
        if ($this->feedbacks->removeElement($feedback)) {
            // set the owning side to null (unless already changed)
            if ($feedback->getSummary() === $this) {
                $feedback->setSummary(null);
            }
        }

        return $this;
    }

    public function getPositiveVotes(): int
    {
        return $this->positiveVotes;
    }

    public function setPositiveVotes(int $positiveVotes): static
    {
        $this->positiveVotes = max(0, $positiveVotes);

        return $this;
    }

    public function getNegativeVotes(): int
    {
        return $this->negativeVotes;
    }

    public function setNegativeVotes(int $negativeVotes): static
    {
        $this->negativeVotes = max(0, $negativeVotes);

        return $this;
    }

    public function getFeedbackRatio(): float
    {
        return (float) $this->feedbackRatio;
    }

    public function setFeedbackRatio(float $feedbackRatio): static
    {
        $this->feedbackRatio = (string) max(0.0, min(1.0, $feedbackRatio));

        return $this;
    }

    public function getTotalVotes(): int
    {
        return $this->positiveVotes + $this->negativeVotes;
    }

    public function hasMinimumFeedback(int $minVotes = 10): bool
    {
        return $this->getTotalVotes() >= $minVotes;
    }

    public function updateFeedbackMetrics(): void
    {
        $totalVotes = $this->getTotalVotes();

        if ($totalVotes > 0) {
            $this->feedbackRatio = (string) ($this->positiveVotes / $totalVotes);
        } else {
            $this->feedbackRatio = '0.0000';
        }
    }
}
