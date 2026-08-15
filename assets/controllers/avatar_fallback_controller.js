import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['image', 'fallback'];

    connect() {
        // The image starts loading as soon as the HTML parser sees it, before
        // Stimulus connects — if it already failed, the error event fired too
        // early for data-action to catch it, so check the loaded state here too.
        if (this.imageTarget.complete && this.imageTarget.naturalWidth === 0) {
            this.error();
        }
    }

    error() {
        this.imageTarget.classList.add('hidden');
        this.fallbackTarget.classList.remove('hidden');
    }
}
