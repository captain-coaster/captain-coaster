import { Controller } from '@hotwired/stimulus';

/**
 * Share controller — uses the Web Share API where supported (mobile native sheet)
 * and falls back to copying the URL to the clipboard.
 *
 * On copy success, the share icon is swapped in-place with a checkmark for ~1.5s.
 * No separate toast that could clip behind a hero overlay.
 */
export default class extends Controller {
    static targets = ['icon'];
    static values = {
        url: String,
        title: { type: String, default: '' },
        text: { type: String, default: '' },
    };

    async share(event) {
        event.preventDefault();

        const data = {
            url: this.urlValue,
            title: this.titleValue || document.title,
            text: this.textValue,
        };

        if (typeof navigator.share === 'function') {
            try {
                await navigator.share(data);
                return;
            } catch (err) {
                if (err && err.name === 'AbortError') return;
                // fall through to clipboard
            }
        }

        try {
            await navigator.clipboard.writeText(data.url);
            this.flashCopied();
        } catch {
            const tmp = document.createElement('input');
            tmp.value = data.url;
            document.body.appendChild(tmp);
            tmp.select();
            document.execCommand('copy');
            document.body.removeChild(tmp);
            this.flashCopied();
        }
    }

    flashCopied() {
        if (!this.hasIconTarget) return;
        if (!this.originalIcon) this.originalIcon = this.iconTarget.innerHTML;

        // Swap the share icon to a checkmark briefly. Inline SVG to avoid loading deps.
        this.iconTarget.innerHTML =
            '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5L20 7"/></svg>';
        clearTimeout(this.flashTimer);
        this.flashTimer = setTimeout(() => {
            if (this.hasIconTarget && this.originalIcon) {
                this.iconTarget.innerHTML = this.originalIcon;
            }
        }, 1500);
    }

    disconnect() {
        clearTimeout(this.flashTimer);
    }
}
