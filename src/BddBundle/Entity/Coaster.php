<?php

namespace BddBundle\Entity;

use BddBundle\Service\RankingService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Coaster
 *
 * @ORM\Table(name="coaster")
 * @ORM\Entity(repositoryClass="BddBundle\Repository\CoasterRepository")
 */
class Coaster
{
    // @todo : optimize ?
    CONST NON_RATEABLE_STATUS = [3, 6];

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     *
     * @Assert\NotBlank()
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="slug", type="string", length=255, unique=true)
     * @Gedmo\Slug(fields={"name"}, updatable=true, separator="-", handlers={
     *      @Gedmo\SlugHandler(class="BddBundle\Handler\CustomRelativeSlugHandler", options={
     *          @Gedmo\SlugHandlerOption(name="relationField", value="park"),
     *          @Gedmo\SlugHandlerOption(name="relationSlugField", value="slug"),
     *          @Gedmo\SlugHandlerOption(name="separator", value="-")
     *      })
     * })
     */
    private $slug;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="openingDate", type="date", nullable=true)
     */
    private $openingDate;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="closingDate", type="date", nullable=true)
     */
    private $closingDate;

    /**
     * @var float
     *
     * @ORM\Column(name="averageRating", type="decimal", precision=5, scale=3, nullable=true)
     */
    private $averageRating;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false)
     */
    private $totalRatings = 0;

    /**
     * @var float
     *
     * @ORM\Column(type="decimal", precision=6, scale=3, nullable=true)
     */
    private $averageTopRank;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false)
     */
    private $totalTopsIn = 0;

    /**
     * @var float
     *
     * @ORM\Column(type="decimal", precision=13, scale=11, nullable=true)
     */
    private $score;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    private $rank;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    private $previousRank;

    /**
     * @var BuiltCoaster
     *
     * @ORM\ManyToOne(targetEntity="BuiltCoaster", inversedBy="coasters", cascade={"persist"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $builtCoaster;

    /**
     * @var Park
     *
     * @ORM\ManyToOne(targetEntity="Park", inversedBy="coasters")
     * @ORM\JoinColumn(nullable=false)
     */
    private $park;

    /**
     * @var Status
     *
     * @ORM\ManyToOne(targetEntity="Status", inversedBy="coasters")
     * @ORM\JoinColumn(nullable=false)
     */
    private $status;

    /**
     * @var string
     *
     * @ORM\Column(name="video", type="string", length=255, unique=false, nullable=true)
     */
    private $video;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    private $price;

    /**
     * @var Currency
     *
     * @ORM\ManyToOne(targetEntity="Currency")
     * @ORM\JoinColumn(nullable=true)
     */
    private $currency;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $vr = false;

    /**
     * @var string
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private $notes;

    /**
     * @var RiddenCoaster
     *
     * @ORM\OneToMany(targetEntity="BddBundle\Entity\RiddenCoaster", mappedBy="coaster")
     */
    private $ratings;

    /**
     * @var MainTag
     *
     * @ORM\OneToMany(targetEntity="BddBundle\Entity\MainTag", mappedBy="coaster")
     */
    private $mainTags;

    /**
     * @var \DateTime $createdAt
     *
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * @var \DateTime $updatedAt
     *
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime")
     */
    private $updatedAt;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->ratings = new ArrayCollection();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->name;
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return Coaster
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set slug
     *
     * @param string $slug
     *
     * @return Coaster
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * Get slug
     *
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }

    /**
     * Set openingDate
     *
     * @param \DateTime $openingDate
     *
     * @return Coaster
     */
    public function setOpeningDate($openingDate)
    {
        $this->openingDate = $openingDate;

        return $this;
    }

    /**
     * Get openingDate
     *
     * @return \DateTime
     */
    public function getOpeningDate()
    {
        return $this->openingDate;
    }

    /**
     * Set closingDate
     *
     * @param \DateTime $closingDate
     *
     * @return Coaster
     */
    public function setClosingDate($closingDate)
    {
        $this->closingDate = $closingDate;

        return $this;
    }

    /**
     * Get closingDate
     *
     * @return \DateTime
     */
    public function getClosingDate()
    {
        return $this->closingDate;
    }

    /**
     * Set averageRating
     *
     * @param string $averageRating
     *
     * @return Coaster
     */
    public function setAverageRating($averageRating)
    {
        $this->averageRating = $averageRating;

        return $this;
    }

    /**
     * Get averageRating
     *
     * @return string
     */
    public function getAverageRating()
    {
        return $this->averageRating;
    }

    /**
     * Set builtCoaster
     *
     * @param \BddBundle\Entity\BuiltCoaster $builtCoaster
     *
     * @return Coaster
     */
    public function setBuiltCoaster(BuiltCoaster $builtCoaster = null)
    {
        $this->builtCoaster = $builtCoaster;

        return $this;
    }

    /**
     * Get builtCoaster
     *
     * @return \BddBundle\Entity\BuiltCoaster
     */
    public function getBuiltCoaster()
    {
        return $this->builtCoaster;
    }

    /**
     * Set park
     *
     * @param \BddBundle\Entity\Park $park
     *
     * @return Coaster
     */
    public function setPark(Park $park)
    {
        $this->park = $park;

        return $this;
    }

    /**
     * Get park
     *
     * @return \BddBundle\Entity\Park
     */
    public function getPark()
    {
        return $this->park;
    }

    /**
     * Set status
     *
     * @param \BddBundle\Entity\Status $status
     *
     * @return Coaster
     */
    public function setStatus(Status $status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get status
     *
     * @return \BddBundle\Entity\Status
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Add rating
     *
     * @param \BddBundle\Entity\RiddenCoaster $rating
     *
     * @return Coaster
     */
    public function addRating(RiddenCoaster $rating)
    {
        $this->ratings[] = $rating;

        return $this;
    }

    /**
     * Remove rating
     *
     * @param \BddBundle\Entity\RiddenCoaster $rating
     */
    public function removeRating(RiddenCoaster $rating)
    {
        $this->ratings->removeElement($rating);
    }

    /**
     * Get ratings
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getRatings()
    {
        return $this->ratings;
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     *
     * @return Coaster
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get createdAt
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set updatedAt
     *
     * @param \DateTime $updatedAt
     *
     * @return Coaster
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get updatedAt
     *
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * Set video
     *
     * @param string $video
     *
     * @return Coaster
     */
    public function setVideo($video)
    {
        $this->video = $video;

        return $this;
    }

    /**
     * Get video
     *
     * @return string
     */
    public function getVideo()
    {
        return $this->video;
    }

    /**
     * Set totalRatings
     *
     * @param integer $totalRatings
     *
     * @return Coaster
     */
    public function setTotalRatings($totalRatings)
    {
        $this->totalRatings = $totalRatings;

        return $this;
    }

    /**
     * Get totalRatings
     *
     * @return integer
     */
    public function getTotalRatings()
    {
        return $this->totalRatings;
    }

    /**
     * Set price
     *
     * @param integer $price
     *
     * @return Coaster
     */
    public function setPrice($price)
    {
        $this->price = $price;

        return $this;
    }

    /**
     * Get price
     *
     * @return integer
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * Set vr
     *
     * @param boolean $vr
     *
     * @return Coaster
     */
    public function setVr($vr)
    {
        $this->vr = $vr;

        return $this;
    }

    /**
     * Get vr
     *
     * @return boolean
     */
    public function getVr()
    {
        return $this->vr;
    }

    /**
     * Set notes
     *
     * @param string $notes
     *
     * @return Coaster
     */
    public function setNotes($notes)
    {
        $this->notes = $notes;

        return $this;
    }

    /**
     * Get notes
     *
     * @return string
     */
    public function getNotes()
    {
        return $this->notes;
    }

    /**
     * Set currency
     *
     * @param \BddBundle\Entity\Currency $currency
     *
     * @return Coaster
     */
    public function setCurrency(Currency $currency)
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * Get currency
     *
     * @return \BddBundle\Entity\Currency
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Can we rate this coaster ?
     *
     * @return bool
     */
    public function isRateable(): bool
    {
        return !in_array($this->getStatus()->getId(), self::NON_RATEABLE_STATUS);
    }

    /**
     * Can we rank this coaster ?
     * Only if ratings number + tops number > MIN_RATINGS_PLUS_TOPS
     *
     * @return bool
     */
    public function isRankable(): bool
    {
        return (
            $this->getBuiltCoaster()->getIsKiddie() === false &&
            ($this->getTotalRatings() + $this->getTotalTopsIn()) >= RankingService::MIN_RATINGS_PLUS_TOPS
        );
    }

    /**
     * @param User $user
     * @return RiddenCoaster|null
     */
    public function getUserRating(User $user)
    {
        /** @var RiddenCoaster $rating */
        foreach ($this->ratings as $rating) {
            if ($rating->getUser() === $user) {
                return $rating;
            }
        }

        return null;
    }

    /**
     * Set score
     *
     * @param string $score
     *
     * @return Coaster
     */
    public function setScore($score)
    {
        $this->score = $score;

        return $this;
    }

    /**
     * Get score
     *
     * @return string
     */
    public function getScore()
    {
        return $this->score;
    }

    /**
     * Set rank
     *
     * @param integer $rank
     *
     * @return Coaster
     */
    public function setRank($rank)
    {
        $this->rank = $rank;

        return $this;
    }

    /**
     * Get rank
     *
     * @return integer
     */
    public function getRank()
    {
        return $this->rank;
    }

    /**
     * Set previousRank
     *
     * @param integer $previousRank
     *
     * @return Coaster
     */
    public function setPreviousRank($previousRank)
    {
        $this->previousRank = $previousRank;

        return $this;
    }

    /**
     * Get previousRank
     *
     * @return integer
     */
    public function getPreviousRank()
    {
        return $this->previousRank;
    }

    /**
     * Get mainTags
     *
     * @return MainTag
     */
    public function getMainTags()
    {
        return $this->mainTags;
    }

    /**
     * @param int $totalTopsIn
     * @return Coaster
     */
    public function setTotalTopsIn(int $totalTopsIn): Coaster
    {
        $this->totalTopsIn = $totalTopsIn;

        return $this;
    }

    /**
     * @return int
     */
    public function getTotalTopsIn(): int
    {
        return $this->totalTopsIn;
    }

    /**
     * @param int $averageTopRank
     * @return Coaster
     */
    public function setAverageTopRank(int $averageTopRank): Coaster
    {
        $this->averageTopRank = $averageTopRank;

        return $this;
    }

    /**
     * @return int
     */
    public function getAverageTopRank(): int
    {
        return $this->averageTopRank;
    }
}
