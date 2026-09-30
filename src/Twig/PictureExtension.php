<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\PictureUrlSigner;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PictureExtension extends AbstractExtension
{
    public function __construct(
        private readonly PictureUrlSigner $pictureUrlSigner,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('picture_url', $this->pictureUrlSigner->signImage(...)),
            new TwigFunction('avatar_url', $this->pictureUrlSigner->signAvatar(...)),
        ];
    }
}
