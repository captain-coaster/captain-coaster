<?php declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Handler\CustomRelativeSlugHandler;
use App\Repository\CoasterRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Coaster.
 */
#[ApiResource(operations: [new Get(normalizationContext: ['groups' => ['read_coaster']]), new GetCollection(normalizationContext: ['groups' => ['list_coaster']])], normalizationContext: ['groups' => ['list_coaster', 'read_coaster']])]
#[ORM\Table(name: 'coaster')]
#[ORM\Entity(repositoryClass: CoasterRepository::class)]
#[ApiFilter(filterClass: SearchFilter::class, properties: ['id' => 'exact', 'name' => 'partial', 'manufacturer' => 'exact'])]
#[ApiFilter(filterClass: OrderFilter::class, properties: ['id', 'rank' => ['nulls_comparison' => 'nulls_largest']], arguments: ['orderParameterName' => 'order'])]
#[ApiFilter(filterClass: RangeFilter::class, properties: ['rank', 'totalRatings'])]
#[ApiFilter(filterClass: ExistsFilter::class, properties: ['mainImage'])]
class Coaster implements \Stringable
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[Groups(['list_coaster', 'read_coaster'])]
    private ?int $id = null;
    #[ORM\Column(name: 'name', type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Groups(['list_coaster', 'read_coaster'])]
    private ?string $name = null;
    #[ORM\Column(name: 'slug', type: Types::STRING, length: 255, unique: true)]
    #[Gedmo\Slug(fields: ['name'], updatable: true, separator: '-', handlers: [new Gedmo\SlugHandler(class: CustomRelativeSlugHandler::class, options: [new Gedmo\SlugHandlerOption(name: 'relationField', value: 'park'), new Gedmo\SlugHandlerOption(name: 'relationSlugField', value: 'slug'), new Gedmo\SlugHandlerOption(name: 'separator', value: '-')])])]
    private ?string $slug = null;
    #[ORM\ManyToOne(targetEntity: 'MaterialType')]
    #[ORM\JoinColumn]
    #[Groups(['read_coaster'])]
    private ?MaterialType $materialType = null;
    #[ORM\ManyToOne(targetEntity: 'SeatingType')]
    #[ORM\JoinColumn]
    #[Groups(['read_coaster'])]
    private ?SeatingType $seatingType = null;
    #[ORM\ManyToOne(targetEntity: 'Model')]
    #[ORM\JoinColumn]
    #[Groups(['read_coaster'])]
    private ?Model $model = null;
    #[ORM\Column(name: 'speed', type: Types::INTEGER, nullable: true)]
    #[Groups(['read_coaster'])]
    private ?int $speed = null;
    #[ORM\Column(name: 'height', type: Types::INTEGER, nullable: true)]
    #[Groups(['read_coaster'])]
    private ?int $height = null;
    #[ORM\Column(name: 'length', type: Types::INTEGER, nullable: true)]
    #[Groups(['read_coaster'])]
    private ?int $length = null;
    #[ORM\Column(name: 'inversions_number', type: Types::INTEGER, nullable: true)]
    #[Groups(['read_coaster'])]
    private ?int $inversionsNumber = 0;
    #[ORM\ManyToOne(targetEntity: 'Manufacturer')]
    #[ORM\JoinColumn]
    #[Groups(['read_coaster'])]
    private ?Manufacturer $manufacturer = null;
    #[ORM\ManyToOne(targetEntity: 'Restraint', inversedBy: 'coasters')]
    #[ORM\JoinColumn]
    #[Groups(['read_coaster'])]
    private ?Restraint $restraint = null;

    #[ORM\ManyToMany(targetEntity: 'Launch', inversedBy: 'coasters')]
    #[ORM\JoinColumn]
    #[Groups(['read_coaster'])]
    private Collection $launchs;
    #[ORM\Column(name: 'is_kiddie', type: Types::BOOLEAN)]
    private ?bool $kiddie = false;
    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $vr = false;
    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $indoor = false;
    #[ORM\ManyToOne(targetEntity: 'Park', inversedBy: 'coasters', fetch: 'EAGER')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['list_coaster', 'read_coaster'])]
    private ?Park $park = null;
    #[ORM\ManyToOne(targetEntity: 'Status', inversedBy: 'coasters')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['list_coaster', 'read_coaster'])]
    private ?Status $status = null;
    #[ORM\Column(name: 'openingDate', type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['read_coaster'])]
    private ?\DateTimeInterface $openingDate = null;
    #[ORM\Column(name: 'closingDate', type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['read_coaster'])]
    private ?\DateTimeInterface $closingDate = null;
    #[ORM\Column(name: 'video', type: Types::STRING, length: 255, nullable: true)]
    private ?string $video = null;
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $price = null;
    #[ORM\ManyToOne(targetEntity: 'Currency')]
    #[ORM\JoinColumn]
    private ?Currency $currency = null;
    /**
     * @var float
     */
    #[ORM\Column(name: 'averageRating', type: Types::DECIMAL, precision: 5, scale: 3, nullable: true)]
    private ?string $averageRating = null;
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['read_coaster'])]
    private ?int $totalRatings = 0;
    /**
     * @var float
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 3, nullable: true)]
    private ?string $averageTopRank = null;
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $totalTopsIn = 0;
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['read_coaster'])]
    private ?int $validDuels = 0;
    /**
     * @var float
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 11, nullable: true)]
    #[Groups(['read_coaster'])]
    private ?string $score = null;
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['list_coaster', 'read_coaster'])]
    private ?int $rank = null;
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $previousRank = null;
    /**
     * @var Collection<RiddenCoaster>
     */
    #[ORM\OneToMany(targetEntity: 'RiddenCoaster', mappedBy: 'coaster')]
    private Collection $ratings;
    /**
     * @var Collection<MainTag>
     */
    #[ORM\OneToMany(targetEntity: 'MainTag', mappedBy: 'coaster')]
    private Collection $mainTags;
    /**
     * @var Collection<Image>
     */
    #[ORM\OneToMany(targetEntity: 'Image', mappedBy: 'coaster')]
    private Collection $images;
    #[ORM\OneToOne(targetEntity: 'Image', fetch: 'EAGER')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    #[Groups(['read_coaster'])]
    private ?Image $mainImage = null;
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeInterface $createdAt = null;
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeInterface $updatedAt = null;
    #[ORM\Column(type: Types::BOOLEAN)]
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
        return (string)$this->name;
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
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
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
     * Get slug.
     *
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
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

    public function getMaterialType(): ?MaterialType
    {
        return $this->materialType;
    }

    public function setMaterialType(MaterialType $materialType): self
    {
        $this->materialType = $materialType;

        return $this;
    }

    public function getSeatingType(): ?SeatingType
    {
        return $this->seatingType;
    }

    public function setSeatingType(SeatingType $seatingType): self
    {
        $this->seatingType = $seatingType;

        return $this;
    }

    public function getModel(): ?Model
    {
        return $this->model;
    }

    public function setModel(?Model $model): self
    {
        $this->model = $model;

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
    public function getHeight()
    {
        return $this->height;
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
    public function getLength()
    {
        return $this->length;
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
    public function getInversionsNumber()
    {
        return $this->inversionsNumber;
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

    public function getManufacturer(): ?Manufacturer
    {
        return $this->manufacturer;
    }

    public function setManufacturer(?Manufacturer $manufacturer): self
    {
        $this->manufacturer = $manufacturer;

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
    public function setRestraint(Restraint $restraint)
    {
        $this->restraint = $restraint;

        return $this;
    }

    public function addLaunch(Launch $launch): self
    {
        $this->launchs[] = $launch;

        return $this;
    }

    public function removeLaunch(Launch $launch): void
    {
        $this->launchs->removeElement($launch);
    }

    public function getLaunchs(): Collection
    {
        return $this->launchs;
    }

    public function setKiddie(bool $kiddie): self
    {
        $this->kiddie = $kiddie;

        return $this;
    }

    /**
     * @return bool
     */
    public function getVr()
    {
        return $this->vr;
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

    public function setIndoor(bool $indoor): self
    {
        $this->indoor = $indoor;

        return $this;
    }

    public function isIndoor(): bool
    {
        return $this->indoor;
    }

    public function getPark(): ?Park
    {
        return $this->park;
    }

    public function setPark(Park $park): self
    {
        $this->park = $park;

        return $this;
    }

    public function getStatus(): ?Status
    {
        return $this->status;
    }

    public function setStatus(Status $status): self
    {
        $this->status = $status;

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
    public function getClosingDate()
    {
        return $this->closingDate;
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
     * @return string
     */
    public function getVideo()
    {
        return $this->video;
    }

    /**
     * @param string $video
     */
    public function setVideo($video): self
    {
        $this->video = $video;

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
     * @param int $price
     *
     * @return Coaster
     */
    public function setPrice($price)
    {
        $this->price = $price;

        return $this;
    }

    public function getCurrency(): ?Currency
    {
        return $this->currency;
    }

    public function setCurrency(?Currency $currency): self
    {
        $this->currency = $currency;

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
     * @return int
     */
    public function getTotalRatings()
    {
        return $this->totalRatings;
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

    public function getAverageTopRank(): ?int
    {
        return $this->averageTopRank;
    }

    public function setAverageTopRank(int $averageTopRank): self
    {
        $this->averageTopRank = $averageTopRank;

        return $this;
    }

    public function getTotalTopsIn(): int
    {
        return $this->totalTopsIn;
    }

    public function setTotalTopsIn(int $totalTopsIn): self
    {
        $this->totalTopsIn = $totalTopsIn;

        return $this;
    }

    public function getValidDuels(): int
    {
        return $this->validDuels;
    }

    public function setValidDuels(int $validDuels): self
    {
        $this->validDuels = $validDuels;

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
     * @return int
     */
    public function getRank()
    {
        return $this->rank;
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
    public function getPreviousRank()
    {
        return $this->previousRank;
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
     * @return Coaster
     */
    public function addRating(RiddenCoaster $rating)
    {
        $this->ratings[] = $rating;

        return $this;
    }

    public function removeRating(RiddenCoaster $rating): void
    {
        $this->ratings->removeElement($rating);
    }

    public function getRatings(): Collection
    {
        return $this->ratings;
    }

    public function getMainTags(): ArrayCollection|Collection
    {
        return $this->mainTags;
    }

    public function getImages(): ArrayCollection|Collection
    {
        $criteria = Criteria::create()->where(Criteria::expr()->eq('enabled', true))->orderBy(['likeCounter' => Criteria::DESC, 'updatedAt' => Criteria::DESC]);

        return $this->images->matching($criteria);
    }

    public function setImages(Image $images): self
    {
        $this->images = $images;

        return $this;
    }

    public function getMainImage(): ?Image
    {
        return $this->mainImage;
    }

    public function setMainImage(Image $mainImage): self
    {
        $this->mainImage = $mainImage;

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
     * Get updatedAt.
     *
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
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

    public function isKiddie(): bool
    {
        return $this->kiddie;
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

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
