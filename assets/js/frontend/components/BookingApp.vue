<template>
  <div class="erta-booking-wizard">

    <!-- Progress bar -->
    <div class="erta-steps" v-if="!isComplete">
      <div
        v-for="n in store.visualTotalSteps"
        :key="n"
        class="erta-step"
        :class="{
          'erta-step--active':    n === store.visualCurrentStep,
          'erta-step--completed': n < store.visualCurrentStep,
        }"
      >{{ n }}</div>
    </div>

    <!-- Global error -->
    <div v-if="store.error" class="erta-alert erta-alert--error">
      {{ store.error }}
      <button class="erta-alert__close" @click="store.error = null">×</button>
    </div>

    <!-- Loading overlay -->
    <div v-if="store.loading" class="erta-loading">
      <span class="erta-spinner"></span>
      {{ t('loading') }}
    </div>

    <!-- Step 1: Department -->
    <DepartmentStep
      v-else-if="store.currentStep === 1 && !store.skipDepartmentStep && store.departmentsEnabled"
      :departments="store.departments"
      @select="store.selectDepartment"
    />

    <!-- Step 2: Provider -->
    <ProviderStep
      v-else-if="store.currentStep === 2 && !store.skipProviderStep"
      :providers="store.providers"
      :department="store.selectedDepartment"
      @select="store.selectProvider"
      @back="store.goBack"
    />

    <!-- Step 3: Calendar -->
    <CalendarStep
      v-else-if="store.currentStep === 3"
      :provider="store.selectedProvider"
      :availableDates="store.availableDates"
      @load-month="onLoadMonth"
      @select="store.selectDate"
      @back="store.goBack"
    />

    <!-- Step 4: Time Slots -->
    <SlotsStep
      v-else-if="store.currentStep === 4"
      :slots="store.slots"
      :date="store.selectedDate"
      @select="store.selectSlot"
      @back="store.goBack"
    />

    <!-- Step 5: Booking Form -->
    <FormStep
      v-else-if="store.currentStep === 5"
      :form="store.form"
      :summary="bookingSummary"
      :intro-text="store.bookingMeta.booking_form_intro || ''"
      v-model="store.formData"
      @submit="handleSubmit"
      @back="store.goBack"
    />

    <!-- Step 6: Success -->
    <SuccessStep
      v-else-if="isComplete"
      :appointment="store.bookedAppointment"
      :paymentUrl="store.paymentUrl"
      :appointment-location="store.bookedAppointment?.appointment_location || ''"
      :arrival-notice="store.bookedAppointment?.arrival_notice || ''"
      :post-booking-instructions="store.bookedAppointment?.post_booking_instructions || ''"
      @book-again="store.reset"
    />

  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useBookingStore } from '../stores/bookingStore.js';
import DepartmentStep from './steps/DepartmentStep.vue';
import ProviderStep   from './steps/ProviderStep.vue';
import CalendarStep   from './steps/CalendarStep.vue';
import SlotsStep      from './steps/SlotsStep.vue';
import FormStep       from './steps/FormStep.vue';
import SuccessStep    from './steps/SuccessStep.vue';

const props = defineProps({
  preselectedDepartment: { type: String,  default: null },
  preselectedProvider:   { type: Number,  default: null },
  formOverrideId:        { type: Number,  default: null },
  generalBooking:        { type: Boolean, default: false },
  lockDepartment:        { type: Boolean, default: false },
  lockProvider:          { type: Boolean, default: false },
});

const store = useBookingStore();
const t     = (key) => store.i18n[key] ?? key;

const isComplete = computed(() => store.currentStep === 6);

const bookingSummary = computed(() => ({
  department: store.selectedDepartment?.name,
  provider:   store.selectedProvider?.name,
  date:       store.selectedDate,
  time:       store.selectedSlot?.time,
  duration:   store.selectedSlot?.duration_minutes,
}));

onMounted(() => {
  store.init({
    preselectedDepartment: props.preselectedDepartment,
    preselectedProvider:   props.preselectedProvider,
    formOverrideId:        props.formOverrideId,
    generalBooking:        props.generalBooking,
    lockDepartment:        props.lockDepartment,
    lockProvider:          props.lockProvider,
  });
});

async function handleSubmit() {
  const ok = await store.submitBooking();
  if (!ok && store.paymentUrl) {
    window.location.href = store.paymentUrl;
  }
}

function onLoadMonth({ providerId, year, month }) {
  store.loadCalendar(providerId, year, month);
}
</script>
