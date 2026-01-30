<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ProfilePictureManager
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly FilesystemOperator $profilePicturesFilesystem,
    ) {
    }

    /** Upload a profile picture from a file. */
    public function uploadProfilePicture(UploadedFile $file, User $user): ?string
    {
        try {
            $extension = $file->guessExtension() ?: 'jpg';
            $stream = fopen($file->getPathname(), 'r');

            if (false === $stream) {
                throw new \RuntimeException('Failed to open file stream');
            }

            return $this->handleUpload($user, $stream, $extension);
        } catch (\Exception $e) {
            return $this->handleError('Failed to upload profile picture', $e);
        }
    }

    /** Upload a profile picture from a URL. */
    public function uploadProfilePictureFromUrl(string $url, User $user): ?string
    {
        try {
            // Use stream context to set timeout and user agent
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                ],
            ]);

            // Use streams instead of downloading entire file to memory
            if ($stream = fopen($url, 'r', false, $context)) {
                return $this->handleUpload($user, $stream, 'jpg');
            }

            throw new \RuntimeException('Failed to open URL stream');
        } catch (\Exception $e) {
            return $this->handleError('Failed to upload profile picture', $e);
        }
    }

    /**
     * Handle the upload process and return the filename.
     *
     * @param resource $stream
     */
    private function handleUpload(User $user, mixed $stream, string $extension): string
    {
        $this->deleteOldProfilePicture($user);

        $userId = $user->getId();
        if (null === $userId) {
            throw new \RuntimeException('Cannot upload profile picture for user without ID');
        }

        $filename = $this->generateFilename($userId, $extension);

        // Use writeStream instead of write for better memory efficiency
        $this->profilePicturesFilesystem->writeStream(
            $filename,
            $stream,
            ['Metadata' => ['type' => 'profile']]
        );

        return $filename;
    }

    /** Delete the old profile picture if it exists. */
    private function deleteOldProfilePicture(User $user): void
    {
        $oldPicture = $user->getProfilePicture();
        if (null !== $oldPicture) {
            try {
                // Add exists check to avoid unnecessary delete attempts
                if ($this->profilePicturesFilesystem->fileExists($oldPicture)) {
                    $this->profilePicturesFilesystem->delete($oldPicture);
                }
            } catch (\Exception $e) {
                $this->logger->warning('Failed to delete old profile picture: '.$e->getMessage());
            }
        }
    }

    /** Delete a profile picture file. */
    public function deleteProfilePicture(string $filename): void
    {
        try {
            if ($this->profilePicturesFilesystem->fileExists($filename)) {
                $this->profilePicturesFilesystem->delete($filename);
            }
        } catch (\Exception $e) {
            $this->logger->warning('Failed to delete profile picture', [
                'filename' => $filename,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Generate a unique filename for the profile picture. */
    private function generateFilename(int|string $userId, string $extension): string
    {
        return \sprintf('pp_%s_%s.%s', $userId, uniqid(), $extension);
    }

    /** Handle errors and log exceptions. */
    private function handleError(string $message, \Exception $e): null
    {
        // Add exception context to log
        $this->logger->error($message, [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return null;
    }
}
