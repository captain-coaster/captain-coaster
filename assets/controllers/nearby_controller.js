import { Controller } from '@hotwired/stimulus';
import { isSupported, locate, permission, position } from '../js/location';

/**
 * "Parks near you" on Home (Home:Nearby). On load, the parks show from a
 * position known without asking (js/location.js); the browser is only asked
 * from the buttons. The position is posted, never put in a URL.
 *
 * The element carries the state (data-state: idle | loading | ready | denied,
 * and data-error after a failed attempt); the markup shows what goes with it.
 */
export default class extends Controller {
    static targets = ['results'];
    static values = { url: String };

    async connect() {
        const state = await permission();
        if (!isSupported() || state === 'denied') {
            this.state = 'denied';
            return;
        }

        // A granted fix can take a few seconds: no button meanwhile.
        if (state === 'granted') this.state = 'loading';
        const known = await position();
        if (known) {
            this.state = 'loading';
            this.load(known);
        } else {
            this.state = 'idle';
        }
    }

    async locate() {
        if (this.state === 'loading') return;
        this.state = 'loading';

        try {
            this.load(await locate());
        } catch (reason) {
            // A timeout can be retried; a refusal or no position can't.
            if (reason === 'timeout') this.fail();
            else this.state = 'denied';
        }
    }

    async load({ latitude, longitude }) {
        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams({ latitude, longitude }),
            });
            if (!response.ok) throw new Error(response.statusText);

            this.resultsTarget.innerHTML = await response.text();
            this.state = 'ready';

            const moment = this.resultsTarget.querySelector(
                'template[data-nearby-moment]'
            );
            const slot = document.querySelector('[data-home-moment]');
            if (moment && slot) slot.replaceChildren(moment.content);
        } catch {
            this.fail();
        }
    }

    // Back to the button, with the error line.
    fail() {
        this.state = 'idle';
        this.element.toggleAttribute('data-error', true);
    }

    get state() {
        return this.element.dataset.state;
    }

    set state(value) {
        this.element.dataset.state = value;
        this.element.removeAttribute('data-error');
    }
}
