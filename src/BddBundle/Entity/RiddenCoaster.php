<?php

namespace BddBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * RiddenCoaster
 *
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(name="user_coaster_unique", columns={"coaster_id", "user_id"})})
 * @ORM\Entity(repositoryClass="BddBundle\Repository\RiddenCoasterRepository")
 */
class RiddenCoaster
{
    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var Coaster
     *
     * @ORM\ManyToOne(targetEntity="BddBundle\Entity\Coaster", inversedBy="ratings")
     * @ORM\JoinColumn(nullable=false)
     */
    private $coaster;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="BddBundle\Entity\User", inversedBy="ratings")
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * @var float
     *
     * @ORM\Column(name="rating", type="float", precision=2, scale=1, nullable=false)
     * @Assert\Choice({0.5, 1, 1.5, 2, 2.5, 3, 3.5, 4, 4.5, 5})
     */
    private $value;

    /**
     * @var string
     *
     * @ORM\Column(type="text", nullable=true)
     */
    private $review;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=5, nullable=false)
     */
    private $language = 'en';

    /**
     * @var ArrayCollection
     *
     * @ORM\ManyToMany(targetEntity="BddBundle\Entity\Tag")
     * @ORM\JoinColumn(nullable=true)
     */
    private $pros;

    /**
     * @var ArrayCollection
     *
     * @ORM\ManyToMany(targetEntity="BddBundle\Entity\Tag")
     * @ORM\JoinColumn(nullable=true)
     */
    private $cons;

    /**
     * @var int
     *
     * @ORM\Column(name="likes", type="integer", nullable=true)
     */
    private $like = 0;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    private $dislike = 0;

    /**
     * @var \DateTime $createdAt
     *
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * @var \DateTime $updatedAt
     *
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime")
     */
    private $updatedAt;

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
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Set coaster
     *
     * @param \BddBundle\Entity\Coaster $coaster
     *
     * @return RiddenCoaster
     */
    public function setCoaster(Coaster $coaster): RiddenCoaster
    {
        $this->coaster = $coaster;

        return $this;
    }

    /**
     * Get coaster
     *
     * @return \BddBundle\Entity\Coaster
     */
    public function getCoaster(): Coaster
    {
        return $this->coaster;
    }

    /**
     * Set user
     *
     * @param \BddBundle\Entity\User $user
     *
     * @return RiddenCoaster
     */
    public function setUser(User $user): RiddenCoaster
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return \BddBundle\Entity\User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * Set value
     *
     * @param string $value
     *
     * @return RiddenCoaster
     */
    public function setValue($value): RiddenCoaster
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Get value
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Set review
     *
     * @param string $review
     *
     * @return RiddenCoaster
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
    public function getReview(): string
    {
        return $this->review;
    }

    /**
     * Set language
     *
     * @param string $language
     *
     * @return RiddenCoaster
     */
    public function setLanguage($language): RiddenCoaster
    {
        $this->language = $language;

        return $this;
    }

    /**
     * Get language
     *
     * @return string
     */
    public function getLanguage(): string
    {
        return $this->language;
    }

    /**
     * Add pro
     *
     * @param \BddBundle\Entity\Tag $pro
     *
     * @return RiddenCoaster
     */
    public function addPro(Tag $pro): RiddenCoaster
    {
        $this->pros[] = $pro;

        return $this;
    }

    /**
     * Remove pro
     *
     * @param \BddBundle\Entity\Tag $pro
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
     * @param \BddBundle\Entity\Tag $con
     *
     * @return RiddenCoaster
     */
    public function addCon(Tag $con): RiddenCoaster
    {
        $this->cons[] = $con;

        return $this;
    }

    /**
     * Remove con
     *
     * @param \BddBundle\Entity\Tag $con
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
     * @param \DateTime $createdAt
     *
     * @return RiddenCoaster
     */
    public function setCreatedAt(\DateTime $createdAt): RiddenCoaster
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get createdAt
     *
     * @return \DateTime
     */
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    /**
     * Set updatedAt
     *
     * @param \DateTime $updatedAt
     *
     * @return RiddenCoaster
     */
    public function setUpdatedAt(\DateTime $updatedAt): RiddenCoaster
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get updatedAt
     *
     * @return \DateTime
     */
    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }
}
