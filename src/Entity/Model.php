<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\ModelRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Sluggable\Handler\RelativeSlugHandler;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Status.
 */
#[ApiResource(operations: [new Get(), new GetCollection()], normalizationContext: ['groups' => ['read_model']])]
#[ORM\Table(name: 'model')]
#[ORM\Entity(repositoryClass: ModelRepository::class)]
class Model implements \Stringable
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 255, unique: true)]
    #[Groups(['read_model', 'read_coaster'])]
    private ?string $name = null;

    #[ORM\Column(name: 'slug', type: Types::STRING, length: 255, unique: true)]
    #[Gedmo\Slug(fields: ['name'])]
    #[Gedmo\SlugHandler(class: RelativeSlugHandler::class, options: [
        'relationField' => 'manufacturer',
        'relationSlugField' => 'slug',
        'separator' => '-',
    ])]
    private ?string $slug = null;

    #[ORM\ManyToOne(targetEntity: Manufacturer::class)]
    #[ORM\JoinColumn]
    #[Groups(['read_model', 'read_coaster'])]
    private ?Manufacturer $manufacturer = null;

    public function __toString(): string
    {
        return (string) $this->getManufacturer() ? $this->getManufacturer()->getName().' '.$this->name : $this->name;
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
     * @return Model
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set slug.
     *
     * @param string $slug
     *
     * @return Model
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * Get slug.
     *
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }

    public function getManufacturer(): ?Manufacturer
    {
        return $this->manufacturer;
    }

    public function setManufacturer(?Manufacturer $manufacturer): self
    {
        $this->manufacturer = $manufacturer;

        return $this;
    }
}
