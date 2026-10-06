import { Controller } from '@hotwired/stimulus';

/**
 * "Load more" on the ranking: fetches the next page's results, appends its rows
 * to the list and swaps in its pager. The link stays a real ?page=N URL, which
 * replaces the current one so a refresh or back lands on the same rows.
 */
export default class extends Controller {
    static values = { endpoint: String };

    async more(event) {
        event.preventDefault();
        const link = event.currentTarget;
        if (link.ariaBusy === 'true') return;
        link.ariaBusy = 'true';

        const url = new URL(link.href);
        try {
            const response = await fetch(this.endpointValue + url.search, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) throw new Error(response.statusText);

            const page = new DOMParser().parseFromString(
                await response.text(),
                'text/html'
            );
            const list = this.element.parentElement.querySelector(
                '[data-ranking-rows]'
            );
            // Not the column headings: the list already has them.
            const rows = [...page.querySelectorAll('[data-ranking-rows] > li:not([aria-hidden])')];
            list.append(...rows);

            if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                rows.forEach((row) =>
                    row.animate([{ opacity: 0 }, { opacity: 1 }], {
                        duration: 180,
                        easing: 'cubic-bezier(.16, 1, .3, 1)',
                    })
                );
            }

            history.replaceState(history.state, '', url.pathname + url.search);
            rows[0]?.querySelector('a')?.focus({ preventScroll: true });

            const pager = page.querySelector('nav[data-controller="ranking-pager"]');
            if (pager) {
                this.element.replaceWith(pager);
            } else {
                this.element.remove();
            }
        } catch {
            // Plain navigation still works
            window.location.assign(link.href);
        }
    }
}
