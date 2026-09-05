<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NotificationRecipientRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * One user's delivery/read state for a {@see Notification}.
 */
#[ORM\Table(name: 'notification_recipient')]
#[ORM\Index(name: 'idx_notification_recipient_user_created', columns: ['user_id', 'created_at'])]
#[ORM\Index(name: 'idx_notification_recipient_user_unread', columns: ['user_id', 'is_read'])]
#[ORM\Entity(repositoryClass: NotificationRecipientRepository::class)]
class NotificationRecipient
{
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Notification::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Notification $notification = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'notifications')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /** Copy of Notification::createdAt, so listing/pagination never needs a join to sort. */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isRead = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $readAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNotification(): ?Notification
    {
        return $this->notification;
    }

    public function setNotification(Notification $notification): static
    {
        $this->notification = $notification;

        return $this;
    }

    /**
     * Set explicitly (rather than derived from the association) so bulk
     * fan-out can pass an already-known value without dereferencing a
     * {@see EntityManagerInterface::getReference()} proxy,
     * which would force a needless load per recipient.
     */
    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function getReadAt(): ?\DateTimeInterface
    {
        return $this->readAt;
    }

    public function markRead(): static
    {
        $this->isRead = true;
        $this->readAt = new \DateTime();

        return $this;
    }
}
