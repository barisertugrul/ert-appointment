<template>
  <div class="erta-page">
    <div class="erta-page-header">
      <h1 class="erta-page-title">{{ t('dashboard') }}</h1>
    </div>

    <div v-if="loading" class="erta-loading"><span class="erta-spinner"></span></div>

    <template v-else>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px">
        <div class="erta-card" style="padding:12px">
          <h3 style="margin:0 0 8px 0;font-size:14px">{{ t('latestAppointments') }}</h3>
          <div v-if="!latest.length" style="font-size:12px;color:#9ca3af">{{ t('noAppointments') }}</div>
          <div v-for="appt in latest" :key="`latest-${appt.id}`" style="font-size:12px;padding:6px 0;border-bottom:1px solid #f1f5f9">
            <strong>#{{ appt.id }}</strong> · {{ appt.customer_name }}
            <div style="color:#6b7280">{{ fmt(appt.start_datetime) }}</div>
          </div>
        </div>

        <div class="erta-card" style="padding:12px">
          <h3 style="margin:0 0 8px 0;font-size:14px">{{ t('upcomingAppointments') }}</h3>
          <div v-if="!upcoming.length" style="font-size:12px;color:#9ca3af">{{ t('noUpcomingAppointments') }}</div>
          <div v-for="appt in upcoming" :key="`upcoming-${appt.id}`" style="font-size:12px;padding:6px 0;border-bottom:1px solid #f1f5f9">
            <strong>#{{ appt.id }}</strong> · {{ appt.customer_name }}
            <div style="color:#6b7280">{{ fmt(appt.start_datetime) }}</div>
          </div>
        </div>

        <div class="erta-card" style="padding:12px">
          <h3 style="margin:0 0 8px 0;font-size:14px">{{ t('pendingApproval') }}</h3>
          <div v-if="!pending.length" style="font-size:12px;color:#9ca3af">{{ t('noAppointments') }}</div>
          <div v-for="appt in pending" :key="`pending-${appt.id}`" style="font-size:12px;padding:6px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;gap:8px;align-items:center">
            <div>
              <strong>#{{ appt.id }}</strong> · {{ appt.customer_name }}
              <div style="color:#6b7280">{{ fmt(appt.start_datetime) }}</div>
            </div>
            <div style="display:flex;gap:6px">
              <button class="erta-btn erta-btn--sm erta-btn--primary" :disabled="actionLoading" @click="doConfirm(appt)">✓</button>
              <button class="erta-btn erta-btn--sm erta-btn--danger" :disabled="actionLoading" @click="openCancel(appt)">✕</button>
            </div>
          </div>
        </div>
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
const latest = ref([]);
const upcoming = ref([]);
const pending = ref([]);
const cancelTarget = ref(null);
const cancelReason = ref('');

onMounted(async () => {
  const today = new Date().toISOString().slice(0, 10);
  const [latestRes, upcomingRes, pendingRes] = await Promise.all([
    api.listAppointments({ page: 1, per_page: 10 }),
    api.listAppointments({ date_from: today, per_page: 50 }),
    api.listAppointments({ status: 'pending', page: 1, per_page: 10 }),
  ]);

  loading.value = false;
  latest.value = latestRes.data?.items ?? [];
  pending.value = pendingRes.data?.items ?? [];
  upcoming.value = (upcomingRes.data?.items ?? []).filter((a) => ['pending', 'confirmed'].includes(a.status)).slice(0, 10);
});

function formatDate(dt) { return new Date(dt).toLocaleDateString(); }
function formatTime(dt) { return new Date(dt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }
function fmt(dt) { return new Date(dt).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }); }

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
  pending.value = pending.value.filter((item) => item.id !== appt.id);
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
  pending.value = pending.value.filter((appt) => appt.id !== target.id);

  cancelTarget.value = null;
  cancelReason.value = '';
}
</script>
