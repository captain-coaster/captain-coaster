<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\StatusRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(operations: [new Get(), new GetCollection()], normalizationContext: ['groups' => ['read_status']])]
#[ORM\Table(name: 'status')]
#[ORM\Entity(repositoryClass: StatusRepository::class)]
class Status implements \Stringable, Vocabulary
{
    final public const string OPERATING = 'status.operating';
    final public const string CLOSED_DEFINITELY = 'status.closed.definitely';
    final public const string CONSTRUCTION = 'status.construction';
    final public const string ANNOUNCED = 'status.announced';
    final public const string RELOCATED = 'status.relocated';
    final public const string RETRACKED = 'status.retracked';

    /** Statuses of a coaster that can no longer be ridden as it was. */
    final public const array GONE = [self::CLOSED_DEFINITELY, self::RELOCATED, self::RETRACKED];

    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[Groups(['read_status'])]
    private ?int $id = null;

    /** Translation key in the `database` domain. */
    #[ORM\Column(length: 64, unique: true, nullable: true)]
    #[Groups(['list_coaster', 'read_coaster', 'read_status'])]
    private ?string $code = null;

    /** English label, shown when the code has no translation. */
    #[ORM\Column(name: 'name', type: Types::STRING, length: 255, unique: true)]
    #[Groups(['list_coaster', 'read_coaster', 'read_status'])]
    private string $name = '';

    #[ORM\Column(name: 'slug', type: Types::STRING, length: 255, unique: true)]
    #[Gedmo\Slug(fields: ['name'])]
    private ?string $slug = null;

    #[ORM\Column(name: 'type', type: Types::STRING, length: 255)]
    private string $type = '';

    /** @var Collection<int, Coaster> */
    #[ORM\OneToMany(targetEntity: Coaster::class, mappedBy: 'status')]
    private Collection $coasters;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['read_status'])]
    private bool $isRateable = false;

    #[ORM\Column(name: '`order`', type: Types::INTEGER, nullable: false)]
    private int $order;

    public function __construct()
    {
        $this->coasters = new ArrayCollection();
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function addCoaster(Coaster $coaster): static
    {
        $this->coasters[] = $coaster;

        return $this;
    }

    public function removeCoaster(Coaster $coaster): void
    {
        $this->coasters->removeElement($coaster);
    }

    /** @return Collection<int, Coaster> */
    public function getCoasters(): Collection
    {
        return $this->coasters;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setIsRateable(bool $isRateable): static
    {
        $this->isRateable = $isRateable;

        return $this;
    }

    public function isRateable(): bool
    {
        return $this->isRateable;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function setOrder(int $order): static
    {
        $this->order = $order;

        return $this;
    }
}
