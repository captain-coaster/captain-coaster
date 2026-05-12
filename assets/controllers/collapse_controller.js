import { Controller } from '@hotwired/stimulus';
import { show, hide } from '../js/utils/dom.js';

/**
 * Simple Collapse Controller
 *
 * Toggles visibility of content with smooth animation.
 * Used for collapsible help sections and expandable content.
 */
export default class extends Controller {
    static targets = ['content', 'icon'];

    toggle(event) {
        event.preventDefault();

        const content = this.contentTarget;
        const icon = this.hasIconTarget ? this.iconTarget : null;
        const button = event.currentTarget;

        if (content.classList.contains('hidden')) {
            // Show content
            show(content);
            button.setAttribute('aria-expanded', 'true');

            // Rotate icon
            if (icon) {
                icon.classList.add('rotate-180');
            }
        } else {
            // Hide content
            hide(content);
            button.setAttribute('aria-expanded', 'false');

            // Reset icon
            if (icon) {
                icon.classList.remove('rotate-180');
            }
        }
    }
}
