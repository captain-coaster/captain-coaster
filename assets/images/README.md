# Image Asset Optimization

This directory contains optimized images for the Captain Coaster application. The build system automatically processes these images for optimal web delivery.

## Features

- **Automatic Optimization**: Images are compressed and optimized during the build process
- **WebP Generation**: Modern WebP format is generated automatically with fallbacks
- **Asset Hashing**: Images get unique hashes for cache busting
- **Lazy Loading**: Built-in lazy loading support for better performance
- **Responsive Images**: Utilities for creating responsive image sets

## Directory Structure

```
assets/images/
├── backgrounds/     # Background images and textures
├── brands/         # Brand logos and icons
├── login_cover.jpg # Login page hero image
└── README.md       # This file
```

## Usage in Templates

### Basic Image with Optimization

```twig
{# Standard image tag - will be processed and optimized #}
<img src="{{ asset('build/images/login_cover.jpg') }}" alt="Login cover">
```

### Responsive Image with WebP Support

```twig
{# Using the responsive image Stimulus controller #}
<div data-controller="responsive-image" 
     data-responsive-image-base-path-value="{{ asset('build/images/hero') }}"
     data-responsive-image-fallback-ext-value="jpg"
     data-responsive-image-alt-value="Hero image">
</div>
```

### Lazy Loading

```twig
{# Lazy loaded image #}
<img data-controller="responsive-image"
     data-responsive-image-lazy-value="true"
     data-src="{{ asset('build/images/photo.jpg') }}"
     alt="Photo"
     class="lazy">
```

### Picture Element with WebP

```twig
<picture>
    <source srcset="{{ asset('build/images/hero.webp') }}" type="image/webp">
    <img src="{{ asset('build/images/hero.jpg') }}" alt="Hero image">
</picture>
```

## JavaScript Usage

### Import Image Utilities

```javascript
import { getOptimalImageSrc, createResponsiveImage, setupLazyLoading } from '@js/modules/image-utils';

// Get optimal image source based on browser support
const imageSrc = await getOptimalImageSrc('/build/images/photo', 'jpg');

// Create responsive image element
const picture = createResponsiveImage('/build/images/hero', 'Hero image', {
    fallbackExt: 'jpg',
    className: 'hero-image',
    sizes: '(max-width: 768px) 100vw, 50vw'
});

// Setup lazy loading for existing images
setupLazyLoading('img[data-src]');
```

## Build Process

### Development
- Images are copied to `public/build/images/` with hash-based filenames
- Source maps are preserved for debugging
- No optimization applied for faster builds

### Production
- Images are compressed and optimized using Sharp
- WebP versions are generated automatically
- Asset hashing is applied for cache busting
- Unused images can be identified and removed

## Optimization Settings

The image optimization is configured in `webpack/image-optimization-plugin.js`:

- **JPEG Quality**: 85% (good balance of quality/size)
- **PNG Quality**: 60-80% range with adaptive compression
- **WebP Quality**: 80% (excellent compression with good quality)
- **Small Image Inlining**: Images under 8KB are inlined as data URLs

## Best Practices

### Image Formats
- Use **JPEG** for photos and complex images
- Use **PNG** for images with transparency or simple graphics
- Use **SVG** for icons and simple illustrations
- **WebP** is generated automatically - no need to create manually

### File Naming
- Use descriptive names: `hero-image.jpg` instead of `img1.jpg`
- Use kebab-case: `login-background.png`
- Include size hints for different versions: `logo-small.png`, `logo-large.png`

### Performance
- Keep source images reasonably sized (max 2MB for web)
- Use appropriate dimensions - don't rely on CSS to resize large images
- Consider using lazy loading for images below the fold
- Preload critical images that appear above the fold

### Accessibility
- Always provide meaningful alt text
- Use empty alt="" for decorative images
- Consider providing image descriptions for complex images

## Troubleshooting

### Images Not Loading
1. Check that the image exists in `assets/images/`
2. Verify the asset path in templates uses `asset('build/images/...')`
3. Run `npm run build` to ensure images are processed
4. Check browser console for 404 errors

### WebP Not Working
1. Verify browser supports WebP (Chrome, Firefox, Safari 14+)
2. Check that WebP files are generated in `public/build/images/`
3. Ensure the responsive image controller is properly loaded

### Large Bundle Sizes
1. Remove unused images from `assets/images/`
2. Optimize source images before adding them
3. Use appropriate image formats (JPEG for photos, PNG for graphics)
4. Consider using CSS sprites for small icons

## File Size Guidelines

- **Hero images**: < 200KB optimized
- **Thumbnails**: < 50KB optimized  
- **Icons**: < 10KB (consider SVG or CSS sprites)
- **Background patterns**: < 20KB optimized

The build system will automatically optimize images, but starting with reasonably sized source images will give better results.