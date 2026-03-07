/**
 * useAdminApi — Admin REST API composable.
 * Extends the base API with admin-only endpoints.
 */

const BASE  = window.ertaAdminData?.restUrl ?? '/wp-json/erta/v1/';
const NONCE = window.ertaAdminData?.nonce   ?? '';

async function req(path, options = {}) {
    try {
        const res  = await fetch(`${BASE}${path}`, {
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE, ...(options.headers ?? {}) },
            ...options,
        });
        const json = await res.json();
        if (!res.ok) return { data: null, error: json?.message ?? `HTTP ${res.status}` };
        return { data: json, error: null };
    } catch (e) {
        return { data: null, error: e.message };
    }
}

export function useAdminApi() {
    // ── Appointments ──────────────────────────────────────────────────────
    const listAppointments = (params = {}) => {
        const qs = new URLSearchParams(params).toString();
        return req(`admin/appointments${qs ? '?' + qs : ''}`);
    };
    const confirmAppointment   = (id)         => req(`appointments/${id}/confirm`,   { method: 'POST' });
    const unconfirmAppointment = (id)         => req(`appointments/${id}/unconfirm`, { method: 'POST' });
    const cancelAppointment    = (id, reason) => req(`appointments/${id}/cancel`,    { method: 'POST', body: JSON.stringify({ reason }) });
    const rescheduleAppointment = (id, dt)    => req(`appointments/${id}/reschedule`,{ method: 'POST', body: JSON.stringify({ new_start_datetime: dt }) });

    // ── Departments ───────────────────────────────────────────────────────
    const listDepartments  = ()           => req('admin/departments');
    const saveDepartment   = (data)       => req('admin/departments' + (data.id ? `/${data.id}` : ''), {
        method: data.id ? 'PUT' : 'POST', body: JSON.stringify(data),
    });
    const deleteDepartment = (id)         => req(`admin/departments/${id}`, { method: 'DELETE' });

    // ── Providers ─────────────────────────────────────────────────────────
    const listProviders  = (params = {}) => {
        const qs = new URLSearchParams(params).toString();
        return req(`admin/providers${qs ? '?' + qs : ''}`);
    };
    const saveProvider   = (data)  => req('admin/providers' + (data.id ? `/${data.id}` : ''), {
        method: data.id ? 'PUT' : 'POST', body: JSON.stringify(data),
    });
    const deleteProvider = (id)    => req(`admin/providers/${id}`, { method: 'DELETE' });

    // ── Settings ──────────────────────────────────────────────────────────
    const getSettings    = (scope, scopeId) => {
        const qs = scopeId ? `?scope=${scope}&scope_id=${scopeId}` : `?scope=${scope}`;
        return req(`admin/settings${qs}`);
    };
    const saveSettings   = (scope, scopeId, data) => req('admin/settings', {
        method: 'POST', body: JSON.stringify({ scope, scope_id: scopeId, settings: data }),
    });
    const repairInstallation = () => req('admin/settings/repair', { method: 'POST' });

    // ── Forms ─────────────────────────────────────────────────────────────
    const getForms    = ()     => req('admin/forms');
    const saveForm    = (data) => req('admin/forms' + (data.id ? `/${data.id}` : ''), {
        method: data.id ? 'PUT' : 'POST', body: JSON.stringify(data),
    });
    const deleteForm  = (id)   => req(`admin/forms/${id}`, { method: 'DELETE' });

    // ── Notification templates ────────────────────────────────────────────
    const getTemplates  = ()     => req('admin/notification-templates');
    const getPlaceholders = ()   => req('admin/notification-templates/placeholders');
    const saveTemplate  = (data) => req('admin/notification-templates' + (data.id ? `/${data.id}` : ''), {
        method: data.id ? 'PUT' : 'POST', body: JSON.stringify(data),
    });

    // ── Reports (Pro) ─────────────────────────────────────────────────────
    const getReports    = (params = {}) => {
        const qs = new URLSearchParams(params).toString();
        return req(`admin/reports${qs ? '?' + qs : ''}`);
    };

    // ── Working hours ─────────────────────────────────────────────────────
    const getWorkingHours  = (scope, scopeId) => {
        const qs = new URLSearchParams({ scope, ...(scopeId ? { scope_id: scopeId } : {}) }).toString();
        return req(`admin/working-hours?${qs}`);
    };
    const saveWorkingHours = (scope, scopeId, data) => req('admin/working-hours', {
        method: 'POST',
        body: JSON.stringify({ scope, scope_id: scopeId, hours: data }),
    });

    // ── Breaks ────────────────────────────────────────────────────────────
    const getBreaks  = (scope, scopeId) => {
        const qs = new URLSearchParams({ scope, ...(scopeId ? { scope_id: scopeId } : {}) }).toString();
        return req(`admin/breaks?${qs}`);
    };
    const saveBreaks = (scope, scopeId, data) => req('admin/breaks', {
        method: 'POST',
        body: JSON.stringify({ scope, scope_id: scopeId, breaks: data }),
    });

    // ── Special days ──────────────────────────────────────────────────────
    const getSpecialDays  = (scope, scopeId) => {
        const qs = new URLSearchParams({ scope, ...(scopeId ? { scope_id: scopeId } : {}) }).toString();
        return req(`admin/special-days?${qs}`);
    };
    const saveSpecialDays = (scope, scopeId, data) => req('admin/special-days', {
        method: 'POST',
        body: JSON.stringify({ scope, scope_id: scopeId, days: data }),
    });

    return {
        listAppointments, confirmAppointment, unconfirmAppointment, cancelAppointment, rescheduleAppointment,
        listDepartments, saveDepartment, deleteDepartment,
        listProviders, saveProvider, deleteProvider,
        getSettings, saveSettings, repairInstallation,
        getForms, saveForm, deleteForm,
        getTemplates, getPlaceholders, saveTemplate,
        getReports,
        getWorkingHours, saveWorkingHours,
        getBreaks, saveBreaks,
        getSpecialDays, saveSpecialDays,
    };
}
