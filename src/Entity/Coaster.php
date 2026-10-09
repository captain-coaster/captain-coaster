<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\DTO\PartialDate;
use App\Enum\DatePrecision;
use App\Repository\CoasterRepository;
use App\Validator\Constraints as CaptainConstraints;
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
#[ORM\Index(name: 'idx_coaster_name_search', columns: ['name'])]
#[ORM\Index(name: 'idx_coaster_slug_search', columns: ['slug'])]
#[ORM\Index(name: 'idx_coaster_updated_at', columns: ['updated_at'])]
#[ORM\Index(name: 'idx_coaster_opening_date', columns: ['openingDate'])]
#[ORM\Index(name: 'idx_coaster_rank', columns: ['rank'])]
#[ApiResource(
    operations: [
        new Get(normalizationContext: ['groups' => ['read_coaster']]),
        new GetCollection(normalizationContext: ['groups' => ['list_coaster']]),
    ],
    normalizationContext: ['groups' => ['list_coaster', 'read_coaster']]
)]
#[ApiFilter(filterClass: SearchFilter::class, properties: ['id' => 'exact', 'name' => 'partial', 'manufacturer' => 'exact'])]
#[ApiFilter(filterClass: OrderFilter::class, properties: ['id'], arguments: ['orderParameterName' => 'order'])]
#[ApiFilter(filterClass: ExistsFilter::class, properties: ['mainImage'])]
#[Assert\Expression(
    '(this.getPrice() and this.getCurrency()) or (!this.getPrice() and !this.getCurrency())',
    message: 'Missing Price or Currency',
)]
class Coaster implements \Stringable
{
    /** No ride date is accepted before this day. */
    public const string RIDE_DATE_FLOOR = '1950-01-01';

