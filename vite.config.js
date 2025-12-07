import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/ha.css',
                'resources/css/scheduled-jobs.css',
                'resources/js/app.js',
                'resources/js/homeassistant-monitor.js',
            ],
            refresh: true,
        }),
    ],
});
