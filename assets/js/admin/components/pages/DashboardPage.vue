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
                  <button v-if="appt.status === 'pending'" class="erta-btn erta-btn--sm erta-btn--primary" @click="confirm(appt.id)">
                    ✓
                  </button>
                  <button class="erta-btn erta-btn--sm erta-btn--danger" @click="openCancel(appt)">✕</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useAdminApi } from '../../composables/useAdminApi.js';

const api     = useAdminApi();
const t       = (k) => window.ertaAdminData?.i18n?.[k] ?? k;
const loading = ref(true);
const stats   = ref({});
const upcoming = ref([]);

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

async function confirm(id) {
  await api.confirmAppointment(id);
  const appt = upcoming.value.find(a => a.id === id);
  if (appt) { appt.status = 'confirmed'; appt.status_label = 'Confirmed'; }
}

function openCancel(appt) {
  if (confirm(t('confirmCancel') + ' #' + appt.id + '?')) {
    api.cancelAppointment(appt.id, '').then(() => {
      const a = upcoming.value.find(x => x.id === appt.id);
      if (a) a.status = 'cancelled';
    });
  }
}
</script>
