<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class HeroiconExtension extends AbstractExtension
{
    private string $iconsPath;
    private array $cache = [];

    public function __construct(string $projectDir)
    {
        $this->iconsPath = $projectDir . '/node_modules/heroicons/24';
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('heroicon', [$this, 'render'], ['is_safe' => ['html']]),
        ];
    }

    public function render(string $name, string $class = '', string $variant = 'outline'): string
    {
        $cacheKey = "$variant/$name";

        // Use cache in production
        if (isset($this->cache[$cacheKey])) {
            return $this->addClasses($this->cache[$cacheKey], $class);
        }

        $svgPath = "{$this->iconsPath}/{$variant}/{$name}.svg";

        if (!file_exists($svgPath)) {
            return "<!-- Icon not found: {$name} -->";
        }

        $svg = file_get_contents($svgPath);
        $this->cache[$cacheKey] = $svg;

        return $this->addClasses($svg, $class);
    }

    private function addClasses(string $svg, string $class): string
    {
        if (empty($class)) {
            return $svg;
        }

        return preg_replace('/<svg/', "<svg class=\"{$class}\"", $svg, 1);
    }
}
