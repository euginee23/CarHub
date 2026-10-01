/**
 * Turns a phone or laptop into a GPS tracker for the admin GPS test page: it
 * watches the device's position and POSTs it to the tracking API with the
 * vehicle tracker's token, exactly as the ESP does.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('gpsSender', ({ endpoint, token = '', latitude = null, longitude = null } = {}) => ({
        endpoint,
        token,
        interval: 10,
        running: false,
        watchId: null,
        timer: null,
        fix: null,
        error: null,
        sent: 0,
        lastStatus: null,
        lastResponse: null,
        secure: window.isSecureContext,
        supported: 'geolocation' in navigator,
        manualLat: latitude,
        manualLng: longitude,

        start() {
            this.error = null;

            if (! this.token) {
                this.error = 'Paste the tracker token first.';

                return;
            }

            if (! this.supported) {
                this.error = 'This browser has no GPS access.';

                return;
            }

            this.running = true;

            this.watchId = navigator.geolocation.watchPosition(
                (position) => {
                    const first = this.fix === null;

                    this.fix = {
                        lat: Number(position.coords.latitude.toFixed(7)),
                        lng: Number(position.coords.longitude.toFixed(7)),
                        accuracy: Math.round(position.coords.accuracy),
                        // The Geolocation API reports m/s; the tracking API expects km/h.
                        speed: Number.isFinite(position.coords.speed) ? Math.round(position.coords.speed * 3.6 * 10) / 10 : null,
                        heading: Number.isFinite(position.coords.heading) ? Math.round(position.coords.heading) % 360 : null,
                        at: new Date().toLocaleTimeString(),
                    };

                    if (first) {
                        this.sendLatest();
                    }
                },
                (failure) => {
                    this.error = failure.code === failure.PERMISSION_DENIED
                        ? 'Location permission was denied. Allow it in the browser settings and try again.'
                        : `Could not get a GPS fix: ${failure.message}`;
                },
                { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 },
            );

            this.timer = setInterval(() => this.sendLatest(), this.interval * 1000);
        },

        stop() {
            if (this.watchId !== null) {
                navigator.geolocation.clearWatch(this.watchId);
            }

            clearInterval(this.timer);
            this.watchId = null;
            this.timer = null;
            this.running = false;
        },

        sendLatest() {
            if (this.fix) {
                const { lat, lng, speed, heading } = this.fix;

                this.send({ lat, lng, ...(speed !== null && { speed }), ...(heading !== null && { heading }) });
            }
        },

        sendManual() {
            if (! this.token) {
                this.error = 'Paste the tracker token first.';

                return;
            }

            this.error = null;
            this.send({ lat: Number(this.manualLat), lng: Number(this.manualLng) });
        },

        async send(payload) {
            try {
                const response = await fetch(this.endpoint, {
                    method: 'POST',
                    headers: {
                        Authorization: `Bearer ${this.token}`,
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                    },
                    body: JSON.stringify(payload),
                });

                this.lastStatus = response.status;
                this.lastResponse = await response.text();
                this.sent++;
            } catch (failure) {
                this.lastStatus = null;
                this.error = `Request failed: ${failure.message}`;
            }
        },

        destroy() {
            this.stop();
        },
    }));
});
