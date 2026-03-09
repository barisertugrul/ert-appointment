<template>
  <div class="erta-page">
    <div class="erta-page-header">
      <h1 class="erta-page-title">{{ t('dashboard') }}</h1>
    </div>

    <div v-if="loading" class="erta-loading"><span class="erta-spinner"></span></div>

    <template v-else>
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

      <div class="erta-dashboard-panels">
        <section class="erta-dashboard-panel">
          <h3 class="erta-dashboard-panel__title">{{ t('latestAppointments') }}</h3>
          <div v-if="!latestLimited.length" class="erta-dashboard-panel__empty">{{ t('noAppointments') }}</div>
          <div
            v-for="(appt, index) in latestLimited"
            :key="`latest-${appt.id}`"
            class="erta-dashboard-item"
          >
            <div>
              <div><strong>{{ index + 1 }}.</strong> {{ appt.customer_name }}</div>
              <div class="erta-dashboard-item__meta">{{ fmt(appt.start_datetime) }}</div>
            </div>
            <span class="erta-badge" :class="`erta-badge--${appt.status}`">{{ statusLabel(appt) }}</span>
          </div>
        </section>

        <section class="erta-dashboard-panel">
          <h3 class="erta-dashboard-panel__title">{{ t('upcomingAppointments') }}</h3>
          <div v-if="!upcomingLimited.length" class="erta-dashboard-panel__empty">{{ t('noUpcomingAppointments') }}</div>
          <div
            v-for="(appt, index) in upcomingLimited"
            :key="`upcoming-${appt.id}`"
            class="erta-dashboard-item"
          >
            <div>
              <div><strong>{{ index + 1 }}.</strong> {{ appt.customer_name }}</div>
              <div class="erta-dashboard-item__meta">{{ fmt(appt.start_datetime) }}</div>
            </div>
            <span class="erta-badge" :class="`erta-badge--${appt.status}`">{{ statusLabel(appt) }}</span>
          </div>
        </section>

        <section class="erta-dashboard-panel">
          <div class="erta-dashboard-panel__head">
            <h3 class="erta-dashboard-panel__title">{{ t('pendingApproval') }}</h3>
            <select v-model="pendingSort" class="erta-dashboard-sort">
              <option value="date_asc">Tarih (Artan)</option>
              <option value="id_asc">Kayıt No (Artan)</option>
            </select>
          </div>
          <div v-if="!pendingLimited.length" class="erta-dashboard-panel__empty">{{ t('noAppointments') }}</div>
          <div
            v-for="(appt, index) in pendingLimited"
            :key="`pending-${appt.id}`"
            class="erta-dashboard-item"
          >
            <div>
              <div><strong>{{ index + 1 }}.</strong> {{ appt.customer_name }}</div>
              <div class="erta-dashboard-item__meta">{{ fmt(appt.start_datetime) }}</div>
            </div>
            <div class="erta-dashboard-item__actions">
              <span class="erta-badge" :class="`erta-badge--${appt.status}`">{{ statusLabel(appt) }}</span>
              <button class="erta-btn erta-btn--sm erta-btn--primary" :disabled="actionLoading" @click="doConfirm(appt)">✓</button>
              <button class="erta-btn erta-btn--sm erta-btn--danger" :disabled="actionLoading" @click="openCancel(appt)">✕</button>
            </div>
          </div>
        </section>
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
import { computed, ref, onMounted } from 'vue';
import { useAdminApi } from '../../composables/useAdminApi.js';

const api = useAdminApi();
const t = (k) => window.ertaAdminData?.i18n?.[k] ?? k;
const loading = ref(true);
const actionLoading = ref(false);
const error = ref(null);
const stats = ref({});
const latest = ref([]);
const upcoming = ref([]);
const pending = ref([]);
const cancelTarget = ref(null);
const cancelReason = ref('');
const pendingSort = ref('date_asc');

const toTs = (value) => {
  const ts = new Date(value).getTime();
  return Number.isFinite(ts) ? ts : 0;
};

const nowTs = () => Date.now();

const latestLimited = computed(() => [...latest.value]
  .sort((left, right) => {
    const createdDiff = toTs(right.created_at) - toTs(left.created_at);
    if (createdDiff !== 0) {
      return createdDiff;
    }

    return Number(right.id || 0) - Number(left.id || 0);
  })
  .slice(0, 5));

const upcomingLimited = computed(() => [...upcoming.value]
  .filter((item) => ['pending', 'confirmed'].includes(item.status))
  .filter((item) => toTs(item.start_datetime) >= nowTs())
  .sort((left, right) => {
    const startDiff = toTs(left.start_datetime) - toTs(right.start_datetime);
    if (startDiff !== 0) {
      return startDiff;
    }

    return Number(left.id || 0) - Number(right.id || 0);
  })
  .slice(0, 5));

const pendingLimited = computed(() => [...pending.value]
  .filter((item) => item.status === 'pending')
  .filter((item) => toTs(item.start_datetime) >= nowTs())
  .sort((left, right) => {
    if (pendingSort.value === 'id_asc') {
      return Number(left.id || 0) - Number(right.id || 0);
    }

    const startDiff = toTs(left.start_datetime) - toTs(right.start_datetime);
    if (startDiff !== 0) {
      return startDiff;
    }

    return Number(left.id || 0) - Number(right.id || 0);
  })
  .slice(0, 5));

onMounted(async () => {
  const today = new Date().toISOString().slice(0, 10);
  const [latestRes, upcomingRes, pendingRes] = await Promise.all([
    api.listAppointments({ page: 1, per_page: 50, order_by: 'created_at', order: 'desc' }),
    api.listAppointments({ date_from: today, per_page: 100, order_by: 'start_datetime', order: 'asc' }),
    api.listAppointments({ status: 'pending', page: 1, per_page: 100, date_from: today, order_by: 'start_datetime', order: 'asc' }),
  ]);

  loading.value = false;
  latest.value = latestRes.data?.items ?? [];
  pending.value = pendingRes.data?.items ?? [];
  upcoming.value = upcomingRes.data?.items ?? [];

  stats.value = {
    today: (upcomingRes.data?.items ?? []).filter((a) => String(a.start_datetime).startsWith(today)).length,
    pending: pendingRes.data?.total ?? pending.value.length,
    confirmed: (upcomingRes.data?.items ?? []).filter((a) => a.status === 'confirmed').length,
    thisMonth: latestRes.data?.total ?? latest.value.length,
  };
});

function fmt(dt) { return new Date(dt).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }); }
function statusLabel(appt) { return appt.status_label || t(appt.status || 'pending'); }

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
  latest.value = latest.value.map((item) => item.id === appt.id ? { ...item, status: 'confirmed', status_label: t('confirmed') } : item);
  upcoming.value = upcoming.value.map((item) => item.id === appt.id ? { ...item, status: 'confirmed', status_label: t('confirmed') } : item);
  pending.value = pending.value.filter((item) => item.id !== appt.id);
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

  upcoming.value = upcoming.value.map((appt) => appt.id === target.id ? { ...appt, status: 'cancelled', status_label: t('cancelled') } : appt);
  latest.value = latest.value.map((appt) => appt.id === target.id ? { ...appt, status: 'cancelled', status_label: t('cancelled') } : appt);
  pending.value = pending.value.filter((appt) => appt.id !== target.id);
  stats.value.pending = Math.max(0, (stats.value.pending ?? 0) - 1);

  cancelTarget.value = null;
  cancelReason.value = '';
}
</script>
