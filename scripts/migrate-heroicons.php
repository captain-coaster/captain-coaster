#!/usr/bin/env php
<?php

/**
 * Migration script to convert heroicon() calls to ux_icon() calls
 * Usage: php scripts/migrate-heroicons.php
 */

$templatesDir = __DIR__ . '/../templates';

function migrateFile(string $filePath): void
{
    $content = file_get_contents($filePath);
    $originalContent = $content;
    
    // Pattern to match heroicon() calls with 2 or 3 parameters
    $pattern = '/heroicon\(\s*\'([^\']+)\'\s*,\s*\'([^\']*)\'\s*(?:,\s*\'([^\']*)\')?\s*\)/';
    
    $content = preg_replace_callback($pattern, function ($matches) {
        $iconName = $matches[1];
        $classes = $matches[2];
        $variant = $matches[3] ?? 'outline'; // Default to outline if not specified
        
        // Build attributes array
        $attributes = [];
        
        if (!empty($classes)) {
            $attributes[] = "'class': '{$classes}'";
        }
        
        // Convert heroicons name to ux_icon format
        $uxIconName = "heroicons:{$iconName}";
        if ($variant === 'solid') {
            $uxIconName = "heroicons-solid:{$iconName}";
        }
        
        // Build the new ux_icon call
        if (empty($attributes)) {
            return "ux_icon('{$uxIconName}')";
        } else {
            $attributesStr = '{' . implode(', ', $attributes) . '}';
            return "ux_icon('{$uxIconName}', {$attributesStr})";
        }
    }, $content);
    
    // Only write if content changed
    if ($content !== $originalContent) {
        file_put_contents($filePath, $content);
        echo "✓ Migrated: " . basename($filePath) . "\n";
        return;
    }
    
    echo "- No changes: " . basename($filePath) . "\n";
}

function findTwigFiles(string $dir): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'twig') {
            $files[] = $file->getPathname();
        }
    }
    
    return $files;
}

echo "🚀 Starting Heroicons to UX Icons migration...\n\n";

$twigFiles = findTwigFiles($templatesDir);
$migratedCount = 0;

foreach ($twigFiles as $file) {
    // Check if file contains heroicon calls
    $content = file_get_contents($file);
    if (strpos($content, 'heroicon(') !== false) {
        migrateFile($file);
        $migratedCount++;
    }
}

echo "\n✅ Migration completed!\n";
echo "📊 Files processed: " . count($twigFiles) . "\n";
echo "🔄 Files with heroicon() calls: {$migratedCount}\n\n";

echo "📝 Next steps:\n";
echo "1. Remove the HeroiconExtension class: src/Twig/HeroiconExtension.php\n";
echo "2. Update services.yaml to remove the extension registration\n";
echo "3. Test your templates to ensure icons render correctly\n";