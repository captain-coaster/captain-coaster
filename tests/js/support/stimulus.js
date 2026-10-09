import { Application } from '@hotwired/stimulus';
import { vi } from 'vitest';

let application = null;

/**
 * Renders `html` and starts Stimulus on it with `controllers` ({ identifier: class }).
 * Resolves once every controller is connected.
 */
export async function mount(html, controllers) {
    document.body.innerHTML = html;
    application = Application.start();
    for (const [identifier, controller] of Object.entries(controllers)) {
        application.register(identifier, controller);
    }
    await settle();

    return application;
}

export function unmount() {
    application?.stop();
    application = null;
}

/** The controller instance connected to the element matching `selector`. */
export function controllerOf(selector, identifier) {
    return application.getControllerForElementAndIdentifier(
        document.querySelector(selector),
        identifier
    );
}

/** Lets pending promises and Stimulus' mutation observers run. */
export async function settle() {
    for (let i = 0; i < 5; i++) {
        await new Promise((resolve) => setTimeout(resolve, 0));
    }
}

/** Stubs fetch with one response per call, in order; a rejected call is given as an Error. */
export function stubFetch(...responses) {
    const fetch = vi.fn();
    for (const response of responses) {
        if (response instanceof Error) {
            fetch.mockRejectedValueOnce(response);
        } else {
            const { status = 200, body = '' } = response;
            const text = typeof body === 'string' ? body : JSON.stringify(body);
            // A 204 has no body
            fetch.mockResolvedValueOnce(
                new Response(status === 204 ? null : text, { status })
            );
        }
    }
    vi.stubGlobal('fetch', fetch);

    return fetch;
}

/** The server-rendered <template id="js-icons"> that icon() reads. */
export function jsIcons(...names) {
    return `<template id="js-icons">${names.map((name) => `<span data-icon="${name}"><svg data-name="${name}"></svg></span>`).join('')}</template>`;
}

/** Replaces window.location, which jsdom cannot navigate: `href` and `assign` record where the page went. */
export function stubLocation() {
    const location = { ...window.location, assign: vi.fn() };
    vi.stubGlobal('location', location);

    return location;
}

/** Phone unless `tablet`: what isTabletUp() and prefers-reduced-motion checks read. */
export function stubViewport({ tablet = false } = {}) {
    vi.stubGlobal('matchMedia', () => ({ matches: tablet }));
}

/** jsdom has no <dialog> behaviour: open, close and the close event are enough for the controllers. */
export function stubDialog() {
    HTMLDialogElement.prototype.showModal = function () {
        this.open = true;
    };
    HTMLDialogElement.prototype.close = function () {
        this.open = false;
        this.dispatchEvent(new Event('close'));
    };
}

/**
 * Stubs an observer class jsdom lacks (IntersectionObserver, ResizeObserver).
 * Returns its instances: each has `observed` and `trigger(...entries)`.
 */
export function stubObserver(name) {
    const instances = [];
    vi.stubGlobal(
        name,
        class {
            observed = [];

            constructor(callback, options) {
                this.options = options;
                this.trigger = (...entries) => callback(entries, this);
                instances.push(this);
            }

            observe(element) {
                this.observed.push(element);
            }

            disconnect() {
                this.observed = [];
            }
        }
    );

    return instances;
}

/** Stubs navigator.geolocation; `undefined` removes it. Returns the getCurrentPosition mock. */
export function stubGeolocation(getCurrentPosition) {
    if (getCurrentPosition === undefined) {
        delete navigator.geolocation;

        return undefined;
    }
    const mock = vi.fn(getCurrentPosition);
    Object.defineProperty(navigator, 'geolocation', {
        value: { getCurrentPosition: mock },
        configurable: true,
    });

    return mock;
}
