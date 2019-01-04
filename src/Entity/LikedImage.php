<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * LikedImage
 *
 * @ORM\Table(name="liked_image")
 * @ORM\Entity(repositoryClass="App\Repository\LikedImageRepository")
 */
class LikedImage
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * @var Image
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Image")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private $image;

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param User $user
     * @return LikedImage
     */
    public function setUser(User $user): LikedImage
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @param Image $image
     * @return LikedImage
     */
    public function setImage(Image $image): LikedImage
    {
        $this->image = $image;

        return $this;
    }

    /**
     * @return Image
     */
    public function getImage(): Image
    {
        return $this->image;
    }
}
