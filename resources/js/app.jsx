import '@shopify/polaris/build/esm/styles.css';
import axios from 'axios';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { AppProvider } from '@shopify/polaris';
import en from '@shopify/polaris/locales/en.json';
import AppFrame from '@/components/AppFrame';

// Every Inertia visit carries a fresh App Bridge session token (the app runs in the Shopify
// admin iframe, where cookies are unreliable). The server answers 401 with a retry header when a
// token has expired; App Bridge then issues a new one.
axios.interceptors.request.use(async (config) => {
    if (window.shopify?.idToken) {
        config.headers.Authorization = 'Bearer ' + (await window.shopify.idToken());
    }
    return config;
});

const pages = import.meta.glob('./pages/**/*.jsx');

createInertiaApp({
    title: (title) => (title ? title + ' · Composa' : 'Composa'),
    resolve: async (name) => {
        const page = await pages[`./pages/${name}.jsx`]();
        const Component = page.default;
        Component.layout ??= (children) => <AppFrame>{children}</AppFrame>;
        return Component;
    },
    setup({ el, App, props }) {
        createRoot(el).render(
            <AppProvider i18n={en}>
                <App {...props} />
            </AppProvider>,
        );
    },
    progress: { color: '#0E7C6B' },
});
