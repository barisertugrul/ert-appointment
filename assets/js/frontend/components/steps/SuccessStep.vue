<template>
  <div class="erta-step-panel erta-step-success">

    <!-- Payment redirect scenario -->
    <template v-if="paymentUrl">
      <div class="erta-success-icon erta-success-icon--pay">💳</div>
      <h3 class="erta-success-title">{{ t('redirectingToPayment') }}</h3>
      <p>{{ t('completePayment') }}</p>
      <a :href="paymentUrl" class="erta-btn erta-btn--primary">
        {{ t('goToPayment') }}
      </a>
    </template>

    <!-- Normal success -->
    <template v-else>
      <div class="erta-success-icon">✅</div>
      <h3 class="erta-success-title">{{ t('bookingSuccess') }}</h3>

      <div v-if="appointment" class="erta-success-details">
        <div class="erta-detail-row">
          <span class="erta-detail-label">{{ t('date') }}</span>
          <span class="erta-detail-value">{{ formatDate(appointment.start_datetime) }}</span>
        </div>
        <div class="erta-detail-row">
          <span class="erta-detail-label">{{ t('time') }}</span>
          <span class="erta-detail-value">{{ formatTime(appointment.start_datetime) }}</span>
        </div>
        <div class="erta-detail-row">
          <span class="erta-detail-label">{{ t('status') }}</span>
          <span class="erta-badge" :class="`erta-badge--${appointment.status}`">
            {{ appointment.status_label }}
          </span>
        </div>
        <p class="erta-success-email-note">
          {{ t('confirmationEmailSent') }}
        </p>
      </div>

      <button class="erta-btn erta-btn--ghost" @click="$emit('book-again')">
        {{ t('bookAnother') }}
      </button>
    </template>

  </div>
</template>

<script setup>
import { formatDateSafe, formatTimeSafe } from '../../utils/locale.js';

const props = defineProps({
  appointment: { type: Object, default: null },
  paymentUrl:  { type: String, default: null },
});

defineEmits(['book-again']);

const t = (k) => window.ertaData?.i18n?.[k] ?? k;

function formatDate(dt) {
  return formatDateSafe(dt);
}

function formatTime(dt) {
  return formatTimeSafe(dt, undefined, {
    hour: '2-digit', minute: '2-digit',
  });
}
</script>
