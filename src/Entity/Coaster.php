<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Coaster.
 */
#[ApiResource(operations: [new Get(normalizationContext: ['groups' => ['read_coaster']]), new GetCollection(normalizationContext: ['groups' => ['list_coaster']])], normalizationContext: ['groups' => ['list_coaster', 'read_coaster']])]
#[ORM\Table(name: 'coaster')]
#[ORM\Entity(repositoryClass: \App\Repository\CoasterRepository::class)]
#[ApiFilter(filterClass: SearchFilter::class, properties: ['id' => 'exact', 'name' => 'partial', 'manufacturer' => 'exact'])]
#[ApiFilter(filterClass: OrderFilter::class, properties: ['id', 'rank' => ['nulls_comparison' => 'nulls_largest']], arguments: ['orderParameterName' => 'order'])]
#[ApiFilter(filterClass: RangeFilter::class, properties: ['rank', 'totalRatings'])]
#[ApiFilter(filterClass: ExistsFilter::class, properties: ['mainImage'])]
class Coaster implements \Stringable
{
    /**
     * @var int
     */
    #[ORM\Column(name: 'id', type: \Doctrine\DBAL\Types\Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[Groups(['list_coaster', 'read_coaster'])]
    private ?int $id = null;
    /**
     * @var string
     */
    #[ORM\Column(name: 'name', type: \Doctrine\DBAL\Types\Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Groups(['list_coaster', 'read_coaster'])]
    private ?string $name = null;
    /**
     * @var string
     */
    #[ORM\Column(name: 'slug', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, unique: true)]
    #[Gedmo\Slug(fields: ['name'], updatable: true, separator: '-', handlers: [new Gedmo\SlugHandler(class: \App\Handler\CustomRelativeSlugHandler::class, options: [new Gedmo\SlugHandlerOption(name: 'relationField', value: 'park'), new Gedmo\SlugHandlerOption(name: 'relationSlugField', value: 'slug'), new Gedmo\SlugHandlerOption(name: 'separator', value: '-')])])]
    private ?string $slug = null;
    #[ORM\ManyToOne(targetEntity: 'MaterialType')]
    #[ORM\JoinColumn]
    #[Groups(['read_coaster'])]
    private ?\App\Entity\MaterialType $materialType = null;
    #[ORM\ManyToOne(targetEntity: 'SeatingType')]
    #[ORM\JoinColumn]
    #[Groups(['read_coaster'])]
    private ?\App\Entity\SeatingType $seatingType = null;
    #[ORM\ManyToOne(targetEntity: 'Model')]
    #[ORM\JoinColumn]
    #[Groups(['read_coaster'])]
    private ?\App\Entity\Model $model = null;
    /**
     * @var int
     */
    #[ORM\Column(name: 'speed', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: true)]
    #[Groups(['read_coaster'])]
    private ?int $speed = null;
    /**
     * @var int
     */
    #[ORM\Column(name: 'height', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: true)]
    #[Groups(['read_coaster'])]
    private ?int $height = null;
    /**
     * @var int
     */
    #[ORM\Column(name: 'length', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: true)]
    #[Groups(['read_coaster'])]
    private ?int $length = null;
    /**
     * @var int
     */
    #[ORM\Column(name: 'inversions_number', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: true)]
    #[Groups(['read_coaster'])]
    private ?int $inversionsNumber = 0;
    #[ORM\ManyToOne(targetEntity: 'Manufacturer')]
    #[ORM\JoinColumn]
    #[Groups(['read_coaster'])]
    private ?\App\Entity\Manufacturer $manufacturer = null;
    #[ORM\ManyToOne(targetEntity: 'Restraint', inversedBy: 'coasters')]
    #[ORM\JoinColumn]
    #[Groups(['read_coaster'])]
    private ?\App\Entity\Restraint $restraint = null;
    /**
     * @var \Doctrine\Common\Collections\Collection<\App\Entity\Launch>
     */
    #[ORM\ManyToMany(targetEntity: 'Launch', inversedBy: 'coasters')]
    #[ORM\JoinColumn]
    #[Groups(['read_coaster'])]
    private \Doctrine\Common\Collections\Collection $launchs;
    /**
     * @var bool
     */
    #[ORM\Column(name: 'is_kiddie', type: \Doctrine\DBAL\Types\Types::BOOLEAN)]
    private ?bool $kiddie = false;
    /**
     * @var bool
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BOOLEAN, nullable: true)]
    private ?bool $vr = false;
    /**
     * @var bool
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BOOLEAN, nullable: true)]
    private ?bool $indoor = false;
    #[ORM\ManyToOne(targetEntity: 'Park', inversedBy: 'coasters', fetch: 'EAGER')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['list_coaster', 'read_coaster'])]
    private ?\App\Entity\Park $park = null;
    #[ORM\ManyToOne(targetEntity: 'Status', inversedBy: 'coasters')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['list_coaster', 'read_coaster'])]
    private ?\App\Entity\Status $status = null;
    /**
     * @var \DateTimeInterface
     */
    #[ORM\Column(name: 'openingDate', type: \Doctrine\DBAL\Types\Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['read_coaster'])]
    private ?\DateTimeInterface $openingDate = null;
    /**
     * @var \DateTimeInterface
     */
    #[ORM\Column(name: 'closingDate', type: \Doctrine\DBAL\Types\Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['read_coaster'])]
    private ?\DateTimeInterface $closingDate = null;
    /**
     * @var string
     */
    #[ORM\Column(name: 'video', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $video = null;
    /**
     * @var int
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: true)]
    private ?int $price = null;
    #[ORM\ManyToOne(targetEntity: 'Currency')]
    #[ORM\JoinColumn]
    private ?\App\Entity\Currency $currency = null;
    /**
     * @var float
     */
    #[ORM\Column(name: 'averageRating', type: \Doctrine\DBAL\Types\Types::DECIMAL, precision: 5, scale: 3, nullable: true)]
    private ?string $averageRating = null;
    /**
     * @var int
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    #[Groups(['read_coaster'])]
    private ?int $totalRatings = 0;
    /**
     * @var float
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DECIMAL, precision: 6, scale: 3, nullable: true)]
    private ?string $averageTopRank = null;
    /**
     * @var int
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    private ?int $totalTopsIn = 0;
    /**
     * @var int
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    #[Groups(['read_coaster'])]
    private ?int $validDuels = 0;
    /**
     * @var float
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DECIMAL, precision: 14, scale: 11, nullable: true)]
    #[Groups(['read_coaster'])]
    private ?string $score = null;
    /**
     * @var int
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: true)]
    #[Groups(['list_coaster', 'read_coaster'])]
    private ?int $rank = null;
    /**
     * @var int
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: true)]
    private ?int $previousRank = null;
    /**
     * @var \Doctrine\Common\Collections\Collection<\App\Entity\RiddenCoaster>
     */
    #[ORM\OneToMany(targetEntity: 'RiddenCoaster', mappedBy: 'coaster')]
    private \Doctrine\Common\Collections\Collection $ratings;
    /**
     * @var \Doctrine\Common\Collections\Collection<\App\Entity\MainTag>
     */
    #[ORM\OneToMany(targetEntity: 'MainTag', mappedBy: 'coaster')]
    private \Doctrine\Common\Collections\Collection $mainTags;
    /**
     * @var \Doctrine\Common\Collections\Collection<\App\Entity\Image>
     */
    #[ORM\OneToMany(targetEntity: 'Image', mappedBy: 'coaster')]
    private \Doctrine\Common\Collections\Collection $images;
    #[ORM\OneToOne(targetEntity: 'Image', fetch: 'EAGER')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    #[Groups(['read_coaster'])]
    private ?\App\Entity\Image $mainImage = null;
    /**
     * @var \DateTimeInterface $createdAt
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeInterface $createdAt = null;
    /**
     * @var \DateTimeInterface $updatedAt
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeInterface $updatedAt = null;
    /**
     * @var bool $enabled
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BOOLEAN)]
    private ?bool $enabled = false;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->launchs = new ArrayCollection();
        $this->ratings = new ArrayCollection();
        $this->mainTags = new ArrayCollection();
        $this->images = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) $this->name;
    }

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
     * Set name.
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
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set slug.
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
     * Get slug.
     *
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }

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

    public function setModel(?Model $model): Coaster
    {
        $this->model = $model;

        return $this;
    }

    public function getModel(): ?Model
    {
        return $this->model;
    }

    /**
     * @param int $speed
     *
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
     * @param int $height
     *
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
     * @param int $length
     *
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
     * @param int $inversionsNumber
     *
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
     * @return Coaster
     */
    public function addLaunch(Launch $launch)
    {
        $this->launchs[] = $launch;

        return $this;
    }

