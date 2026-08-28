import { Controller } from '@hotwired/stimulus';

/**
 * Map Controller - Stimulus component for MapLibre GL maps.
 * Parks are rendered as a single GeoJSON symbol layer, not one DOM node
 * per marker, so the map stays smooth with thousands of parks.
 *
 * MapLibre renders a symbol layer's icons and text labels as two
 * separate draw batches internally, even within one layer — so even
 * with icon+text on the same feature, a background marker's label can
 * still paint over a foreground marker's icon. To get true DOM-marker-
 * style stacking (like the old Leaflet markers), the circle and its
 * count are baked into a single raster icon per coaster count — one
 * pre-rendered image, nothing left to draw separately.
 */
export default class extends Controller {
    static values = {
        markers: Array,
        parkId: String,
    };

    static targets = ['container'];

    SOURCE_ID = 'parks';
    MARKER_LAYER_ID = 'park-markers';
    MARKER_ICON_SIZE = 44; // drawn at 2x for retina, see createMarkerIcon()
    registeredIcons = new Set();

    connect() {
        // Make controller accessible for filter functionality
        this.element.mapController = this;
        this.initializeMap();
    }

    disconnect() {
        if (this.map) {
            this.map.remove();
        }
    }

    async initializeMap() {
        try {
            const maplibregl = await import('maplibre-gl');
            await import('maplibre-gl/dist/maplibre-gl.css');

            this.maplibregl = maplibregl;

            // The worker URL MapLibre resolves at runtime doesn't survive
            // Webpack bundling; see webpack.config.js copyFiles() for why
            // this static path exists.
            maplibregl.setWorkerUrl('/build/vendor/maplibre-gl-worker.js');

            this.map = new maplibregl.Map({
                container: this.containerTarget,
                style: 'https://tiles.openfreemap.org/styles/liberty',
                center: [6.6323, 46.5197],
                zoom: 6,
                minZoom: 2,
                maxZoom: 18,
            });

            this.map.addControl(new maplibregl.NavigationControl(), 'top-right');

            this.map.on('load', () => {
                this.addParksLayer();
                this.bindLayerInteractions();

                if (this.parkIdValue) {
                    this.focusOnPark(this.parkIdValue);
                } else {
                    this.setUserLocation();
                }
            });
        } catch (error) {
            console.error('Failed to initialize map:', error);
        }
    }

    colorForCount(nb) {
        if (nb === 1) return '#22c55e'; // 1 coaster
        if (nb <= 5) return '#f59e0b'; // 2-5
        if (nb <= 10) return '#ef4444'; // 6-10
        if (nb <= 15) return '#dc2626'; // 11-15
        return '#8b5cf6'; // 15+
    }

    markerIconId(nb) {
        return `park-marker-${nb}`;
    }

