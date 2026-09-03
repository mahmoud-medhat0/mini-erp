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
                    // Each locale is its own chunk so a user only downloads the
                    // language they actually use (not both ar + en at once).
                    if (id.includes('/resources/js/locales/ar.json')) return 'locale-ar';
                    if (id.includes('/resources/js/locales/en.json')) return 'locale-en';

                    if (id.includes('/node_modules/datatables.net')) return 'datatables';
                    if (id.includes('/node_modules/@inertiajs/'))     return 'inertia';

                    if (
                        id.includes('/node_modules/react/') ||
                        id.includes('/node_modules/react-dom/')
                    ) return 'react-vendor';
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
    // Vite discovers dependencies lazily. When a navigation pulls in a package
    // that was not pre-bundled, the dev server stops to re-optimize and forces a
    // reload, which shows up as a page chunk stalling for seconds. Declaring the
    // heavy shared dependencies up front keeps navigation off that path.
    optimizeDeps: {
        include: [
            'react',
            'react-dom',
            'react-dom/client',
            '@inertiajs/react',
            'datatables.net-react',
            'datatables.net-dt',
            'datatables.net-responsive-dt',
        ],
    },
    server: {
        // The hot file otherwise advertises [::1]; the IPv6 round trip is slow
        // on Windows and the app is reached over 127.0.0.1 anyway.
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
        // Transform the client graph at boot instead of on first click. The cost
        // is almost entirely the shared component graph: once it is warm, an
        // extra page costs milliseconds even at 38KB of source, whereas the
        // first cold page paid 16-32s for the whole subtree it dragged in.
        warmup: {
            clientFiles: [
                './resources/js/app.tsx',
                './resources/js/lib/**/*.ts',
                './resources/js/Components/**/*.tsx',
                './resources/js/Pages/**/*.tsx',
            ],
        },
    },
});