    public function removeLaunch(Launch $launch)
    {
        $this->launchs->removeElement($launch);
    }

    /**
     * @return Launch[]|ArrayCollection
     */
    public function getLaunchs(): array|ArrayCollection
    {
        return $this->launchs;
    }

    public function setKiddie(bool $kiddie): Coaster
    {
        $this->kiddie = $kiddie;

        return $this;
    }

    public function isKiddie(): bool
    {
        return $this->kiddie;
    }

    /**
     * @param bool $vr
     *
     * @return Coaster
     */
    public function setVr($vr)
    {
        $this->vr = $vr;

        return $this;
    }

    /**
     * @return bool
     */
    public function getVr()
    {
        return $this->vr;
    }

    public function setIndoor(bool $indoor): Coaster
    {
        $this->indoor = $indoor;

        return $this;
    }

    public function isIndoor(): bool
    {
        return $this->indoor;
    }

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
     *
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
     *
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
     * @param int $price
     *
     * @return Coaster
     */
    public function setPrice($price)
    {
        $this->price = $price;

        return $this;
    }

    /**
     * @return int
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * @param Currency $currency
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
     *
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
     * @param int $totalRatings
     *
     * @return Coaster
     */
    public function setTotalRatings($totalRatings)
    {
        $this->totalRatings = $totalRatings;

        return $this;
    }

