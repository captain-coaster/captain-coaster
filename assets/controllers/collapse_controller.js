import { Controller } from '@hotwired/stimulus';

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
        
        if (content.style.display === 'none') {
            // Show content
            content.style.display = 'block';
            button.setAttribute('aria-expanded', 'true');
            
            // Rotate icon
            if (icon) {
                icon.style.transform = 'rotate(180deg)';
            }
        } else {
            // Hide content
            content.style.display = 'none';
            button.setAttribute('aria-expanded', 'false');
            
            // Reset icon
            if (icon) {
                icon.style.transform = 'rotate(0deg)';
            }
        }
    }
}
