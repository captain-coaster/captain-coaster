<?php

declare(strict_types=1);

namespace App\Tests\Service;

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
}