    /**
     * @return int
     */
    public function getTotalRatings()
    {
        return $this->totalRatings;
    }

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

    public function setTotalTopsIn(int $totalTopsIn): Coaster
    {
        $this->totalTopsIn = $totalTopsIn;

        return $this;
    }

    public function getTotalTopsIn(): int
    {
        return $this->totalTopsIn;
    }

    public function setValidDuels(int $validDuels): Coaster
    {
        $this->validDuels = $validDuels;

        return $this;
    }

    public function getValidDuels(): int
    {
        return $this->validDuels;
    }

    /**
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
     * @return string
     */
    public function getScore()
    {
        return $this->score;
    }

    /**
     * @param int $rank
     *
     * @return Coaster
     */
    public function setRank($rank)
    {
        $this->rank = $rank;

        return $this;
    }

    /**
     * @return int
     */
    public function getRank()
    {
        return $this->rank;
    }

    /**
     * @param int $previousRank
     *
     * @return Coaster
     */
    public function setPreviousRank($previousRank)
    {
        $this->previousRank = $previousRank;

        return $this;
    }

    /**
     * @return int
     */
    public function getPreviousRank()
    {
        return $this->previousRank;
    }

    /**
     * @return Coaster
     */
    public function addRating(RiddenCoaster $rating)
    {
        $this->ratings[] = $rating;

        return $this;
    }

    public function removeRating(RiddenCoaster $rating)
    {
        $this->ratings->removeElement($rating);
    }

    /**
     * @return RiddenCoaster[]|ArrayCollection
     */
    public function getRatings(): array|ArrayCollection
    {
        return $this->ratings;
    }

    /**
     * @return MainTag[]|ArrayCollection
     */
    public function getMainTags(): array|ArrayCollection
    {
        return $this->mainTags;
    }

    public function setImages(Image $images): Coaster
    {
        $this->images = $images;

        return $this;
    }

    public function getImages(): ArrayCollection|Collection
    {
        $criteria = Criteria::create()->where(Criteria::expr()->eq('enabled', true))->orderBy(['likeCounter' => Criteria::DESC, 'updatedAt' => Criteria::DESC]);

        return $this->images->matching($criteria);
    }

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
     * Set createdAt.
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
     * Get createdAt.
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set updatedAt.
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
     * Get updatedAt.
     *
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * Can we rate this coaster ?
     */
    public function isRateable(): bool
    {
        return $this->status->isRateable();
    }

    /**
     * We don't rank kiddie coasters.
     */
    public function isRankable(): bool
    {
        return !$this->isKiddie();
    }

    public function getUserRating(User $user): ?RiddenCoaster
    {
        /** @var RiddenCoaster $rating */
        foreach ($this->ratings as $rating) {
            if ($rating->getUser() === $user) {
                return $rating;
            }
        }

        return null;
    }

    public function setEnabled(bool $enabled): Coaster
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
