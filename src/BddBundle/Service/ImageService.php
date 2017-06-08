<?php

namespace BddBundle\Service;

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

        // Find image files on disk
        $finder = new Finder();
        $finder
            ->files()
            ->in($this->basePath.'/'.$coasterId)
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
}