<?php

namespace BddBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use FOS\UserBundle\Model\User as BaseUser;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="users")
 * @ORM\Entity(repositoryClass="BddBundle\Repository\UserRepository")
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
     * @ORM\Column(name="firstName", type="string", length=255, nullable=true)
     */
    private $firstName;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $displayName;

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
     * @var Liste
     *
     * @ORM\OneToMany(targetEntity="BddBundle\Entity\Liste", mappedBy="user")
     */
    private $listes;

    /**
     * @var Badge
     *
     * @ORM\ManyToMany(targetEntity="BddBundle\Entity\Badge", inversedBy="users")
     * @ORM\JoinColumn(nullable=true)
     */
    private $badges;

    /**
     * @var Notification
     *
     * @ORM\OneToMany(targetEntity="BddBundle\Entity\Notification", mappedBy="user")
     */
    private $notifications;

    /**
     * @var mixed
     *
     * @ORM\OneToMany(targetEntity="BddBundle\Entity\LikeReport", mappedBy="user")
     */
    private $reportLikes;

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
     * @ORM\Column(
     *     type="boolean",
     *     options={"default": 1}
     * )
     */
    private $emailNotification = 1;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private $preferredLocale = 'en';

    /**
     * User constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->ratings = new ArrayCollection();
        $this->listes = new ArrayCollection();
        $this->badges = new ArrayCollection();
        $this->notifications = new ArrayCollection();
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
     * @param \BddBundle\Entity\RiddenCoaster $rating
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
     * @param \BddBundle\Entity\RiddenCoaster $rating
     */
    public function removeRating(RiddenCoaster $rating)
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
     * Add liste
     *
     * @param \BddBundle\Entity\Liste $liste
     *
     * @return User
     */
    public function addListe(Liste $liste)
    {
        $this->listes[] = $liste;

        return $this;
    }

    /**
     * Remove liste
     *
     * @param \BddBundle\Entity\Liste $liste
     */
    public function removeListe(Liste $liste)
    {
        $this->listes->removeElement($liste);
    }

    /**
     * Get listes
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getListes()
    {
        return $this->listes;
    }

    /**
     * Get main Top Coaster
     * @return Liste
     */
    public function getMainListe()
    {
        /** @var Liste $liste */
        foreach ($this->listes as $liste) {
            if ($liste->getType() === Liste::MAIN_LISTE) {
                return $liste;
            }
        }

        return new Liste();
    }

    /**
     * Add badge
     *
     * @param \BddBundle\Entity\Badge $badge
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
     * @param \BddBundle\Entity\Badge $badge
     */
    public function removeBadge(Badge $badge)
    {
        $this->badges->removeElement($badge);
    }

    /**
     * Get badges
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getBadges()
    {
        return $this->badges;
    }

    /**
     * Add notification
     *
     * @param \BddBundle\Entity\Notification $notification
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
     * @param \BddBundle\Entity\Notification $notification
     */
    public function removeNotification(Notification $notification)
    {
        $this->notifications->removeElement($notification);
    }

    /**
     * Get notifications
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getNotifications()
    {
        return $this->notifications;
    }

    /**
     * @return \Doctrine\Common\Collections\ArrayCollection|\Doctrine\Common\Collections\Collection
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
     * @param mixed $reportLikes
     * @return User
     */
    public function setReportLikes($reportLikes)
    {
        $this->reportLikes = $reportLikes;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getReportLikes()
    {
        return $this->reportLikes;
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
}
