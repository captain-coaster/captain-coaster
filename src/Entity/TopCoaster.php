<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'liste_coaster')]
#[ORM\Entity(repositoryClass: \App\Repository\TopCoasterRepository::class)]
class TopCoaster implements \Stringable
{
    #[ORM\Column(type: Types::INTEGER), ORM\Id, ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    private int $position;

    #[ORM\ManyToOne(targetEntity: Top::class, inversedBy: 'topCoasters')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Top $top;

    #[ORM\ManyToOne(targetEntity: \App\Entity\Coaster::class, fetch: 'EAGER')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Coaster $coaster;

    public function __toString(): string
    {
        return (string) $this->coaster.' ('.$this->position.')';
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

    public function setTop(Top $top): static
    {
        $this->top = $top;

        return $this;
    }

    public function getTop(): Top
    {
        return $this->top;
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
}
