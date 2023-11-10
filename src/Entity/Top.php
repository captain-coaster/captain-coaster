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
    #[ORM\Column(type: Types::INTEGER), ORM\Id, ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $type = 'top';

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'tops')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /** @var Collection<TopCoaster> */
    #[ORM\OneToMany(mappedBy: 'top', targetEntity: 'TopCoaster', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[CaptainConstraints\UniqueCoaster]
    private Collection $topCoasters;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $main = false;

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

    public function getId(): int|null
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

    public function getTopCoasters(): Collection
    {
        return $this->topCoasters;
    }

    public function isMain(): bool
    {
        return $this->main;
    }

    public function setMain(bool $main): static
    {
        $this->main = $main;

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
