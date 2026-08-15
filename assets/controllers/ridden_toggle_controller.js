import BaseController from './base_controller.js';
import { celebrationBurst, scaleUp } from '../js/utils/animation.js';

/**
 * RiddenToggle — mark / remove "ridden" for a coaster.
 * Emits ride:marked / ride:removed so siblings (stars, RideTracker, hero) can react.
 * Listens to ride:marked / ride:removed to keep its own icon in sync.
 */
export default class extends BaseController {
    static values = {
        coasterId: Number,
        riddenCoasterId: Number,
        ridden: Boolean,
        locale: String,
        confirmRemove: String,
    };
    static targets = ['on', 'off', 'btn'];

    connect() {
        this.render();
        this._onMarked = (e) => this.sync(e, true);
        this._onRemoved = (e) => this.sync(e, false);
        document.addEventListener('ride:marked', this._onMarked);
        document.addEventListener('ride:removed', this._onRemoved);
    }

    disconnect() {
        document.removeEventListener('ride:marked', this._onMarked);
        document.removeEventListener('ride:removed', this._onRemoved);
    }

    sync(event, ridden) {
        if (event.detail.coasterId !== this.coasterIdValue) return;
        if (ridden && event.detail.riddenCoasterId) {
            this.riddenCoasterIdValue = event.detail.riddenCoasterId;
        }
        this.riddenValue = ridden;
        this.render();
    }

    render() {
        if (this.hasOnTarget) this.onTarget.classList.toggle('hidden', !this.riddenValue);
        if (this.hasOffTarget) this.offTarget.classList.toggle('hidden', this.riddenValue);
        if (this.hasBtnTarget) {
            this.btnTarget.classList.toggle('bg-neutral-700/70', !this.riddenValue);
            this.btnTarget.classList.toggle('backdrop-blur-sm', !this.riddenValue);
            this.btnTarget.classList.toggle('bg-green-800', this.riddenValue);
        }
    }

    async toggle(event) {
        const btn = event?.currentTarget;
        if (btn && this.isLoading(btn)) return;
        if (this.riddenValue) return this.remove(btn);
        return this.mark(btn);
    }

    async mark(btn) {
        if (btn) this.showLoading(btn);
        try {
            const data = await this.send('POST',
                Routing.generate('rating_edit', { id: this.coasterIdValue, _locale: this.localeValue }),
                'action=mark_ridden');
            this.riddenCoasterIdValue = data.id;
            this.riddenValue = true;
            this.render();
            if (btn) celebrationBurst(btn);
            document.dispatchEvent(new CustomEvent('ride:marked', {
                detail: { coasterId: this.coasterIdValue, riddenCoasterId: data.id },
            }));
        } catch (e) {
            this.fail(e, 'Unable to mark as ridden.');
        } finally {
            if (btn) this.hideLoading(btn);
        }
    }

    async remove(btn) {
        if (!this.riddenCoasterIdValue) return;
        if (this.hasConfirmRemoveValue && this.confirmRemoveValue && !confirm(this.confirmRemoveValue)) return;
        if (btn) this.showLoading(btn);
        try {
            await this.send('DELETE',
                Routing.generate('rating_delete', { id: this.riddenCoasterIdValue, _locale: this.localeValue }));
            this.riddenValue = false;
            this.riddenCoasterIdValue = 0;
            this.render();
            if (btn) scaleUp(btn);
            document.dispatchEvent(new CustomEvent('ride:removed', {
                detail: { coasterId: this.coasterIdValue },
            }));
        } catch (e) {
            this.fail(e, 'Unable to remove ride.');
        } finally {
            if (btn) this.hideLoading(btn);
        }
    }

    async send(method, url, body = '') {
        const response = await fetch(url.replace(/^http:/, 'https:'), {
            method,
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: this.addCsrfToBody(body),
        });
        if (!response.ok) throw new Error(`Request failed (${response.status})`);
        try { return await response.json(); } catch { return {}; }
    }

    fail(error, msg) { console.error(error); this.showError(msg); }
}
