/**
 * Booking store — Pinia
 *
 * Manages the multi-step booking wizard state:
 *  Step 1: Department selection (optional)
 *  Step 2: Provider selection
 *  Step 3: Date selection (calendar)
 *  Step 4: Time slot selection
 *  Step 5: Form fill
 *  Step 6: Confirmation
 *  Step 7: Success / Payment redirect
 */

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { useApi } from '../composables/useApi.js';

export const useBookingStore = defineStore('booking', () => {
    const api = useApi();

    // ── Step tracking ──────────────────────────────────────────────────────
    const currentStep = ref(1);
    const totalSteps  = ref(6);

    // ── Data from API ──────────────────────────────────────────────────────
    const departments = ref([]);
    const providers   = ref([]);
    const slots       = ref([]);
    const form        = ref(null);
    const calendar    = ref({}); // { 'YYYY-MM-DD': slotsCount }

    // ── User selections ────────────────────────────────────────────────────
    const selectedDepartment = ref(null);
    const selectedProvider   = ref(null);
    const selectedDate       = ref('');     // 'YYYY-MM-DD'
    const selectedSlot       = ref(null);   // TimeSlot object
    const formData           = ref({});     // form field values

    // ── UI state ───────────────────────────────────────────────────────────
    const loading  = ref(false);
    const error    = ref(null);

    // ── Booking result ─────────────────────────────────────────────────────
    const bookedAppointment = ref(null);
    const paymentUrl        = ref(null);

    // ── Computed ───────────────────────────────────────────────────────────
    const i18n             = computed(() => window.ertaData?.i18n ?? {});
    const departmentsEnabled = computed(() => departments.value.length > 0);
    const availableDates   = computed(() =>
        Object.entries(calendar.value)
            .filter(([, count]) => count > 0)
            .map(([date]) => date)
    );

    // ── Actions ────────────────────────────────────────────────────────────

    async function init(options = {}) {
        loading.value = true;
        error.value   = null;

        try {
            // Load departments.
            const { data: depts } = await api.getDepartments();
            departments.value = depts ?? [];

            // Apply pre-selections from shortcode attributes.
            if (options.preselectedDepartment) {
                const dept = departments.value.find(d => d.slug === options.preselectedDepartment);
                if (dept) selectedDepartment.value = dept;
            }

            if (!departmentsEnabled.value) {
                currentStep.value = 2;
            } else if (selectedDepartment.value) {
                currentStep.value = 2;
            }

            // If no departments or one pre-selected, jump straight to providers.
            if (!departmentsEnabled.value || selectedDepartment.value) {
                await loadProviders(selectedDepartment.value?.id ?? null);

                if (options.preselectedProvider) {
                    const prov = providers.value.find(p => p.id === options.preselectedProvider);
                    if (prov) {
                        selectedProvider.value = prov;
                        currentStep.value = 3; // Jump to calendar.
                    }
                }
            }

            // Load form.
            await loadForm(options.formOverrideId ?? null);

        } finally {
            loading.value = false;
        }
    }

    async function loadProviders(departmentId = null) {
        const { data, error: err } = await api.getProviders(departmentId);
        if (err) { error.value = err; return; }
        providers.value = data ?? [];
    }

    async function loadCalendar(providerId, year, month) {
        const pad = (n) => String(n).padStart(2, '0');
        const from = `${year}-${pad(month)}-01`;
        // To = last day of month.
        const lastDay = new Date(year, month, 0).getDate();
        const to = `${year}-${pad(month)}-${pad(lastDay)}`;

        const { data, error: err } = await api.getCalendar(providerId, from, to);
        if (err) { error.value = err; return; }
        calendar.value = { ...calendar.value, ...(data?.availability ?? {}) };
    }

    async function loadSlots(providerId, date) {
        loading.value = true;
        slots.value   = [];

        const { data, error: err } = await api.getSlots(providerId, date);

        loading.value = false;

        if (err) { error.value = err; return; }
        slots.value = data?.slots ?? [];
    }

    async function loadForm(formIdOverride = null) {
        let result;
        if (formIdOverride) {
            result = await api.getForm('global'); // simplified; extend to pass ID
        } else if (selectedProvider.value) {
            result = await api.getForm('provider', selectedProvider.value.id);
        } else if (selectedDepartment.value) {
            result = await api.getForm('department', selectedDepartment.value.id);
        } else {
            result = await api.getForm('global');
        }
        const { data, error: err } = result;
        if (!err && data) form.value = data;
    }

    async function submitBooking() {
        loading.value = true;
        error.value   = null;

        const payload = {
            provider_id:    selectedProvider.value?.id,
            department_id:  selectedDepartment.value?.id ?? null,
            start_datetime: selectedSlot.value?.datetime,
            customer_name:  formData.value.customer_name,
            customer_email: formData.value.customer_email,
            customer_phone: formData.value.customer_phone ?? '',
            notes:          formData.value.notes ?? '',
            form_data:      formData.value,
        };

        const { data, error: err } = await api.bookAppointment(payload);

        loading.value = false;

        if (err) {
            error.value = err;
            return false;
        }

        bookedAppointment.value = data;

        // Pro: payment redirect.
        if (data?.payment_url) {
            paymentUrl.value = data.payment_url;
        }

        currentStep.value = totalSteps.value + 1; // Success step.
        return true;
    }

    function selectDepartment(dept) {
        selectedDepartment.value = dept;
        selectedProvider.value   = null;
        selectedDate.value       = '';
        selectedSlot.value       = null;
        currentStep.value        = 2;
        loadProviders(dept?.id ?? null);
    }

    function selectProvider(provider) {
        selectedProvider.value = provider;
        selectedDate.value     = '';
        selectedSlot.value     = null;
        currentStep.value      = 3;
        loadForm();
    }

    function selectDate(date) {
        selectedDate.value = date;
        selectedSlot.value = null;
        currentStep.value  = 4;
        loadSlots(selectedProvider.value.id, date);
    }

    function selectSlot(slot) {
        selectedSlot.value = slot;
        currentStep.value  = 5;
    }

    function goBack() {
        if (currentStep.value > 1) currentStep.value--;
    }

    function reset() {
        currentStep.value       = departmentsEnabled.value ? 1 : 2;
        selectedDepartment.value = null;
        selectedProvider.value   = null;
        selectedDate.value       = '';
        selectedSlot.value       = null;
        formData.value           = {};
        bookedAppointment.value  = null;
        paymentUrl.value         = null;
        error.value              = null;
    }

    return {
        // State
        currentStep, totalSteps, departments, providers, slots, form,
        calendar, selectedDepartment, selectedProvider, selectedDate,
        selectedSlot, formData, loading, error, bookedAppointment, paymentUrl,
        // Computed
        i18n, departmentsEnabled, availableDates,
        // Actions
        init, loadProviders, loadCalendar, loadSlots, loadForm, submitBooking,
        selectDepartment, selectProvider, selectDate, selectSlot, goBack, reset,
    };
});
