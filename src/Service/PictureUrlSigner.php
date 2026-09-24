<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\PictureRef;
use App\Entity\Image;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Signs resize URLs for pictures.captaincoaster.com's image Lambda. A valid HMAC
 * signature is what authorizes a request there -- not a fixed size allowlist -- so
 * any width/height/format combination this app asks for is honored, and nothing else
 * can mint a URL for a size/format the app never actually uses.
 *
 * Two layouts, chosen per image/user by PICTURES_URL_SCHEME / PICTURES_AVATAR_SCHEME
 * (`legacy`, `canary:P` for id % 100 < P, or `v2`):
 *   legacy  /{W}x{H}/{format}/{filename}?s={32 hex}    handler.mjs (isValidSignature())
 *   v2      /i/{id}/{v}/{sig}/{W}x{H}/{seo}.{ext}      v2.mjs
 *           /a/{ref}/{v}/{sig}/{S}x{S}/avatar.{ext}    v2.mjs
 * Canonical strings, hashes and signatures must stay identical to captain-infra's
 * image-resizer; the shared vectors in PictureUrlSignerTest pin them.
 */
class PictureUrlSigner
{
    /** Generator version, hashed into `v`. Bump together with the Lambda's GEN_VERSIONS when the encoder, crop or watermark changes. */
    public const int GEN = 1;

    public function __construct(
        #[Autowire('%env(string:PICTURES_CDN)%')]
        private readonly string $picturesCdn,
        #[Autowire('%env(string:PICTURES_SIGNING_SECRET)%')]
        private readonly string $signingSecret,
        #[Autowire('%env(string:PICTURES_URL_SCHEME)%')]
        private readonly string $photoScheme = 'legacy',
        #[Autowire('%env(string:PICTURES_AVATAR_SCHEME)%')]
        private readonly string $avatarScheme = 'legacy',
    ) {
    }

    /**
     * Legacy layout, by filename.
     *
     * @param 'jpg'|'avif' $format
     */
    public function sign(string $filename, int $width, int $height, string $format): string
    {
        $canonical = \sprintf('%dx%d/%s/%s', $width, $height, $format, $filename);
        $signature = substr(hash_hmac('sha256', $canonical, $this->signingSecret), 0, 32);

        return \sprintf('%s/%s?s=%s', $this->picturesCdn, $canonical, $signature);
    }

    /**
     * A photo URL in whichever layout the scheme picks for this image.
     *
     * @param 'jpg'|'avif' $format
     */
    public function signImage(Image|PictureRef $image, int $width, int $height, string $format): string
    {
        $picture = $image instanceof Image ? PictureRef::fromImage($image) : $image;

        if (!self::isV2($this->photoScheme, $picture->id)) {
            return $this->sign($picture->filename, $width, $height, $format);
        }

        $v = self::sha6(implode('|', [
            self::GEN,
            $picture->id,
            self::canonicalFocal($picture->focalX),
            self::canonicalFocal($picture->focalY),
            $picture->watermarked ? '1' : '0',
        ]));

        return $this->signV2(\sprintf('i/%d/%s', $picture->id, $v), \sprintf('%dx%d/%s.%s', $width, $height, $picture->seo, $format));
    }

    /**
     * A v2 avatar URL, or null when the legacy `/profile-pictures/{file}` path applies (scheme,
     * or a filename that isn't `pp_{userId}_{uniqid}.{ext}`).
     *
     * @param 'jpg'|'avif' $format
     */
    public function signAvatar(string $profilePicture, int $size, string $format): ?string
    {
        if (1 !== preg_match('/^pp_(([0-9]+)_[0-9a-f]{13})\.[a-z]+$/', $profilePicture, $m)
            || !self::isV2($this->avatarScheme, (int) $m[2])) {
            return null;
        }

        $ref = $m[1];
        $v = self::sha6(self::GEN.'|'.$ref);

        return $this->signV2(\sprintf('a/%s/%s', $ref, $v), \sprintf('%dx%d/avatar.%s', $size, $size, $format));
    }

    /** Mirrors captain-infra's canonicalFocalComponent (v2.mjs): the string written to S3 metadata, missing/empty -> '-'. */
    public static function canonicalFocal(float|string|null $value): string
    {
        if (null === $value || '' === $value) {
            return '-';
        }

        return \is_float($value) ? (string) $value : $value;
    }

    /** The sig covers the path without its own segment: `{prefix}/{suffix}`. */
    private function signV2(string $prefix, string $suffix): string
    {
        $sig = substr(hash_hmac('sha256', $prefix.'/'.$suffix, $this->signingSecret), 0, 6);

        return \sprintf('%s/%s/%s/%s', $this->picturesCdn, $prefix, $sig, $suffix);
    }

    private static function sha6(string $input): string
    {
        return substr(hash('sha256', $input), 0, 6);
    }

    /** `v2` -> true, `canary:P` -> key % 100 < P (so each palier contains the previous one), anything else -> legacy. */
    private static function isV2(string $scheme, int $key): bool
    {
        if ('v2' === $scheme) {
            return true;
        }

        return 1 === preg_match('/^canary:([0-9]{1,3})$/', $scheme, $m) && $key % 100 < (int) $m[1];
    }
}
