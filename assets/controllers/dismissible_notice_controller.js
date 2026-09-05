import { Controller } from '@hotwired/stimulus';

/** A banner that stays hidden (per browser) once dismissed. */
export default class extends Controller {
    static values = { key: String };

    connect() {
        if (this.wasDismissed()) {
            this.element.hidden = true;
        }
    }

    dismiss() {
        try {
            window.localStorage.setItem(this.storageKey(), '1');
        } catch (e) {
            // localStorage unavailable (private mode, etc.) — just hide for this view.
        }
        this.element.hidden = true;
    }

    wasDismissed() {
        try {
            return window.localStorage.getItem(this.storageKey()) === '1';
        } catch (e) {
            return false;
        }
    }

    storageKey() {
        return `notice-dismissed:${this.keyValue}`;
    }
}
