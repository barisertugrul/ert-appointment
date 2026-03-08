<template>
  <div class="erta-booking-wizard" :style="wizardStyle">

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
      :title-text="store.i18n.selectDepartment || ''"
      @select="store.selectDepartment"
    />

    <!-- Step 2: Provider -->
    <ProviderStep
      v-else-if="store.currentStep === 2 && !store.skipProviderStep"
      :providers="store.providers"
      :department="store.selectedDepartment"
      :title-text="store.i18n.selectProvider || ''"
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
      :intro-color="store.bookingMeta.booking_form_intro_color || ''"
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
      :post-booking-color="store.bookedAppointment?.post_booking_instructions_color || store.bookingMeta.post_booking_instructions_color || ''"
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
  bookingMode:          { type: String,  default: null },
  generalBooking:        { type: Boolean, default: false },
  lockDepartment:        { type: Boolean, default: false },
  lockProvider:          { type: Boolean, default: false },
});

const store = useBookingStore();
const t     = (key) => store.i18n[key] ?? key;

const isComplete = computed(() => store.currentStep === 6);

const wizardStyle = computed(() => {
  const styles = store.form?.ui_styles ?? {};
  const vars = {};

  if (styles.primary_color) vars['--erta-pro-primary'] = styles.primary_color;
  if (styles.panel_background) vars['--erta-pro-panel-bg'] = styles.panel_background;
  if (styles.panel_radius) vars['--erta-pro-panel-radius'] = styles.panel_radius;
  if (styles.button_radius) vars['--erta-pro-button-radius'] = styles.button_radius;
  if (styles.input_radius) vars['--erta-pro-input-radius'] = styles.input_radius;
  if (styles.title_font_size) vars['--erta-pro-title-size'] = styles.title_font_size;
  if (styles.body_font_size) vars['--erta-pro-body-size'] = styles.body_font_size;
  if (styles.card_border_width) vars['--erta-pro-card-border-width'] = styles.card_border_width;
  if (styles.card_border_color) vars['--erta-pro-card-border-color'] = styles.card_border_color;

  return vars;
});

const bookingSummary = computed(() => ({
  department: store.selectedDepartment?.name,
  provider:   store.showsProviderStep ? store.selectedProvider?.name : null,
  date:       store.selectedDate,
  time:       store.selectedSlot?.time,
  duration:   store.selectedSlot?.duration_minutes,
}));

onMounted(() => {
  store.init({
    preselectedDepartment: props.preselectedDepartment,
    preselectedProvider:   props.preselectedProvider,
    formOverrideId:        props.formOverrideId,
    bookingMode:           props.bookingMode,
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

function onLoadMonth({ year, month }) {
  store.loadCalendarForFlow(year, month);
}
</script>
