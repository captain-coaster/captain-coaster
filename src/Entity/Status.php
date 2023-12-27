<?php declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Status
 *
 */
#[ApiResource(operations: [new Get(), new GetCollection()], normalizationContext: ['groups' => ['read_status']])]
#[ORM\Table(name: 'status')]
#[ORM\Entity(repositoryClass: \App\Repository\StatusRepository::class)]
class Status implements \Stringable
{
    final public const OPERATING = 'status.operating';
    final public const CLOSED_DEFINITELY = 'status.closed.definitely';
    #[ORM\Column(name: 'id', type: \Doctrine\DBAL\Types\Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[Groups(['read_status'])]
    private ?int $id = null;
    #[ORM\Column(name: 'name', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, unique: true)]
    #[Groups(['list_coaster', 'read_coaster', 'read_status'])]
    private ?string $name = null;
    #[ORM\Column(name: 'slug', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, unique: true)]
    #[Gedmo\Slug(fields: ['name'])]
    private ?string $slug = null;
    #[ORM\Column(name: 'type', type: \Doctrine\DBAL\Types\Types::STRING, length: 255)]
    private ?string $type = null;
    /**
     * @var \Doctrine\Common\Collections\Collection<\App\Entity\Coaster>
     */
    #[ORM\OneToMany(targetEntity: 'Coaster', mappedBy: 'status')]
    private \Doctrine\Common\Collections\Collection $coasters;
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BOOLEAN)]
    #[Groups(['read_status'])]
    private ?bool $isRateable = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->coasters = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string)$this->name;
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
     * @return Status
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
     * @return Status
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

    /**
     * Add coaster
     *
     *
     * @return Status
     */
    public function addCoaster(Coaster $coaster)
    {
        $this->coasters[] = $coaster;
        return $this;
    }

    /**
     * Remove coaster
     */
    public function removeCoaster(Coaster $coaster): void
    {
        $this->coasters->removeElement($coaster);
    }

    /**
     * Get coasters
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getCoasters()
    {
        return $this->coasters;
    }

    /**
     * Set type
     *
     * @param string $type
     *
     * @return Status
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Get type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    public function setIsRateable(bool $isRateable): self
    {
        $this->isRateable = $isRateable;
        return $this;
    }

    public function isRateable(): bool
    {
        return $this->isRateable;
    }
}
