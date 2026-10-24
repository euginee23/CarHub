import {
    defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        cors: true,
        watch: {
            // Only front-end sources need watching. The Python virtualenv (TensorFlow)
            // and vendor/ alone exceed Linux's file-watcher limit and crash the dev server.
            ignored: ['**/storage/**', '**/vendor/**', '**/ml/**', '**/public/build/**', '**/bootstrap/cache/**'],
        },
    },
});
