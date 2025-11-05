import { Controller } from "@hotwired/stimulus";

/**
 * Generic PhotoSwipe gallery controller
 * 
 * Usage:
 * <div data-controller="gallery" 
 *      data-gallery-name-value="coaster-images">
 *   <a href="large-image.jpg">
 *     <img src="thumbnail.jpg" alt="Image">
 *   </a>
 * </div>
 */
export default class extends Controller {
    static values = { 
        name: String, // Gallery name (e.g., "coaster-images", "user-images")
        showHideAnimation: { type: String, default: "fade" },
        bgOpacity: { type: Number, default: 0.9 }
    };

    connect() {
        this.initializePhotoSwipe();
        
        // Listen for refresh events (e.g., after AJAX content loads)
        this.element.addEventListener('gallery:refresh', () => {
            this.refresh();
        });
    }

    async initializePhotoSwipe() {
        try {
            // Dynamically import PhotoSwipe
            const [PhotoSwipeLightbox, PhotoSwipe] = await Promise.all([
                import(/* webpackChunkName: "photoswipe" */ "photoswipe/lightbox"),
                import(/* webpackChunkName: "photoswipe" */ "photoswipe"),
                import(/* webpackChunkName: "photoswipe" */ "photoswipe/style.css")
            ]);

            // Store globally for legacy compatibility
            window.PhotoSwipeLightbox = PhotoSwipeLightbox.default;
            window.PhotoSwipe = PhotoSwipe.default;

            // Initialize PhotoSwipe for this gallery
            const gallerySelector = `[data-gallery="${this.nameValue}"]`;
            
            const lightbox = new PhotoSwipeLightbox.default({
                gallery: gallerySelector,
                children: 'a',
                pswpModule: PhotoSwipe.default,
                
                // PhotoSwipe options
                showHideAnimationType: this.showHideAnimationValue,
                bgOpacity: this.bgOpacityValue,
                spacing: 0.1,
                allowPanToNext: true,
                zoom: true,
                close: true,
                arrowKeys: true,
                returnFocus: true,
                trapFocus: true,
                clickToCloseNonZoomable: true
            });

            // Handle dynamic image dimensions
            lightbox.on('contentLoad', (e) => {
                const { content } = e;
                
                if (content.type === 'image') {
                    const img = new Image();
                    img.onload = () => {
                        content.width = img.naturalWidth;
                        content.height = img.naturalHeight;
                        
                        // Update PhotoSwipe layout
                        if (lightbox.pswp) {
                            lightbox.pswp.updateSize();
                        }
                    };
                    img.src = content.data.src;
                }
            });
            
            lightbox.init();
            
            // Store reference for cleanup
            this.lightbox = lightbox;

            console.log(`PhotoSwipe initialized for gallery: ${this.nameValue}`);

        } catch (error) {
            console.error('Failed to load PhotoSwipe:', error);
        }
    }

    disconnect() {
        // Clean up PhotoSwipe when controller is removed
        if (this.lightbox) {
            this.lightbox.destroy();
        }
    }

    // Action to refresh gallery (useful after dynamic content changes)
    refresh() {
        if (this.lightbox) {
            this.lightbox.destroy();
            this.initializePhotoSwipe();
        }
    }
}