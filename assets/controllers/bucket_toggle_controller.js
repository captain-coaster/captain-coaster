import BaseController from './base_controller.js';
import { scaleUp } from '../js/utils/animation.js';

/**
 * BucketToggle — add / remove a coaster from the bucket list.
 *
 * Values:
 *   coasterId — the coaster ID (integer)
 *   bucketed  — current boolean state
 *   locale    — current locale string
 *   token     — per-resource CSRF token; empty = read from csrf-protection outlet / meta tag
 *
 * Targets:
 *   on  — shown when bucketed
 *   off — shown when not bucketed
 *
 * Events:
 *   wishlist:changed — dispatched/received for cross-widget sync
 *     detail: { coasterId: Number, bucketed: Boolean }
 */
export default class extends BaseController {
    static values = {
        coasterId: Number,
        bucketed: Boolean,
        locale: String,
        token: { type: String, default: '' },
    };
    static targets = ['on', 'off', 'btn'];

    connect() {
        this.render();
        this._onChanged = (e) => {
            if (e.detail.coasterId !== this.coasterIdValue) return;
            this.bucketedValue = e.detail.bucketed;
            this.render();
        };
        document.addEventListener('wishlist:changed', this._onChanged);
    }

    disconnect() {
        document.removeEventListener('wishlist:changed', this._onChanged);
    }

    render() {
        if (this.hasOnTarget) this.onTarget.classList.toggle('hidden', !this.bucketedValue);
        if (this.hasOffTarget) this.offTarget.classList.toggle('hidden', this.bucketedValue);
        if (this.hasBtnTarget) {
            this.btnTarget.classList.toggle('bg-neutral-700/70', !this.bucketedValue);
            this.btnTarget.classList.toggle('backdrop-blur-sm', !this.bucketedValue);
            this.btnTarget.classList.toggle('bg-cc-warm-600', this.bucketedValue);
        }
    }

    async toggle(event) {
        const btn = event?.currentTarget;
        if (btn && this.isLoading(btn)) return;
        if (btn) this.showLoading(btn);
        const adding = !this.bucketedValue;
        try {
            const url = Routing.generate(
                adding ? 'wishlist_add' : 'wishlist_remove',
                { coasterId: this.coasterIdValue, _locale: this.localeValue }
            );
            await this._send(adding ? 'POST' : 'DELETE', url);
            this.bucketedValue = adding;
            this.render();
            if (btn) scaleUp(btn);
            document.dispatchEvent(
                new CustomEvent('wishlist:changed', {
                    detail: { coasterId: this.coasterIdValue, bucketed: adding },
                })
            );
        } catch (e) {
            console.error(e);
            this.showError('Unable to update bucket list.');
        } finally {
            if (btn) this.hideLoading(btn);
        }
    }

    /** Build the CSRF token body string. */
    _buildBody() {
        const token = this.tokenValue || this.getCsrfToken();
        return token ? `_token=${encodeURIComponent(token)}` : '';
    }

    /** Fetch wrapper for wishlist endpoints. */
    async _send(method, url) {
        const body = this._buildBody();
        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body,
        });
        if (!response.ok) throw new Error(`Request failed (${response.status})`);
    }
}
