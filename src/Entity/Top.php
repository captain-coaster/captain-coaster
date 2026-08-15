<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TopRepository;
use App\Validator\Constraints as CaptainConstraints;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Table(name: 'liste')]
#[ORM\Entity(repositoryClass: TopRepository::class)]
class Top
{
    public const string TYPE_RANKING = 'ranking';
    public const string TYPE_BUCKET = 'bucket';
    public const string TYPE_CUSTOM = 'custom';

    #[ORM\Column(type: Types::INTEGER), ORM\Id, ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $type = self::TYPE_CUSTOM;

    #[ORM\Column(name: 'is_public', type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isPublic = true;

    #[ORM\ManyToOne(targetEntity: Coaster::class)]
    #[ORM\JoinColumn(name: 'cover_coaster_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Coaster $coverCoaster = null;

    #[ORM\Column(name: 'likes_count', type: Types::INTEGER, options: ['default' => 0])]
    private int $likesCount = 0;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'tops')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /** @var Collection<int, TopCoaster> */
    #[ORM\OneToMany(mappedBy: 'top', targetEntity: TopCoaster::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[CaptainConstraints\UniqueCoaster]
    private Collection $topCoasters;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'update')]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->topCoasters = new ArrayCollection();
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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function addTopCoaster(TopCoaster $topCoaster): static
    {
        $topCoaster->setTop($this);

        $this->topCoasters->add($topCoaster);

        return $this;
    }

    public function removeTopCoaster(TopCoaster $topCoaster): void
    {
        $this->topCoasters->removeElement($topCoaster);
    }

    /** @return Collection<int, TopCoaster> */
    public function getTopCoasters(): Collection
    {
        return $this->topCoasters;
    }

    public function isRanking(): bool
    {
        return self::TYPE_RANKING === $this->type;
    }

    public function isBucket(): bool
    {
        return self::TYPE_BUCKET === $this->type;
    }

    public function isCustom(): bool
    {
        return self::TYPE_CUSTOM === $this->type;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): static
    {
        $this->isPublic = $isPublic;

        return $this;
    }

    public function getCoverCoaster(): ?Coaster
    {
        return $this->coverCoaster;
    }

    public function setCoverCoaster(?Coaster $coverCoaster): static
    {
        $this->coverCoaster = $coverCoaster;

        return $this;
    }

    public function getLikesCount(): int
    {
        return $this->likesCount;
    }

    public function setLikesCount(int $likesCount): static
    {
        $this->likesCount = $likesCount;

        return $this;
    }

    public function incrementLikesCount(): static
    {
        ++$this->likesCount;

        return $this;
    }

    public function decrementLikesCount(): static
    {
        $this->likesCount = max(0, $this->likesCount - 1);

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
