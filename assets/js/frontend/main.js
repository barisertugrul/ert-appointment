/**
 * WP Appointment — Frontend Booking Widget
 * Vue 3 Composition API + Pinia stores
 *
 * Entry point. Mounts the booking app and customer appointment manager
 * on their respective DOM elements injected by shortcodes.
 */

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import BookingApp from './components/BookingApp.vue';
import MyAppointmentsApp from './components/MyAppointmentsApp.vue';

// ── Booking widget ─────────────────────────────────────────────────────────
const bookingEl = document.getElementById('erta-booking-app');
if (bookingEl) {
    const pinia = createPinia();
    const app   = createApp(BookingApp, {
        preselectedDepartment: bookingEl.dataset.department || null,
        preselectedProvider:   bookingEl.dataset.provider   ? Number(bookingEl.dataset.provider) : null,
        formOverrideId:        bookingEl.dataset.form       ? Number(bookingEl.dataset.form) : null,
    });
    app.use(pinia);
    app.mount(bookingEl);
}

// ── Customer appointments manager ─────────────────────────────────────────
const myApptEl = document.getElementById('erta-my-appointments-app');
if (myApptEl) {
    const pinia = createPinia();
    const app   = createApp(MyAppointmentsApp, {
        userId: Number(myApptEl.dataset.userId),
    });
    app.use(pinia);
    app.mount(myApptEl);
}
