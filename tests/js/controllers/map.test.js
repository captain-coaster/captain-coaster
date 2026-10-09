import { beforeEach, describe, expect, it, vi } from 'vitest';
import MapController from '../../../assets/controllers/map_controller';
import {
    controllerOf,
    mount,
    patch,
    settle,
    stubFetch,
    stubGeolocation,
} from '../support/stimulus';

// MapLibre draws on WebGL, which jsdom lacks: a stand-in records what the controller asks of it.
const maplibre = vi.hoisted(() => ({ map: null, popups: [], workerUrl: null }));
vi.mock('maplibre-gl/dist/maplibre-gl.css', () => ({}));
vi.mock('maplibre-gl/package.json', () => ({ version: '9.9.9' }));
vi.mock('maplibre-gl', () => {
    class Map {
        handlers = {};
        images = [];
        source = null;
        layer = null;
        removed = false;

        constructor(options) {
            this.options = options;
            maplibre.map = this;
        }

        on(event, layerOrHandler, handler) {
            this.handlers[handler ? `${event}:${layerOrHandler}` : event] =
                handler ?? layerOrHandler;
        }

        addControl() {}
        addImage(id) {
            this.images.push(id);
        }
        addSource(id, source) {
            this.source = { id, data: source.data };
        }
        addLayer(layer) {
            this.layer = layer;
        }
        getSource() {
            return { setData: (data) => (this.source.data = data) };
        }
        getCanvas() {
            return { style: {} };
        }
        setCenter(center) {
            this.center = center;
        }
        setZoom(zoom) {
            this.zoom = zoom;
        }
        remove() {
            this.removed = true;
        }
    }

    class Popup {
        removed = false;

        constructor() {
            maplibre.popups.push(this);
        }

        setLngLat(coordinates) {
            this.coordinates = coordinates;

            return this;
        }
        setHTML(html) {
            this.html = html;

            return this;
        }
        addTo() {
            return this;
        }
        remove() {
            this.removed = true;
        }
    }

    return {
        Map,
        Popup,
        NavigationControl: class {},
        setWorkerUrl: (url) => (maplibre.workerUrl = url),
    };
});

const parks = [
    {
        id: 9,
        name: 'Phantasialand',
        nb: '6',
        latitude: '50.8',
        longitude: '6.88',
    },
    { id: 4, name: 'Tiny Park', nb: '1', latitude: '50.81', longitude: '6.89' },
    {
        id: 5,
        name: 'Europa-Park',
        nb: '13',
        latitude: '48.27',
        longitude: '7.72',
    },
];

const page = (parkId = '') => `
    <form id="form-filter"><input name="filters[manufacturer]" value="43"><input name="filters[name]" value=""></form>
    <div id="map" data-controller="map" data-map-park-id-value="${parkId}" data-map-markers-value='${JSON.stringify(parks)}'>
        <div data-map-target="container"></div>
    </div>`;

const features = () =>
    maplibre.map.source.data.features.map((feature) => [
        feature.properties.id,
        feature.properties.nb,
        feature.geometry.coordinates,
    ]);
const feature = (id) =>
    maplibre.map.source.data.features.find(
        (candidate) => candidate.properties.id === id
    );

async function start(parkId) {
    document.documentElement.lang = 'fr';
    await mount(page(parkId), { map: MapController });
    await settle();
    maplibre.map.handlers.load();
}

describe('map', () => {
    beforeEach(() => {
        maplibre.popups.length = 0;
        // No canvas in jsdom: the marker icon pixels are not what is tested
        patch(
            HTMLCanvasElement.prototype,
            'getContext',
            () =>
                new Proxy(
                    {},
                    {
                        get: (_, name) =>
                            name === 'getImageData' ? () => ({}) : () => {},
                        set: () => true,
                    }
                )
        );
        stubGeolocation(() => {});
    });

    it('draws one marker per park, sized by its number of coasters', async () => {
        await start();

        expect(features()).toEqual([
            [9, 6, [6.88, 50.8]],
            [4, 1, [6.89, 50.81]],
            [5, 13, [7.72, 48.27]],
        ]);
        expect(maplibre.map.images).toEqual([
            'park-marker-6',
            'park-marker-1',
            'park-marker-13',
        ]);
        expect(maplibre.map.layer.layout['symbol-sort-key']).toEqual([
            'get',
            'nb',
        ]);
        expect(maplibre.workerUrl).toBe(
            '/build/vendor/maplibre-gl-9.9.9/maplibre-gl-worker.mjs'
        );
    });

    it('opens the biggest park when markers overlap, with its coasters under the current filters', async () => {
        const fetch = stubFetch({ body: '<ul><li>Taron</li></ul>' });
        await start();

        maplibre.map.handlers['click:park-markers']({
            features: [feature(4), feature(9)],
        });
        await settle();

        expect(Routing.generate).toHaveBeenCalledWith('map_coasters_ajax', {
            id: 9,
            _locale: 'fr',
        });
        expect(fetch.mock.calls[0][0]).toBe(
            '/fr/map_coasters_ajax/9?filters%5Bmanufacturer%5D=43'
        );
        expect(maplibre.popups[0].coordinates).toEqual([6.88, 50.8]);
        expect(maplibre.popups[0].html).toBe('<ul><li>Taron</li></ul>');
    });

    it('shows one popup at a time', async () => {
        stubFetch({ body: 'a' }, { body: 'b' });
        await start();

        maplibre.map.handlers['click:park-markers']({ features: [feature(9)] });
        maplibre.map.handlers['click:park-markers']({ features: [feature(5)] });
        await settle();

        expect(maplibre.popups.map((popup) => popup.removed)).toEqual([
            true,
            false,
        ]);
    });

    it('says so in the popup when the coasters cannot be loaded', async () => {
        stubFetch(new Error('offline'));
        vi.spyOn(console, 'error').mockImplementation(() => {});
        await start();

        maplibre.map.handlers['click:park-markers']({ features: [feature(9)] });
        await settle();

        expect(maplibre.popups[0].html).toBe('map.error_loading');
    });

    it('opens on the park of a park page link', async () => {
        stubFetch({ body: 'coasters' });
        await start(5);
        await settle();

        expect(maplibre.map.center).toEqual([7.72, 48.27]);
        expect(maplibre.map.zoom).toBe(9);
        expect(maplibre.popups[0].coordinates).toEqual([7.72, 48.27]);
    });

    it('centres on the rider otherwise', async () => {
        stubGeolocation((success) =>
            success({ coords: { latitude: 46.2, longitude: 6.14 } })
        );

        await start();

        expect(maplibre.map.center).toEqual([6.14, 46.2]);
        expect(maplibre.popups).toHaveLength(0);
    });

    it('redraws the markers the filters keep', async () => {
        const fetch = stubFetch({
            body: [
                {
                    id: 5,
                    name: 'Europa-Park',
                    nb: '2',
                    latitude: '48.27',
                    longitude: '7.72',
                },
            ],
        });
        await start();

        controllerOf('#map', 'map').filterData();
        await settle();

        expect(fetch.mock.calls[0][0]).toBe(
            '/fr/map_markers_ajax/?filters%5Bmanufacturer%5D=43'
        );
        expect(features()).toEqual([[5, 2, [7.72, 48.27]]]);
        expect(maplibre.map.images).toContain('park-marker-2');
    });

    it('frees the map when it leaves the page', async () => {
        await start();

        document.getElementById('map').remove();
        await settle();

        expect(maplibre.map.removed).toBe(true);
    });
});
