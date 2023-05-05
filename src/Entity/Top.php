<?php

namespace App\Entity;

use App\Validator\Constraints as CaptainConstraints;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Top (rename mysql table later)
 */
#[ORM\Table(name: 'liste')]
#[ORM\Entity(repositoryClass: \App\Repository\TopRepository::class)]
class Top
{
    /**
     * @var int
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    /**
     * @var string
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255)]
    private ?string $name = null;

    /**
     * @var string
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255)]
    private ?string $type = 'top';

    /**
     * @var User
     */
    #[ORM\ManyToOne(targetEntity: \App\Entity\User::class, inversedBy: 'tops')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?\App\Entity\User $user = null;

    /**
     * @var \Doctrine\Common\Collections\Collection<\App\Entity\TopCoaster>
     *
     * @CaptainConstraints\UniqueCoaster
     */
    #[ORM\OneToMany(targetEntity: 'TopCoaster', mappedBy: 'top', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private \Doctrine\Common\Collections\Collection $topCoasters;

    /**
     * @var bool
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BOOLEAN)]
    private ?bool $main = false;

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
     * Constructor
     */
    public function __construct()
    {
        $this->topCoasters = new ArrayCollection();
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
     * @return Top
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
     * Set type
     *
     * @param string $type
     *
     * @return Top
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

    /**
     * Set user
     *
     *
     * @return Top
     */
    public function setUser(User $user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Add topCoaster
     *
     *
     * @return Top
     */
    public function addTopCoaster(TopCoaster $topCoaster)
    {
        $topCoaster->setTop($this);

        $this->topCoasters->add($topCoaster);

        return $this;
    }

    /**
     * Remove topCoaster
     */
    public function removeTopCoaster(TopCoaster $topCoaster)
    {
        $this->topCoasters->removeElement($topCoaster);
    }

    /**
     * @return TopCoaster[]|ArrayCollection
     */
    public function getTopCoasters(): array|\Doctrine\Common\Collections\ArrayCollection
    {
        return $this->topCoasters;
    }

    public function setMain(bool $main): Top
    {
        $this->main = $main;

        return $this;
    }

    public function isMain(): bool
    {
        return $this->main;
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     *
     * @return Top
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get createdAt
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set updatedAt
     *
     * @param \DateTime $updatedAt
     *
     * @return Top
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get updatedAt
     *
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }
}
