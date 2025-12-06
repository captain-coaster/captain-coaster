<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\RestraintRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Restraint.
 */
#[ApiResource(operations: [new Get(), new GetCollection()], normalizationContext: ['groups' => ['read_restraint']])]
#[ORM\Table(name: 'restraint')]
#[ORM\Entity(repositoryClass: RestraintRepository::class)]
class Restraint implements \Stringable
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;
    #[ORM\Column(name: 'name', type: Types::STRING, length: 255, unique: true)]
    #[Groups(['read_restraint', 'read_coaster'])]
    private ?string $name = null;
    #[ORM\Column(name: 'slug', type: Types::STRING, length: 255, unique: true)]
    #[Gedmo\Slug(fields: ['name'])]
    private ?string $slug = null;
    /** @var Collection<Coaster> */
    #[ORM\OneToMany(targetEntity: Coaster::class, mappedBy: 'restraint')]
    private Collection $coasters;

    /** Constructor */
    public function __construct()
    {
        $this->coasters = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) $this->name;
    }

    /** @return int */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $name
     *
     * @return Restraint
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /** @return string */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $slug
     *
     * @return Restraint
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;

        return $this;
    }

    /** @return string */
    public function getSlug()
    {
        return $this->slug;
    }

    /** @return Restraint */
    public function addCoaster(Coaster $coaster)
    {
        $this->coasters[] = $coaster;

        return $this;
    }

    public function removeCoaster(Coaster $coaster): void
    {
        $this->coasters->removeElement($coaster);
    }

    /** @return Collection<Coaster> */
    public function getCoasters(): Collection
    {
        return $this->coasters;
    }
}
