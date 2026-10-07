import { Controller } from '@hotwired/stimulus';

const SHARED_KEY = 'nearby-shared';

/**
 * "Parks near you" on Home (Home:Nearby). The position is only asked from the
 * button; when the browser says it is already granted, the parks load on their
 * own. Safari doesn't report that state reliably, so a position once shared
 * from the button is remembered on this device (a flag, never the position).
 * The position is rounded to about 100m and posted, never put in a URL.
 *
 * The element carries the state (data-state: idle | loading | ready | denied,
 * and data-error after a failed attempt); the markup shows what goes with it.
 */
export default class extends Controller {
    static targets = ['results'];
    static values = { url: String };

    async connect() {
        if (!('geolocation' in navigator)) {
            this.state = 'denied';
            return;
        }

        try {
            const status = await navigator.permissions.query({ name: 'geolocation' });
            if (status.state === 'denied') {
                this.state = 'denied';
                return;
            }
            if (status.state === 'granted') {
                this.locate();
                return;
            }
        } catch {
            // No Permissions API: the flag decides.
        }

        if (this.shared) this.locate();
    }

    locate() {
        if (this.state === 'loading') return;
        this.state = 'loading';

        navigator.geolocation.getCurrentPosition(
            (position) => {
                this.shared = true;
                this.load(position.coords);
            },
            // A timeout can be retried; a refusal or no position can't.
            (error) => {
                if (error.code === error.TIMEOUT) return this.fail();
                if (error.code === error.PERMISSION_DENIED) this.shared = false;
                this.state = 'denied';
            },
            { maximumAge: 600000, timeout: 10000 }
        );
    }

    async load({ latitude, longitude }) {
        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams({
                    latitude: latitude.toFixed(3),
                    longitude: longitude.toFixed(3),
                }),
            });
            if (!response.ok) throw new Error(response.statusText);

            this.resultsTarget.innerHTML = await response.text();
            this.state = 'ready';

            const moment = this.resultsTarget.querySelector('template[data-nearby-moment]');
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

    // Storage can be unavailable (private mode): the button then asks every time.
    get shared() {
        try {
            return localStorage.getItem(SHARED_KEY) === '1';
        } catch {
            return false;
        }
    }

    set shared(value) {
        try {
            if (value) localStorage.setItem(SHARED_KEY, '1');
            else localStorage.removeItem(SHARED_KEY);
        } catch {
            // Nothing to remember.
        }
    }

    get state() {
        return this.element.dataset.state;
    }

    set state(value) {
        this.element.dataset.state = value;
        this.element.removeAttribute('data-error');
    }
}
