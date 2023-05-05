<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * RankingHistory
 */
#[ORM\UniqueConstraint(name: 'unique_coaster_per_ranking_history', columns: ['ranking_id', 'coaster_id'])]
#[ORM\Entity(repositoryClass: \App\Repository\RankingHistoryRepository::class)]
class RankingHistory
{
    /**
     * @var int
     */
    #[ORM\Column(name: 'id', type: \Doctrine\DBAL\Types\Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: 'Ranking')]
    #[ORM\JoinColumn(nullable: false)]
    private ?\App\Entity\Ranking $ranking = null;

    #[ORM\ManyToOne(targetEntity: 'Coaster')]
    #[ORM\JoinColumn(nullable: false)]
    private ?\App\Entity\Coaster $coaster = null;

    /**
     * @var int
     */
    #[ORM\Column(name: 'rank', type: \Doctrine\DBAL\Types\Types::INTEGER)]
    private ?int $rank = null;

    /**
     * @var string
     */
    #[ORM\Column(name: 'score', type: \Doctrine\DBAL\Types\Types::DECIMAL, precision: 14, scale: 11)]
    private ?string $score = null;

    /**
     * @var int
     */
    #[ORM\Column(name: 'validDuels', type: \Doctrine\DBAL\Types\Types::INTEGER)]
    private ?int $validDuels = null;

    /**
     * @var int
     */
    #[ORM\Column(name: 'totalTopsIn', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: true)]
    private ?int $totalTopsIn = null;

    /**
     * @var string
     */
    #[ORM\Column(name: 'averageTopRank', type: \Doctrine\DBAL\Types\Types::DECIMAL, precision: 6, scale: 3, nullable: true)]
    private ?string $averageTopRank = null;

    /**
     * @var int
     */
    #[ORM\Column(name: 'totalRatings', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: true)]
    private ?int $totalRatings = null;

    /**
     * @var string
     */
    #[ORM\Column(name: 'averageRating', type: \Doctrine\DBAL\Types\Types::DECIMAL, precision: 5, scale: 3, nullable: true)]
    private ?string $averageRating = null;


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

    public function setRanking(Ranking $ranking): RankingHistory
    {
        $this->ranking = $ranking;

        return $this;
    }

    public function getRanking(): Ranking
    {
        return $this->ranking;
    }

    public function setCoaster(Coaster $coaster): RankingHistory
    {
        $this->coaster = $coaster;

        return $this;
    }

    public function getCoaster(): Coaster
    {
        return $this->coaster;
    }
}
