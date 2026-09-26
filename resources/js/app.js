import '../css/app.css';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { createPinia } from 'pinia';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

createInertiaApp({
    title: (title) => title ? title + ' | پومسه' : 'پومسه',
    resolve: (name) => resolvePageComponent('./Pages/' + name + '.vue', import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) }).use(plugin).use(createPinia()).mount(el);
    },
    progress: { color: '#287668' },
});

