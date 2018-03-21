<?php

namespace BddBundle\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Class ImageService
 * @package BddBundle\Service
 */
class ImageService
{
    protected $basePath;
    protected $baseUrl;

    /**
     * ImageService constructor.
     * @param string $basePath
     * @param string $baseUrl
     */
    public function __construct(string $basePath, string $baseUrl)
    {
        $this->basePath = $basePath;
        $this->baseUrl = $baseUrl;
    }

    /**
     * Find image files on disk, and return array of URLs to display them.
     *
     * @param int $coasterId
     * @return array
     */
    public function getCoasterImagesUrl(int $coasterId): array
    {
        $urls = [];

        // Absolute path is base_path + base_url
        // Symlink from web to photo folder
        $absolutePath = $this->basePath.str_replace(['{coasterId}', '{fileName}'], [$coasterId, ''], $this->baseUrl);

        if (!$this->isDirectory($absolutePath)) {
            return $urls;
        }

        // Find image files on disk
        $finder = new Finder();
        $finder
            ->files()
            ->in($absolutePath)
            ->name('*.jpg');

        // Generate URLs
        foreach ($finder as $file) {
            $urls[] = str_replace(
                ['{coasterId}', '{fileName}'],
                [$coasterId, $file->getFilename()],
                $this->baseUrl
            );
        }

        return $urls;
    }

    /**
     * Return image paths for multiple coaster ids
     *
     * @param array $ids
     * @return array
     */
    public function getMultipleImagesUrl(array $ids): array
    {
        $urls = [];
        foreach ($ids as $id) {
            $urls[$id] = $this->getCoasterImagesUrl($id);
        }

        return $urls;
    }

    /**
     * @param string $path
     * @return bool
     */
    private function isDirectory(string $path): bool
    {
        $fs = new Filesystem();

        return $fs->exists($path);
    }
}
