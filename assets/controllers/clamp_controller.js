import { Controller } from '@hotwired/stimulus';

/**
 * A text cut after a few lines (line-clamp-*) that opens in place: tapping the
 * text or its chevron button shows it whole, again cuts it back. This only
 * measures whether the text is cut (again whenever its box changes: rotation,
 * fonts) and flips the button's aria-expanded; the markup derives the clamp
 * and the pointer from the button's state.
 */
export default class extends Controller {
    static targets = ['text', 'toggle'];

    connect() {
        this.observer = new ResizeObserver(() => this.measure());
        this.observer.observe(this.textTarget);
    }

    disconnect() {
        this.observer.disconnect();
    }

    measure() {
        // An open text has nothing to compare with: keep its button.
        if (this.toggleTarget.ariaExpanded === 'true') return;
        this.toggleTarget.hidden =
            this.textTarget.scrollHeight <= this.textTarget.clientHeight + 1;
    }

    toggle(event) {
        if (this.toggleTarget.hidden) return;
        // Selecting text in the review is not a tap.
        if (event.currentTarget === this.textTarget && window.getSelection()?.toString()) return;

        this.toggleTarget.ariaExpanded = String(this.toggleTarget.ariaExpanded !== 'true');
    }
}
