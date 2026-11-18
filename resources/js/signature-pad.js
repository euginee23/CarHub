/**
 * A signature pad for signing the rental contract with a finger, pen, or mouse.
 * Strokes are kept as points (outside Alpine's reactivity, so drawing stays smooth)
 * and redrawn on every frame, which lets the pad survive resizes and undo. The
 * finished signature is trimmed to its ink and handed back as a PNG file.
 *
 * Tells its parent how it is doing with a `signature-changed` event carrying
 * `{ hasSignature }`.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('signaturePad', ({ color = '#1e2d8a', lineWidth = 2.6, minimumPoints = 10 } = {}) => {
        let strokes = [];
        let current = null;
        let frame = null;
        let observer = null;

        const midpoint = (a, b) => ({ x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 });

        return {
            inkPoints: 0,

            init() {
                this.fit();
                observer = new ResizeObserver(() => this.fit());
                observer.observe(this.$refs.canvas);
            },

            destroy() {
                observer?.disconnect();
                cancelAnimationFrame(frame);
            },

            /** Match the canvas to its size on screen (and the screen's pixel density). */
            fit() {
                const canvas = this.$refs.canvas;
                const { width, height } = canvas.getBoundingClientRect();

                if (! width || ! height) {
                    return;
                }

                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = Math.round(width * ratio);
                canvas.height = Math.round(height * ratio);
                canvas.getContext('2d').setTransform(ratio, 0, 0, ratio, 0, 0);

                this.redraw();
            },

            point(event) {
                const rect = this.$refs.canvas.getBoundingClientRect();

                return { x: event.clientX - rect.left, y: event.clientY - rect.top };
            },

            start(event) {
                if (event.button > 0) {
                    return;
                }

                event.preventDefault();
                this.$refs.canvas.setPointerCapture?.(event.pointerId);
                current = [this.point(event)];
                strokes.push(current);
                this.inkPoints += 1;
                this.schedule();
            },

            move(event) {
                if (! current) {
                    return;
                }

                event.preventDefault();
                (event.getCoalescedEvents?.() ?? [event]).forEach((moved) => current.push(this.point(moved)));
                this.schedule();
            },

            end() {
                if (! current) {
                    return;
                }

                current = null;
                this.changed();
            },

            undo() {
                strokes.pop();
                this.changed();
                this.redraw();
            },

            clear() {
                strokes = [];
                this.changed();
                this.redraw();
            },

            changed() {
                this.inkPoints = strokes.reduce((total, stroke) => total + stroke.length, 0);
                this.$dispatch('signature-changed', { hasSignature: this.inkPoints >= minimumPoints });
            },

            schedule() {
                if (frame) {
                    return;
                }

                frame = requestAnimationFrame(() => {
                    frame = null;
                    this.redraw();
                });
            },

            style(context) {
                context.strokeStyle = color;
                context.fillStyle = color;
                context.lineWidth = lineWidth;
                context.lineCap = 'round';
                context.lineJoin = 'round';
            },

            redraw() {
                const context = this.$refs.canvas.getContext('2d');

                context.save();
                context.setTransform(1, 0, 0, 1, 0, 0);
                context.clearRect(0, 0, context.canvas.width, context.canvas.height);
                context.restore();

                this.style(context);
                strokes.forEach((stroke) => this.paint(context, stroke, { x: 0, y: 0 }));
            },

            /** Draw one stroke as a smooth curve through the midpoints of its points. */
            paint(context, points, offset) {
                const at = (point) => [point.x - offset.x, point.y - offset.y];

                if (points.length === 1) {
                    context.beginPath();
                    context.arc(...at(points[0]), lineWidth / 2, 0, Math.PI * 2);
                    context.fill();

                    return;
                }

                context.beginPath();
                context.moveTo(...at(points[0]));

                for (let index = 1; index < points.length - 1; index++) {
                    context.quadraticCurveTo(...at(points[index]), ...at(midpoint(points[index], points[index + 1])));
                }

                context.lineTo(...at(points[points.length - 1]));
                context.stroke();
            },

            /** The signature trimmed to its ink (with a margin), as a PNG file. */
            toFile({ margin = 12, scale = 2, minWidth = 160, minHeight = 60 } = {}) {
                const all = strokes.flat();
                const left = Math.min(...all.map((point) => point.x));
                const top = Math.min(...all.map((point) => point.y));
                const width = Math.max(Math.max(...all.map((point) => point.x)) - left + margin * 2, minWidth);
                const height = Math.max(Math.max(...all.map((point) => point.y)) - top + margin * 2, minHeight);

                const canvas = document.createElement('canvas');
                canvas.width = Math.ceil(width * scale);
                canvas.height = Math.ceil(height * scale);

                const context = canvas.getContext('2d');
                context.setTransform(scale, 0, 0, scale, 0, 0);
                this.style(context);
                strokes.forEach((stroke) => this.paint(context, stroke, { x: left - margin, y: top - margin }));

                return new Promise((resolve, reject) => canvas.toBlob(
                    (blob) => (blob ? resolve(new File([blob], 'signature.png', { type: 'image/png' })) : reject(new Error('Could not save the signature.'))),
                    'image/png',
                ));
            },
        };
    });
});
