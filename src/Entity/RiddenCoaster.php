<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * RiddenCoaster
 */
#[ORM\UniqueConstraint(name: 'user_coaster_unique', columns: ['coaster_id', 'user_id'])]
#[ORM\Entity(repositoryClass: \App\Repository\RiddenCoasterRepository::class)]
#[UniqueEntity(['coaster', 'user'])]
#[Table(options: ['collate' => 'utf8mb4_unicode_ci', 'charset' => 'utf8mb4'])]
class RiddenCoaster
{
    /**
     * @var int
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    /**
     * @var Coaster
     */
    #[ORM\ManyToOne(targetEntity: \App\Entity\Coaster::class, inversedBy: 'ratings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?\App\Entity\Coaster $coaster = null;

    /**
     * @var User
     */
    #[ORM\ManyToOne(targetEntity: \App\Entity\User::class, inversedBy: 'ratings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?\App\Entity\User $user = null;

    /**
     * @var float
     */
    #[ORM\Column(name: 'rating', type: \Doctrine\DBAL\Types\Types::FLOAT)]
    #[Assert\Choice(['0.5', '1.0', '1.5', '2.0', '2.5', '3.0', '3.5', '4.0', '4.5', '5.0'], strict: true)]
    private ?float $value = null;

    /**
     * @var string
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true, options: ['collation' => 'utf8mb4_unicode_ci'])]
    private ?string $review = null;

    /**
     * @var string
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 5)]
    private ?string $language = 'en';

    /**
     * @var \Doctrine\Common\Collections\Collection<\App\Entity\Tag>
     */
    #[ORM\JoinTable(name: 'ridden_coaster_pro')]
    #[ORM\ManyToMany(targetEntity: \App\Entity\Tag::class)]
    #[ORM\JoinColumn]
    private \Doctrine\Common\Collections\Collection $pros;

    /**
     * @var \Doctrine\Common\Collections\Collection<\App\Entity\Tag>
     */
    #[ORM\JoinTable(name: 'ridden_coaster_con')]
    #[ORM\ManyToMany(targetEntity: \App\Entity\Tag::class)]
    #[ORM\JoinColumn]
    private \Doctrine\Common\Collections\Collection $cons;

    /**
     * @var int
     */
    #[ORM\Column(name: 'likes', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: true)]
    private ?int $like = 0;

    /**
     * @var int
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: true)]
    private ?int $dislike = 0;

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
     * @var \DateTimeInterface $riddenAt
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $riddenAt = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->pros = new ArrayCollection();
        $this->cons = new ArrayCollection();
    }

    /**
     * Get id
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Set coaster
     *
     *
     */
    public function setCoaster(Coaster $coaster): RiddenCoaster
    {
        $this->coaster = $coaster;

        return $this;
    }

    /**
     * Get coaster
     */
    public function getCoaster(): Coaster
    {
        return $this->coaster;
    }

    /**
     * Set user
     *
     *
     */
    public function setUser(User $user): RiddenCoaster
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * Set value
     *
     *
     */
    public function setValue(float $value): RiddenCoaster
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Get value
     *
     * @return float
     */
    public function getValue(): ?float
    {
        return $this->value;
    }

    /**
     * Set review
     *
     * @param string $review
     */
    public function setReview($review): RiddenCoaster
    {
        $this->review = $review;

        return $this;
    }

    /**
     * Get review
     *
     * @return string
     */
    public function getReview(): ?string
    {
        return $this->review;
    }

    /**
     * Set language
     *
     * @param string $language
     */
    public function setLanguage($language): RiddenCoaster
    {
        $this->language = $language;

        return $this;
    }

    /**
     * Get language
     */
    public function getLanguage(): string
    {
        return $this->language;
    }

    /**
     * Add pro
     *
     *
     */
    public function addPro(Tag $pro): RiddenCoaster
    {
        $this->pros[] = $pro;

        return $this;
    }

    /**
     * Remove pro
     */
    public function removePro(Tag $pro): void
    {
        $this->pros->removeElement($pro);
    }

    /**
     * Get pros
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getPros()
    {
        return $this->pros;
    }

    /**
     * Add con
     *
     *
     */
    public function addCon(Tag $con): RiddenCoaster
    {
        $this->cons[] = $con;

        return $this;
    }

    /**
     * Remove con
     */
    public function removeCon(Tag $con): void
    {
        $this->cons->removeElement($con);
    }

    /**
     * Get cons
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getCons()
    {
        return $this->cons;
    }

    /**
     * Set like
     *
     * @param integer $like
     *
     * @return RiddenCoaster
     */
    public function setLike($like)
    {
        $this->like = $like;

        return $this;
    }

    /**
     * Get like
     *
     * @return integer
     */
    public function getLike()
    {
        return $this->like;
    }

    /**
     * Set dislike
     *
     * @param integer $dislike
     *
     * @return RiddenCoaster
     */
    public function setDislike($dislike)
    {
        $this->dislike = $dislike;

        return $this;
    }

    /**
     * Get dislike
     *
     * @return integer
     */
    public function getDislike()
    {
        return $this->dislike;
    }

    /**
     * Set createdAt
     *
     *
     */
    public function setCreatedAt(\DateTime $createdAt): RiddenCoaster
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get createdAt
     */
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    /**
     * Set updatedAt
     *
     *
     */
    public function setUpdatedAt(\DateTime $updatedAt): RiddenCoaster
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get updatedAt
     */
    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    /**
     * Ignore rating if too different from other ratings
     */
    public function isAberrantRating(): bool
    {
        return (abs((float)$this->getCoaster()->getAverageRating() - (float)$this->getValue()) > 3);
    }

    /**
     * @param mixed $riddenAt
     */
    public function setRiddenAt(?\DateTime $riddenAt): RiddenCoaster
    {
        $this->riddenAt = $riddenAt;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getRiddenAt(): ?\DateTime
    {
        return $this->riddenAt;
    }
}
