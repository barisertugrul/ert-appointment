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

    const i18n = computed(() => window.ertaData?.i18n ?? {});
    const departmentsEnabled = computed(() => departments.value.length > 0);
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

    function recalculateFlow() {
        skipDepartmentStep.value =
            !departmentsEnabled.value ||
            Boolean(initOptions.value.generalBooking) ||
            (Boolean(initOptions.value.lockDepartment) && Boolean(selectedDepartment.value));

        skipProviderStep.value =
            Boolean(initOptions.value.generalBooking) ||
            (Boolean(initOptions.value.lockProvider) && Boolean(selectedProvider.value));

        stepOffset.value = (skipDepartmentStep.value ? 1 : 0) + (skipProviderStep.value ? 1 : 0);

        if (selectedProvider.value) {
            currentStep.value = 3;
        } else if (skipDepartmentStep.value) {
            currentStep.value = 2;
        } else {
            currentStep.value = 1;
        }
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

        try {
            const { data: depts } = await api.getDepartments();
            departments.value = depts ?? [];

            if (options.preselectedDepartment) {
                const dept = departments.value.find((item) => item.slug === options.preselectedDepartment);
                if (dept) {
                    selectedDepartment.value = dept;
                }
            }

            if (!departmentsEnabled.value || selectedDepartment.value || options.generalBooking) {
                await loadProviders(selectedDepartment.value?.id ?? null);
            }

            if (options.preselectedProvider) {
                const providerId = Number(options.preselectedProvider);
                const provider = providers.value.find((item) => Number(item.id) === providerId);
                if (provider) {
                    selectedProvider.value = provider;
                }
            }

            if (options.generalBooking && !selectedProvider.value && providers.value.length > 0) {
                const preferredId = Number(bookingMeta.value?.general_provider_id || 0);
                selectedProvider.value = providers.value.find((item) => Number(item.id) === preferredId) ?? providers.value[0];
            }

            recalculateFlow();
            await loadForm(options.formOverrideId ?? null);

            if (skipProviderStep.value && !selectedProvider.value) {
                error.value = i18n.value.bookingError || 'Unable to resolve provider for general booking.';
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

    async function loadForm(formIdOverride = null) {
        let result;
        if (formIdOverride) {
            result = await api.getForm('global');
        } else if (selectedDepartment.value?.id) {
            result = await api.getForm('department', selectedDepartment.value.id);
        } else if (selectedProvider.value?.id) {
            result = await api.getForm('provider', selectedProvider.value.id);
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

        const payload = {
            provider_id: selectedProvider.value?.id,
            department_id: selectedDepartment.value?.id ?? null,
            start_datetime: selectedSlot.value?.datetime,
            customer_name: formData.value.customer_name,
            customer_email: formData.value.customer_email,
            customer_phone: formData.value.customer_phone ?? '',
            notes: formData.value.notes ?? '',
            form_data: formData.value,
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
        currentStep.value = 2;

        loadProviders(department?.id ?? null);
    }

    function selectProvider(provider) {
        selectedProvider.value = provider;
        selectedDate.value = '';
        selectedSlot.value = null;
        currentStep.value = 3;

        loadForm();
    }

    function selectDate(date) {
        selectedDate.value = date;
        selectedSlot.value = null;
        currentStep.value = 4;

        loadSlots(selectedProvider.value.id, date);
    }

    function selectSlot(slot) {
        selectedSlot.value = slot;
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
        i18n,
        departmentsEnabled,
        availableDates,
        init,
        loadProviders,
        loadCalendar,
        loadSlots,
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
