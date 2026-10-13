import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/pbb.css', 'resources/css/admin.css', 'resources/css/admin-workspace.css', 'resources/css/admin-services.css', 'resources/css/admin-content.css', 'resources/css/admin-health.css', 'resources/css/admin-pbb.css', 'resources/css/admin-dpt.css', 'resources/css/public-modules.css', 'resources/js/app.js', 'resources/js/admin-dpt.js'],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
