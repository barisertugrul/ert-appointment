/**
 * WP Appointment — Admin SPA Entry Point
 * Vue 3 + Pinia. Mounts on #erta-admin-app and #erta-provider-app.
 */

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import AdminApp    from './components/AdminApp.vue';
import ProviderApp from './components/ProviderApp.vue';

// ── Admin panel ───────────────────────────────────────────────────────────
const adminEl = document.getElementById('erta-admin-app');
if (adminEl) {
    const app = createApp(AdminApp, { page: adminEl.dataset.page ?? 'erta-dashboard' });
    app.use(createPinia());
    app.mount(adminEl);
}

// ── Provider panel ────────────────────────────────────────────────────────
const providerEl = document.getElementById('erta-provider-app');
if (providerEl) {
    const app = createApp(ProviderApp, {
        page:   providerEl.dataset.page   ?? 'erta-provider-dashboard',
        userId: Number(providerEl.dataset.userId ?? 0),
    });
    app.use(createPinia());
    app.mount(providerEl);
}
