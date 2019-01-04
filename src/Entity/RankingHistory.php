<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * RankingHistory
 *
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(name="unique_coaster_per_ranking_history", columns={"ranking_id", "coaster_id"})})
 * @ORM\Entity(repositoryClass="App\Repository\RankingHistoryRepository")
 */
class RankingHistory
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
     * @var Ranking
     *
     * @ORM\ManyToOne(targetEntity="Ranking")
     * @ORM\JoinColumn(nullable=false)
     */
    private $ranking;

    /**
     * @var Coaster
     *
     * @ORM\ManyToOne(targetEntity="Coaster")
     * @ORM\JoinColumn(nullable=false)
     */
    private $coaster;

    /**
     * @var int
     *
     * @ORM\Column(name="rank", type="integer")
     */
    private $rank;

    /**
     * @var string
     *
     * @ORM\Column(name="score", type="decimal", precision=14, scale=11)
     */
    private $score;

    /**
     * @var int
     *
     * @ORM\Column(name="validDuels", type="integer")
     */
    private $validDuels;

    /**
     * @var int
     *
     * @ORM\Column(name="totalTopsIn", type="integer", nullable=true)
     */
    private $totalTopsIn;

    /**
     * @var string
     *
     * @ORM\Column(name="averageTopRank", type="decimal", precision=6, scale=3, nullable=true)
     */
    private $averageTopRank;

    /**
     * @var int
     *
     * @ORM\Column(name="totalRatings", type="integer", nullable=true)
     */
    private $totalRatings;

    /**
     * @var string
     *
     * @ORM\Column(name="averageRating", type="decimal", precision=5, scale=3, nullable=true)
     */
    private $averageRating;


    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set rank.
     *
     * @param int $rank
     *
     * @return RankingHistory
     */
    public function setRank($rank)
    {
        $this->rank = $rank;

        return $this;
    }

    /**
     * Get rank.
     *
     * @return int
     */
    public function getRank()
    {
        return $this->rank;
    }

    /**
     * Set score.
     *
     * @param string $score
     *
     * @return RankingHistory
     */
    public function setScore($score)
    {
        $this->score = $score;

        return $this;
    }

    /**
     * Get score.
     *
     * @return string
     */
    public function getScore()
    {
        return $this->score;
    }

    /**
     * Set validDuels.
     *
     * @param int $validDuels
     *
     * @return RankingHistory
     */
    public function setValidDuels($validDuels)
    {
        $this->validDuels = $validDuels;

        return $this;
    }

    /**
     * Get validDuels.
     *
     * @return int
     */
    public function getValidDuels()
    {
        return $this->validDuels;
    }

    /**
     * Set totalTopsIn.
     *
     * @param int $totalTopsIn
     *
     * @return RankingHistory
     */
    public function setTotalTopsIn($totalTopsIn)
    {
        $this->totalTopsIn = $totalTopsIn;

        return $this;
    }

    /**
     * Get totalTopsIn.
     *
     * @return int
     */
    public function getTotalTopsIn()
    {
        return $this->totalTopsIn;
    }

    /**
     * Set averageTopRank.
     *
     * @param string $averageTopRank
     *
     * @return RankingHistory
     */
    public function setAverageTopRank($averageTopRank)
    {
        $this->averageTopRank = $averageTopRank;

        return $this;
    }

    /**
     * Get averageTopRank.
     *
     * @return string
     */
    public function getAverageTopRank()
    {
        return $this->averageTopRank;
    }

    /**
     * Set totalRatings.
     *
     * @param int $totalRatings
     *
     * @return RankingHistory
     */
    public function setTotalRatings($totalRatings)
    {
        $this->totalRatings = $totalRatings;

        return $this;
    }

    /**
     * Get totalRatings.
     *
     * @return int
     */
    public function getTotalRatings()
    {
        return $this->totalRatings;
    }

    /**
     * Set averageRating.
     *
     * @param string $averageRating
     *
     * @return RankingHistory
     */
    public function setAverageRating($averageRating)
    {
        $this->averageRating = $averageRating;

        return $this;
    }

    /**
     * Get averageRating.
     *
     * @return string
     */
    public function getAverageRating()
    {
        return $this->averageRating;
    }

    /**
     * @param Ranking $ranking
     * @return RankingHistory
     */
    public function setRanking(Ranking $ranking): RankingHistory
    {
        $this->ranking = $ranking;

        return $this;
    }

    /**
     * @return Ranking
     */
    public function getRanking(): Ranking
    {
        return $this->ranking;
    }

    /**
     * @param Coaster $coaster
     * @return RankingHistory
     */
    public function setCoaster(Coaster $coaster): RankingHistory
    {
        $this->coaster = $coaster;

        return $this;
    }

    /**
     * @return Coaster
     */
    public function getCoaster(): Coaster
    {
        return $this->coaster;
    }
}
