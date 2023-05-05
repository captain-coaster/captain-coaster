<?php

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
 * Park
 *
 */
#[ApiResource(operations: [new Get(), new GetCollection()], normalizationContext: ['groups' => ['read_park']])]
#[ORM\Table(name: 'park')]
#[ORM\Entity(repositoryClass: \App\Repository\ParkRepository::class)]
class Park implements \Stringable
{
    /**
     * @var int
     */
    #[ORM\Column(name: 'id', type: \Doctrine\DBAL\Types\Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[Groups(['read_park'])]
    private ?int $id = null;
    /**
     * @var string
     */
    #[ORM\Column(name: 'name', type: \Doctrine\DBAL\Types\Types::STRING, length: 255)]
    #[Groups(['list_coaster', 'read_coaster', 'read_park'])]
    private ?string $name = null;
    /**
     * @var string
     */
    #[ORM\Column(name: 'slug', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, unique: true)]
    #[Gedmo\Slug(fields: ['name'])]
    private ?string $slug = null;
    /**
     * @var \Doctrine\Common\Collections\Collection<\App\Entity\Coaster>
     */
    #[ORM\OneToMany(targetEntity: 'Coaster', mappedBy: 'park')]
    #[ORM\OrderBy(['status' => 'ASC', 'score' => 'DESC'])]
    private \Doctrine\Common\Collections\Collection $coasters;
    #[ORM\ManyToOne(targetEntity: 'Country')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['read_coaster', 'read_park'])]
    private ?\App\Entity\Country $country = null;
    /**
     * @var float
     */
    #[ORM\Column(name: 'latitude', type: \Doctrine\DBAL\Types\Types::FLOAT, precision: 8, scale: 6, nullable: true)]
    #[Groups(['read_park'])]
    private ?float $latitude = null;
    /**
     * @var float
     */
    #[ORM\Column(name: 'longitude', type: \Doctrine\DBAL\Types\Types::FLOAT, precision: 8, scale: 6, nullable: true)]
    #[Groups(['read_park'])]
    private ?float $longitude = null;
    /**
     * @var \DateTimeInterface $createdAt
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeInterface $createdAt = null;
    /**
     * @var \DateTimeInterface $updatedAt
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeInterface $updatedAt = null;
    /**
     * @var boolean $enabled
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BOOLEAN)]
    private ?bool $enabled = false;
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->coasters = new ArrayCollection();
    }
    /**
     * @return string
     */
    public function __toString() : string
    {
        return (string) $this->name;
    }
    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }
    /**
     * @param string $name
     * @return Park
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }
    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
    /**
     * @param string $slug
     * @return Park
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;
        return $this;
    }
    /**
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }
    /**
     * @return Park
     */
    public function addCoaster(Coaster $coaster)
    {
        $this->coasters[] = $coaster;
        return $this;
    }
    public function removeCoaster(Coaster $coaster)
    {
        $this->coasters->removeElement($coaster);
    }
    /**
     * @return Coaster[]|ArrayCollection
     */
    public function getCoasters() : array|\Doctrine\Common\Collections\ArrayCollection
    {
        return $this->coasters;
    }
    /**
     * @return Park
     */
    public function setCountry(Country $country)
    {
        $this->country = $country;
        return $this;
    }
    /**
     * @return Country
     */
    public function getCountry()
    {
        return $this->country;
    }
    /**
     * @param string $latitude
     * @return Park
     */
    public function setLatitude($latitude)
    {
        $this->latitude = $latitude;
        return $this;
    }
    /**
     * @return string
     */
    public function getLatitude()
    {
        return $this->latitude;
    }
    /**
     * @param string $longitude
     * @return Park
     */
    public function setLongitude($longitude)
    {
        $this->longitude = $longitude;
        return $this;
    }
    /**
     * @return string
     */
    public function getLongitude()
    {
        return $this->longitude;
    }
    /**
     * @param \DateTime $createdAt
     * @return Park
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
        return $this;
    }
    /**
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }
    /**
     * @param \DateTime $updatedAt
     * @return Park
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
    /**
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }
    /**
     * @return ArrayCollection
     */
    public function getOpenedCoasters()
    {
        return $this->getCoasters()->filter(fn(Coaster $coaster) => $coaster->getStatus()->getId() == 1);
    }
    /**
     * @return ArrayCollection
     */
    public function getKiddies()
    {
        return $this->getCoasters()->filter(fn(Coaster $coaster) => $coaster->isKiddie() == 1);
    }
    public function setEnabled(bool $enabled) : Park
    {
        $this->enabled = $enabled;
        return $this;
    }
    public function isEnabled() : bool
    {
        return $this->enabled;
    }
}
