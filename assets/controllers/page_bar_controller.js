import { Controller } from '@hotwired/stimulus';

/**
 * Page:Header: once the large title scrolls under the compact bar, the bar
 * gets its translucent background and shows the title.
 */
export default class extends Controller {
    static targets = ['bar', 'title'];

    connect() {
        this.observer = new IntersectionObserver(
            ([entry]) => {
                const under =
                    !entry.isIntersecting && entry.boundingClientRect.top < 0;
                this.barTarget.toggleAttribute('data-scrolled', under);
            },
            { rootMargin: `-${this.barTarget.offsetHeight}px 0px 0px 0px` }
        );
        this.observer.observe(this.titleTarget);
    }

    disconnect() {
        this.observer?.disconnect();
    }
}
