import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import {
    defineConfig
} from 'vite';
import tailwindcss from "@tailwindcss/vite";
import { safariCssCompat } from './vite-plugins/safari-css-compat.js';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.jsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
        // Convert oklch → sRGB so iPhone 6s/7 (Safari 15) keep colors & hero visible
        safariCssCompat(),
    ],
    esbuild: {
        jsx: 'automatic',
    },
    build: {
        // Keep ES2019 so older WebKit can parse the shop JS more reliably
        target: ['es2019', 'safari13'],
    },
});