<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\AvatarPlaceholder;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AvatarExtension extends AbstractExtension
{
    public function __construct(
        private readonly AvatarPlaceholder $avatarPlaceholder,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('avatar_initials', $this->avatarPlaceholder->initials(...)),
            new TwigFunction('avatar_slot', $this->avatarPlaceholder->slot(...)),
        ];
    }
}
