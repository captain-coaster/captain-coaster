<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RankingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * A monthly ranking. RankingService computes it into RankingHistory rows, pending, then publishes it by copying
 * them into the coasters' rank columns: RankingHistory is the source of truth, Coaster::$rank a copy.
 */
#[ORM\Table(name: 'ranking')]
#[ORM\Entity(repositoryClass: RankingRepository::class)]
class Ranking
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $ratingNumber = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $topNumber = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $coasterInTopNumber = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $comparisonNumber = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $userNumber = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $rankedCoasterNumber = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeInterface $computedAt = null;

    // First day of the ranking's month: one ranking per month
    #[ORM\Column(type: Types::DATE_IMMUTABLE, unique: true)]
    private \DateTimeImmutable $month;

    // Null while pending
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    /**
     * The run: its RankingReport, duration, peak memory, riders who fed it and the thresholds it used.
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $report = null;

    /**
     * Head-to-head shown on the learn-more page (RankingCalculator::featuredDuel()): riders who compared the pair,
     * and the comparisons the better-ranked coaster won (a tie counts half for each).
     *
     * @var array{first: int, second: int, comparisons: int, firstWins: float}|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $featuredDuel = null;

    public function __construct(\DateTimeImmutable $month)
    {
        $this->month = $month;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getMonth(): \DateTimeImmutable
    {
        return $this->month;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): self
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getReport(): ?array
    {
        return $this->report;
    }

    /** @param array<string, mixed>|null $report */
    public function setReport(?array $report): self
    {
        $this->report = $report;

        return $this;
    }

    /** @return list<string> */
    public function getAnomalies(): array
    {
        return $this->report['anomalies'] ?? [];
    }

    public function getRatingNumber(): int
    {
        return $this->ratingNumber;
    }

    public function setRatingNumber(int $ratingNumber): self
    {
        $this->ratingNumber = $ratingNumber;

        return $this;
    }

    public function getTopNumber(): int
    {
        return $this->topNumber;
    }

    public function setTopNumber(int $topNumber): self
    {
        $this->topNumber = $topNumber;

        return $this;
    }

    public function getCoasterInTopNumber(): int
    {
        return $this->coasterInTopNumber;
    }

    public function setCoasterInTopNumber(int $coasterInTopNumber): self
    {
        $this->coasterInTopNumber = $coasterInTopNumber;

        return $this;
    }

    public function getComparisonNumber(): int
    {
        return $this->comparisonNumber;
    }

    public function setComparisonNumber(int $comparisonNumber): self
    {
        $this->comparisonNumber = $comparisonNumber;

        return $this;
    }

    public function getUserNumber(): int
    {
        return $this->userNumber;
    }

    public function setUserNumber(int $userNumber): self
    {
        $this->userNumber = $userNumber;

        return $this;
    }

    public function getComputedAt(): ?\DateTimeInterface
    {
        return $this->computedAt;
    }

    public function setComputedAt(\DateTime $computedAt): self
    {
        $this->computedAt = $computedAt;

        return $this;
    }

    public function getRankedCoasterNumber(): int
    {
        return $this->rankedCoasterNumber;
    }

    public function setRankedCoasterNumber(int $rankedCoasterNumber): self
    {
        $this->rankedCoasterNumber = $rankedCoasterNumber;

        return $this;
    }

    /** @return array{first: int, second: int, comparisons: int, firstWins: float}|null */
    public function getFeaturedDuel(): ?array
    {
        return $this->featuredDuel;
    }

    /** @param array{first: int, second: int, comparisons: int, firstWins: float}|null $featuredDuel */
    public function setFeaturedDuel(?array $featuredDuel): self
    {
        $this->featuredDuel = $featuredDuel;

        return $this;
    }
}
