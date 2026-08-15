import BaseController from './base_controller.js';

/**
 * Nearby coasters widget — homepage geolocation feature.
 *
 * Fetches a server-rendered HTML partial from /api/nearby-coasters.
 * No JS templating — the server owns the markup.
 *
 * Behaviour:
 *   - First load: shows a CTA button to enable geolocation.
 *   - On click → request geolocation, fetch HTML partial, inject into list.
 *   - Persists permission grant + rendered HTML in localStorage (TTL 1h)
 *     so subsequent visits auto-render without re-prompting.
 *   - Permission denied / API error → widget hides itself silently.
 */
export default class extends BaseController {
    static targets = ['cta', 'loading', 'results', 'list'];

    static values = {
        ttl: { type: Number, default: 3600000 }, // 1h in ms
        radius: { type: Number, default: 200 },
        limit: { type: Number, default: 5 },
        storageKey: { type: String, default: 'nearbyCoasters.cache' },
        consentKey: { type: String, default: 'nearbyCoasters.consent' },
        kmLabel: { type: String, default: 'km' },
    };

    connect() {
        if (this.hasCachedHtml()) {
            this.renderHtml(this.getCachedHtml());
            return;
        }

        if (this.hasPreviousConsent()) {
            // Only auto-fire if browser already granted permission — no surprise popup.
            if ('permissions' in navigator) {
                navigator.permissions
                    .query({ name: 'geolocation' })
                    .then((result) => {
                        if (result.state === 'granted') {
                            this.requestLocation();
                        } else if (result.state === 'denied') {
                            this.hideAll();
                        }
                        // 'prompt' → CTA stays visible
                    })
                    .catch(() => {
                        // Permissions API unavailable — CTA stays visible
                    });
            }
        }
    }

    /** Triggered by the CTA button — explicit user opt-in. */
    enable() {
        this.requestLocation();
    }

    requestLocation() {
        if (!('geolocation' in navigator)) {
            this.hideAll();
            return;
        }

        this.showLoadingState();

        navigator.geolocation.getCurrentPosition(
            (position) => {
                localStorage.setItem(this.consentKeyValue, '1');
                this.fetchNearby(
                    position.coords.latitude,
                    position.coords.longitude
                );
            },
            () => {
                this.hideAll();
            },
            { enableHighAccuracy: false, timeout: 10000, maximumAge: 600000 }
        );
    }

    async fetchNearby(lat, lng) {
        try {
            const locale = document.documentElement.lang || 'en';
            const url =
                typeof Routing !== 'undefined' && Routing.generate
                    ? Routing.generate('api_nearby_coasters', {
                          lat: lat.toFixed(4),
                          lng: lng.toFixed(4),
                          radius: this.radiusValue,
                          limit: this.limitValue,
                          kmLabel: this.kmLabelValue,
                          _locale: locale,
                      })
                    : `/${locale}/api/nearby-coasters?lat=${lat.toFixed(4)}&lng=${lng.toFixed(4)}&radius=${this.radiusValue}&limit=${this.limitValue}&kmLabel=${encodeURIComponent(this.kmLabelValue)}`;

            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (response.status === 204) {
                // No coasters found nearby
                this.hideAll();
                return;
            }

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const html = await response.text();
            this.cacheHtml(html);
            this.renderHtml(html);
        } catch (err) {
            this.hideAll();
        }
    }

    renderHtml(html) {
        if (!this.hasListTarget) return;
        this.listTarget.innerHTML = html;
        this.showResultsState();
    }

    cacheHtml(html) {
        try {
            localStorage.setItem(
                this.storageKeyValue,
                JSON.stringify({ timestamp: Date.now(), html })
            );
        } catch {
            // localStorage full — non-fatal
        }
    }

    hasCachedHtml() {
        try {
            const raw = localStorage.getItem(this.storageKeyValue);
            if (!raw) return false;
            const { timestamp } = JSON.parse(raw);
            return Date.now() - timestamp < this.ttlValue;
        } catch {
            return false;
        }
    }

    getCachedHtml() {
        try {
            return JSON.parse(localStorage.getItem(this.storageKeyValue)).html;
        } catch {
            return '';
        }
    }

    hasPreviousConsent() {
        return localStorage.getItem(this.consentKeyValue) === '1';
    }

    showLoadingState() {
        this.hideTarget(this.ctaTarget);
        this.showTarget(this.loadingTarget);
        this.hideTarget(this.resultsTarget);
    }

    showResultsState() {
        this.hideTarget(this.ctaTarget);
        this.hideTarget(this.loadingTarget);
        this.showTarget(this.resultsTarget);
    }

    hideAll() {
        this.element.classList.add('hidden');
    }

    showTarget(el) {
        el?.classList.remove('hidden');
    }

    hideTarget(el) {
        el?.classList.add('hidden');
    }
}
