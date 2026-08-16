<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TopCoaster;
use App\Entity\User;
use App\Repository\CountryRepository;
use App\Repository\ParkRepository;
use Aws\S3\S3Client;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

class BannerService
{
    public function __construct(
        private readonly S3Client $s3Client,
        private readonly ParkRepository $parkRepository,
        private readonly CountryRepository $countryRepository,
        private readonly Environment $twig,
        #[Autowire('%env(string:AWS_S3_CACHE_BUCKET_NAME)%')]
        private readonly string $s3CacheBucket,
    ) {
    }

    /** Generate SVG banner and upload to S3. */
    public function generateAndUpload(User $user): void
    {
        $svg = $this->generateSvg($user);

        $this->s3Client->putObject([
            'Bucket' => $this->s3CacheBucket,
            'Key' => 'banner/'.$user->getId().'.svg',
            'Body' => $svg,
            'ContentType' => 'image/svg+xml',
            'CacheControl' => 'public, max-age=86400, stale-while-revalidate=604800',
        ]);
    }

    public function generateSvg(User $user): string
    {
        $topNames = $this->getTopCoasterNames($user, 3);

        return $this->twig->render('Profile/_banner.svg.twig', [
            'coasters' => $user->getRatings()->count(),
            'parks' => $this->parkRepository->countForUser($user),
            'countries' => $this->countryRepository->countForUser($user),
            'top1' => $topNames[0] ?? '—',
            'top2' => $topNames[1] ?? '—',
            'top3' => $topNames[2] ?? '—',
        ]);
    }

    /** @return list<string> */
    private function getTopCoasterNames(User $user, int $limit): array
    {
        $names = [];

        /** @var TopCoaster $topCoaster */
        foreach ($user->getMainTop()->getTopCoasters()->slice(0, $limit) as $topCoaster) {
            $names[] = $topCoaster->getCoaster()->getName();
        }

        return $names;
    }
}
