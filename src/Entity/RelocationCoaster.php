<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RelocationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'relocation_coaster')]
#[ORM\Entity(repositoryClass: RelocationRepository::class)]
class RelocationCoaster implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private int $position;

    #[ORM\ManyToOne(targetEntity: Coaster::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Coaster $coaster;

    #[ORM\ManyToOne(targetEntity: Relocation::class, inversedBy: 'coasters')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Relocation $relocation;

    public function __toString(): string
    {
        return $this->coaster->__toString();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setCoaster(Coaster $coaster): static
    {
        $this->coaster = $coaster;

        return $this;
    }

    public function getCoaster(): Coaster
    {
        return $this->coaster;
    }

    public function getRelocation(): Relocation
    {
        return $this->relocation;
    }

    public function setRelocation(Relocation $relocation): static
    {
        $this->relocation = $relocation;

        return $this;
    }
}
