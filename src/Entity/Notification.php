<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Notification
 */
#[ORM\Table(name: 'notification')]
#[ORM\Entity(repositoryClass: \App\Repository\NotificationRepository::class)]
class Notification
{
    /**
     * @var int
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    /**
     * @var string
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255)]
    private ?string $message = null;

    /**
     * @var string
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $parameter = null;

    /**
     * @var \DateTimeInterface
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeInterface $createdAt = null;

    /**
     * @var string
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255)]
    private ?string $type = null;

    /**
     * @var boolean
     */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BOOLEAN)]
    private ?bool $isRead = false;

    /**
     * @var User
     */
    #[ORM\ManyToOne(targetEntity: \App\Entity\User::class, inversedBy: 'notifications')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?\App\Entity\User $user = null;

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
     * Set message
     *
     * @param string $message
     *
     * @return Notification
     */
    public function setMessage($message)
    {
        $this->message = $message;

        return $this;
    }

    /**
     * Get message
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     *
     * @return Notification
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
     * Set type
     *
     * @param string $type
     *
     * @return Notification
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set isRead
     *
     * @param boolean $isRead
     *
     * @return Notification
     */
    public function setIsRead($isRead)
    {
        $this->isRead = $isRead;

        return $this;
    }

    /**
     * Get isRead
     *
     * @return boolean
     */
    public function getIsRead()
    {
        return $this->isRead;
    }

    /**
     * Set user
     *
     *
     * @return Notification
     */
    public function setUser(\App\Entity\User $user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return \App\Entity\User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set parameter
     *
     * @param string $parameter
     *
     * @return Notification
     */
    public function setParameter($parameter)
    {
        $this->parameter = $parameter;

        return $this;
    }

    /**
     * Get parameter
     *
     * @return string
     */
    public function getParameter()
    {
        return $this->parameter;
    }
}
