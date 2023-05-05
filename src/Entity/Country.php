<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Country
 */
#[ORM\Table(name: 'country')]
#[ORM\Entity(repositoryClass: \App\Repository\CountryRepository::class)]
class Country implements \Stringable
{
    /**
     * @var int
     */
    #[ORM\Column(name: 'id', type: \Doctrine\DBAL\Types\Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    /**
     * @var string
     */
    #[ORM\Column(name: 'name', type: \Doctrine\DBAL\Types\Types::STRING, length: 255)]
    #[Groups(['read_coaster', 'read_park'])]
    private ?string $name = null;

    /**
     * @var string
     */
    #[ORM\Column(name: 'slug', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, unique: true)]
    #[Gedmo\Slug(fields: ['name'])]
    private ?string $slug = null;

    #[ORM\ManyToOne(targetEntity: 'Continent')]
    #[ORM\JoinColumn]
    private ?\App\Entity\Continent $continent = null;

    /**
     * @return string
     */
    public function __toString(): string
    {
        return (string) $this->name;
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return Country
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set slug
     *
     * @param string $slug
     *
     * @return Country
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * Get slug
     *
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }

    public function setContinent(Continent $continent): Country
    {
        $this->continent = $continent;

        return $this;
    }

    /**
     * @return Continent
     */
    public function getContinent(): ?Continent
    {
        return $this->continent;
    }
}
