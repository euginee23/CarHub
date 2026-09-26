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
});
