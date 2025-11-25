import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['content'];

    connect() {
        this.tooltipListeners();
    }

    tooltipListeners() {
        this.element.addEventListener('mouseenter', () => {
            this.contentTarget.style.display = 'block';
        });

        this.element.addEventListener('mouseleave', () => {
            this.contentTarget.style.display = 'none';
        });
    }
}
