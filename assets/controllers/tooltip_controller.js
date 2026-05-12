import { Controller } from '@hotwired/stimulus';
import { show, hide } from '../js/utils/dom.js';

export default class extends Controller {
    static targets = ['content'];

    connect() {
        this.tooltipListeners();
    }

    tooltipListeners() {
        this.element.addEventListener('mouseenter', () => {
            show(this.contentTarget);
        });

        this.element.addEventListener('mouseleave', () => {
            hide(this.contentTarget);
        });
    }
}
