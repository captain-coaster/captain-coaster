import { Controller } from '@hotwired/stimulus';

/**
 * Page:Header back button on detail pages. Arriving from another Captain
 * Coaster page, it goes back in history (the previous page keeps its filters
 * and scroll); otherwise (search engine, shared link) it follows its href,
 * the page's parent.
 */
export default class extends Controller {
    connect() {
        this.fromSite = this.sameOriginReferrer() && window.history.length > 1;
    }

    go(event) {
        if (this.fromSite) {
            event.preventDefault();
            window.history.back();
        }
    }

    sameOriginReferrer() {
        try {
            return (
                new URL(document.referrer).origin === window.location.origin
            );
        } catch {
            return false;
        }
    }
}
