import { Controller } from '@hotwired/stimulus';

// The page comes with the first photos, a phone showing only two of them.
// "Show all" reveals the hidden ones, then fetches only those the page lacks.
export default class extends Controller {
    static targets = ['grid', 'more'];
    static values = {
        slug: String,
        locale: String,
        totalImages: Number,
    };

    showAll(event) {
        const button = event.currentTarget;
        if (button.disabled) {
            return;
        }

        this.gridTarget
            .querySelectorAll('[data-phone-hidden]')
            .forEach((tile) => {
                tile.classList.remove('max-md:hidden');
                tile.removeAttribute('data-phone-hidden');
            });

        const shown = this.gridTarget.querySelectorAll(
            '[data-gallery-target="link"]'
        ).length;
        if (shown >= this.totalImagesValue) {
            this.moreTarget.remove();
            return;
        }

        button.disabled = true;
        fetch(this.buildUrl(shown), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.text();
            })
            .then((html) => {
                this.gridTarget.insertAdjacentHTML('beforeend', html);
                this.moreTarget.remove();
            })
            .catch((error) => {
                // The photos already shown stay; the button can be tried again.
                console.error('Error loading images:', error);
                button.disabled = false;
            });
    }

    buildUrl(offset) {
        if (typeof Routing !== 'undefined' && Routing.generate) {
            try {
                return Routing.generate('coaster_images_ajax_load', {
                    slug: this.slugValue,
                    offset: offset,
                    _locale: this.localeValue,
                });
            } catch (error) {
                console.warn('Routing failed:', error);
            }
        }

        return `${window.location.origin}/${this.localeValue}/coasters/${this.slugValue}/images/ajax/${offset}`;
    }
}
