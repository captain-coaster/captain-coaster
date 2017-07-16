<?php

namespace BddBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use FOS\UserBundle\Model\User as BaseUser;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity(repositoryClass="BddBundle\Repository\UserRepository")
 * @ORM\Table(name="users")
 */
class User extends BaseUser
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="facebookId", type="string", length=255, nullable=true)
     */
    private $facebookId;

    /**
     * @var string
     */
    private $facebookAccessToken;

    /**
     * @var string
     *
     * @ORM\Column(name="lastName", type="string", length=255, nullable=true)
     */
    private $lastName;

    /**
     * @var string
     *
     * @ORM\Column(name="firstName", type="string", length=255, nullable=true)
     */
    private $firstName;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $profilePicture;

    /**
     * @var RiddenCoaster
     *
     * @ORM\OneToMany(targetEntity="BddBundle\Entity\RiddenCoaster", mappedBy="user")
     */
    private $ratings;

    /**
     * @var TopCoaster
     *
     * @ORM\OneToMany(targetEntity="TopCoaster", mappedBy="user", cascade={"persist"})
     * @ORM\OrderBy({"position" = "ASC"})
     */
    private $topCoasters;

    /**
     * @var Coaster
     *
     * @ORM\ManyToMany(targetEntity="BddBundle\Entity\Coaster", inversedBy="user")
     * @ORM\JoinColumn(nullable=false)
     * @ORM\JoinTable("user_wish_coaster")
     */
    private $wishCoasters;

    /**
     * @var \DateTime $createdAt
     *
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * User constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->ratings = new \Doctrine\Common\Collections\ArrayCollection();
        $this->topCoasters = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * @param string $facebookId
     * @return User
     */
    public function setFacebookId($facebookId)
    {
        $this->facebookId = $facebookId;

        return $this;
    }

    /**
     * @return string
     */
    public function getFacebookId()
    {
        return $this->facebookId;
    }

    /**
     * @param string $facebookAccessToken
     * @return User
     */
    public function setFacebookAccessToken($facebookAccessToken)
    {
        $this->facebookAccessToken = $facebookAccessToken;

        return $this;
    }

    /**
     * @return string
     */
    public function getFacebookAccessToken()
    {
        return $this->facebookAccessToken;
    }

    /**
     * Set lastName
     *
     * @param string $lastName
     *
     * @return User
     */
    public function setLastName($lastName)
    {
        $this->lastName = $lastName;

        return $this;
    }

    /**
     * Get lastName
     *
     * @return string
     */
    public function getLastName()
    {
        return $this->lastName;
    }

    /**
     * Set firstName
     *
     * @param string $firstName
     *
     * @return User
     */
    public function setFirstName($firstName)
    {
        $this->firstName = $firstName;

        return $this;
    }

    /**
     * Get firstName
     *
     * @return string
     */
    public function getFirstName()
    {
        return $this->firstName;
    }

    /**
     * Add rating
     *
     * @param \BddBundle\Entity\RiddenCoaster $rating
     *
     * @return User
     */
    public function addRating(\BddBundle\Entity\RiddenCoaster $rating)
    {
        $this->ratings[] = $rating;

        return $this;
    }

    /**
     * Remove rating
     *
     * @param \BddBundle\Entity\RiddenCoaster $rating
     */
    public function removeRating(\BddBundle\Entity\RiddenCoaster $rating)
    {
        $this->ratings->removeElement($rating);
    }

    /**
     * Get ratings
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getRatings()
    {
        return $this->ratings;
    }

    /**
     * Add topCoaster
     *
     * @param \BddBundle\Entity\TopCoaster $topCoaster
     *
     * @return User
     */
    public function addTopCoaster(\BddBundle\Entity\TopCoaster $topCoaster)
    {
        $this->topCoasters[] = $topCoaster;

        return $this;
    }

    /**
     * Remove topCoaster
     *
     * @param \BddBundle\Entity\TopCoaster $topCoaster
     */
    public function removeTopCoaster(\BddBundle\Entity\TopCoaster $topCoaster)
    {
        $this->topCoasters->removeElement($topCoaster);
    }

    /**
     * Get topCoasters
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getTopCoasters()
    {
        return $this->topCoasters;
    }

    /**
     * Add wishCoaster
     *
     * @param \BddBundle\Entity\Coaster $wishCoaster
     *
     * @return User
     */
    public function addWishCoaster(\BddBundle\Entity\Coaster $wishCoaster)
    {
        $this->wishCoasters[] = $wishCoaster;

        return $this;
    }

    /**
     * Remove wishCoaster
     *
     * @param \BddBundle\Entity\Coaster $wishCoaster
     */
    public function removeWishCoaster(\BddBundle\Entity\Coaster $wishCoaster)
    {
        $this->wishCoasters->removeElement($wishCoaster);
    }

    /**
     * Get wishCoasters
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getWishCoasters()
    {
        return $this->wishCoasters;
    }

    /**
     * Set profilePicture
     *
     * @param string $profilePicture
     *
     * @return User
     */
    public function setProfilePicture($profilePicture)
    {
        $this->profilePicture = $profilePicture;

        return $this;
    }

    /**
     * Get profilePicture
     *
     * @return string
     */
    public function getProfilePicture()
    {
        return $this->profilePicture;
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     *
     * @return User
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
}
