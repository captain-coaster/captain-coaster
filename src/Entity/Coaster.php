<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Coaster
 *
 * @ORM\Table(name="coaster")
 * @ORM\Entity(repositoryClass="App\Repository\CoasterRepository")
 * @ApiResource(
 *     attributes={
 *         "normalization_context"={"groups"={"list_coaster","read_coaster"}}
 *     },
 *     collectionOperations={"get"={"method"="GET","normalization_context"={"groups"={"list_coaster"}}}},
 *     itemOperations={"get"={"method"="GET","normalization_context"={"groups"={"read_coaster"}}}}
 * )
 * @ApiFilter(SearchFilter::class, properties={"id": "exact", "name": "partial", "manufacturer": "exact"})
 * @ApiFilter(OrderFilter::class, properties={"id", "rank": {"nulls_comparison": OrderFilter::NULLS_LARGEST}}, arguments={"orderParameterName"="order"})
 * @ApiFilter(RangeFilter::class, properties={"rank", "totalRatings"})
 * @ApiFilter(ExistsFilter::class, properties={"mainImage"})
 */
class Coaster
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Groups({"list_coaster", "read_coaster"})
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     * @Assert\NotBlank()
     * @Groups({"list_coaster", "read_coaster"})
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="slug", type="string", length=255, unique=true)
     * @Gedmo\Slug(fields={"name"}, updatable=true, separator="-", handlers={
     *      @Gedmo\SlugHandler(class="App\Handler\CustomRelativeSlugHandler", options={
     *          @Gedmo\SlugHandlerOption(name="relationField", value="park"),
     *          @Gedmo\SlugHandlerOption(name="relationSlugField", value="slug"),
     *          @Gedmo\SlugHandlerOption(name="separator", value="-")
     *      })
     * })
     */
    private $slug;

    /**
     * @var MaterialType
     *
     * @ORM\ManyToOne(targetEntity="MaterialType")
     * @ORM\JoinColumn(nullable=true)
     * @Groups({"read_coaster"})
     */
    private $materialType;

    /**
     * @var SeatingType
     *
     * @ORM\ManyToOne(targetEntity="SeatingType")
     * @ORM\JoinColumn(nullable=true)
     * @Groups({"read_coaster"})
     */
    private $seatingType;

    /**
     * @var Model
     *
     * @ORM\ManyToOne(targetEntity="Model")
     * @ORM\JoinColumn(nullable=true)
     * @Groups({"read_coaster"})
     */
    private $model;

    /**
     * @var int
     *
     * @ORM\Column(name="speed", type="integer", nullable=true)
     * @Groups({"read_coaster"})
     */
    private $speed;

    /**
     * @var int
     *
     * @ORM\Column(name="height", type="integer", nullable=true)
     * @Groups({"read_coaster"})
     */
    private $height;

    /**
     * @var int
     *
     * @ORM\Column(name="length", type="integer", nullable=true)
     * @Groups({"read_coaster"})
     */
    private $length;

    /**
     * @var int
     *
     * @ORM\Column(name="inversions_number", type="integer", nullable=true)
     * @Groups({"read_coaster"})
     */
    private $inversionsNumber = 0;

    /**
     * @var Manufacturer
     *
     * @ORM\ManyToOne(targetEntity="Manufacturer")
     * @ORM\JoinColumn(nullable=true)
     * @Groups({"read_coaster"})
     */
    private $manufacturer;

    /**
     * @var Restraint
     *
     * @ORM\ManyToOne(targetEntity="Restraint", inversedBy="coasters")
     * @ORM\JoinColumn(nullable=true)
     * @Groups({"read_coaster"})
     */
    private $restraint;

    /**
     * @var ArrayCollection|Launch[]
     *
     * @ORM\ManyToMany(targetEntity="Launch", inversedBy="coasters")
     * @ORM\JoinColumn(nullable=true)
     * @Groups({"read_coaster"})
     */
    private $launchs;

    /**
     * @var boolean
     *
     * @ORM\Column(name="is_kiddie", type="boolean", nullable=false)
     */
    private $kiddie = false;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $vr = false;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $indoor = false;

    /**
     * @var Park
     *
     * @ORM\ManyToOne(targetEntity="Park", inversedBy="coasters", fetch="EAGER")
     * @ORM\JoinColumn(nullable=false)
     * @Groups({"list_coaster", "read_coaster"})
     */
    private $park;

    /**
     * @var Status
     *
     * @ORM\ManyToOne(targetEntity="Status", inversedBy="coasters")
     * @ORM\JoinColumn(nullable=false)
     * @Groups({"read_coaster"})
     */
    private $status;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="openingDate", type="date", nullable=true)
     * @Groups({"read_coaster"})
     */
    private $openingDate;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="closingDate", type="date", nullable=true)
     * @Groups({"read_coaster"})
     */
    private $closingDate;

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
     * @var float
     *
     * @ORM\Column(name="averageRating", type="decimal", precision=5, scale=3, nullable=true)
     */
    private $averageRating;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false)
     * @Groups({"read_coaster"})
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
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false)
     * @Groups({"read_coaster"})
     */
    private $validDuels = 0;

    /**
     * @var float
     *
     * @ORM\Column(type="decimal", precision=14, scale=11, nullable=true)
     * @Groups({"read_coaster"})
     */
    private $score;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=true)
     * @Groups({"list_coaster", "read_coaster"})
     */
    private $rank;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    private $previousRank;

    /**
     * @var ArrayCollection|RiddenCoaster[]
     *
     * @ORM\OneToMany(targetEntity="RiddenCoaster", mappedBy="coaster")
     */
    private $ratings;

    /**
     * @var ArrayCollection|MainTag[]
     *
     * @ORM\OneToMany(targetEntity="MainTag", mappedBy="coaster")
     */
    private $mainTags;

    /**
     * @var ArrayCollection|Image[]
     *
     * @ORM\OneToMany(targetEntity="Image", mappedBy="coaster")
     */
    private $images;

    /**
     * @var Image
     *
     * @ORM\OneToOne(targetEntity="Image", fetch="EAGER")
     * @ORM\JoinColumn(onDelete="SET NULL")
     * @Groups({"read_coaster"})
     */
    private $mainImage;

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
     * @var boolean $enabled
     *
     * @ORM\Column(type="boolean")
     */
    private $enabled = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->launchs = new ArrayCollection();
        $this->ratings = new ArrayCollection();
        $this->mainTags = new ArrayCollection();
        $this->images = new ArrayCollection();
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
     * @param MaterialType $materialType
     * @return Coaster
     */
    public function setMaterialType(MaterialType $materialType): Coaster
    {
        $this->materialType = $materialType;

        return $this;
    }

    /**
     * @return MaterialType
     */
    public function getMaterialType(): ?MaterialType
    {
        return $this->materialType;
    }

    /**
     * @param SeatingType $seatingType
     * @return Coaster
     */
    public function setSeatingType(SeatingType $seatingType): Coaster
    {
        $this->seatingType = $seatingType;

        return $this;
    }

    /**
     * @return SeatingType
     */
    public function getSeatingType(): ?SeatingType
    {
        return $this->seatingType;
    }

    /**
     * @param Model $model
     * @return Coaster
     */
    public function setModel(Model $model): Coaster
    {
        $this->model = $model;

        return $this;
    }

    /**
     * @return Model
     */
    public function getModel(): ?Model
    {
        return $this->model;
    }

    /**
     * @param integer $speed
     * @return Coaster
     */
    public function setSpeed($speed)
    {
        $this->speed = $speed;

        return $this;
    }

    /**
     * @return int
     */
    public function getSpeed()
    {
        return $this->speed;
    }

    /**
     * @param integer $height
     * @return Coaster
     */
    public function setHeight($height)
    {
        $this->height = $height;

        return $this;
    }

    /**
     * @return int
     */
    public function getHeight()
    {
        return $this->height;
    }

    /**
     * @param integer $length
     * @return Coaster
     */
    public function setLength($length)
    {
        $this->length = $length;

        return $this;
    }

    /**
     * @return int
     */
    public function getLength()
    {
        return $this->length;
    }

    /**
     * @param integer $inversionsNumber
     * @return Coaster
     */
    public function setInversionsNumber($inversionsNumber)
    {
        $this->inversionsNumber = $inversionsNumber;

        return $this;
    }

    /**
     * @return int
     */
    public function getInversionsNumber()
    {
        return $this->inversionsNumber;
    }

    public function setManufacturer(?Manufacturer $manufacturer): Coaster
    {
        $this->manufacturer = $manufacturer;

        return $this;
    }

    public function getManufacturer(): ?Manufacturer
    {
        return $this->manufacturer;
    }

    /**
     * @param Restraint $restraint
     * @return Coaster
     */
    public function setRestraint(Restraint $restraint)
    {
        $this->restraint = $restraint;

        return $this;
    }

    /**
     * @return Restraint
     */
    public function getRestraint()
    {
        return $this->restraint;
    }

    /**
     * @param Launch $launch
     * @return Coaster
     */
    public function addLaunch(Launch $launch)
    {
        $this->launchs[] = $launch;

        return $this;
    }

    /**
     * @param Launch $launch
     */
    public function removeLaunch(Launch $launch)
    {
        $this->launchs->removeElement($launch);
    }

    /**
     * @return Launch[]|ArrayCollection
     */
    public function getLaunchs()
    {
        return $this->launchs;
    }

    /**
     * @param bool $kiddie
     * @return Coaster
     */
    public function setKiddie(bool $kiddie): Coaster
    {
        $this->kiddie = $kiddie;

        return $this;
    }

    /**
     * @return bool
     */
    public function isKiddie(): bool
    {
        return $this->kiddie;
    }

    /**
     * @param boolean $vr
     * @return Coaster
     */
    public function setVr($vr)
    {
        $this->vr = $vr;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getVr()
    {
        return $this->vr;
    }

    /**
     * @param bool $indoor
     * @return Coaster
     */
    public function setIndoor(bool $indoor): Coaster
    {
        $this->indoor = $indoor;

        return $this;
    }

    /**
     * @return bool
     */
    public function isIndoor(): bool
    {
        return $this->indoor;
    }

    /**
     * @param Park $park
     * @return Coaster
     */
    public function setPark(Park $park): Coaster
    {
        $this->park = $park;

        return $this;
    }

    /**
     * @return Park
     */
    public function getPark(): ?Park
    {
        return $this->park;
    }

    /**
     * @param Status $status
     * @return Coaster
     */
    public function setStatus(Status $status): Coaster
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return Status
     */
    public function getStatus(): ?Status
    {
        return $this->status;
    }

    /**
     * @param \DateTime $openingDate
     * @return Coaster
     */
    public function setOpeningDate($openingDate)
    {
        $this->openingDate = $openingDate;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getOpeningDate()
    {
        return $this->openingDate;
    }

    /**
     * @param \DateTime $closingDate
     * @return Coaster
     */
    public function setClosingDate($closingDate)
    {
        $this->closingDate = $closingDate;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getClosingDate()
    {
        return $this->closingDate;
    }

    /**
     * @param string $video
     * @return Coaster
     */
    public function setVideo($video): Coaster
    {
        $this->video = $video;

        return $this;
    }

    /**
     * @return string
     */
    public function getVideo()
    {
        return $this->video;
    }

    /**
     * @param integer $price
     * @return Coaster
     */
    public function setPrice($price)
    {
        $this->price = $price;

        return $this;
    }

    /**
     * @return integer
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * @param Currency $currency
     * @return Coaster
     */
    public function setCurrency(?Currency $currency): Coaster
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * @return Currency
     */
    public function getCurrency(): ?Currency
    {
        return $this->currency;
    }

    /**
     * @param string $averageRating
     * @return Coaster
     */
    public function setAverageRating($averageRating)
    {
        $this->averageRating = $averageRating;

        return $this;
    }

    /**
     * @return string
     */
    public function getAverageRating()
    {
        return $this->averageRating;
    }

    /**
     * @param integer $totalRatings
     * @return Coaster
     */
    public function setTotalRatings($totalRatings)
    {
        $this->totalRatings = $totalRatings;

        return $this;
    }

    /**
     * @return integer
     */
    public function getTotalRatings()
    {
        return $this->totalRatings;
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
    public function getAverageTopRank(): ?int
    {
        return $this->averageTopRank;
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
     * @param int $validDuels
     * @return Coaster
     */
    public function setValidDuels(int $validDuels): Coaster
    {
        $this->validDuels = $validDuels;

        return $this;
    }

    /**
     * @return int
     */
    public function getValidDuels(): int
    {
        return $this->validDuels;
    }

    /**
     * @param string $score
     * @return Coaster
     */
    public function setScore($score)
    {
        $this->score = $score;

        return $this;
    }

    /**
     * @return string
     */
    public function getScore()
    {
        return $this->score;
    }

    /**
     * @param integer $rank
     * @return Coaster
     */
    public function setRank($rank)
    {
        $this->rank = $rank;

        return $this;
    }

    /**
     * @return integer
     */
    public function getRank()
    {
        return $this->rank;
    }

    /**
     * @param integer $previousRank
     * @return Coaster
     */
    public function setPreviousRank($previousRank)
    {
        $this->previousRank = $previousRank;

        return $this;
    }

    /**
     * @return integer
     */
    public function getPreviousRank()
    {
        return $this->previousRank;
    }

    /**
     * @param RiddenCoaster $rating
     * @return Coaster
     */
    public function addRating(RiddenCoaster $rating)
    {
        $this->ratings[] = $rating;

        return $this;
    }

    /**
     * @param RiddenCoaster $rating
     */
    public function removeRating(RiddenCoaster $rating)
    {
        $this->ratings->removeElement($rating);
    }

    /**
     * @return RiddenCoaster[]|ArrayCollection
     */
    public function getRatings()
    {
        return $this->ratings;
    }

    /**
     * @return MainTag[]|ArrayCollection
     */
    public function getMainTags()
    {
        return $this->mainTags;
    }

    /**
     * @param Image $images
     * @return Coaster
     */
    public function setImages(Image $images): Coaster
    {
        $this->images = $images;

        return $this;
    }

    /**
     * @return ArrayCollection|Collection
     */
    public function getImages()
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('enabled', true))
            ->orderBy(['likeCounter' => Criteria::DESC, 'updatedAt' => Criteria::DESC]);

        return $this->images->matching($criteria);
    }

    /**
     * @param Image $mainImage
     * @return Coaster
     */
    public function setMainImage(Image $mainImage): Coaster
    {
        $this->mainImage = $mainImage;

        return $this;
    }

    /**
     * @return Image
     */
    public function getMainImage(): ?Image
    {
        return $this->mainImage;
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
     * Can we rate this coaster ?
     *
     * @return bool
     */
    public function isRateable(): bool
    {
        return $this->status->isRateable();
    }

    /**
     * We don't rank kiddie coasters
     *
     * @return bool
     */
    public function isRankable(): bool
    {
        return !$this->isKiddie();
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
     * @param bool $enabled
     * @return Coaster
     */
    public function setEnabled(bool $enabled): Coaster
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
