import BaseController from './base_controller.js';
import { scaleUp } from '../js/utils/animation.js';

/**
 * Unified toggle for like, image-like, and wishlist actions.
 *
 * Values:
 *   url              — endpoint URL
 *   active           — current boolean state
 *   token            — per-resource CSRF token; empty = read from meta tag; 'none' = no token
 *   method           — HTTP method when activating (default POST)
 *   deactivateMethod — HTTP method when deactivating (empty = same as method)
 *   animation        — '' | 'zoom'
 *   optimistic       — update UI before server confirms (default false)
 *   eventName        — optional custom event name dispatched on success
 *   silentStatus     — HTTP status code to ignore silently (e.g. 403 for own image)
 *
 * State rendering:
 *   - Sets data-active="true"/"false" on the root element for CSS
 *   - Toggles hidden on [data-when="on"/"off"] children
 *   - Updates [data-toggle-action-target="count"] text on success
 */
export default class extends BaseController {
    static targets = ['count'];
    static values = {
        url: String,
        active: { type: Boolean, default: false },
        token: { type: String, default: '' },
        method: { type: String, default: 'POST' },
        deactivateMethod: { type: String, default: '' },
        animation: { type: String, default: '' },
        optimistic: { type: Boolean, default: false },
        eventName: { type: String, default: '' },
        silentStatus: { type: Number, default: 0 },
    };

    async toggle(event) {
        event.preventDefault();
        const btn = event.currentTarget;
        if (this.isLoading(btn)) return;

        const wasActive = this.activeValue;
        const prevCount = this.hasCountTarget
            ? parseInt(this.countTarget.textContent || '0', 10) || 0
            : null;

        if (this.animationValue === 'zoom') {
            const icon = btn.querySelector('svg');
            if (icon) scaleUp(icon, 200);
        }

        if (this.optimisticValue) {
            this.#setState(!wasActive);
            if (prevCount !== null) {
                this.countTarget.textContent = String(prevCount + (wasActive ? -1 : 1));
            }
        }

        this.showLoading(btn);

        try {
            const method = wasActive
                ? (this.deactivateMethodValue || this.methodValue)
                : this.methodValue;
            const body = this.#buildBody(method);

            const response = await fetch(this.urlValue, {
                method,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(body ? { 'Content-Type': 'application/x-www-form-urlencoded' } : {}),
                },
                body,
            });

            if (this.silentStatusValue && response.status === this.silentStatusValue) {
                if (this.optimisticValue) this.#setState(wasActive);
                return;
            }

            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const data = await response.json().catch(() => ({}));
            const newActive = data.liked ?? data.active ?? data.wishlisted ?? !wasActive;
            const serverCount = data.count ?? data.likeCount;

            this.#setState(newActive);
            if (this.hasCountTarget && serverCount !== undefined) {
                this.countTarget.textContent = String(serverCount);
            }

            if (this.eventNameValue) {
                document.dispatchEvent(
                    new CustomEvent(this.eventNameValue, {
                        detail: { active: newActive, url: this.urlValue },
                    })
                );
            }
        } catch (err) {
            if (this.optimisticValue) {
                this.#setState(wasActive);
                if (prevCount !== null) this.countTarget.textContent = String(prevCount);
            }
            this.showError('An error occurred.');
        } finally {
            this.hideLoading(btn);
        }
    }

    activeValueChanged(value) {
        this.element.dataset.active = value ? 'true' : 'false';
        this.element.querySelectorAll('[data-when]').forEach((el) => {
            el.classList.toggle('hidden', (el.dataset.when === 'on') !== value);
        });
    }

    #setState(active) {
        this.activeValue = active;
    }

    #buildBody(method) {
        if (method === 'GET') return null;
        if (this.tokenValue === 'none') return null;
        const token = this.tokenValue || this.getCsrfToken();
        return token ? `_token=${encodeURIComponent(token)}` : null;
    }
}