    createMarkerIcon(nb) {
        const size = this.MARKER_ICON_SIZE;
        const canvas = document.createElement('canvas');
        canvas.width = size;
        canvas.height = size;
        const ctx = canvas.getContext('2d');

        ctx.beginPath();
        ctx.arc(size / 2, size / 2, size / 2 - 3, 0, Math.PI * 2);
        ctx.fillStyle = this.colorForCount(nb);
        ctx.fill();
        ctx.lineWidth = 3;
        ctx.strokeStyle = '#ffffff';
        ctx.stroke();

        ctx.fillStyle = '#ffffff';
        ctx.font = `bold ${Math.round(size * 0.42)}px -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(String(nb), size / 2, size / 2 + 1);

        return ctx.getImageData(0, 0, size, size);
    }

    // Registers one raster icon per distinct coaster count so far
    // unseen — safe to call repeatedly (e.g. after a filter refresh).
    ensureMarkerIcons(markers) {
        const counts = new Set((markers || []).map((p) => parseInt(p.nb) || 1));
        counts.forEach((nb) => {
            const id = this.markerIconId(nb);
            if (!this.registeredIcons.has(id)) {
                this.map.addImage(id, this.createMarkerIcon(nb), { pixelRatio: 2 });
                this.registeredIcons.add(id);
            }
        });
    }

    addParksLayer() {
        this.ensureMarkerIcons(this.markersValue);

        this.map.addSource(this.SOURCE_ID, {
            type: 'geojson',
            data: this.toGeoJSON(this.markersValue),
        });

        this.map.addLayer({
            id: this.MARKER_LAYER_ID,
            type: 'symbol',
            source: this.SOURCE_ID,
            layout: {
                'icon-image': ['get', 'icon'],
                'icon-size': 1,
                'icon-allow-overlap': true,
                // Bigger parks draw on top of smaller ones when they
                // overlap (higher sort-key = drawn later = on top).
                'symbol-sort-key': ['get', 'nb'],
            },
        });
    }

    bindLayerInteractions() {
        this.map.on('click', this.MARKER_LAYER_ID, (e) => {
            const feature = e.features[0];
            this.showParkPopup(feature.properties, feature.geometry.coordinates);
        });

        this.map.on('mouseenter', this.MARKER_LAYER_ID, () => {
            this.map.getCanvas().style.cursor = 'pointer';
        });

        this.map.on('mouseleave', this.MARKER_LAYER_ID, () => {
            this.map.getCanvas().style.cursor = '';
        });
    }

    toGeoJSON(markers) {
        const features = (markers || []).map((park) => {
            const nb = parseInt(park.nb) || 1;
            return {
                type: 'Feature',
                properties: {
                    id: park.id,
                    name: park.name,
                    nb,
                    icon: this.markerIconId(nb),
                },
                geometry: {
                    type: 'Point',
                    coordinates: [parseFloat(park.longitude), parseFloat(park.latitude)],
                },
            };
        });

        return { type: 'FeatureCollection', features };
    }

    showParkPopup(properties, coordinates) {
        if (this.currentPopup) {
            this.currentPopup.remove();
        }

        this.currentPopup = new this.maplibregl.Popup({ offset: 12 })
            .setLngLat(coordinates)
            .setHTML('Loading...')
            .addTo(this.map);

        this.loadParkData(properties.id, this.currentPopup);
    }

    loadParkData(parkId, popup) {
        const url = window.Routing.generate('map_coasters_ajax', {
            id: parkId,
            _locale: document.documentElement.lang || 'en',
        });

        const form = document.querySelector('form');
        const formData = new FormData(form);
        const params = new URLSearchParams();

        for (const [key, value] of formData.entries()) {
            if (value && value.trim() !== '') {
                params.set(key, value);
            }
        }

        fetch(`${url}?${params}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => response.text())
            .then((coasters) => {
                popup.setHTML(coasters);
            })
            .catch((error) => {
                console.error('Failed to load park data:', error);
                popup.setHTML('Error loading data');
            });
    }

    focusOnPark(parkId) {
        const park = (this.markersValue || []).find((p) => p.id == parkId);
        if (park) {
            const coordinates = [parseFloat(park.longitude), parseFloat(park.latitude)];
            this.map.setCenter(coordinates);
            this.map.setZoom(9);
            this.showParkPopup(
                { id: park.id, name: park.name, nb: parseInt(park.nb) || 1 },
                coordinates
            );
        }
    }

    setUserLocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition((position) => {
                this.map.setCenter([
                    position.coords.longitude,
                    position.coords.latitude,
                ]);
                this.map.setZoom(5);
            });
        }
    }

    filterData() {
        const url = window.Routing.generate('map_markers_ajax', {
            _locale: document.documentElement.lang || 'en',
        });

        const form = document.querySelector('form');
        const formData = new FormData(form);
        const params = new URLSearchParams();

        for (const [key, value] of formData.entries()) {
            if (value && value.trim() !== '') {
                params.set(key, value);
            }
        }

        fetch(`${url}?${params}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => response.json())
            .then((data) => {
                this.markersValue = data;
                this.ensureMarkerIcons(data);
                this.map.getSource(this.SOURCE_ID).setData(this.toGeoJSON(data));

                const filterElement = document.querySelector(
                    '[data-controller="filter"]'
                );
                if (filterElement && filterElement.filterController) {
                    const filterController = filterElement.filterController;
                    if (filterController.updateUrlValue) {
                        filterController.updateBrowserUrl();
                    }
                }
            })
            .catch((error) => {
                console.error('Failed to filter markers:', error);
            });
    }
}
