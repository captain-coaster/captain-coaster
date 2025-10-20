<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RiddenCoasterRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\UniqueConstraint(name: 'user_coaster_unique', columns: ['coaster_id', 'user_id'])]
#[ORM\Entity(repositoryClass: RiddenCoasterRepository::class)]
#[UniqueEntity(['coaster', 'user'])]
#[ORM\Table(options: ['collate' => 'utf8mb4_unicode_ci', 'charset' => 'utf8mb4'])]
class RiddenCoaster
{
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Coaster::class, inversedBy: 'ratings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Coaster $coaster = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'ratings')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(name: 'rating', type: Types::FLOAT, nullable: false)]
    #[Assert\Choice([0.5, 1.0, 1.5, 2.0, 2.5, 3.0, 3.5, 4.0, 4.5, 5.0], strict: true)]
    private ?float $value = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $review = null;

    #[ORM\Column(type: Types::STRING, length: 5)]
    private ?string $language = 'en';

    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[ORM\JoinTable(name: 'ridden_coaster_pro')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Collection $pros;

    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[ORM\JoinTable(name: 'ridden_coaster_con')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Collection $cons;

    #[ORM\Column(name: 'likes', type: Types::INTEGER, nullable: true)]
    private ?int $like = 0;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $dislike = 0;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $score = 0.0;

    #[ORM\OneToMany(mappedBy: 'review', targetEntity: ReviewUpvote::class, orphanRemoval: true)]
    private Collection $upvotes;

    #[ORM\OneToMany(mappedBy: 'review', targetEntity: ReviewReport::class, orphanRemoval: true)]
    private Collection $reports;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $riddenAt = null;

    public function __construct()
    {
        $this->pros = new ArrayCollection();
        $this->cons = new ArrayCollection();
        $this->upvotes = new ArrayCollection();
        $this->reports = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setCoaster(Coaster $coaster): self
    {
        $this->coaster = $coaster;

        return $this;
    }

    public function getCoaster(): Coaster
    {
        return $this->coaster;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setValue(float $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function getValue(): ?float
    {
        return $this->value;
    }

    public function setReview($review): self
    {
        $this->review = $review;

        return $this;
    }

    public function getReview(): ?string
    {
        return $this->review;
    }

    public function setLanguage($language): self
    {
        $this->language = $language;

        return $this;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function addPro(Tag $pro): self
    {
        $this->pros[] = $pro;

        return $this;
    }

    public function removePro(Tag $pro): void
    {
        $this->pros->removeElement($pro);
    }

    public function getPros()
    {
        return $this->pros;
    }

    public function addCon(Tag $con): self
    {
        $this->cons[] = $con;

        return $this;
    }

    public function removeCon(Tag $con): void
    {
        $this->cons->removeElement($con);
    }

    public function getCons()
    {
        return $this->cons;
    }

    public function setLike($like)
    {
        $this->like = $like;

        return $this;
    }

    public function getLike()
    {
        return $this->like;
    }

    public function setDislike($dislike)
    {
        $this->dislike = $dislike;

        return $this;
    }

    public function getDislike()
    {
        return $this->dislike;
    }

    public function getScore(): ?float
    {
        return $this->score;
    }

    public function setScore(?float $score): self
    {
        $this->score = $score;

        return $this;
    }

    /** @return Collection<int, ReviewUpvote> */
    public function getUpvotes(): Collection
    {
        return $this->upvotes;
    }

    public function addUpvote(ReviewUpvote $upvote): self
    {
        if (!$this->upvotes->contains($upvote)) {
            $this->upvotes->add($upvote);
            $upvote->setReview($this);
        }

        return $this;
    }

    public function removeUpvote(ReviewUpvote $upvote): self
    {
        if ($this->upvotes->removeElement($upvote)) {
            // set the owning side to null (unless already changed)
            if ($upvote->getReview() === $this) {
                $upvote->setReview(null);
            }
        }

        return $this;
    }

    /** @return Collection<int, ReviewReport> */
    public function getReports(): Collection
    {
        return $this->reports;
    }

    public function addReport(ReviewReport $report): self
    {
        if (!$this->reports->contains($report)) {
            $this->reports->add($report);
            $report->setReview($this);
        }

        return $this;
    }

    public function removeReport(ReviewReport $report): self
    {
        if ($this->reports->removeElement($report)) {
            // set the owning side to null (unless already changed)
            if ($report->getReview() === $this) {
                $report->setReview(null);
            }
        }

        return $this;
    }

    /** Get upvote count (for backward compatibility) */
    public function getUpvoteCount(): int
    {
        return $this->upvotes->count();
    }

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setRiddenAt(?\DateTime $riddenAt): self
    {
        $this->riddenAt = $riddenAt;

        return $this;
    }

    public function getRiddenAt(): ?\DateTimeInterface
    {
        return $this->riddenAt;
    }
}
