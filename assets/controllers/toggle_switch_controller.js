import { Controller } from '@hotwired/stimulus';

/**
 * Toggle Switch Controller - Modern replacement for Switchery
 * Converts checkboxes to toggle switches with clean styling
 */
export default class extends Controller {
    static targets = ['checkbox'];

    connect() {
        this.initializeToggle();
    }

    initializeToggle() {
        const checkbox = this.element.querySelector('input[type="checkbox"]');
        if (!checkbox || checkbox.dataset.toggleInit) return;
        
        checkbox.dataset.toggleInit = 'true';

        const toggle = document.createElement('span');
        toggle.className = 'toggle-switch' + (checkbox.checked ? ' on' : '');
        
        // Insert toggle into the label (for right positioning)
        const label = checkbox.closest('label');
        if (label) {
            label.appendChild(toggle);
        } else {
            checkbox.parentNode.insertBefore(toggle, checkbox.nextSibling);
        }

        toggle.addEventListener('click', (e) => {
            e.preventDefault();
            
            // Add click animation
            toggle.style.transform = 'scale(0.95)';
            setTimeout(() => {
                toggle.style.transform = '';
            }, 150);
            
            checkbox.checked = !checkbox.checked;
            toggle.classList.toggle('on');
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }
}