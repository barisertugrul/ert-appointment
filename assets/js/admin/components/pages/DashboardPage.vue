<template>
  <div class="erta-page">
    <div class="erta-page-header">
      <h1 class="erta-page-title">{{ t('dashboard') }}</h1>
    </div>

    <div v-if="loading" class="erta-loading"><span class="erta-spinner"></span></div>

    <template v-else>
      <!-- Stats -->
      <div class="erta-stats-grid">
        <div class="erta-stat-card">
          <div class="erta-stat-card__value">{{ stats.today ?? 0 }}</div>
          <div class="erta-stat-card__label">{{ t('todayAppointments') }}</div>
        </div>
        <div class="erta-stat-card">
          <div class="erta-stat-card__value">{{ stats.pending ?? 0 }}</div>
          <div class="erta-stat-card__label">{{ t('pending') }}</div>
        </div>
        <div class="erta-stat-card">
          <div class="erta-stat-card__value">{{ stats.confirmed ?? 0 }}</div>
          <div class="erta-stat-card__label">{{ t('confirmed') }}</div>
        </div>
        <div class="erta-stat-card">
          <div class="erta-stat-card__value">{{ stats.thisMonth ?? 0 }}</div>
          <div class="erta-stat-card__label">{{ t('thisMonth') }}</div>
        </div>
      </div>

      <!-- Upcoming appointments table -->
      <h2 class="erta-section-title">{{ t('upcomingAppointments') }}</h2>
      <div class="erta-table-wrap">
        <table class="erta-table">
          <thead>
            <tr>
              <th>#</th>
              <th>{{ t('customer') }}</th>
              <th>{{ t('provider') }}</th>
              <th>{{ t('date') }}</th>
              <th>{{ t('time') }}</th>
              <th>{{ t('status') }}</th>
              <th>{{ t('actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!upcoming.length">
              <td colspan="7" style="text-align:center;color:#9ca3af;padding:24px">
                {{ t('noUpcomingAppointments') }}
              </td>
            </tr>
            <tr v-for="appt in upcoming" :key="appt.id">
              <td>#{{ appt.id }}</td>
              <td>
                <div>{{ appt.customer_name }}</div>
                <div style="font-size:.8rem;color:#6b7280">{{ appt.customer_email }}</div>
              </td>
              <td>{{ appt.provider_name ?? '—' }}</td>
              <td>{{ formatDate(appt.start_datetime) }}</td>
              <td>{{ formatTime(appt.start_datetime) }}</td>
              <td>
                <span class="erta-badge" :class="`erta-badge--${appt.status}`">
                  {{ appt.status_label }}
                </span>
              </td>
              <td>
                <div style="display:flex;gap:6px">
                  <button
                    v-if="appt.status === 'pending'"
                    class="erta-btn erta-btn--sm erta-btn--primary"
                    :disabled="actionLoading"
                    @click="doConfirm(appt)"
                  >
                    ✓
                  </button>
                  <button
                    class="erta-btn erta-btn--sm erta-btn--danger"
                    :disabled="actionLoading"
                    @click="openCancel(appt)"
                  >✕</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="cancelTarget" class="erta-modal-overlay" @click.self="cancelTarget = null">
        <div class="erta-modal">
          <h4>{{ t('cancelAppointment') }} #{{ cancelTarget.id }}</h4>
          <textarea
            class="erta-input"
            v-model="cancelReason"
            :placeholder="t('cancelReason')"
            rows="3"
          ></textarea>
          <div class="erta-modal-actions">
            <button class="erta-btn erta-btn--ghost" :disabled="actionLoading" @click="cancelTarget = null">{{ t('back') }}</button>
            <button class="erta-btn erta-btn--danger" :disabled="actionLoading" @click="doCancel">{{ t('confirm') }}</button>
          </div>
        </div>
      </div>
    </template>

    <div v-if="error" class="erta-alert erta-alert--error">{{ error }}</div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useAdminApi } from '../../composables/useAdminApi.js';

const api     = useAdminApi();
const t       = (k) => window.ertaAdminData?.i18n?.[k] ?? k;
const loading = ref(true);
const actionLoading = ref(false);
const error = ref(null);
const stats   = ref({});
const upcoming = ref([]);
const cancelTarget = ref(null);
const cancelReason = ref('');

onMounted(async () => {
  const today = new Date().toISOString().slice(0, 10);
  const { data } = await api.listAppointments({ date_from: today, per_page: 20 });
  loading.value = false;
  if (data) {
    upcoming.value = data.items ?? [];
    stats.value = {
      today:     (data.items ?? []).filter(a => a.start_datetime.startsWith(today)).length,
      pending:   (data.items ?? []).filter(a => a.status === 'pending').length,
      confirmed: (data.items ?? []).filter(a => a.status === 'confirmed').length,
      thisMonth: data.total ?? 0,
    };
  }
});

function formatDate(dt) { return new Date(dt).toLocaleDateString(); }
function formatTime(dt) { return new Date(dt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }

function openCancel(appt) {
  cancelTarget.value = appt;
  cancelReason.value = '';
}

async function doConfirm(appt) {
  actionLoading.value = true;
  error.value = null;
  const { error: err } = await api.confirmAppointment(appt.id);
  actionLoading.value = false;
  if (err) {
    error.value = err;
    return;
  }

  appt.status = 'confirmed';
  appt.status_label = t('confirmed');
  stats.value.pending = Math.max(0, (stats.value.pending ?? 0) - 1);
  stats.value.confirmed = (stats.value.confirmed ?? 0) + 1;
}

async function doCancel() {
  if (!cancelTarget.value) {
    return;
  }

  actionLoading.value = true;
  error.value = null;
  const target = cancelTarget.value;
  const { error: err } = await api.cancelAppointment(target.id, cancelReason.value);
  actionLoading.value = false;

  if (err) {
    error.value = err;
    return;
  }

  const appt = upcoming.value.find(a => a.id === target.id);
  if (appt) {
    if (appt.status === 'pending') {
      stats.value.pending = Math.max(0, (stats.value.pending ?? 0) - 1);
    }
    if (appt.status === 'confirmed') {
      stats.value.confirmed = Math.max(0, (stats.value.confirmed ?? 0) - 1);
    }
    appt.status = 'cancelled';
    appt.status_label = t('cancelled');
  }

  cancelTarget.value = null;
  cancelReason.value = '';
}
</script>
