import { Controller } from '@hotwired/stimulus';

/**
 * Map Controller - Clean Stimulus component for Leaflet maps
 * Handles marker creation, colors, and interactions
 */
export default class extends Controller {
    static values = {
        markers: Array,  // Try Array - Stimulus might auto-parse JSON
        parkId: String
    };

    static targets = ['container'];

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
            // Dynamic import of Leaflet
            const L = await import('leaflet');
            await import('leaflet/dist/leaflet.css');
            
            // Fix default marker icons (Leaflet + Webpack issue)
            delete L.Icon.Default.prototype._getIconUrl;
            L.Icon.Default.mergeOptions({
                iconRetinaUrl: require('leaflet/dist/images/marker-icon-2x.png'),
                iconUrl: require('leaflet/dist/images/marker-icon.png'),
                shadowUrl: require('leaflet/dist/images/marker-shadow.png'),
            });

            // Create map with world copy jump enabled
            this.map = L.map(this.containerTarget, {
                worldCopyJump: true
            }).setView([46.5197, 6.6323], 6);
            
            // Add CartoDB Voyager tile layer (colorful, English labels worldwide)
            L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                attribution: '© OpenStreetMap contributors © CARTO',
                maxZoom: 15,
                minZoom: 3
            }).addTo(this.map);

            // Store Leaflet globally for compatibility
            window.L = L;

            // Generate markers
            this.generateMarkers();

            // Handle specific park if provided
            if (this.parkIdValue) {
                this.focusOnPark(this.parkIdValue);
            } else {
                this.setUserLocation();
            }

        } catch (error) {
            console.error('Failed to initialize map:', error);
        }
    }

    generateMarkers() {
        this.gmarkers = [];

        if (!this.markersValue || this.markersValue.length === 0) {
            return;
        }

        this.markersValue.forEach(park => {
            const coasterCount = parseInt(park.nb) || 1;
            const marker = window.L.marker([park.latitude, park.longitude], {
                icon: this.createCoasterMarker(coasterCount),
                zIndexOffset: coasterCount * 100  // Higher coaster count = higher z-index
            }).addTo(this.map);

            marker.parkId = park.id;
            marker.title = park.name;
            this.gmarkers.push(marker);

            marker.on('click', () => {
                this.loadParkData(marker);
            });
        });
    }

    createCoasterMarker(coasterCount) {
        let color;
        if (coasterCount === 1) {
            color = '#22c55e';      // Green - 1 coaster
        } else if (coasterCount <= 5) {
            color = '#f59e0b';      // Orange - 2-5 coasters
        } else if (coasterCount <= 10) {
            color = '#ef4444';      // Red - 6-10 coasters
        } else if (coasterCount <= 15) {
            color = '#dc2626';      // Dark Red - 11-15 coasters
        } else {
            color = '#8b5cf6';      // Purple - 15+ coasters
        }
        
        return window.L.divIcon({
            className: 'coaster-marker',
            html: `<div class="coaster-marker-inner" data-count="${coasterCount}" style="background: ${color};">${coasterCount}</div>`,
            iconSize: [20, 20],
            iconAnchor: [10, 10],
            popupAnchor: [0, -10]
        });
    }

    loadParkData(marker) {
        // Always reload popup data to respect current filters
        marker.bindPopup('Loading...').openPopup();

        const url = window.Routing.generate('map_coasters_ajax', {
            'id': marker.parkId,
            '_locale': document.documentElement.lang || 'en',
        });

        // Get current form data directly (simple approach)
        const form = document.querySelector('form');
        const formData = new FormData(form);
        const params = new URLSearchParams();
        
        // Only include non-empty values
        for (const [key, value] of formData.entries()) {
            if (value && value.trim() !== '') {
                params.set(key, value);
            }
        }
        
        fetch(`${url}?${params}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.text())
            .then(coasters => {
                marker.bindPopup(coasters).openPopup();
            })
            .catch(error => {
                console.error('Failed to load park data:', error);
                marker.bindPopup('Error loading data').openPopup();
            });
    }

    focusOnPark(parkId) {
        const park = this.gmarkers.find(marker => marker.parkId == parkId);
        if (park) {
            this.map.setView([park.getLatLng().lat, park.getLatLng().lng], 9);
            this.loadParkData(park);
        }
    }

    setUserLocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(position => {
                this.map.setView([position.coords.latitude, position.coords.longitude], 5);
            });
        }
    }

    filterData() {
        console.log('Map filterData called');
        
        const url = window.Routing.generate('map_markers_ajax', {
            '_locale': document.documentElement.lang || 'en'
        });

        const form = document.querySelector('form');
        const formData = new FormData(form);
        const params = new URLSearchParams();
        
        // Only include non-empty values
        for (const [key, value] of formData.entries()) {
            if (value && value.trim() !== '') {
                params.set(key, value);
            }
        }
        
        console.log('Filter request URL:', `${url}?${params}`);

        fetch(`${url}?${params}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.json())
            .then(data => {
                console.log('Received filtered data:', data.length, 'markers');
                this.removeMarkers();
                this.markersValue = data;
                this.generateMarkers();
                
                // Update browser URL if filter controller has URL updates enabled
                const filterElement = document.querySelector('[data-controller="filter"]');
                if (filterElement && filterElement.filterController) {
                    const filterController = filterElement.filterController;
                    if (filterController.updateUrlValue) {
                        filterController.updateBrowserUrl();
                    }
                }
            })
            .catch(error => {
                console.error('Failed to filter markers:', error);
            });
    }



    removeMarkers() {
        if (this.gmarkers) {
            this.gmarkers.forEach(marker => {
                this.map.removeLayer(marker);
            });
            this.gmarkers = [];
        }
    }


}