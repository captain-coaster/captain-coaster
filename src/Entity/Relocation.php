<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RelocationRepository;
use App\Validator\Constraints as CaptainConstraints;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Table(name: 'relocation')]
#[ORM\Entity(repositoryClass: RelocationRepository::class)]
class Relocation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToMany(mappedBy: 'relocation', targetEntity: RelocationCoaster::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[CaptainConstraints\UniqueCoaster]
    #[CaptainConstraints\Relocation]
    private Collection $coasters;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->coasters = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCoasters(): Collection
    {
        return $this->coasters;
    }

    public function addCoaster(RelocationCoaster $coaster): static
    {
        $coaster->setRelocation($this);

        $this->coasters->add($coaster);

        return $this;
    }

    public function removeCoaster(RelocationCoaster $coaster): static
    {
        $this->coasters->removeElement($coaster);

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
}
