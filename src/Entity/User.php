<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'users')]
#[ORM\Entity(repositoryClass: \App\Repository\UserRepository::class)]
class User implements UserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    #[ORM\GeneratedValue]
    protected ?int $id = null;

    #[ORM\Column(name: 'facebookId', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $facebookId = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $googleId = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: false)]
    private ?string $email = null;

    #[ORM\Column(name: 'lastName', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(name: 'firstName', type: \Doctrine\DBAL\Types\Types::STRING, length: 255)]
    #[Assert\NotBlank]
    private ?string $firstName = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255)]
    #[Assert\NotBlank]
    private ?string $displayName = null;

    #[ORM\Column(name: 'slug', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, unique: true)]
    #[Gedmo\Slug(fields: ['displayName'])]
    private ?string $slug = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 1024, nullable: true)]
    private ?string $profilePicture = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: RiddenCoaster::class)]
    private \Doctrine\Common\Collections\Collection $ratings;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Top::class)]
    private \Doctrine\Common\Collections\Collection $tops;

    #[ORM\ManyToMany(targetEntity: \App\Entity\Badge::class, inversedBy: 'users')]
    #[ORM\JoinColumn]
    private \Doctrine\Common\Collections\Collection $badges;

    #[ORM\OneToMany(targetEntity: \App\Entity\Notification::class, mappedBy: 'user')]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private \Doctrine\Common\Collections\Collection $notifications;

    #[ORM\OneToMany(targetEntity: \App\Entity\Image::class, mappedBy: 'uploader')]
    private \Doctrine\Common\Collections\Collection $images;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BOOLEAN, options: ['default' => 1])]
    private ?bool $emailNotification = true;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255)]
    private ?string $preferredLocale = 'en';

    /**
     * @var Park
     *
     *
     */
    #[ORM\ManyToOne(targetEntity: \App\Entity\Park::class)]
    #[ORM\JoinColumn]
    private ?\App\Entity\Park $homePark = null;

    /**
     * @var string
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, unique: true, nullable: true)]
    private ?string $apiKey = null;

    /**
     * Auto add today's date when I rate a coaster.
     *
     * @var bool
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BOOLEAN, options: ['default' => 0])]
    private ?bool $addTodayDateWhenRating = false;

    public function __construct()
    {
        $this->ratings = new ArrayCollection();
        $this->tops = new ArrayCollection();
        $this->badges = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->images = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) $this->displayName;
    }

    public function getId()
    {
        return $this->id;
    }

    /**
         * @param string $facebookId
         *
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
     *
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
     * Set googleId.
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
     * Get googleId.
     *
     * @return string
     */
    public function getGoogleId()
    {
        return $this->googleId;
    }

    /**
     * @param string $googleAccessToken
     *
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
     * Set lastName.
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
     * Get lastName.
     *
     * @return string
     */
    public function getLastName()
    {
        return $this->lastName;
    }

    /**
     * Set firstName.
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
     * Get firstName.
     *
     * @return string
     */
    public function getFirstName()
    {
        return $this->firstName;
    }

    /**
     * Add rating.
     *
     * @return User
     */
    public function addRating(RiddenCoaster $rating)
    {
        $this->ratings[] = $rating;

        return $this;
    }

    /**
     * Remove rating.
     */
    public function removeRating(RiddenCoaster $rating)
    {
        $this->ratings->removeElement($rating);
    }

    /**
     * Get ratings.
     *
     * @return Collection
     */
    public function getRatings()
    {
        return $this->ratings;
    }

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
     * Set profilePicture.
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
     * Get profilePicture.
     *
     * @return string
     */
    public function getProfilePicture()
    {
        return $this->profilePicture;
    }

    /**
     * Set createdAt.
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
     * Get createdAt.
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set displayName.
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
     * Get displayName.
     *
     * @return string
     */
    public function getDisplayName()
    {
        return $this->displayName;
    }

    /**
     * Add top.
     *
     * @return User
     */
    public function addTop(Top $top)
    {
        $this->tops[] = $top;

        return $this;
    }

    /**
     * Remove top.
     */
    public function removeTop(Top $top)
    {
        $this->tops->removeElement($top);
    }

    /**
     * Get tops.
     *
     * @return Collection
     */
    public function getTops()
    {
        return $this->tops;
    }

    /**
     * Get main top.
     *
     * @return Top
     */
    public function getMainTop()
    {
        foreach ($this->tops as $top) {
            if ($top->isMain()) {
                return $top;
            }
        }

        // always return top object
        return new Top();
    }

    /**
     * Add badge.
     *
     *
     * @return User
     */
    public function addBadge(Badge $badge)
    {
        $this->badges[] = $badge;

        return $this;
    }

    /**
     * Remove badge.
     */
    public function removeBadge(Badge $badge)
    {
        $this->badges->removeElement($badge);
    }

    /**
     * Get badges.
     *
     * @return Collection
     */
    public function getBadges()
    {
        return $this->badges;
    }

    /**
     * Add notification.
     *
     *
     * @return User
     */
    public function addNotification(Notification $notification)
    {
        $this->notifications[] = $notification;

        return $this;
    }

    /**
     * Remove notification.
     */
    public function removeNotification(Notification $notification)
    {
        $this->notifications->removeElement($notification);
    }

    /**
     * Get notifications.
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
    public function getUnreadNotifications(): \Doctrine\Common\Collections\ArrayCollection|\Doctrine\Common\Collections\Collection
    {
        return $this->notifications->filter(
            fn(Notification $notif) => !$notif->getIsRead()
        );
    }

    public function isEmailNotification(): bool
    {
        return $this->emailNotification;
    }

    public function setEmailNotification(bool $emailNotification): User
    {
        $this->emailNotification = $emailNotification;

        return $this;
    }

    public function getPreferredLocale(): string
    {
        return $this->preferredLocale;
    }

    public function setPreferredLocale(string $preferredLocale): User
    {
        $this->preferredLocale = $preferredLocale;

        return $this;
    }

    /**
     * @param string $slug
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
     */
    public function setHomePark(?Park $homePark): User
    {
        $this->homePark = $homePark;

        return $this;
    }

    public function getHomePark(): ?Park
    {
        return $this->homePark;
    }

    public function setApiKey(string $apiKey): User
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    /**
     * Add image.
     *
     * @return User
     */
    public function addImage(Image $image)
    {
        $this->images[] = $image;

        return $this;
    }

    /**
     * Remove image.
     */
    public function removeImage(Image $image)
    {
        $this->images->removeElement($image);
    }

    /**
     * Get images.
     *
     * @return Collection
     */
    public function getImages(): ?Collection
    {
        return $this->images;
    }

    public function setAddTodayDateWhenRating(bool $addTodayDateWhenRating): User
    {
        $this->addTodayDateWhenRating = $addTodayDateWhenRating;

        return $this;
    }

    public function isAddTodayDateWhenRating(): bool
    {
        return $this->addTodayDateWhenRating;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials()
    {
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }
}
