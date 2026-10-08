import { Controller } from '@hotwired/stimulus';

/**
 * Page:Header back button on detail pages. Arriving from another Captain
 * Coaster page, it goes back in history (the previous page keeps its filters
 * and scroll); otherwise (search engine, shared link) it follows its href,
 * the page's parent. So does a page that reloaded onto itself after saving
 * its form (settings): going back in history would show it again.
 */
export default class extends Controller {
    connect() {
        this.fromSite = window.history.length > 1 && this.fromAnotherSitePage();
    }

    go(event) {
        if (this.fromSite) {
            event.preventDefault();
            window.history.back();
        }
    }

    fromAnotherSitePage() {
        try {
            const referrer = new URL(document.referrer);

            return (
                referrer.origin === window.location.origin &&
                this.page(referrer) !== this.page(window.location)
            );
        } catch {
            return false;
        }
    }

    // Without the locale: saving a new interface language reloads the page
    // under another prefix.
    page(url) {
        return url.pathname.replace(/^\/[a-z]{2}\//, '/');
    }
}
