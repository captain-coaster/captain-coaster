<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity]
#[ORM\Table(name: 'coaster_ai_summary')]
class CoasterAiSummary
{
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Coaster::class)]
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
        $this->dynamicPros = $dynamicPros;
        return $this;
    }

    public function getDynamicCons(): array
    {
        return $this->dynamicCons;
    }

    public function setDynamicCons(array $dynamicCons): static
    {
        $this->dynamicCons = $dynamicCons;
        return $this;
    }

    public function getReviewsAnalyzed(): int
    {
        return $this->reviewsAnalyzed;
    }

    public function setReviewsAnalyzed(int $reviewsAnalyzed): static
    {
        $this->reviewsAnalyzed = $reviewsAnalyzed;
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

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): static
    {
        $this->language = $language;
        return $this;
    }
}