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
                // Replace container content (use innerHTML to preserve the container element)
                this.containerTarget.innerHTML = html;
                
                // Re-initialize PhotoSwipe for new images
                this.#refreshPhotoSwipe();
                
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

    #refreshPhotoSwipe() {
        // Find the gallery controller and refresh it after AJAX content is loaded
        setTimeout(() => {
            const galleryElement = document.querySelector('[data-gallery="coaster-images"]');
            if (galleryElement) {
                // Trigger a custom event that the gallery controller can listen to
                galleryElement.dispatchEvent(new CustomEvent('gallery:refresh'));
            }
        }, 100);
    }
}