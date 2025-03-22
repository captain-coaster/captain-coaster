<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;

class ProfilePictureManager
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly FilesystemOperator $profilePicturesFilesystem,
    ) {
    }

    public function uploadProfilePictureFromUrl(string $url, User $user): ?string
    {
        try {
            // Download the image from URL
            $tempFile = tempnam(sys_get_temp_dir(), 'profile_');
            if (false === file_put_contents($tempFile, file_get_contents($url))) {
                throw new \RuntimeException('Failed to download profile picture');
            }

            // Generate a unique filename for the profile picture
            $filename = 'pp_'.$user->getId().'_'.uniqid().'.jpg';

            // Upload to S3
            $this->profilePicturesFilesystem->write(
                $filename,
                file_get_contents($tempFile),
                ['Metadata' => ['type' => 'profile']]
            );

            // Clean up temp file
            unlink($tempFile);

            return $filename;
        } catch (\Exception $e) {
            $this->logger->error('Failed to upload profile picture: '.$e->getMessage());

            return null;
        }
    }
}
