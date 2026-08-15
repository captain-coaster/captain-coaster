<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TopLikeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'liste_like')]
#[ORM\UniqueConstraint(name: 'UNIQ_LISTE_LIKE_USER_TOP', columns: ['user_id', 'top_id'])]
#[ORM\Entity(repositoryClass: TopLikeRepository::class)]
class TopLike
{
    #[ORM\Column(type: Types::INTEGER), ORM\Id, ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Top::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Top $top;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, Top $top)
    {
        $this->user = $user;
        $this->top = $top;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getTop(): Top
    {
        return $this->top;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
