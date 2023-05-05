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
 * Launch
 *
 */
#[ApiResource(operations: [new Get(), new GetCollection()], normalizationContext: ['groups' => ['read_launch']])]
#[ORM\Table(name: 'launch')]
#[ORM\Entity(repositoryClass: \App\Repository\LaunchRepository::class)]
class Launch implements \Stringable
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
    #[Groups(['read_launch', 'read_coaster'])]
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
    #[ORM\ManyToMany(targetEntity: 'Coaster', mappedBy: 'launchs')]
    #[ORM\JoinColumn(nullable: false)]
    private \Doctrine\Common\Collections\Collection $coasters;
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
        return $this->getName();
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
     * @return Launch
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
     * @return Launch
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
     * @return Launch
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
}
