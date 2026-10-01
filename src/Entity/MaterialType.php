<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MaterialTypeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Status.
 */
#[ORM\Table(name: 'material_type')]
#[ORM\Entity(repositoryClass: MaterialTypeRepository::class)]
class MaterialType implements \Stringable, Vocabulary
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

    /** Get name. */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return MaterialType
     */
    public function setName($name)
    {
        $this->name = $name;

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

    /**
     * Set slug.
     *
     * @param string|null $slug
     *
     * @return MaterialType
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;

        return $this;
    }
}
