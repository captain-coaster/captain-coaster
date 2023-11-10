<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\RankingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Ranking
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
    private ?int $ratingNumber = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $topNumber = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $coasterInTopNumber = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $comparisonNumber = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $userNumber = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $rankedCoasterNumber = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeInterface $computedAt = null;

    public function getId(): int
    {
        return $this->id;
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

    public function getComputedAt(): \DateTime
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
}
