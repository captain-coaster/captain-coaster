import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['container'];
    static values = {
        slug: String,
        locale: String,
        totalImages: Number,
    };

    connect() {
        this.loadImages();
    }

    loadImages(imageNumber = null) {
        const count = imageNumber || this.getImageCount();

        fetch(this.buildUrl(count), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => response.text())
            .then((html) => {
                this.containerTarget.innerHTML = html;
                this.attachShowAllHandler();
            })
            .catch((error) => {
                console.error('Error loading images:', error);
                this.containerTarget.style.display = 'none';
            });
    }

    showAllImages() {
        this.loadImages(this.totalImagesValue);
    }

    getImageCount() {
        const isMobile = window.innerWidth < 768;
        return isMobile ? 2 : 8;
    }

    buildUrl(imageNumber) {
        if (typeof Routing !== 'undefined' && Routing.generate) {
            try {
                return Routing.generate('coaster_images_ajax_load', {
                    slug: this.slugValue,
                    imageNumber: imageNumber,
                    _locale: this.localeValue,
                });
            } catch (error) {
                console.warn('Routing failed:', error);
            }
        }

        return `${window.location.origin}/${this.localeValue}/coasters/${this.slugValue}/images/ajax/${imageNumber}`;
    }

    attachShowAllHandler() {
        const showAllButton = document.getElementById('show-all');
        if (showAllButton) {
            showAllButton.addEventListener('click', () => {
                this.showAllImages();
            });
        }
    }
}
