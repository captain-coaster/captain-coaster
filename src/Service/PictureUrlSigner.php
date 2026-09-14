<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Signs resize URLs for pictures.captaincoaster.com's image Lambda. A valid HMAC
 * signature is what authorizes a request there -- not a fixed size allowlist -- so
 * any width/height/format combination this app asks for is honored, and nothing else
 * can mint a URL for a size/format the app never actually uses.
 *
 * Canonical string and signature scheme must stay identical to captain-infra's
 * image-resizer handler.mjs (isValidSignature()).
 */
class PictureUrlSigner
{
    public function __construct(
        private readonly string $picturesCdn,
        private readonly string $signingSecret,
    ) {
    }

    /** @param 'jpg'|'webp'|'avif' $format */
    public function sign(string $filename, int $width, int $height, string $format): string
    {
        $canonical = \sprintf('%dx%d/%s/%s', $width, $height, $format, $filename);
        $signature = substr(hash_hmac('sha256', $canonical, $this->signingSecret), 0, 32);

        return \sprintf('%s/%s?s=%s', $this->picturesCdn, $canonical, $signature);
    }
}
