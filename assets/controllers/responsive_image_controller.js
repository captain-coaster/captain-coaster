import { Controller } from "@hotwired/stimulus";
import { getOptimalImageSrc, createResponsiveImage, setupLazyLoading } from "../js/modules/image-utils";

/**
 * Stimulus controller for handling responsive images with WebP support
 * 
 * Usage:
 * <div data-controller="responsive-image" 
 *      data-responsive-image-base-path-value="/build/images/hero"
 *      data-responsive-image-fallback-ext-value="jpg"
 *      data-responsive-image-alt-value="Hero image">
 * </div>
 * 
 * Or for lazy loading:
 * <img data-controller="responsive-image" 
 *      data-responsive-image-lazy-value="true"
 *      data-src="/build/images/photo.jpg" 
 *      alt="Photo">
 */
export default class extends Controller {
    static values = {
        basePath: String,
        fallbackExt: { type: String, default: "jpg" },
        alt: String,
        lazy: { type: Boolean, default: false },
        className: String,
        sizes: String
    };

    connect() {
        if (this.lazyValue) {
            this.setupLazyLoading();
        } else if (this.basePathValue) {
            this.loadResponsiveImage();
        }
    }

    async loadResponsiveImage() {
        try {
            // If element is already an img tag, update its src
            if (this.element.tagName === 'IMG') {
                const optimalSrc = await getOptimalImageSrc(this.basePathValue, this.fallbackExtValue);
                this.element.src = optimalSrc;
                if (this.altValue) this.element.alt = this.altValue;
                if (this.classNameValue) this.element.className = this.classNameValue;
            } else {
                // Create a picture element with WebP support
                const picture = createResponsiveImage(this.basePathValue, this.altValue, {
                    fallbackExt: this.fallbackExtValue,
                    className: this.classNameValue,
                    sizes: this.sizesValue
                });
                
                // Replace the controller element with the picture
                this.element.parentNode.replaceChild(picture, this.element);
            }
        } catch (error) {
            console.warn('Failed to load responsive image:', error);
            // Fallback to original behavior
            if (this.element.tagName === 'IMG') {
                this.element.src = `${this.basePathValue}.${this.fallbackExtValue}`;
            }
        }
    }

    setupLazyLoading() {
        // Set up lazy loading for this specific element
        if (this.element.tagName === 'IMG' && this.element.dataset.src) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        this.loadImageWithWebPSupport(img);
                        observer.unobserve(img);
                    }
                });
            }, {
                rootMargin: '50px 0px',
                threshold: 0.01
            });

            observer.observe(this.element);
        }
    }

    async loadImageWithWebPSupport(img) {
        const originalSrc = img.dataset.src;
        
        try {
            // Try to determine if there's a WebP version available
            const basePath = originalSrc.replace(/\.[^.]+$/, '');
            const optimalSrc = await getOptimalImageSrc(basePath, this.fallbackExtValue);
            
            img.src = optimalSrc;
            img.removeAttribute('data-src');
            img.classList.remove('lazy');
        } catch (error) {
            // Fallback to original src
            img.src = originalSrc;
            img.removeAttribute('data-src');
            img.classList.remove('lazy');
        }
    }

    // Action to manually trigger image loading
    load() {
        if (this.basePathValue) {
            this.loadResponsiveImage();
        }
    }
}