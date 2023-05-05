<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * LikedImage
 */
#[ORM\Table(name: 'liked_image')]
#[ORM\Entity(repositoryClass: \App\Repository\LikedImageRepository::class)]
class LikedImage
{
    /**
     * @var int
     */
    #[ORM\Column(name: 'id', type: \Doctrine\DBAL\Types\Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    /**
     * @var User
     */
    #[ORM\ManyToOne(targetEntity: \App\Entity\User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?\App\Entity\User $user = null;

    /**
     * @var Image
     */
    #[ORM\ManyToOne(targetEntity: \App\Entity\Image::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?\App\Entity\Image $image = null;

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    public function setUser(User $user): LikedImage
    {
        $this->user = $user;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setImage(Image $image): LikedImage
    {
        $this->image = $image;

        return $this;
    }

    public function getImage(): Image
    {
        return $this->image;
    }
}
