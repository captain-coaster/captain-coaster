import { Controller } from '@hotwired/stimulus';

/**
 * Toggle Switch Controller
 * Handles the visual state of toggle switches based on checkbox state
 *
 * Usage:
 *   <div data-controller="toggle-switch" class="toggle-switch-form-group">
 *     <input type="checkbox" data-toggle-switch-target="checkbox" data-action="toggle-switch#toggle">
 *   </div>
 */
export default class extends Controller {
    static targets = ['checkbox'];

    connect() {
        this.updateState();
    }

    toggle() {
        this.updateState();
    }

    updateState() {
        if (this.checkboxTarget.checked) {
            this.element.classList.add('checked');
        } else {
            this.element.classList.remove('checked');
        }
    }

    checkboxTargetConnected() {
        // Set initial state when checkbox is connected
        this.updateState();

        // Handle focus/blur
        this.checkboxTarget.addEventListener('focus', () => {
            this.element.classList.add('focused');
        });

        this.checkboxTarget.addEventListener('blur', () => {
            this.element.classList.remove('focused');
        });
    }
}
