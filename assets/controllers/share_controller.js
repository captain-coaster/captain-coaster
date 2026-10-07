import { Controller } from '@hotwired/stimulus';

/**
 * Share a page: the platform's share sheet where there is one, the link
 * copied to the clipboard otherwise. Messages come from the template.
 *
 * Usage:
 *   <button data-controller="share" data-action="share#share"
 *           data-share-url-value="…" data-share-title-value="…"
 *           data-share-copied-value="Link copied" data-share-failed-value="…">
 */
export default class extends Controller {
    static values = {
        url: String,
        title: String,
        copied: String,
        failed: String,
    };

    async share() {
        if (navigator.share) {
            try {
                await navigator.share({
                    title: this.titleValue,
                    url: this.urlValue,
                });
                return;
            } catch (error) {
                // Dismissing the sheet is not a failure.
                if (error.name === 'AbortError') return;
            }
        }

        try {
            await navigator.clipboard.writeText(this.urlValue);
            this.toast(this.copiedValue, 'success');
        } catch {
            this.toast(this.failedValue, 'danger');
        }
    }

    toast(message, type) {
        const element = document.getElementById('toasts');
        const controller =
            element &&
            this.application.getControllerForElementAndIdentifier(
                element,
                'toast'
            );
        controller?.show(message, type);
    }
}
