<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\NotificationType;
use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Shared content for one notification event. Broadcast-style notifications
 * (e.g. a ranking update) have one row here and many {@see NotificationRecipient}
 * rows, instead of duplicating message/parameter/type per recipient.
 */
#[ORM\Table(name: 'notification')]
#[ORM\Entity(repositoryClass: NotificationRepository::class)]
class Notification
{
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $message = '';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $parameter = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(length: 20, enumType: NotificationType::class)]
    private NotificationType $type;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getParameter(): ?string
    {
        return $this->parameter;
    }

    public function setParameter(?string $parameter): static
    {
        $this->parameter = $parameter;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getType(): NotificationType
    {
        return $this->type;
    }

    public function setType(NotificationType $type): static
    {
        $this->type = $type;

        return $this;
    }
}
