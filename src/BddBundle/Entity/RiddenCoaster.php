<?php

namespace BddBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Coaster
 *
 * @ORM\Table(
 *     uniqueConstraints={@ORM\UniqueConstraint(name="user_coaster_unique", columns={"coaster_id", "user_id"})}
 *     )
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
     * @ORM\Column(name="rating", type="float", precision=2, scale=1, nullable=true)
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
     * @var PositiveKeyword
     *
     * @ORM\ManyToMany(targetEntity="BddBundle\Entity\PositiveKeyword")
     * @ORM\JoinColumn(nullable=true)
     */
    private $positiveKeywords;

    /**
     * @var NegativeKeyword
     *
     * @ORM\ManyToMany(targetEntity="BddBundle\Entity\NegativeKeyword")
     * @ORM\JoinColumn(nullable=true)
     */
    private $negativeKeywords;

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
     * Get id
     *
     * @return int
     */
    public function getId()
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
    public function setCoaster(Coaster $coaster)
    {
        $this->coaster = $coaster;

        return $this;
    }

    /**
     * Get coaster
     *
     * @return \BddBundle\Entity\Coaster
     */
    public function getCoaster()
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
    public function setUser(User $user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return \BddBundle\Entity\User
     */
    public function getUser()
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
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Get value
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     *
     * @return RiddenCoaster
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
     * @return RiddenCoaster
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
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->positiveKeywords = new \Doctrine\Common\Collections\ArrayCollection();
        $this->negativeKeywords = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Set rating
     *
     * @param float $rating
     *
     * @return RiddenCoaster
     */
    public function setRating($rating)
    {
        $this->rating = $rating;

        return $this;
    }

    /**
     * Get rating
     *
     * @return float
     */
    public function getRating()
    {
        return $this->rating;
    }

    /**
     * Set review
     *
     * @param string $review
     *
     * @return RiddenCoaster
     */
    public function setReview($review)
    {
        $this->review = $review;

        return $this;
    }

    /**
     * Get review
     *
     * @return string
     */
    public function getReview()
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
    public function setLanguage($language)
    {
        $this->language = $language;

        return $this;
    }

    /**
     * Get language
     *
     * @return string
     */
    public function getLanguage()
    {
        return $this->language;
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
     * Add positiveKeyword
     *
     * @param \BddBundle\Entity\PositiveKeyword $positiveKeyword
     *
     * @return RiddenCoaster
     */
    public function addPositiveKeyword(\BddBundle\Entity\PositiveKeyword $positiveKeyword)
    {
        $this->positiveKeywords[] = $positiveKeyword;

        return $this;
    }

    /**
     * Remove positiveKeyword
     *
     * @param \BddBundle\Entity\PositiveKeyword $positiveKeyword
     */
    public function removePositiveKeyword(\BddBundle\Entity\PositiveKeyword $positiveKeyword)
    {
        $this->positiveKeywords->removeElement($positiveKeyword);
    }

    /**
     * Get positiveKeywords
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getPositiveKeywords()
    {
        return $this->positiveKeywords;
    }

    /**
     * Add negativeKeyword
     *
     * @param \BddBundle\Entity\NegativeKeyword $negativeKeyword
     *
     * @return RiddenCoaster
     */
    public function addNegativeKeyword(\BddBundle\Entity\NegativeKeyword $negativeKeyword)
    {
        $this->negativeKeywords[] = $negativeKeyword;

        return $this;
    }

    /**
     * Remove negativeKeyword
     *
     * @param \BddBundle\Entity\NegativeKeyword $negativeKeyword
     */
    public function removeNegativeKeyword(\BddBundle\Entity\NegativeKeyword $negativeKeyword)
    {
        $this->negativeKeywords->removeElement($negativeKeyword);
    }

    /**
     * Get negativeKeywords
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getNegativeKeywords()
    {
        return $this->negativeKeywords;
    }
}
