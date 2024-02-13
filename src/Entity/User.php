<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'users')]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface
{
    public function __construct()
    {
        $this->ratings = new ArrayCollection();
        $this->tops = new ArrayCollection();
        $this->badges = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->images = new ArrayCollection();
        $this->apiKey = Uuid::v4()->toRfc4122();
    }

    private const string ROLE_DEFAULT = 'ROLE_USER';

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    protected ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, unique: true)]
    private ?string $facebookId = null;

    private ?string $facebookAccessToken = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, unique: true)]
    private ?string $googleId = null;

    private ?string $googleAccessToken = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false, unique: true)]
    private string $email;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    private string $firstName;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    private ?string $displayName = null;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    #[Gedmo\Slug(fields: ['displayName'])]
    private string $slug;

    #[ORM\Column(type: Types::STRING, length: 1024, nullable: true)]
    private ?string $profilePicture = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: RiddenCoaster::class)]
    private Collection $ratings;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Top::class)]
    private Collection $tops;

    #[ORM\ManyToMany(targetEntity: Badge::class, inversedBy: 'users')]
    #[JoinTable]
    #[JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Collection $badges;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Notification::class)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $notifications;

    #[ORM\OneToMany(mappedBy: 'uploader', targetEntity: Image::class)]
    private Collection $images;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private \DateTimeInterface $lastLogin;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => 1])]
    private bool $emailNotification = true;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $preferredLocale = 'en';

    #[ORM\ManyToOne(targetEntity: Park::class)]
    #[ORM\JoinColumn]
    private ?Park $homePark = null;

    #[ORM\Column(type: Types::STRING, unique: true, nullable: false)]
    private string $apiKey;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => 0])]
    private bool $addTodayDateWhenRating = false;

    #[ORM\Column(type: Types::ARRAY)]
    private array $roles = [];

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => 0])]
    private bool $enabled = false;

    public function __toString(): string
    {
        return $this->displayName;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFacebookId(): ?string
    {
        return $this->facebookId;
    }

    public function setFacebookId(string $facebookId): static
    {
        $this->facebookId = $facebookId;

        return $this;
    }

    public function setFacebookAccessToken(string $facebookAccessToken): static
    {
        $this->facebookAccessToken = $facebookAccessToken;

        return $this;
    }

    public function getFacebookAccessToken(): ?string
    {
        return $this->facebookAccessToken;
    }

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    public function setGoogleId(string $googleId): static
    {
        $this->googleId = $googleId;

        return $this;
    }

    public function setGoogleAccessToken(string $googleAccessToken): static
    {
        $this->googleAccessToken = $googleAccessToken;

        return $this;
    }

    public function getGoogleAccessToken(): ?string
    {
        return $this->googleAccessToken;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName($firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function addRating(RiddenCoaster $rating): static
    {
        $this->ratings[] = $rating;

        return $this;
    }

    public function removeRating(RiddenCoaster $rating): void
    {
        $this->ratings->removeElement($rating);
    }

    public function getRatings(): Collection
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

    public function getProfilePicture(): ?string
    {
        return $this->profilePicture;
    }

    public function setProfilePicture(?string $profilePicture): static
    {
        $this->profilePicture = $profilePicture;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setLastLogin(?\DateTimeInterface $lastLogin): static
    {
        $this->lastLogin = $lastLogin;

        return $this;
    }

    public function getLastLogin(): ?\DateTimeInterface
    {
        return $this->lastLogin;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): static
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function addTop(Top $top): static
    {
        $this->tops[] = $top;

        return $this;
    }

    public function removeTop(Top $top): void
    {
        $this->tops->removeElement($top);
    }

    public function getTops(): Collection
    {
        return $this->tops;
    }

    public function getMainTop(): Top
    {
        foreach ($this->tops as $top) {
            if ($top->isMain()) {
                return $top;
            }
        }

        // always return top object
        return new Top();
    }

    public function addBadge(Badge $badge): static
    {
        $this->badges[] = $badge;

        return $this;
    }

    public function removeBadge(Badge $badge): void
    {
        $this->badges->removeElement($badge);
    }

    public function getBadges(): Collection
    {
        return $this->badges;
    }

    public function addNotification(Notification $notification): static
    {
        $this->notifications[] = $notification;

        return $this;
    }

    public function removeNotification(Notification $notification): void
    {
        $this->notifications->removeElement($notification);
    }

    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function getUnreadNotifications(): Collection
    {
        return $this->notifications->filter(fn (Notification $notif) => !$notif->getIsRead());
    }

    public function isEmailNotification(): bool
    {
        return $this->emailNotification;
    }

    public function setEmailNotification(bool $emailNotification): static
    {
        $this->emailNotification = $emailNotification;

        return $this;
    }

    public function getPreferredLocale(): string
    {
        return $this->preferredLocale;
    }

    public function setPreferredLocale(string $preferredLocale): static
    {
        $this->preferredLocale = $preferredLocale;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getHomePark(): ?Park
    {
        return $this->homePark;
    }

    public function setHomePark(Park $homePark): static
    {
        $this->homePark = $homePark;

        return $this;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function setApiKey(string $apiKey): static
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    public function addImage(Image $image): static
    {
        $this->images[] = $image;

        return $this;
    }

    public function removeImage(Image $image): void
    {
        $this->images->removeElement($image);
    }

    public function getImages(): ?Collection
    {
        return $this->images;
    }

    public function setAddTodayDateWhenRating(bool $addTodayDateWhenRating): static
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
        $roles = $this->roles;

        // we need to make sure to have at least one role
        $roles[] = self::ROLE_DEFAULT;

        return array_values(array_unique($roles));
    }
    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }
}
