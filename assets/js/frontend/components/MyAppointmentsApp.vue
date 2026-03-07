<template>
  <div class="erta-my-appointments">
    <h3 class="erta-section-title">{{ t('myAppointments') }}</h3>

    <!-- Tab: Upcoming / Past -->
    <div class="erta-tabs">
      <button
        class="erta-tab"
        :class="{ 'erta-tab--active': activeTab === 'upcoming' }"
        @click="activeTab = 'upcoming'"
      >{{ t('upcoming') }}</button>
      <button
        class="erta-tab"
        :class="{ 'erta-tab--active': activeTab === 'past' }"
        @click="activeTab = 'past'"
      >{{ t('past') }}</button>
    </div>

    <div v-if="loading" class="erta-loading"><span class="erta-spinner"></span></div>

    <div v-else-if="!visibleItems.length" class="erta-empty">
      {{ t('noAppointments') }}
    </div>

    <div v-else class="erta-appt-list">
      <div
        v-for="appt in visibleItems"
        :key="appt.id"
        class="erta-appt-card"
      >
        <div class="erta-appt-card__header">
          <span class="erta-appt-card__date">{{ formatDate(appt.start_datetime) }}</span>
          <span class="erta-appt-card__time">{{ formatTime(appt.start_datetime) }}</span>
          <span class="erta-badge" :class="`erta-badge--${appt.status}`">{{ appt.status_label }}</span>
        </div>

        <div class="erta-appt-card__body">
          <p class="erta-appt-card__provider">{{ appt.provider_name }}</p>
        </div>

        <!-- Actions: only on upcoming cancellable/reschedulable -->
        <div v-if="appt.is_cancellable || appt.is_reschedulable" class="erta-appt-card__actions">
          <button
            v-if="appt.is_reschedulable"
            class="erta-btn erta-btn--sm erta-btn--ghost"
            @click="openReschedule(appt)"
          >{{ t('reschedule') }}</button>
          <button
            v-if="appt.is_cancellable"
            class="erta-btn erta-btn--sm erta-btn--danger"
            @click="openCancel(appt)"
          >{{ t('cancel') }}</button>
        </div>
      </div>
    </div>

    <!-- Cancel modal -->
    <div v-if="cancelTarget" class="erta-modal-overlay" @click.self="cancelTarget = null">
      <div class="erta-modal">
        <h4>{{ t('cancelAppointment') }}</h4>
        <textarea
          v-model="cancelReason"
          class="erta-input"
          :placeholder="t('cancelReason')"
          rows="3"
        ></textarea>
        <div class="erta-modal-actions">
          <button class="erta-btn erta-btn--ghost" @click="cancelTarget = null">{{ t('back') }}</button>
          <button class="erta-btn erta-btn--danger" @click="confirmCancel">{{ t('confirm') }}</button>
        </div>
      </div>
    </div>

    <!-- Reschedule modal -->
    <div v-if="rescheduleTarget" class="erta-modal-overlay" @click.self="rescheduleTarget = null">
      <div class="erta-modal">
        <h4>{{ t('rescheduleAppointment') }}</h4>
        <label class="erta-form-label">{{ t('newDateTime') }}</label>
        <input type="datetime-local" class="erta-input" v-model="newDatetime" />
        <div class="erta-modal-actions">
          <button class="erta-btn erta-btn--ghost" @click="rescheduleTarget = null">{{ t('back') }}</button>
          <button class="erta-btn erta-btn--primary" @click="confirmReschedule">{{ t('confirm') }}</button>
        </div>
      </div>
    </div>

    <!-- Inline error -->
    <div v-if="error" class="erta-alert erta-alert--error">{{ error }}</div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useApi } from '../composables/useApi.js';
import { formatDateSafe, formatTimeSafe } from '../utils/locale.js';

defineProps({ userId: Number });

const api         = useApi();
const t           = (k) => window.ertaData?.i18n?.[k] ?? k;

const loading         = ref(true);
const error           = ref(null);
const appointments    = ref([]);
const activeTab       = ref('upcoming');
const cancelTarget    = ref(null);
const cancelReason    = ref('');
const rescheduleTarget = ref(null);
const newDatetime     = ref('');

const now = new Date();

const visibleItems = computed(() => {
  return appointments.value.filter(a => {
    const dt = new Date(a.start_datetime);
    return activeTab.value === 'upcoming' ? dt >= now : dt < now;
  });
});

onMounted(async () => {
  const { data, error: err } = await api.getMyAppointments();
  loading.value = false;
  if (err) { error.value = err; return; }
  appointments.value = data?.items ?? [];
});

function formatDate(dt) {
  return formatDateSafe(dt);
}

function formatTime(dt) {
  return formatTimeSafe(dt, undefined, {
    hour: '2-digit', minute: '2-digit',
  });
}

function openCancel(appt) {
  cancelTarget.value = appt;
  cancelReason.value = '';
}

function openReschedule(appt) {
  rescheduleTarget.value = appt;
  newDatetime.value = '';
}

async function confirmCancel() {
  if (!cancelTarget.value) return;
  const { error: err } = await api.cancelAppointment(cancelTarget.value.id, cancelReason.value);
  if (err) { error.value = err; return; }

  // Update local state.
  const idx = appointments.value.findIndex(a => a.id === cancelTarget.value.id);
  if (idx > -1) appointments.value[idx].status = 'cancelled';

  cancelTarget.value = null;
}

async function confirmReschedule() {
  if (!rescheduleTarget.value || !newDatetime.value) return;
  const isoDatetime = new Date(newDatetime.value).toISOString();
  const { data, error: err } = await api.rescheduleAppointment(rescheduleTarget.value.id, isoDatetime);
  if (err) { error.value = err; return; }

  // Refresh list.
  const { data: refreshed } = await api.getMyAppointments();
  if (refreshed) appointments.value = refreshed.items ?? [];

  rescheduleTarget.value = null;
}
</script>
