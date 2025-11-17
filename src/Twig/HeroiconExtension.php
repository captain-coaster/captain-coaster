<?php

declare(strict_types=1);

namespace App\Twig;

use Symfony\UX\Icons\IconRendererInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class HeroiconExtension extends AbstractExtension
{
    public function __construct(
        private readonly IconRendererInterface $iconRenderer
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('heroicon', [$this, 'render'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Render icon using Symfony UX Icons with Heroicons
     * Maintains backward compatibility with existing heroicon() calls
     */
    public function render(string $name, string $class = '', string $variant = 'outline'): string
    {
        // Use heroicons prefix for UX Icons on-demand loading
        $iconName = "heroicons:{$name}";
        $attributes = [];
        
        if ($class) {
            $attributes['class'] = $class;
        }
        
        // Use UX Icons renderer
        return $this->iconRenderer->renderIcon($iconName, $attributes);
    }
}
