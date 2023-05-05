<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Ranking
 */
#[ORM\Table(name: 'ranking')]
#[ORM\Entity(repositoryClass: \App\Repository\RankingRepository::class)]
class Ranking
{
    /**
     * @var int
     */
    #[ORM\Column(name: 'id', type: \Doctrine\DBAL\Types\Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    /**
     * @var int
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    private ?int $ratingNumber = null;

    /**
     * @var int
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    private ?int $topNumber = null;

    /**
     * @var int
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    private ?int $coasterInTopNumber = null;

    /**
     * @var int
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    private ?int $comparisonNumber = null;

    /**
     * @var int
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    private ?int $userNumber = null;

    /**
     * @var int
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    private ?int $rankedCoasterNumber = null;

    /**
     * @var \DateTimeInterface $computedAt
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE)]
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

    public function setRatingNumber(int $ratingNumber): Ranking
    {
        $this->ratingNumber = $ratingNumber;

        return $this;
    }

    public function getTopNumber(): int
    {
        return $this->topNumber;
    }

    public function setTopNumber(int $topNumber): Ranking
    {
        $this->topNumber = $topNumber;

        return $this;
    }

    public function getCoasterInTopNumber(): int
    {
        return $this->coasterInTopNumber;
    }

    public function setCoasterInTopNumber(int $coasterInTopNumber): Ranking
    {
        $this->coasterInTopNumber = $coasterInTopNumber;

        return $this;
    }

    public function getComparisonNumber(): int
    {
        return $this->comparisonNumber;
    }

    public function setComparisonNumber(int $comparisonNumber): Ranking
    {
        $this->comparisonNumber = $comparisonNumber;

        return $this;
    }

    public function getUserNumber(): int
    {
        return $this->userNumber;
    }

    public function setUserNumber(int $userNumber): Ranking
    {
        $this->userNumber = $userNumber;

        return $this;
    }

    public function getComputedAt(): \DateTime
    {
        return $this->computedAt;
    }

    public function setComputedAt(\DateTime $computedAt): Ranking
    {
        $this->computedAt = $computedAt;

        return $this;
    }

    public function getRankedCoasterNumber(): int
    {
        return $this->rankedCoasterNumber;
    }

    public function setRankedCoasterNumber(int $rankedCoasterNumber): Ranking
    {
        $this->rankedCoasterNumber = $rankedCoasterNumber;

        return $this;
    }
}
