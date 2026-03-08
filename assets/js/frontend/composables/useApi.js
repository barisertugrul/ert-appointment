/**
 * useApi — Composable for all WP REST API calls.
 *
 * Reads base URL and nonce from ertaData (localized by PHP Assets class).
 * All methods return { data, error } objects so callers handle errors explicitly.
 */

const BASE = window.ertaData?.restUrl ?? '/wp-json/erta/v1/';
const NONCE = window.ertaData?.nonce  ?? '';

/**
 * Generic fetch wrapper with WordPress nonce header.
 * @param {string} path
 * @param {RequestInit} options
 * @returns {Promise<{data: any, error: string|null}>}
 */
async function request(path, options = {}) {
    try {
        const response = await fetch(`${BASE}${path}`, {
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': NONCE,
                ...(options.headers ?? {}),
            },
            ...options,
        });

        const json = await response.json();

        if (!response.ok) {
            return { data: null, error: json?.message ?? `HTTP ${response.status}` };
        }

        return { data: json, error: null };
    } catch (err) {
        return { data: null, error: err.message ?? 'Network error' };
    }
}

// ─── Composable ─────────────────────────────────────────────────────────────

export function useApi() {
    // ── Departments ───────────────────────────────────────────────────────
    const getDepartments = () => request('departments');

    // ── Providers ─────────────────────────────────────────────────────────
    const getProviders = (departmentId = null) => {
        const qs = departmentId ? `?department_id=${departmentId}` : '';
        return request(`providers${qs}`);
    };

    // ── Availability ──────────────────────────────────────────────────────
    const getCalendar = (providerId, from, to) =>
        request(`providers/${providerId}/calendar?from=${from}&to=${to}`);

    const getSlots = (providerId, date) =>
        request(`providers/${providerId}/slots?date=${date}`);

    // ── Forms ─────────────────────────────────────────────────────────────
    const getForm = (scope = 'global', scopeId = null) => {
        const path = scopeId ? `forms/${scope}/${scopeId}` : `forms/${scope}`;
        return request(path);
    };

    const getFormById = (formId) => {
        const numericId = Number(formId || 0);
        if (!numericId) {
            return Promise.resolve({ data: null, error: 'Invalid form ID.' });
        }

        return request(`forms/id/${numericId}`);
    };

    // ── Appointments ──────────────────────────────────────────────────────
    const bookAppointment = (payload) =>
        request('appointments', {
            method: 'POST',
            body: JSON.stringify(payload),
        });

    const cancelAppointment = (id, reason = '') =>
        request(`appointments/${id}/cancel`, {
            method: 'PUT',
            body: JSON.stringify({ reason }),
        });

    const rescheduleAppointment = (id, newStartDatetime, notes = '') =>
        request(`appointments/${id}/reschedule`, {
            method: 'PUT',
            body: JSON.stringify({ new_start_datetime: newStartDatetime, notes }),
        });

    const getMyAppointments = () => request('my-appointments');

    const confirmAppointment = (id) =>
        request(`appointments/${id}/confirm`, { method: 'PUT' });

    return {
        getDepartments,
        getProviders,
        getCalendar,
        getSlots,
        getForm,
        getFormById,
        bookAppointment,
        cancelAppointment,
        rescheduleAppointment,
        getMyAppointments,
        confirmAppointment,
    };
}
