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

    public function testUrlShapeMatchesTheImageResizerLambdaContract(): void
    {
        $url = $this->signer->sign('coaster.jpg', 960, 600, 'avif');

        $expectedSignature = substr(hash_hmac('sha256', '960x600/avif/coaster.jpg', 'test-secret'), 0, 32);

        $this->assertSame(
            'https://pictures.example.com/960x600/avif/coaster.jpg?s='.$expectedSignature,
            $url
        );
    }

    public function testSignatureIsTruncatedToThirtyTwoHexCharacters(): void
    {
        $url = $this->signer->sign('coaster.jpg', 960, 600, 'jpg');
        $signature = substr($url, strpos($url, '?s=') + 3);

        $this->assertSame(32, \strlen($signature));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $signature);
    }

    public function testDifferentFormatsProduceDifferentSignatures(): void
    {
        $jpg = $this->signer->sign('coaster.jpg', 960, 600, 'jpg');
        $avif = $this->signer->sign('coaster.jpg', 960, 600, 'avif');

        $this->assertNotSame($jpg, $avif);
    }

    public function testDifferentSecretsProduceDifferentSignatures(): void
    {
        $otherSigner = new PictureUrlSigner('https://pictures.example.com', 'a-different-secret');

        $this->assertNotSame(
            $this->signer->sign('coaster.jpg', 960, 600, 'jpg'),
            $otherSigner->sign('coaster.jpg', 960, 600, 'jpg')
        );
    }

    // Shared vectors: computed with Node's crypto exactly as captain-infra's v2.mjs does
    // (sha256/HMAC-SHA256 truncated to 6 hex). A mismatch here is a 400/409 in production.
    public function testV2PhotoMatchesTheLambdaVector(): void
    {
        $signer = new PictureUrlSigner('https://pictures.example.com', 'test-secret', 'v2');
        $picture = new PictureRef(48213, '48213.jpg', 0.4213, 0.5871, true, 'voltron-europa-park');

        $this->assertSame(
            'https://pictures.example.com/i/48213/b765d7/a4247d/480x300/voltron-europa-park.avif',
            $signer->signImage($picture, 480, 300, 'avif')
        );
    }

    public function testV2PhotoWithoutFocalPointMatchesTheLambdaVector(): void
    {
        $signer = new PictureUrlSigner('https://pictures.example.com', 'test-secret', 'v2');
        $picture = new PictureRef(7, '7.jpg', null, null, false, 'blue-fire');

        $this->assertSame(
            'https://pictures.example.com/i/7/0765b9/fba667/96x72/blue-fire.jpg',
            $signer->signImage($picture, 96, 72, 'jpg')
        );
    }

    public function testV2AvatarMatchesTheLambdaVector(): void
    {
        $signer = new PictureUrlSigner('https://pictures.example.com', 'test-secret', 'legacy', 'v2');

        $this->assertSame(
            'https://pictures.example.com/a/9_67cd55be84931/4d5c1c/7faa7d/88x88/avatar.avif',
            $signer->signAvatar('pp_9_67cd55be84931.png', 88, 'avif')
        );
    }

    public function testLegacySchemeKeepsTheLegacyLayout(): void
    {
        $picture = new PictureRef(48213, 'voltron.jpg', 0.4213, 0.5871, true, 'voltron-europa-park');

        $this->assertSame($this->signer->sign('voltron.jpg', 480, 300, 'avif'), $this->signer->signImage($picture, 480, 300, 'avif'));
        $this->assertNull($this->signer->signAvatar('pp_9_67cd55be84931.png', 88, 'avif'));
    }

    public function testCanaryPicksByIdModuloHundred(): void
    {
        $signer = new PictureUrlSigner('https://pictures.example.com', 'test-secret', 'canary:10', 'canary:10');

        $this->assertStringContainsString('/i/1209/', $signer->signImage(new PictureRef(1209, 'a.jpg', null, null, false, 'a'), 96, 72, 'jpg'));
        $this->assertStringContainsString('?s=', $signer->signImage(new PictureRef(1210, 'a.jpg', null, null, false, 'a'), 96, 72, 'jpg'));
        $this->assertNotNull($signer->signAvatar('pp_309_67cd55be84931.jpg', 88, 'jpg'));
        $this->assertNull($signer->signAvatar('pp_310_67cd55be84931.jpg', 88, 'jpg'));
    }

    public function testUnrecognisedSchemeFallsBackToLegacy(): void
    {
        $signer = new PictureUrlSigner('https://pictures.example.com', 'test-secret', 'V2', 'canary:abc');

        $this->assertStringContainsString('?s=', $signer->signImage(new PictureRef(1, 'a.jpg', null, null, false, 'a'), 96, 72, 'jpg'));
        $this->assertNull($signer->signAvatar('pp_1_67cd55be84931.jpg', 88, 'jpg'));
    }

    public function testAvatarWithoutAUserIdStaysLegacy(): void
    {
        $signer = new PictureUrlSigner('https://pictures.example.com', 'test-secret', 'v2', 'v2');

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
