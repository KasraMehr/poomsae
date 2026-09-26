import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { resolve } from 'node:path';

export default defineConfig({
    plugins: [
        laravel({ input: ['resources/js/app.js'], refresh: true }),
        vue(),
        tailwindcss(),
    ],
    resolve: {
        // Mercure support is merged in Echo 2.x but not yet present in its npm release.
        alias: { 'laravel-echo': resolve('node_modules/laravel-echo/packages/laravel-echo/src/echo.ts') },
    },
    server: { watch: { ignored: ['**/storage/framework/views/**'] } },
});

