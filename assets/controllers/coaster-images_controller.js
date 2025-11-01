import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['container', 'showAllButton'];
    static values = { 
        slug: String,
        locale: String,
        totalImages: Number
    };

    connect() {
        // Load initial images when controller connects
        // Add a small delay to ensure Routing is available
        setTimeout(() => {
            this.loadImages();
        }, 100);
    }

    loadImages(imageNumber = null) {
        // Determine number of images based on screen size
        if (!imageNumber) {
            const isMobile = Math.max(document.documentElement.clientWidth, window.innerWidth || 0) < 769;
            imageNumber = isMobile ? 2 : 8;
        }

        // Make AJAX request to load images
        fetch(this.#buildImageUrl(imageNumber), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(html => {
                // Replace container content
                this.containerTarget.outerHTML = html;
                
                // Re-initialize PhotoSwipe for new images
                this.#initializePhotoSwipe();
                
                // Re-attach show all button handler if it exists
                const newShowAllButton = document.getElementById('show-all');
                if (newShowAllButton) {
                    newShowAllButton.addEventListener('click', () => {
                        this.showAllImages();
                    });
                }
            })
            .catch(error => {
                console.error('Error loading images:', error);
                // Hide photos section if it fails to load
                this.containerTarget.style.display = 'none';
            });
    }

    showAllImages() {
        this.loadImages(this.totalImagesValue);
    }

    #buildImageUrl(imageNumber) {
        // Use Symfony's exposed routing (requires FOSJsRoutingBundle)
        if (typeof Routing !== 'undefined' && Routing.generate) {
            try {
                const routingUrl = Routing.generate('coaster_images_ajax_load', {
                    'slug': this.slugValue,
                    'imageNumber': imageNumber,
                    '_locale': this.localeValue,
                });
                console.log('Using Routing.generate URL:', routingUrl);
                return routingUrl;
            } catch (error) {
                console.warn('Routing.generate failed, using fallback URL:', error);
            }
        }
        
        // Fallback to manual URL construction
        const baseUrl = window.location.origin;
        const locale = this.localeValue;
        const slug = this.slugValue;
        
        const fallbackUrl = `${baseUrl}/${locale}/coasters/${slug}/images/ajax/${imageNumber}`;
        console.log('Using fallback URL construction:', fallbackUrl);
        return fallbackUrl;
    }

    #initializePhotoSwipe() {
        // Initialize modern PhotoSwipe for lightbox images
        if (window.PhotoSwipeLightbox) {
            const lightbox = new window.PhotoSwipeLightbox({
                gallery: '[data-gallery="coaster-images"]',
                children: 'a',
                pswpModule: window.PhotoSwipe,
                // Modern PhotoSwipe options
                showHideAnimationType: 'fade',
                bgOpacity: 0.9,
                spacing: 0.1,
                allowPanToNext: true,
                zoom: true,
                close: true,
                arrowKeys: true,
                returnFocus: true,
                trapFocus: true,
                clickToCloseNonZoomable: true
            });

            // Add event listener to dynamically load image dimensions
            lightbox.on('uiRegister', () => {
                lightbox.on('contentLoad', (e) => {
                    const { content } = e;
                    
                    if (content.type === 'image') {
                        // Create a new image to get actual dimensions
                        const img = new Image();
                        img.onload = () => {
                            // Update content with actual dimensions
                            content.width = img.naturalWidth;
                            content.height = img.naturalHeight;
                            
                            // Trigger PhotoSwipe to update layout
                            if (lightbox.pswp) {
                                lightbox.pswp.updateSize();
                            }
                        };
                        img.src = content.data.src;
                    }
                });
            });
            
            lightbox.init();
            
            // Store reference for cleanup
            this.lightbox = lightbox;
        }
    }
}