<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\CoasterRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Sluggable\Handler\RelativeSlugHandler;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CoasterRepository::class)]
#[ORM\Table(name: 'coaster')]
#[ApiResource(
    operations: [
        new Get(normalizationContext: ['groups' => ['read_coaster']]),
        new GetCollection(normalizationContext: ['groups' => ['list_coaster']]),
    ],
    normalizationContext: ['groups' => ['list_coaster', 'read_coaster']]
)
]
#[ApiFilter(filterClass: SearchFilter::class, properties: ['id' => 'exact', 'name' => 'partial', 'manufacturer' => 'exact'])]
#[ApiFilter(filterClass: OrderFilter::class, properties: ['id', 'rank' => ['nulls_comparison' => 'nulls_largest']], arguments: ['orderParameterName' => 'order'])]
#[ApiFilter(filterClass: RangeFilter::class, properties: ['rank', 'totalRatings'])]
#[ApiFilter(filterClass: ExistsFilter::class, properties: ['mainImage'])]
#[Assert\Expression(
    '(this.getPrice() and this.getCurrency()) or (!this.getPrice() and !this.getCurrency())',
    message: 'Missing Price or Currency',
)]
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
    #[Gedmo\Slug(fields: ['name'])]
    #[Gedmo\SlugHandler(class: RelativeSlugHandler::class, options: [
        'relationField' => 'park',
        'relationSlugField' => 'slug',
        'separator' => '-',
    ])]
    private ?string $slug = null;

    #[ORM\ManyToOne(targetEntity: 'MaterialType')]
    #[ORM\JoinColumn]
    #[Groups(['read_coaster'])]
    private ?MaterialType $materialType = null;

    #[ORM\ManyToOne(targetEntity: 'SeatingType', fetch: 'EAGER')]
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
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Groups(['read_coaster'])]
    private Collection $launchs;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $kiddie = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $vr = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $indoor = false;

    #[ORM\ManyToOne(targetEntity: 'Park', fetch: 'LAZY', inversedBy: 'coasters')]
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

    #[ORM\Column(name: 'averageRating', type: Types::DECIMAL, precision: 5, scale: 3, nullable: true)]
    private ?string $averageRating = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['read_coaster'])]
    private int $totalRatings = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 3, nullable: true)]
    private ?string $averageTopRank = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $totalTopsIn = 0;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['read_coaster'])]
    private int $validDuels = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 11, nullable: true)]
    #[Groups(['read_coaster'])]
    private ?string $score = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['list_coaster', 'read_coaster'])]
    private ?int $rank = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $previousRank = null;

    #[ORM\OneToMany(targetEntity: 'RiddenCoaster', mappedBy: 'coaster')]
    private Collection $ratings;

    #[ORM\OneToMany(targetEntity: 'MainTag', mappedBy: 'coaster')]
    private Collection $mainTags;

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

    #[ORM\Column(length: 12, nullable: true)]
    private ?string $youtubeId = null;

    public function __construct()
    {
        $this->launchs = new ArrayCollection();
        $this->ratings = new ArrayCollection();
        $this->mainTags = new ArrayCollection();
        $this->images = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getMaterialType(): ?MaterialType
    {
        return $this->materialType;
    }

    public function setMaterialType(MaterialType $materialType): static
    {
        $this->materialType = $materialType;

        return $this;
    }

    public function getSeatingType(): ?SeatingType
    {
        return $this->seatingType;
    }

    public function setSeatingType(SeatingType $seatingType): static
    {
        $this->seatingType = $seatingType;

        return $this;
    }

    public function getModel(): ?Model
    {
        return $this->model;
    }

    public function setModel(?Model $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getSpeed(): ?int
    {
        return $this->speed;
    }

    public function setSpeed(?int $speed): static
    {
        $this->speed = $speed;

        return $this;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(?int $height): static
    {
        $this->height = $height;

        return $this;
    }

    public function getLength(): ?int
    {
        return $this->length;
    }

    public function setLength(?int $length): static
    {
        $this->length = $length;

        return $this;
    }

    public function getInversionsNumber(): ?int
    {
        return $this->inversionsNumber;
    }

    public function setInversionsNumber(?int $inversionsNumber): static
    {
        $this->inversionsNumber = $inversionsNumber;

        return $this;
    }

    public function getManufacturer(): ?Manufacturer
    {
        return $this->manufacturer;
    }

    public function setManufacturer(?Manufacturer $manufacturer): static
    {
        $this->manufacturer = $manufacturer;

        return $this;
    }

    public function getRestraint(): ?Restraint
    {
        return $this->restraint;
    }

    public function setRestraint(Restraint $restraint): static
    {
        $this->restraint = $restraint;

        return $this;
    }

    public function addLaunch(Launch $launch): static
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

    public function isKiddie(): bool
    {
        return $this->kiddie;
    }

    public function setKiddie(bool $kiddie): static
    {
        $this->kiddie = $kiddie;

        return $this;
    }

    public function getVr(): bool
    {
        return $this->vr;
    }

    public function setVr(bool $vr): static
    {
        $this->vr = $vr;

        return $this;
    }

    public function isIndoor(): bool
    {
        return $this->indoor;
    }

    public function setIndoor(bool $indoor): static
    {
        $this->indoor = $indoor;

        return $this;
    }

    public function getPark(): ?Park
    {
        return $this->park;
    }

    public function setPark(Park $park): static
    {
        $this->park = $park;

        return $this;
    }

    public function getStatus(): ?Status
    {
        return $this->status;
    }

    public function setStatus(Status $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getOpeningDate(): ?\DateTimeInterface
    {
        return $this->openingDate;
    }

    public function setOpeningDate(?\DateTimeInterface $openingDate): static
    {
        $this->openingDate = $openingDate;

        return $this;
    }

    public function getClosingDate(): ?\DateTimeInterface
    {
        return $this->closingDate;
    }

    public function setClosingDate(?\DateTimeInterface $closingDate): static
    {
        $this->closingDate = $closingDate;

        return $this;
    }

    public function getVideo(): ?string
    {
        return $this->video;
    }

    public function setVideo($video): static
    {
        $this->video = $video;

        return $this;
    }

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice($price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getCurrency(): ?Currency
    {
        return $this->currency;
    }

    public function setCurrency(?Currency $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getAverageRating(): ?string
    {
        return $this->averageRating;
    }

    public function setAverageRating(?string $averageRating): static
    {
        $this->averageRating = $averageRating;

        return $this;
    }

    public function getTotalRatings(): int
    {
        return $this->totalRatings;
    }

    public function setTotalRatings(int $totalRatings): static
    {
        $this->totalRatings = $totalRatings;

        return $this;
    }

    public function getAverageTopRank(): ?string
    {
        return $this->averageTopRank;
    }

    public function setAverageTopRank(?string $averageTopRank): static
    {
        $this->averageTopRank = $averageTopRank;

        return $this;
    }

    public function getTotalTopsIn(): int
    {
        return $this->totalTopsIn;
    }

    public function setTotalTopsIn(int $totalTopsIn): static
    {
        $this->totalTopsIn = $totalTopsIn;

        return $this;
    }

    public function getValidDuels(): int
    {
        return $this->validDuels;
    }

    public function setValidDuels(int $validDuels): static
    {
        $this->validDuels = $validDuels;

        return $this;
    }

    public function getScore(): ?string
    {
        return $this->score;
    }

    public function setScore(?string $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function getRank(): ?int
    {
        return $this->rank;
    }

    public function setRank($rank): static
    {
        $this->rank = $rank;

        return $this;
    }

    public function getPreviousRank(): ?int
    {
        return $this->previousRank;
    }

    public function setPreviousRank(?int $previousRank): static
    {
        $this->previousRank = $previousRank;

        return $this;
    }

    public function addRating(RiddenCoaster $rating): static
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

    public function getMainTags(): Collection
    {
        return $this->mainTags;
    }

    public function getImages(): Collection
    {
        $criteria = Criteria::create()->where(Criteria::expr()->eq('enabled', true))->orderBy(['likeCounter' => Criteria::DESC, 'updatedAt' => Criteria::DESC]);

        return $this->images->matching($criteria);
    }

    public function setImages(Collection $images): static
    {
        $this->images = $images;

        return $this;
    }

    public function getMainImage(): ?Image
    {
        return $this->mainImage;
    }

    public function setMainImage(?Image $mainImage): static
    {
        $this->mainImage = $mainImage;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /** Can we rate this coaster ? */
    public function isRateable(): bool
    {
        return $this->status->isRateable();
    }

    /** We don't rank kiddie coasters. */
    public function isRankable(): bool
    {
        return !$this->isKiddie();
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getYoutubeId(): ?string
    {
        return $this->youtubeId;
    }

    public function setYoutubeId(?string $youtubeId): static
    {
        $this->youtubeId = $youtubeId;

        return $this;
    }
}
