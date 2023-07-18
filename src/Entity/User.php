<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use FOS\UserBundle\Model\User as BaseUser;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table(name="users")
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
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
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $googleId;

    /**
     * @var string
     */
    private $googleAccessToken;

    /**
     * @var string
     *
     * @ORM\Column(name="lastName", type="string", length=255, nullable=true)
     */
    private $lastName;

    /**
     * @var string
     *
     * @ORM\Column(name="firstName", type="string", length=255, nullable=false)
     * @Assert\NotBlank()
     */
    private $firstName;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=false)
     * @Assert\NotBlank()
     */
    private $displayName;

    /**
     * @var string
     *
     * @ORM\Column(name="slug", type="string", length=255, unique=true)
     * @Gedmo\Slug(fields={"displayName"})
     */
    private $slug;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=1024, nullable=true)
     */
    private $profilePicture;

    /**
     * @var RiddenCoaster
     *
     * @ORM\OneToMany(targetEntity="App\Entity\RiddenCoaster", mappedBy="user")
     */
    private $ratings;

    /**
     * @var Top[]
     *
     * @ORM\OneToMany(targetEntity="App\Entity\Top", mappedBy="user")
     */
    private $tops;

    /**
     * @var Badge
     *
     * @ORM\ManyToMany(targetEntity="App\Entity\Badge", inversedBy="users")
     * @ORM\JoinColumn(nullable=true)
     */
    private $badges;

    /**
     * @var Notification
     *
     * @ORM\OneToMany(targetEntity="App\Entity\Notification", mappedBy="user")
     * @ORM\OrderBy({"createdAt" = "DESC"})
     */
    private $notifications;

    /**
     * @var Image
     *
     * @ORM\OneToMany(targetEntity="App\Entity\Image", mappedBy="uploader")
     */
    private $images;

    /**
     * @var \DateTime $createdAt
     *
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", options={"default": 1})
     */
    private $emailNotification = 1;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private $preferredLocale = 'en';

    /**
     * @var Park
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Park")
     * @ORM\JoinColumn(nullable=true)
     */
    private $homePark;

    /**
     * @var string
     *
     * @ORM\Column(type="string", unique=true, nullable=true)
     */
    private $apiKey;

    /**
     * Auto add today's date when I rate a coaster
     *
     * @var bool
     *
     * @ORM\Column(type="boolean", options={"default": 0})
     */
    private $addTodayDateWhenRating = 0;

    /**
     * User constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->ratings = new ArrayCollection();
        $this->tops = new ArrayCollection();
        $this->badges = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->images = new ArrayCollection();
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
     * Set googleId
     *
     * @param string $googleId
     *
     * @return User
     */
    public function setGoogleId($googleId)
    {
        $this->googleId = $googleId;

        return $this;
    }

    /**
     * Get googleId
     *
     * @return string
     */
    public function getGoogleId()
    {
        return $this->googleId;
    }

    /**
     * @param string $googleAccessToken
     * @return User
     */
    public function setGoogleAccessToken($googleAccessToken)
    {
        $this->googleAccessToken = $googleAccessToken;

        return $this;
    }

    /**
     * @return string
     */
    public function getGoogleAccessToken()
    {
        return $this->googleAccessToken;
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
     * @param RiddenCoaster $rating
     *
     * @return User
     */
    public function addRating(RiddenCoaster $rating)
    {
        $this->ratings[] = $rating;

        return $this;
    }

    /**
     * Remove rating
     *
     * @param RiddenCoaster $rating
     */
    public function removeRating(RiddenCoaster $rating)
    {
        $this->ratings->removeElement($rating);
    }

    /**
     * Get ratings
     *
     * @return Collection
     */
    public function getRatings()
    {
        return $this->ratings;
    }

    /**
     * @param Coaster $coaster
     * @return RiddenCoaster|null
     */
    public function getRating(Coaster $coaster): ?RiddenCoaster
    {
        /** @var RiddenCoaster $rating */
        foreach ($this->ratings as $rating) {
            if ($rating->getCoaster() === $coaster) {
                return $rating;
            }
        }

        return null;
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

    /**
     * Set displayName
     *
     * @param string $displayName
     *
     * @return User
     */
    public function setDisplayName($displayName)
    {
        $this->displayName = $displayName;

        return $this;
    }

    /**
     * Get displayName
     *
     * @return string
     */
    public function getDisplayName()
    {
        return $this->displayName;
    }

    /**
     * Add top
     *
     * @param Top $top
     *
     * @return User
     */
    public function addTop(Top $top)
    {
        $this->tops[] = $top;

        return $this;
    }

    /**
     * Remove top
     *
     * @param \App\Entity\Top $top
     */
    public function removeTop(Top $top)
    {
        $this->tops->removeElement($top);
    }

    /**
     * Get tops
     *
     * @return Collection
     */
    public function getTops()
    {
        return $this->tops;
    }

    /**
     * Get main top
     *
     * @return Top
     */
    public function getMainTop()
    {
        foreach ($this->tops as $top) {
            if ($top->isMain() === true) {
                return $top;
            }
        }

        // always return top object
        return new Top();
    }

    /**
     * Add badge
     *
     * @param \App\Entity\Badge $badge
     *
     * @return User
     */
    public function addBadge(Badge $badge)
    {
        $this->badges[] = $badge;

        return $this;
    }

    /**
     * Remove badge
     *
     * @param \App\Entity\Badge $badge
     */
    public function removeBadge(Badge $badge)
    {
        $this->badges->removeElement($badge);
    }

    /**
     * Get badges
     *
     * @return Collection
     */
    public function getBadges()
    {
        return $this->badges;
    }

    /**
     * Add notification
     *
     * @param \App\Entity\Notification $notification
     *
     * @return User
     */
    public function addNotification(Notification $notification)
    {
        $this->notifications[] = $notification;

        return $this;
    }

    /**
     * Remove notification
     *
     * @param \App\Entity\Notification $notification
     */
    public function removeNotification(Notification $notification)
    {
        $this->notifications->removeElement($notification);
    }

    /**
     * Get notifications
     *
     * @return Collection
     */
    public function getNotifications()
    {
        return $this->notifications;
    }

    /**
     * @return \Doctrine\Common\Collections\ArrayCollection|Collection
     */
    public function getUnreadNotifications()
    {
        return $this->notifications->filter(
            function (Notification $notif) {
                return !$notif->getIsRead();
            }
        );
    }

    /**
     * @return bool
     */
    public function isEmailNotification(): bool
    {
        return $this->emailNotification;
    }

    /**
     * @param bool $emailNotification
     * @return User
     */
    public function setEmailNotification(bool $emailNotification): User
    {
        $this->emailNotification = $emailNotification;

        return $this;
    }

    /**
     * @return string
     */
    public function getPreferredLocale(): string
    {
        return $this->preferredLocale;
    }

    /**
     * @param string $preferredLocale
     * @return User
     */
    public function setPreferredLocale(string $preferredLocale): User
    {
        $this->preferredLocale = $preferredLocale;

        return $this;
    }

    /**
     * @param string $slug
     * @return User
     */
    public function setSlug(?string $slug): User
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * @return string
     */
    public function getSlug(): ?string
    {
        return $this->slug;
    }

    /**
     * @param Park $homePark
     * @return User
     */
    public function setHomePark(?Park $homePark): User
    {
        $this->homePark = $homePark;

        return $this;
    }

    /**
     * @return Park|null
     */
    public function getHomePark(): ?Park
    {
        return $this->homePark;
    }

    /**
     * @param string $apiKey
     * @return User
     */
    public function setApiKey(string $apiKey): User
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    /**
     * Add image
     *
     * @param Image $image
     *
     * @return User
     */
    public function addImage(Image $image)
    {
        $this->images[] = $image;

        return $this;
    }

    /**
     * Remove image
     *
     * @param Image $image
     */
    public function removeImage(Image $image)
    {
        $this->images->removeElement($image);
    }

    /**
     * Get images
     *
     * @return Collection
     */
    public function getImages(): ?Collection
    {
        return $this->images;
    }

    /**
     * @param bool $addTodayDateWhenRating
     * @return User
     */
    public function setAddTodayDateWhenRating(bool $addTodayDateWhenRating): User
    {
        $this->addTodayDateWhenRating = $addTodayDateWhenRating;

        return $this;
    }

    /**
     * @return bool
     */
    public function isAddTodayDateWhenRating(): bool
    {
        return $this->addTodayDateWhenRating;
    }
}
