/**
 * Image utilities for handling optimized images with WebP support
 */

/**
 * Check if the browser supports WebP format
 * @returns {boolean}
 */
export function supportsWebP() {
    if (typeof window === 'undefined') return false;
    
    // Check if we've already determined WebP support
    if (window._webpSupport !== undefined) {
        return window._webpSupport;
    }

    // Create a test WebP image
    const webpTestImage = 'data:image/webp;base64,UklGRjoAAABXRUJQVlA4IC4AAACyAgCdASoCAAIALmk0mk0iIiIiIgBoSygABc6WWgAA/veff/0PP8bA//LwYAAA';
    
    return new Promise((resolve) => {
        const img = new Image();
        img.onload = img.onerror = () => {
            window._webpSupport = img.height === 2;
            resolve(window._webpSupport);
        };
        img.src = webpTestImage;
    });
}

/**
 * Get the optimal image source based on browser support
 * @param {string} imagePath - Base image path without extension
 * @param {string} fallbackExt - Fallback extension (jpg, png, etc.)
 * @returns {Promise<string>} - Optimal image source
 */
export async function getOptimalImageSrc(imagePath, fallbackExt = 'jpg') {
    const webpSupported = await supportsWebP();
    
    if (webpSupported) {
        // Try WebP first
        const webpSrc = `${imagePath}.webp`;
        
        // Check if WebP version exists
        return new Promise((resolve) => {
            const img = new Image();
            img.onload = () => resolve(webpSrc);
            img.onerror = () => resolve(`${imagePath}.${fallbackExt}`);
            img.src = webpSrc;
        });
    }
    
    return `${imagePath}.${fallbackExt}`;
}

/**
 * Create a picture element with WebP support and fallbacks
 * @param {string} basePath - Base image path without extension
 * @param {string} alt - Alt text for the image
 * @param {Object} options - Additional options
 * @returns {HTMLPictureElement}
 */
export function createResponsiveImage(basePath, alt, options = {}) {
    const {
        fallbackExt = 'jpg',
        className = '',
        loading = 'lazy',
        sizes = '100vw'
    } = options;

    const picture = document.createElement('picture');
    
    // WebP source
    const webpSource = document.createElement('source');
    webpSource.srcset = `${basePath}.webp`;
    webpSource.type = 'image/webp';
    if (sizes) webpSource.sizes = sizes;
    picture.appendChild(webpSource);
    
    // Fallback image
    const img = document.createElement('img');
    img.src = `${basePath}.${fallbackExt}`;
    img.alt = alt;
    img.loading = loading;
    if (className) img.className = className;
    if (sizes) img.sizes = sizes;
    picture.appendChild(img);
    
    return picture;
}

/**
 * Preload critical images with WebP support
 * @param {Array<string>} imagePaths - Array of image paths to preload
 */
export async function preloadImages(imagePaths) {
    const webpSupported = await supportsWebP();
    
    imagePaths.forEach(imagePath => {
        const link = document.createElement('link');
        link.rel = 'preload';
        link.as = 'image';
        
        if (webpSupported && imagePath.includes('.')) {
            // Replace extension with .webp
            const webpPath = imagePath.replace(/\.[^.]+$/, '.webp');
            link.href = webpPath;
        } else {
            link.href = imagePath;
        }
        
        document.head.appendChild(link);
    });
}

/**
 * Lazy load images with intersection observer
 * @param {string} selector - CSS selector for images to lazy load
 */
export function setupLazyLoading(selector = 'img[data-src]') {
    if (!('IntersectionObserver' in window)) {
        // Fallback for browsers without IntersectionObserver
        document.querySelectorAll(selector).forEach(img => {
            img.src = img.dataset.src;
            img.removeAttribute('data-src');
        });
        return;
    }

    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
                img.classList.remove('lazy');
                observer.unobserve(img);
            }
        });
    }, {
        rootMargin: '50px 0px',
        threshold: 0.01
    });

    document.querySelectorAll(selector).forEach(img => {
        imageObserver.observe(img);
    });
}