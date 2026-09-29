import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

// Leaflet resolves its default marker images relative to its CSS, which breaks under
// Vite's hashed asset URLs, so point it at the bundled copies explicitly.
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconUrl: markerIcon,
    iconRetinaUrl: markerIcon2x,
    shadowUrl: markerShadow,
});

/** Metro Cebu, used when a listing has no pin yet. */
const DEFAULT_CENTER = [10.3157, 123.8854];

const TILE_URL = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
const TILE_ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

/**
 * Create a Leaflet map with the OpenStreetMap tile layer on the given element.
 */
function createMap(element, center, zoom) {
    const map = L.map(element).setView(center, zoom);

    L.tileLayer(TILE_URL, { maxZoom: 19, attribution: TILE_ATTRIBUTION }).addTo(map);

    return map;
}

document.addEventListener('alpine:init', () => {
    /**
     * Lets an owner drop a pin for a vehicle's pickup location. The chosen
     * coordinates are written back to the Livewire component's properties.
     */
    window.Alpine.data('locationPicker', ({ latitude = null, longitude = null, latProperty = 'latitude', lngProperty = 'longitude' } = {}) => ({
        map: null,
        marker: null,

        init() {
            const hasPin = latitude !== null && longitude !== null;

            this.map = createMap(this.$refs.map, hasPin ? [latitude, longitude] : DEFAULT_CENTER, hasPin ? 15 : 12);

            if (hasPin) {
                this.placeMarker([latitude, longitude]);
            }

            this.map.on('click', (event) => this.pick(event.latlng));
        },

        placeMarker(latlng) {
            if (this.marker) {
                this.marker.setLatLng(latlng);

                return;
            }

            this.marker = L.marker(latlng, { draggable: true }).addTo(this.map);
            this.marker.on('dragend', () => this.pick(this.marker.getLatLng()));
        },

        pick(latlng) {
            this.placeMarker(latlng);

            // Defer the first update so both coordinates travel in a single request.
            this.$wire.set(latProperty, Number(latlng.lat.toFixed(7)), false);
            this.$wire.set(lngProperty, Number(latlng.lng.toFixed(7)));
        },

        useMyLocation() {
            if (! navigator.geolocation) {
                return;
            }

            navigator.geolocation.getCurrentPosition(({ coords }) => {
                const latlng = L.latLng(coords.latitude, coords.longitude);

                this.map.setView(latlng, 15);
                this.pick(latlng);
            });
        },

        destroy() {
            this.map?.remove();
        },
    }));

    /**
     * Plots search results as price-tagged pins. Livewire re-sends the markers
     * after every filter change through the `vehicle-markers` browser event.
     */
    window.Alpine.data('vehicleMap', ({ markers = [], origin = null } = {}) => ({
        map: null,
        layer: null,
        originLayer: null,

        init() {
            this.map = createMap(this.$refs.map, DEFAULT_CENTER, 12);
            this.layer = L.featureGroup().addTo(this.map);
            this.render(markers, origin);
        },

        render(nextMarkers, nextOrigin) {
            this.layer.clearLayers();
            this.originLayer?.remove();
            this.originLayer = null;

            nextMarkers.forEach((marker) => {
                const icon = L.divIcon({
                    className: '',
                    html: `<span class="inline-block -translate-x-1/2 -translate-y-full whitespace-nowrap rounded-full bg-brand-600 px-2.5 py-1 text-xs font-bold text-white shadow-md ring-2 ring-white">${escapeHtml(marker.price)}</span>`,
                });

                L.marker([marker.lat, marker.lng], { icon, title: marker.name })
                    .bindPopup(`<a href="${encodeURI(marker.url)}" class="font-semibold">${escapeHtml(marker.name)}</a><br>${escapeHtml(marker.subtitle)}`)
                    .addTo(this.layer);
            });

            if (nextOrigin) {
                this.originLayer = L.circleMarker([nextOrigin.lat, nextOrigin.lng], {
                    radius: 8, color: '#fff', weight: 3, fillColor: '#2442f5', fillOpacity: 1,
                }).bindTooltip('You').addTo(this.map);
            }

            const bounds = this.layer.getBounds();

            if (nextOrigin) {
                bounds.extend([nextOrigin.lat, nextOrigin.lng]);
            }

            if (bounds.isValid()) {
                this.map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
            }
        },

        destroy() {
            this.map?.remove();
        },
    }));

    /**
     * Shows roughly where a vehicle is picked up. The exact pin is only shared
     * with the renter once a booking is confirmed, so this draws a circle.
     */
    window.Alpine.data('pickupAreaMap', ({ latitude, longitude, radiusMeters = 600 }) => ({
        map: null,

        init() {
            this.map = createMap(this.$refs.map, [latitude, longitude], 14);
            this.map.scrollWheelZoom.disable();

            L.circle([latitude, longitude], { radius: radiusMeters, color: '#2442f5', fillOpacity: 0.15 }).addTo(this.map);
        },

        destroy() {
            this.map?.remove();
        },
    }));
});

/**
 * Escape text for safe interpolation into popup HTML.
 */
function escapeHtml(value) {
    const element = document.createElement('div');
    element.textContent = String(value ?? '');

    return element.innerHTML;
}
