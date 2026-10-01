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
class Restraint implements \Stringable, Vocabulary
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;
    /** Translation key in the `database` domain. */
    #[ORM\Column(length: 64, unique: true, nullable: true)]
    #[Groups(['read_restraint', 'read_coaster'])]
    private ?string $code = null;

    /** English label, shown when the code has no translation. */
    #[ORM\Column(name: 'name', type: Types::STRING, length: 255, unique: true)]
    #[Groups(['read_restraint', 'read_coaster'])]
    private string $name = '';
    #[ORM\Column(name: 'slug', type: Types::STRING, length: 255, unique: true)]
    #[Gedmo\Slug(fields: ['name'])]
    private ?string $slug = null;
    /** @var Collection<int, Coaster> */
    #[ORM\OneToMany(targetEntity: Coaster::class, mappedBy: 'restraint')]
    private Collection $coasters;

    /** Constructor */
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

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string|null $slug
     *
     * @return Restraint
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;

        return $this;
    }

    /** @return string|null */
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

    /** @return Collection<int, Coaster> */
    public function getCoasters(): Collection
    {
        return $this->coasters;
    }
}
