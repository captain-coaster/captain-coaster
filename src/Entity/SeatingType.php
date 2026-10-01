<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SeatingTypeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Status.
 */
#[ORM\Table(name: 'seating_type')]
#[ORM\Entity(repositoryClass: SeatingTypeRepository::class)]
class SeatingType implements \Stringable, Vocabulary
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    /** Translation key in the `database` domain. */
    #[ORM\Column(length: 64, unique: true, nullable: true)]
    #[Groups(['read_coaster'])]
    private ?string $code = null;

    /** English label, shown when the code has no translation. */
    #[ORM\Column(name: 'name', type: Types::STRING, length: 255, unique: true)]
    #[Groups(['read_coaster'])]
    private string $name = '';

    #[ORM\Column(name: 'slug', type: Types::STRING, length: 255, unique: true)]
    #[Gedmo\Slug(fields: ['name'])]
    private ?string $slug = null;

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

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return SeatingType
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /** Get name. */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set slug.
     *
     * @param string|null $slug
     *
     * @return SeatingType
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * Get slug.
     *
     * @return string|null
     */
    public function getSlug()
    {
        return $this->slug;
    }
}
