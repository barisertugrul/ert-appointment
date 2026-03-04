<!-- ReportsPage.vue (Pro) -->
<template>
  <div class="erta-page">
    <div class="erta-page-header">
      <h1 class="erta-page-title">{{ t('reports') }} <span class="erta-pro-badge">PRO</span></h1>
    </div>
    <div v-if="!isPro" class="erta-pro-upgrade-card">
      <h3>{{ t('proFeature') }}</h3>
      <p>{{ t('reportsProDesc') }}</p>
      <a href="https://ertappointment.com/pro" target="_blank" class="erta-btn erta-btn--primary">{{ t('upgradeToPro') }}</a>
    </div>
    <template v-else>
      <div style="display:flex;gap:12px;margin-bottom:20px;align-items:center">
        <input class="erta-input" type="date" v-model="from" style="width:160px" />
        <span>—</span>
        <input class="erta-input" type="date" v-model="to" style="width:160px" />
        <button class="erta-btn erta-btn--primary" @click="load">{{ t('apply') }}</button>
      </div>
      <div v-if="loading" class="erta-loading"><span class="erta-spinner"></span></div>
      <template v-else-if="report">
        <div class="erta-stats-grid">
          <div class="erta-stat-card"><div class="erta-stat-card__value">{{ report.total_appointments }}</div><div class="erta-stat-card__label">{{ t('totalAppointments') }}</div></div>
          <div class="erta-stat-card"><div class="erta-stat-card__value">{{ report.total_revenue }} {{ currency }}</div><div class="erta-stat-card__label">{{ t('revenue') }}</div></div>
          <div class="erta-stat-card"><div class="erta-stat-card__value">{{ report.cancellation_rate }}%</div><div class="erta-stat-card__label">{{ t('cancellationRate') }}</div></div>
          <div class="erta-stat-card"><div class="erta-stat-card__value">{{ report.by_status?.confirmed ?? 0 }}</div><div class="erta-stat-card__label">{{ t('confirmed') }}</div></div>
        </div>
        <h3 style="margin:24px 0 12px">{{ t('topProviders') }}</h3>
        <div class="erta-table-wrap">
          <table class="erta-table">
            <thead><tr><th>{{ t('provider') }}</th><th>{{ t('appointments') }}</th></tr></thead>
            <tbody>
              <tr v-for="p in report.top_providers" :key="p.provider_id">
                <td>{{ p.name }}</td><td>{{ p.cnt }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </template>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue';
import { useAdminApi } from '../../composables/useAdminApi.js';
const api = useAdminApi(); const t = (k) => window.ertaAdminData?.i18n?.[k] ?? k;
const isPro = window.ertaAdminData?.isPro ?? false;
const currency = window.ertaAdminData?.currency ?? 'TRY';
const loading = ref(false); const report = ref(null);
const from = ref(new Date(new Date().setDate(1)).toISOString().slice(0,10));
const to   = ref(new Date().toISOString().slice(0,10));
onMounted(() => { if (isPro) load(); });
async function load() {
  loading.value = true;
  const { data } = await api.getReports({ from: from.value, to: to.value });
  report.value = data; loading.value = false;
}
</script>
