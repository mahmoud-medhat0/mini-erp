import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('/resources/js/locales/')) {
                        return 'locales';
                    }

                    if (id.includes('/node_modules/datatables.net')) {
                        return 'datatables';
                    }

                    if (id.includes('/node_modules/@inertiajs/')) {
                        return 'inertia';
                    }

                    if (id.includes('/node_modules/react/') || id.includes('/node_modules/react-dom/')) {
                        return 'react-vendor';
                    }
                },
            },
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                    optimizedFallbacks: false,
                }),
            ],
        }),
        react(),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
