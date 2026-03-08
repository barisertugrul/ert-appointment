/**
 * ERT Appointment — Frontend Booking Widget
 * Vue 3 Composition API + Pinia stores
 *
 * Entry point. Mounts the booking app and customer appointment manager
 * on their respective DOM elements injected by shortcodes.
 */

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import BookingApp from './components/BookingApp.vue';
import MyAppointmentsApp from './components/MyAppointmentsApp.vue';

function isMountDebugEnabled() {
    const params = new URLSearchParams(window.location.search);
    const fromQuery = params.get('erta_debug_mount');

    if (fromQuery === '1' || fromQuery === 'true') {
        localStorage.setItem('erta_debug_mount', '1');
        return true;
    }

    if (fromQuery === '0' || fromQuery === 'false') {
        localStorage.removeItem('erta_debug_mount');
        return false;
    }

    return localStorage.getItem('erta_debug_mount') === '1';
}

const mountDebug = isMountDebugEnabled();

function debugLog(...args) {
    if (!mountDebug) return;
    console.info('[ERTA Mount]', ...args);
}

function debugError(...args) {
    if (!mountDebug) return;
    console.error('[ERTA Mount]', ...args);
}

// ── Booking widget ─────────────────────────────────────────────────────────
const bookingEls = document.querySelectorAll('.erta-booking-host');
debugLog('booking hosts found', bookingEls.length);
if (bookingEls.length) {
    const asBool = (value) => value === '1' || value === 'true';

    bookingEls.forEach((bookingEl, index) => {
        if (bookingEl.dataset.ertaMounted === '1') {
            debugLog('booking host skipped (already mounted)', { index });
            return;
        }

        const props = {
            preselectedDepartment: bookingEl.dataset.department || null,
            preselectedProvider:   bookingEl.dataset.provider   ? Number(bookingEl.dataset.provider) : null,
            formOverrideId:        bookingEl.dataset.form       ? Number(bookingEl.dataset.form) : null,
            bookingMode:           bookingEl.dataset.bookingMode || null,
            generalBooking:        asBool(bookingEl.dataset.generalBooking),
            lockDepartment:        asBool(bookingEl.dataset.lockDepartment),
            lockProvider:          asBool(bookingEl.dataset.lockProvider),
        };

        try {
            debugLog('booking host mounting', { index, props });
            const pinia = createPinia();
            const app = createApp(BookingApp, props);
            app.use(pinia);
            app.mount(bookingEl);
            bookingEl.dataset.ertaMounted = '1';
            debugLog('booking host mounted', { index });
        } catch (error) {
            debugError('booking host mount failed', { index, error });
        }
    });
}

// ── Customer appointments manager ─────────────────────────────────────────
const myApptEls = document.querySelectorAll('.erta-my-appointments-host');
debugLog('my appointments hosts found', myApptEls.length);
if (myApptEls.length) {
    myApptEls.forEach((myApptEl, index) => {
        if (myApptEl.dataset.ertaMounted === '1') {
            debugLog('my appointments host skipped (already mounted)', { index });
            return;
        }

        const props = {
            userId: Number(myApptEl.dataset.userId),
        };

        try {
            debugLog('my appointments host mounting', { index, props });
            const pinia = createPinia();
            const app = createApp(MyAppointmentsApp, props);
            app.use(pinia);
            app.mount(myApptEl);
            myApptEl.dataset.ertaMounted = '1';
            debugLog('my appointments host mounted', { index });
        } catch (error) {
            debugError('my appointments host mount failed', { index, error });
        }
    });
}
