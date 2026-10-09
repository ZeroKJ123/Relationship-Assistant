import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: true,
            }),
            tailwindcss(),
        ],
        server: {
            host: '0.0.0.0',
            port: Number(env.VITE_PORT || 5176),
            strictPort: true,
            hmr: { host: new URL(env.APP_URL || 'http://localhost:8086').hostname },
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});
