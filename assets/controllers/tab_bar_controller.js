import { Controller } from '@hotwired/stimulus';

/**
 * Nav:TabBar: compact (labels hidden) while scrolling down, full again on
 * scroll up or near the top.
 */
export default class extends Controller {
    connect() {
        this.lastY = window.scrollY;
        this.onScroll = this.onScroll.bind(this);
        window.addEventListener('scroll', this.onScroll, { passive: true });
    }

    disconnect() {
        window.removeEventListener('scroll', this.onScroll);
    }

    onScroll() {
        const y = Math.max(0, window.scrollY);
        if (Math.abs(y - this.lastY) < 8) {
            return;
        }
        this.element.toggleAttribute('data-compact', y > this.lastY && y > 64);
        this.lastY = y;
    }
}
