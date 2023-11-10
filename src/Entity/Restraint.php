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
 * Restraint
 *
 */
#[ApiResource(operations: [new Get(), new GetCollection()], normalizationContext: ['groups' => ['read_restraint']])]
#[ORM\Table(name: 'restraint')]
#[ORM\Entity(repositoryClass: \App\Repository\RestraintRepository::class)]
class Restraint implements \Stringable
{
    #[ORM\Column(name: 'id', type: \Doctrine\DBAL\Types\Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;
    #[ORM\Column(name: 'name', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, unique: true)]
    #[Groups(['read_restraint', 'read_coaster'])]
    private ?string $name = null;
    #[ORM\Column(name: 'slug', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, unique: true)]
    #[Gedmo\Slug(fields: ['name'])]
    private ?string $slug = null;
    /**
     * @var \Doctrine\Common\Collections\Collection<\App\Entity\Coaster>
     */
    #[ORM\OneToMany(targetEntity: 'Coaster', mappedBy: 'restraint')]
    private \Doctrine\Common\Collections\Collection $coasters;

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
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $name
     * @return Restraint
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
     * @return Restraint
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
     * @return Restraint
     */
    public function addCoaster(Coaster $coaster)
    {
        $this->coasters[] = $coaster;
        return $this;
    }

    public function removeCoaster(Coaster $coaster): void
    {
        $this->coasters->removeElement($coaster);
    }

    /**
     * @return Coaster[]|ArrayCollection
     */
    public function getCoasters(): array|\Doctrine\Common\Collections\ArrayCollection
    {
        return $this->coasters;
    }
}