    /** How long before the opening date a ride is accepted. */
    public const int EARLY_RIDE_DAYS = 90;

    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[Groups(['list_coaster', 'read_coaster'])]
    private ?int $id = null;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Groups(['list_coaster', 'read_coaster'])]
    private string $name = '';

    /** @var array<string>|null */
    #[ORM\Column(name: 'formerNames', type: Types::SIMPLE_ARRAY, nullable: true)]
    private ?array $formerNames = null;

    #[ORM\Column(name: 'slug', type: Types::STRING, length: 255, unique: true)]
    #[Gedmo\Slug(fields: ['name'])]
    #[Gedmo\SlugHandler(class: RelativeSlugHandler::class, options: [
        'relationField' => 'park',
        'relationSlugField' => 'slug',
        'separator' => '-',
    ])]
    private ?string $slug = null;

    #[ORM\ManyToOne(targetEntity: MaterialType::class)]
    #[ORM\JoinColumn]
    #[Groups(['read_coaster'])]
    private ?MaterialType $materialType = null;

    #[ORM\ManyToOne(targetEntity: SeatingType::class)]
    #[ORM\JoinColumn]
    #[Groups(['read_coaster'])]
    private ?SeatingType $seatingType = null;

    #[ORM\ManyToOne(targetEntity: Model::class)]
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

    #[ORM\ManyToOne(targetEntity: Manufacturer::class)]
    #[ORM\JoinColumn]
    #[Groups(['read_coaster'])]
    private ?Manufacturer $manufacturer = null;

    #[ORM\ManyToOne(targetEntity: Restraint::class, inversedBy: 'coasters')]
    #[ORM\JoinColumn]
    #[Groups(['read_coaster'])]
    private ?Restraint $restraint = null;

    /** @var Collection<int, Launch> */
    #[ORM\ManyToMany(targetEntity: Launch::class, inversedBy: 'coasters')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Groups(['read_coaster'])]
    private Collection $launchs;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => 0])]
    private bool $kiddie = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => 0])]
    private bool $holdRanking = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => 0])]
    private bool $vr = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => 0])]
    private bool $indoor = false;

    #[ORM\ManyToOne(targetEntity: Park::class, fetch: 'LAZY', inversedBy: 'coasters')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['list_coaster', 'read_coaster'])]
    private ?Park $park = null;

    #[ORM\ManyToOne(targetEntity: Status::class, inversedBy: 'coasters')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['list_coaster', 'read_coaster'])]
    private ?Status $status = null;

    #[ORM\Column(name: 'openingDate', type: Types::DATE_IMMUTABLE, nullable: true)]
    #[CaptainConstraints\DateMinimum]
    #[Groups(['read_coaster'])]
    private ?\DateTimeImmutable $openingDate = null;

    #[ORM\Column(name: 'closingDate', type: Types::DATE_IMMUTABLE, nullable: true)]
    #[CaptainConstraints\DateMinimum]
    #[Groups(['read_coaster'])]
    private ?\DateTimeImmutable $closingDate = null;

    #[ORM\Column(name: 'openingDatePrecision', length: 5, enumType: DatePrecision::class, options: ['default' => 'day'])]
    #[Groups(['read_coaster'])]
    private DatePrecision $openingDatePrecision = DatePrecision::Day;

    #[ORM\Column(name: 'closingDatePrecision', length: 5, enumType: DatePrecision::class, options: ['default' => 'day'])]
    #[Groups(['read_coaster'])]
    private DatePrecision $closingDatePrecision = DatePrecision::Day;

    #[ORM\Column(name: 'video', type: Types::STRING, length: 255, nullable: true)]
    private ?string $video = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $price = null;

    #[ORM\ManyToOne(targetEntity: Currency::class)]
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
    private int $validDuels = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 11, nullable: true)]
    private ?string $score = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $rank = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $previousRank = null;

    /** Best rank ever reached, kept when the coaster leaves the ranking. */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $bestRank = null;

    /** When bestRank was first reached. */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $bestRankAt = null;

    /** @var Collection<int, RiddenCoaster> */
    #[ORM\OneToMany(targetEntity: RiddenCoaster::class, mappedBy: 'coaster', fetch: 'EXTRA_LAZY')]
    private Collection $ratings;

    /** @var Collection<int, Image> */
    #[ORM\OneToMany(targetEntity: Image::class, mappedBy: 'coaster', fetch: 'EXTRA_LAZY')]
    private Collection $images;

    #[ORM\OneToOne(targetEntity: Image::class)]
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
    private bool $enabled = false;

    #[ORM\Column(length: 12, nullable: true)]
    private ?string $youtubeId = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $externalId = null;

    public function __construct()
    {
        $this->launchs = new ArrayCollection();
        $this->ratings = new ArrayCollection();
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /** @return array<string> */
    public function getFormerNames(): array
    {
        return $this->formerNames;
    }

    /** @param array<string>|null $formerNames */
    public function setFormerNames(?array $formerNames): static
    {
        $this->formerNames = $formerNames;

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

    public function setRestraint(?Restraint $restraint): static
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

    /** @return Collection<int, Launch> */
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

    public function isHoldRanking(): bool
    {
        return $this->holdRanking;
    }

    public function setHoldRanking(bool $holdRanking): static
    {
        $this->holdRanking = $holdRanking;

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

    public function getOpeningDate(): ?\DateTimeImmutable
    {
        return $this->openingDate;
    }

    public function getClosingDate(): ?\DateTimeImmutable
    {
        return $this->closingDate;
    }

    public function getOpeningDatePrecision(): DatePrecision
    {
        return $this->openingDatePrecision;
    }

    public function getClosingDatePrecision(): DatePrecision
    {
        return $this->closingDatePrecision;
    }

    /** The opening date with its precision. */
    public function getOpening(): ?PartialDate
    {
        return null === $this->openingDate ? null : new PartialDate($this->openingDate, $this->openingDatePrecision);
    }

    /** The only way to write the opening date: the date and its precision change together. */
    public function setOpening(?PartialDate $opening): static
    {
        // The same day keeps its object: Doctrine compares dates by identity, a new one is an update.
        if ($opening?->date->format('Y-m-d') !== $this->openingDate?->format('Y-m-d')) {
            $this->openingDate = $opening?->date;
        }
        $this->openingDatePrecision = null === $opening ? DatePrecision::Day : $opening->precision;

        return $this;
    }

    /** The closing date with its precision. */
    public function getClosing(): ?PartialDate
    {
        return null === $this->closingDate ? null : new PartialDate($this->closingDate, $this->closingDatePrecision);
    }

    /** The only way to write the closing date: the date and its precision change together. */
    public function setClosing(?PartialDate $closing): static
    {
        if ($closing?->date->format('Y-m-d') !== $this->closingDate?->format('Y-m-d')) {
            $this->closingDate = $closing?->date;
        }
        $this->closingDatePrecision = null === $closing ? DatePrecision::Day : $closing->precision;

        return $this;
    }

    /** The first day a ride is accepted: previews and soft openings come before the opening date. */
    public function getFirstRideDate(): \DateTimeImmutable
    {
        $floor = new \DateTimeImmutable(self::RIDE_DATE_FLOOR);
        if (null === $this->openingDate) {
            return $floor;
        }

        return max($floor, $this->openingDate->modify(\sprintf('-%d days', self::EARLY_RIDE_DAYS)));
    }

    /** The last day the coaster may have run: the end of the year or month when only that is known. Null while it has no closing date. */
    public function getLastRideDate(): ?\DateTimeImmutable
    {
        return null === $this->closingDate ? null : $this->closingDatePrecision->lastDay($this->closingDate);
    }

    public function getVideo(): ?string
    {
        return $this->video;
    }

    public function setVideo(?string $video): static
    {
        $this->video = $video;

        return $this;
    }

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(?int $price): static
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

    public function setRank(?int $rank): static
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

    public function getBestRank(): ?int
    {
        return $this->bestRank;
    }

    public function getBestRankAt(): ?\DateTimeInterface
    {
        return $this->bestRankAt;
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

    /** @return Collection<int, RiddenCoaster> */
    public function getRatings(): Collection
    {
        return $this->ratings;
    }

    /** @return Collection<int, Image> */
    public function getImages(): Collection
    {
        $criteria = Criteria::create()->where(Criteria::expr()->eq('enabled', true))->orderBy(['likeCounter' => Criteria::DESC, 'updatedAt' => Criteria::DESC]);

        return $this->images->matching($criteria);
    }

    /** @param Collection<int, Image> $images */
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

    public function getExternalId(): ?int
    {
        return $this->externalId;
    }

    public function setExternalId(?int $externalId): static
    {
        $this->externalId = $externalId;

        return $this;
    }
}
