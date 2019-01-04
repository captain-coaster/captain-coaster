<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Ranking
 *
 * @ORM\Table(name="ranking")
 * @ORM\Entity(repositoryClass="App\Repository\RankingRepository")
 */
class Ranking
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false)
     */
    private $ratingNumber;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false)
     */
    private $topNumber;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false)
     */
    private $coasterInTopNumber;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false)
     */
    private $comparisonNumber;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false)
     */
    private $userNumber;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false)
     */
    private $rankedCoasterNumber;

    /**
     * @var \DateTime $computedAt
     *
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime")
     */
    private $computedAt;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getRatingNumber(): int
    {
        return $this->ratingNumber;
    }

    /**
     * @param int $ratingNumber
     * @return Ranking
     */
    public function setRatingNumber(int $ratingNumber): Ranking
    {
        $this->ratingNumber = $ratingNumber;

        return $this;
    }

    /**
     * @return int
     */
    public function getTopNumber(): int
    {
        return $this->topNumber;
    }

    /**
     * @param int $topNumber
     * @return Ranking
     */
    public function setTopNumber(int $topNumber): Ranking
    {
        $this->topNumber = $topNumber;

        return $this;
    }

    /**
     * @return int
     */
    public function getCoasterInTopNumber(): int
    {
        return $this->coasterInTopNumber;
    }

    /**
     * @param int $coasterInTopNumber
     * @return Ranking
     */
    public function setCoasterInTopNumber(int $coasterInTopNumber): Ranking
    {
        $this->coasterInTopNumber = $coasterInTopNumber;

        return $this;
    }

    /**
     * @return int
     */
    public function getComparisonNumber(): int
    {
        return $this->comparisonNumber;
    }

    /**
     * @param int $comparisonNumber
     * @return Ranking
     */
    public function setComparisonNumber(int $comparisonNumber): Ranking
    {
        $this->comparisonNumber = $comparisonNumber;

        return $this;
    }

    /**
     * @return int
     */
    public function getUserNumber(): int
    {
        return $this->userNumber;
    }

    /**
     * @param int $userNumber
     * @return Ranking
     */
    public function setUserNumber(int $userNumber): Ranking
    {
        $this->userNumber = $userNumber;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getComputedAt(): \DateTime
    {
        return $this->computedAt;
    }

    /**
     * @param \DateTime $computedAt
     * @return Ranking
     */
    public function setComputedAt(\DateTime $computedAt): Ranking
    {
        $this->computedAt = $computedAt;

        return $this;
    }

    /**
     * @return int
     */
    public function getRankedCoasterNumber(): int
    {
        return $this->rankedCoasterNumber;
    }

    /**
     * @param int $rankedCoasterNumber
     * @return Ranking
     */
    public function setRankedCoasterNumber(int $rankedCoasterNumber): Ranking
    {
        $this->rankedCoasterNumber = $rankedCoasterNumber;

        return $this;
    }
}
