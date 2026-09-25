import { Controller } from '@hotwired/stimulus';

/**
 * Page:Header: once the title row scrolls under the top edge, the compact
 * bar fades in with the back button, title and actions.
 */
export default class extends Controller {
    static targets = ['bar', 'row'];

    connect() {
        if (!this.hasBarTarget) return;
        this.observer = new IntersectionObserver(
            ([entry]) => {
                const under =
                    !entry.isIntersecting && entry.boundingClientRect.top < 0;
                this.barTarget.toggleAttribute('data-scrolled', under);
            },
            { rootMargin: `-${this.barTarget.offsetHeight}px 0px 0px 0px` }
        );
        this.observer.observe(this.rowTarget);
    }

    disconnect() {
        this.observer?.disconnect();
    }
}
