<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DTO\PictureRef;
use App\Service\PictureUrlSigner;
use PHPUnit\Framework\TestCase;

class PictureUrlSignerTest extends TestCase
{
    private PictureUrlSigner $signer;

    protected function setUp(): void
    {
        $this->signer = new PictureUrlSigner('https://pictures.example.com', 'test-secret');
    }

    public function testDifferentSecretsProduceDifferentSignatures(): void
    {
        $otherSigner = new PictureUrlSigner('https://pictures.example.com', 'a-different-secret');

        $picture = new PictureRef(1, '1.jpg', null, null, false, 'photo');

        $this->assertNotSame(
            $this->signer->signImage($picture, 960, 600, 'jpg'),
            $otherSigner->signImage($picture, 960, 600, 'jpg')
        );
    }

    // Shared vectors: computed with Node's crypto exactly as captain-infra's v2.mjs does
    // (sha256/HMAC-SHA256 truncated to 6 hex). A mismatch here is a 400/409 in production.
    public function testV2PhotoMatchesTheLambdaVector(): void
    {
        $signer = $this->signer;
        $picture = new PictureRef(48213, '48213.jpg', 0.4213, 0.5871, true, 'voltron-europa-park');

        $this->assertSame(
            'https://pictures.example.com/i/48213/b765d7/a4247d/480x300/voltron-europa-park.avif',
            $signer->signImage($picture, 480, 300, 'avif')
        );
    }

    public function testV2PhotoWithARevMatchesTheLambdaVector(): void
    {
        $signer = $this->signer;
        $picture = new PictureRef(48213, '48213.jpg', 0.4213, 0.5871, true, 'voltron-europa-park', 2);

        $this->assertSame(
            'https://pictures.example.com/i/48213/967ead/4476dd/480x300/voltron-europa-park.avif',
            $signer->signImage($picture, 480, 300, 'avif')
        );
    }

    public function testANullRevDoesNotChangeTheUrl(): void
    {
        $signer = $this->signer;

        // Same expected URLs as the two vectors around this test, which predate `rev`: adding
        // the property must not move a single existing URL (it would regenerate every variant).
        $this->assertSame(
            'https://pictures.example.com/i/48213/b765d7/a4247d/480x300/voltron-europa-park.avif',
            $signer->signImage(new PictureRef(48213, '48213.jpg', 0.4213, 0.5871, true, 'voltron-europa-park', null), 480, 300, 'avif')
        );
        $this->assertSame(
            'https://pictures.example.com/i/7/0765b9/fba667/96x72/blue-fire.jpg',
            $signer->signImage(PictureRef::fromRow(['id' => 7, 'filename' => '7.jpg', 'focalX' => null, 'focalY' => null, 'watermarked' => false, 'rev' => null, 'coasterId' => 1, 'coasterSlug' => 'blue-fire']), 96, 72, 'jpg')
        );
    }

    public function testV2PhotoWithoutFocalPointMatchesTheLambdaVector(): void
    {
        $signer = $this->signer;
        $picture = new PictureRef(7, '7.jpg', null, null, false, 'blue-fire');

        $this->assertSame(
            'https://pictures.example.com/i/7/0765b9/fba667/96x72/blue-fire.jpg',
            $signer->signImage($picture, 96, 72, 'jpg')
        );
    }

    public function testV2AvatarMatchesTheLambdaVector(): void
    {
        $signer = $this->signer;

        $this->assertSame(
            'https://pictures.example.com/a/9_67cd55be84931/4d5c1c/7faa7d/88x88/avatar.avif',
            $signer->signAvatar('pp_9_67cd55be84931.png', 88, 'avif')
        );
    }

    public function testAvatarUrlIsStableAcrossRepeatedCalls(): void
    {
        $signer = $this->signer;

        $first = $signer->signAvatar('pp_9_67cd55be84931.png', 88, 'avif');

        $this->assertSame($first, $signer->signAvatar('pp_9_67cd55be84931.png', 88, 'avif'));
        $this->assertNotSame($first, $signer->signAvatar('pp_9_67cd55be84931.png', 88, 'jpg'));
    }

    public function testAvatarWithoutAUserIdHasNoUrl(): void
    {
        $signer = $this->signer;

        $this->assertNull($signer->signAvatar('pp__67cd55be84931.jpg', 88, 'jpg'));
    }

    public function testCanonicalFocalMatchesWhatIsWrittenToS3(): void
    {
        $this->assertSame('-', PictureUrlSigner::canonicalFocal(null));
        $this->assertSame('-', PictureUrlSigner::canonicalFocal(''));
        $this->assertSame('0.5', PictureUrlSigner::canonicalFocal(0.5));
        $this->assertSame((string) 0.4213, PictureUrlSigner::canonicalFocal('0.4213'));
    }
}
