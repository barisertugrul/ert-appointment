/**
 * Booking store — Pinia
 *
 * Manages the multi-step booking wizard state:
 *  Step 1: Department selection (optional)
 *  Step 2: Provider selection (optional)
 *  Step 3: Date selection (calendar)
 *  Step 4: Time slot selection
 *  Step 5: Form fill
 *  Step 6: Confirmation / Success
 */

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { useApi } from '../composables/useApi.js';

export const useBookingStore = defineStore('booking', () => {
    const api = useApi();

    const BOOKING_MODES = {
        GENERAL: 'general',
        DEPARTMENT_NO_PROVIDER: 'department_no_provider',
        DEPARTMENT_WITH_PROVIDER: 'department_with_provider',
        PROVIDER_ONLY: 'provider_only',
    };

    const ALLOWED_MODES = Object.values(BOOKING_MODES);

    const currentStep = ref(1);
    const totalSteps = ref(5);
    const stepOffset = ref(0);
    const skipDepartmentStep = ref(false);
    const skipProviderStep = ref(false);
    const initOptions = ref({});

    const departments = ref([]);
    const providers = ref([]);
    const slots = ref([]);
    const form = ref(null);
    const calendar = ref({});

    const selectedDepartment = ref(null);
    const selectedProvider = ref(null);
    const selectedDate = ref('');
    const selectedSlot = ref(null);
    const formData = ref({});

    const loading = ref(false);
    const error = ref(null);

    const bookedAppointment = ref(null);
    const paymentUrl = ref(null);
    const bookingMeta = ref({});
    const bookingMode = ref(BOOKING_MODES.DEPARTMENT_WITH_PROVIDER);

    const baseI18n = computed(() => window.ertaData?.i18n ?? {});
    const i18n = computed(() => {
        const merged = { ...(baseI18n.value ?? {}) };
        const departmentLabel = String(form.value?.department_label ?? '').trim();
        const providerLabel = String(form.value?.provider_label ?? '').trim();

        if (departmentLabel) {
            merged.selectDepartment = departmentLabel;
        }

        if (providerLabel) {
            merged.selectProvider = providerLabel;
        }

        return merged;
    });
    const departmentsEnabled = computed(() => departments.value.length > 0);
    const showsDepartmentStep = computed(() => bookingMode.value === BOOKING_MODES.DEPARTMENT_NO_PROVIDER || bookingMode.value === BOOKING_MODES.DEPARTMENT_WITH_PROVIDER);
    const showsProviderStep = computed(() => bookingMode.value === BOOKING_MODES.DEPARTMENT_WITH_PROVIDER || bookingMode.value === BOOKING_MODES.PROVIDER_ONLY);
    const availableDates = computed(() =>
        Object.entries(calendar.value)
            .filter(([, count]) => count > 0)
            .map(([date]) => date)
    );

    const visualTotalSteps = computed(() => {
        return Math.max(1, totalSteps.value - stepOffset.value);
    });

    const visualCurrentStep = computed(() => {
        return Math.max(1, currentStep.value - stepOffset.value);
    });

    function resetState() {
        selectedDepartment.value = null;
        selectedProvider.value = null;
        selectedDate.value = '';
        selectedSlot.value = null;
        formData.value = {};
        bookedAppointment.value = null;
        paymentUrl.value = null;
        bookingMeta.value = {};
        providers.value = [];
        slots.value = [];
        calendar.value = {};
    }

    function normalizeBookingMode(rawMode) {
        const mode = String(rawMode || '').trim();
        if (ALLOWED_MODES.includes(mode)) {
            return mode;
        }
        return BOOKING_MODES.DEPARTMENT_WITH_PROVIDER;
    }

    function providerCandidates() {
        if (showsProviderStep.value) {
            return selectedProvider.value ? [selectedProvider.value] : [];
        }

        if (bookingMode.value === BOOKING_MODES.DEPARTMENT_NO_PROVIDER) {
            return providers.value;
        }

        if (bookingMode.value === BOOKING_MODES.GENERAL) {
            return providers.value;
        }

        return [];
    }

    async function ensureProviderCandidatesLoaded() {
        if (showsProviderStep.value) {
            return;
        }

        if (providers.value.length > 0) {
            return;
        }

        if (bookingMode.value === BOOKING_MODES.DEPARTMENT_NO_PROVIDER) {
            if (selectedDepartment.value?.id) {
                await loadProviders(selectedDepartment.value.id);
            }
            return;
        }

        if (bookingMode.value === BOOKING_MODES.GENERAL) {
            await loadProviders(null);
        }
    }

    function mergeCalendarMaps(list) {
        const merged = {};
        for (const map of list) {
            for (const [date, count] of Object.entries(map ?? {})) {
                merged[date] = (merged[date] || 0) + Number(count || 0);
            }
        }
        return merged;
    }

    function recalculateFlow() {
        skipDepartmentStep.value = !showsDepartmentStep.value || !departmentsEnabled.value;
        skipProviderStep.value = !showsProviderStep.value;

        if (!showsProviderStep.value) {
            selectedProvider.value = null;
        }

        stepOffset.value = (skipDepartmentStep.value ? 1 : 0) + (skipProviderStep.value ? 1 : 0);

        if (showsDepartmentStep.value && !selectedDepartment.value && departmentsEnabled.value) {
            currentStep.value = 1;
            return;
        }

        if (showsProviderStep.value && !selectedProvider.value) {
            currentStep.value = 2;
            return;
        }

        currentStep.value = 3;
    }

    async function init(options = {}) {
        initOptions.value = { ...options };
        loading.value = true;
        error.value = null;

        resetState();
        skipDepartmentStep.value = false;
        skipProviderStep.value = false;
        stepOffset.value = 0;
        totalSteps.value = 5;

        const requestedMode = options.generalBooking ? BOOKING_MODES.GENERAL : options.bookingMode;
        bookingMode.value = normalizeBookingMode(requestedMode);

        try {
            const { data: depts } = await api.getDepartments();
            departments.value = depts ?? [];

            if (options.preselectedDepartment) {
                const dept = departments.value.find((item) => item.slug === options.preselectedDepartment);
                if (dept) {
                    selectedDepartment.value = dept;
                }
            }

            if (showsProviderStep.value || bookingMode.value === BOOKING_MODES.GENERAL) {
                await loadProviders(bookingMode.value === BOOKING_MODES.DEPARTMENT_WITH_PROVIDER ? selectedDepartment.value?.id ?? null : null);
            } else if (bookingMode.value === BOOKING_MODES.DEPARTMENT_NO_PROVIDER && selectedDepartment.value) {
                await loadProviders(selectedDepartment.value.id);
            }

            if (options.preselectedProvider) {
                const providerId = Number(options.preselectedProvider);
                const provider = providers.value.find((item) => Number(item.id) === providerId);
                if (provider && showsProviderStep.value) {
                    selectedProvider.value = provider;
                }
            }

            recalculateFlow();
            await loadForm(options.formOverrideId ?? null);

            if (showsProviderStep.value && !selectedProvider.value) {
                error.value = i18n.value.selectProvider || 'Please select a provider.';
            }
        } finally {
            loading.value = false;
        }
    }

    async function loadProviders(departmentId = null) {
        const { data, error: err } = await api.getProviders(departmentId);
        if (err) {
            error.value = err;
            return;
        }

        providers.value = data ?? [];
    }

    async function loadCalendar(providerId, year, month) {
        const pad = (value) => String(value).padStart(2, '0');
        const from = `${year}-${pad(month)}-01`;
        const lastDay = new Date(year, month, 0).getDate();
        const to = `${year}-${pad(month)}-${pad(lastDay)}`;

        const { data, error: err } = await api.getCalendar(providerId, from, to);
        if (err) {
            error.value = err;
            return;
        }

        calendar.value = { ...calendar.value, ...(data?.availability ?? {}) };
        bookingMeta.value = { ...bookingMeta.value, ...(data?.meta ?? {}) };
    }

    async function loadCalendarForFlow(year, month) {
        await ensureProviderCandidatesLoaded();
        const candidates = providerCandidates();

        if (candidates.length === 0) {
            calendar.value = {};
            return;
        }

        const pad = (value) => String(value).padStart(2, '0');
        const from = `${year}-${pad(month)}-01`;
        const lastDay = new Date(year, month, 0).getDate();
        const to = `${year}-${pad(month)}-${pad(lastDay)}`;

        const results = await Promise.all(
            candidates.map((provider) => api.getCalendar(provider.id, from, to))
        );

        const calendars = [];
        for (const result of results) {
            if (result.error) {
                continue;
            }
            calendars.push(result.data?.availability ?? {});
            bookingMeta.value = { ...bookingMeta.value, ...(result.data?.meta ?? {}) };
        }

        calendar.value = mergeCalendarMaps(calendars);
    }

    async function loadSlots(providerId, date) {
        loading.value = true;
        slots.value = [];

        const { data, error: err } = await api.getSlots(providerId, date);

        loading.value = false;

        if (err) {
            error.value = err;
            return;
        }

        slots.value = data?.slots ?? [];
        bookingMeta.value = { ...bookingMeta.value, ...(data?.meta ?? {}) };
    }

    async function loadSlotsForFlow(date) {
        await ensureProviderCandidatesLoaded();
        const candidates = providerCandidates();

        if (candidates.length === 0) {
            slots.value = [];
            return;
        }

        loading.value = true;
        slots.value = [];

        const results = await Promise.all(
            candidates.map((provider) => api.getSlots(provider.id, date).then((res) => ({ provider, ...res })))
        );

        loading.value = false;

        if (showsProviderStep.value) {
            const only = results[0];
            if (!only || only.error) {
                error.value = only?.error || i18n.value.bookingError || 'Unable to load slots.';
                return;
            }
            slots.value = (only.data?.slots ?? []).map((slot) => ({
                ...slot,
                provider_id: only.provider.id,
            }));
            bookingMeta.value = { ...bookingMeta.value, ...(only.data?.meta ?? {}) };
            return;
        }

        const byTime = new Map();
        for (const result of results) {
            if (result.error) {
                continue;
            }

            bookingMeta.value = { ...bookingMeta.value, ...(result.data?.meta ?? {}) };
            for (const slot of (result.data?.slots ?? [])) {
                if (!slot.available) continue;
                if (!byTime.has(slot.time)) {
                    byTime.set(slot.time, {
                        ...slot,
                        provider_id: result.provider.id,
                    });
                }
            }
        }

        const mergedSlots = Array.from(byTime.values()).sort((a, b) => String(a.time).localeCompare(String(b.time)));
        slots.value = mergedSlots;
    }

    async function loadForm(formIdOverride = null) {
        const useProviderScopedForm = showsProviderStep.value && Boolean(selectedProvider.value?.id);

        let result;
        if (formIdOverride) {
            result = await api.getFormById(formIdOverride);
        } else if (useProviderScopedForm) {
            result = await api.getForm('provider', selectedProvider.value.id);
        } else if (selectedDepartment.value?.id) {
            result = await api.getForm('department', selectedDepartment.value.id);
        } else {
            result = await api.getForm('global');
        }

        if (result.error) {
            error.value = result.error;
            form.value = null;
            return;
        }

        form.value = result.data;
    }

    async function submitBooking() {
        loading.value = true;
        error.value = null;

        const resolvedProviderId = selectedSlot.value?.provider_id ?? selectedProvider.value?.id ?? null;
        const providerSelected = showsProviderStep.value && Boolean(selectedProvider.value?.id);
        const departmentSelected = showsDepartmentStep.value && Boolean(selectedDepartment.value?.id);

        const payload = {
            provider_id: providerSelected ? resolvedProviderId : null,
            resolved_provider_id: resolvedProviderId,
            department_id: departmentSelected ? selectedDepartment.value?.id ?? null : null,
            start_datetime: selectedSlot.value?.datetime,
            customer_name: formData.value.customer_name,
            customer_email: formData.value.customer_email,
            customer_phone: formData.value.customer_phone ?? '',
            notes: formData.value.notes ?? '',
            form_data: formData.value,
            selection: {
                department_selected: departmentSelected,
                provider_selected: providerSelected,
            },
        };

        const { data, error: err } = await api.bookAppointment(payload);

        loading.value = false;

        if (err) {
            error.value = err;
            return false;
        }

        bookedAppointment.value = data;

        if (data?.payment_url) {
            paymentUrl.value = data.payment_url;
        }

        currentStep.value = 6;
        return true;
    }

    function selectDepartment(department) {
        selectedDepartment.value = department;
        selectedProvider.value = null;
        selectedDate.value = '';
        selectedSlot.value = null;
        calendar.value = {};
        bookingMeta.value = {};
        loadProviders(department?.id ?? null);
        currentStep.value = showsProviderStep.value ? 2 : 3;

        loadForm();
    }

    function selectProvider(provider) {
        selectedProvider.value = provider;
        selectedDate.value = '';
        selectedSlot.value = null;
        calendar.value = {};
        bookingMeta.value = {};
        currentStep.value = 3;

        loadForm();
    }

    function selectDate(date) {
        selectedDate.value = date;
        selectedSlot.value = null;
        currentStep.value = 4;

        loadSlotsForFlow(date);
    }

    function selectSlot(slot) {
        selectedSlot.value = {
            ...slot,
            provider_id: slot.provider_id ?? selectedProvider.value?.id ?? null,
        };
        currentStep.value = 5;
    }

    function goBack() {
        if (currentStep.value <= 1) {
            return;
        }

        let nextStep = currentStep.value - 1;

        while (nextStep > 0) {
            if (nextStep === 1 && skipDepartmentStep.value) {
                nextStep -= 1;
                continue;
            }
            if (nextStep === 2 && skipProviderStep.value) {
                nextStep -= 1;
                continue;
            }
            break;
        }

        if (nextStep > 0) {
            currentStep.value = nextStep;
        }
    }

    async function reset() {
        await init(initOptions.value ?? {});
    }

    return {
        currentStep,
        totalSteps,
        stepOffset,
        skipDepartmentStep,
        skipProviderStep,
        visualCurrentStep,
        visualTotalSteps,
        departments,
        providers,
        slots,
        form,
        calendar,
        selectedDepartment,
        selectedProvider,
        selectedDate,
        selectedSlot,
        formData,
        loading,
        error,
        bookedAppointment,
        paymentUrl,
        bookingMeta,
        bookingMode,
        i18n,
        departmentsEnabled,
        showsDepartmentStep,
        showsProviderStep,
        availableDates,
        init,
        loadProviders,
        loadCalendar,
        loadCalendarForFlow,
        loadSlots,
        loadSlotsForFlow,
        loadForm,
        submitBooking,
        selectDepartment,
        selectProvider,
        selectDate,
        selectSlot,
        goBack,
        reset,
    };
});
