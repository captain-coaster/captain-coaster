<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CountryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Country.
 */
#[ORM\Table(name: 'country')]
#[ORM\Entity(repositoryClass: CountryRepository::class)]
#[UniqueEntity('code')]
class Country implements \Stringable, Vocabulary
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    /** ISO 3166-1 alpha-2, plus the user-assigned XK (Kosovo, SYMFONY_INTL_WITH_USER_ASSIGNED). */
    #[ORM\Column(length: 2, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Country]
    #[Groups(['read_coaster', 'read_park'])]
    private ?string $code = null;

    /** English name, filled from the code. */
    #[ORM\Column(name: 'name', type: Types::STRING, length: 255)]
    #[Groups(['read_coaster', 'read_park'])]
    private string $name = '';

    #[ORM\Column(name: 'slug', type: Types::STRING, length: 255, unique: true)]
    #[Gedmo\Slug(fields: ['name'])]
    private ?string $slug = null;

    #[ORM\ManyToOne(targetEntity: Continent::class)]
    #[ORM\JoinColumn]
    private ?Continent $continent = null;

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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

        if (null !== $code && Countries::exists($code)) {
            $this->name = Countries::getName($code, 'en');
        }

        return $this;
    }

    /**
     * Set name.
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
     * @return Country
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

    public function setContinent(Continent $continent): self
    {
        $this->continent = $continent;

        return $this;
    }

    public function getContinent(): ?Continent
    {
        return $this->continent;
    }
}
