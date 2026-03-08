<template>
  <div class="erta-page">
    <div class="erta-page-header">
      <h1 class="erta-page-title">{{ t('appointments') }}</h1>
    </div>

    <div v-if="success" class="erta-alert erta-alert--success">{{ success }}</div>

    <!-- Filters -->
    <div class="erta-filters">
      <input class="erta-input" type="text"  v-model="filters.search"    :placeholder="t('searchCustomer')"  @input="debouncedLoad" />
      <select class="erta-input" v-model="filters.status"    @change="load">
        <option value="">{{ t('allStatuses') }}</option>
        <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
      </select>
      <select v-if="isPro" class="erta-input" v-model="filters.department_id" @change="load">
        <option value="">{{ t('allDepartments') }}</option>
        <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
      </select>
      <input class="erta-input" type="date" v-model="filters.date_from" @change="load" />
      <input class="erta-input" type="date" v-model="filters.date_to"   @change="load" />
    </div>

    <div v-if="loading" class="erta-loading"><span class="erta-spinner"></span></div>

    <template v-else>
      <div class="erta-filters" style="margin-top:8px">
        <select class="erta-input" v-model="bulkAction">
          <option value="">{{ t('bulkActions') }}</option>
          <option value="confirm">{{ t('bulkConfirm') }}</option>
          <option value="reject">{{ t('bulkReject') }}</option>
          <option value="delete">{{ t('bulkDelete') }}</option>
        </select>
        <button class="erta-btn erta-btn--ghost erta-btn--sm" :disabled="!bulkAction || !selectedIds.length || bulkLoading" @click="runBulkAction">
          <span v-if="bulkLoading" class="erta-spinner erta-spinner--sm"></span>
          {{ t('apply') }} ({{ selectedIds.length }})
        </button>
      </div>

      <div class="erta-table-wrap">
        <table class="erta-table">
          <thead>
            <tr>
              <th><input type="checkbox" :checked="allSelected" @change="toggleSelectAll" /></th>
              <th>#</th><th>{{ t('customer') }}</th><th>{{ t('provider') }}</th>
              <th v-if="isPro">{{ t('department') }}</th>
              <th>{{ t('datetime') }}</th><th>{{ t('status') }}</th>
              <th>{{ t('payment') }}</th><th>{{ t('actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!items.length">
              <td :colspan="isPro ? 9 : 8" class="erta-empty-cell">{{ t('noAppointments') }}</td>
            </tr>
            <tr v-for="a in items" :key="a.id" :class="{ 'erta-row--selected': selected === a.id }">
              <td><input type="checkbox" :checked="selectedIds.includes(a.id)" @change="toggleRowSelection(a.id)" /></td>
              <td>#{{ a.id }}</td>
              <td>
                <strong>{{ a.customer_name }}</strong><br>
                <small>{{ a.customer_email }}</small>
              </td>
              <td>{{ a.provider_name ?? '—' }}</td>
              <td v-if="isPro">{{ a.department_name ?? '—' }}</td>
              <td>{{ fmt(a.start_datetime) }}</td>
              <td><span class="erta-badge" :class="`erta-badge--${a.status}`">{{ a.status_label }}</span></td>
              <td>
                <span v-if="a.payment_status === 'paid'" style="color:#16a34a">✓ {{ t('paid') }}</span>
                <span v-else-if="isFreeAppointment(a)" style="color:#6b7280">{{ t('free') }}</span>
                <span v-else-if="a.payment_amount > 0" style="color:#d97706">{{ a.payment_status }}</span>
                <span v-else style="color:#9ca3af">—</span>
              </td>
              <td>
                <div class="erta-action-btns">
                  <button v-if="a.status === 'pending'"    class="erta-btn erta-btn--sm erta-btn--primary" :title="t('confirm')" @click="doConfirm(a)">✓</button>
                  <button v-if="a.status === 'confirmed'"  class="erta-btn erta-btn--sm erta-btn--ghost" :title="t('undoConfirm')" @click="doUnconfirm(a)">↩</button>
                  <button v-if="a.status !== 'cancelled'"  class="erta-btn erta-btn--sm erta-btn--danger"  @click="openCancel(a)">✕</button>
                  <button class="erta-btn erta-btn--sm erta-btn--danger" :title="t('delete')" @click="doDelete(a)">🗑</button>
                  <button class="erta-btn erta-btn--sm erta-btn--ghost" @click="openDetail(a)">👁</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="erta-pagination">
        <button class="erta-btn erta-btn--ghost erta-btn--sm" :disabled="page <= 1" @click="page--; load()">← {{ t('prev') }}</button>
        <span>{{ t('page') }} {{ page }} / {{ totalPages }}</span>
        <button class="erta-btn erta-btn--ghost erta-btn--sm" :disabled="page >= totalPages" @click="page++; load()">{{ t('next') }} →</button>
      </div>
    </template>

    <!-- Cancel modal -->
    <div v-if="cancelTarget" class="erta-modal-overlay" @click.self="cancelTarget = null">
      <div class="erta-modal">
        <h4>{{ t('cancelAppointment') }} #{{ cancelTarget.id }}</h4>
        <textarea class="erta-input" v-model="cancelReason" :placeholder="t('cancelReason')" rows="3"></textarea>
        <div class="erta-modal-actions">
          <button class="erta-btn erta-btn--ghost" @click="cancelTarget = null">{{ t('back') }}</button>
          <button class="erta-btn erta-btn--danger" @click="doCancel">{{ t('confirm') }}</button>
        </div>
      </div>
    </div>

    <!-- Detail modal -->
    <div v-if="detailTarget" class="erta-modal-overlay" @click.self="detailTarget = null">
      <div class="erta-modal erta-modal--wide">
        <h4>{{ t('appointment') }} #{{ detailTarget.id }}</h4>
        <table class="erta-detail-table">
          <tr v-for="[k,v] in detailRows" :key="k"><th>{{ k }}</th><td>{{ v }}</td></tr>
        </table>
        <div class="erta-modal-actions">
          <button class="erta-btn erta-btn--ghost" @click="detailTarget = null">{{ t('close') }}</button>
        </div>
      </div>
    </div>

    <div v-if="error" class="erta-alert erta-alert--error">{{ error }}</div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAdminApi } from '../../composables/useAdminApi.js';

const api = useAdminApi();
const t   = (k) => window.ertaAdminData?.i18n?.[k] ?? k;
const isPro = window.ertaAdminData?.isPro ?? false;

const loading      = ref(true);
const error        = ref(null);
const success      = ref(null);
const items        = ref([]);
const departments  = ref([]);
const total        = ref(0);
const page         = ref(1);
const perPage      = 20;
const selected     = ref(null);
const cancelTarget = ref(null);
const cancelReason = ref('');
const detailTarget = ref(null);
const selectedIds = ref([]);
const bulkAction = ref('');
const bulkLoading = ref(false);

const filters = ref({ search: '', status: '', department_id: '', date_from: '', date_to: '' });

const totalPages = computed(() => Math.max(1, Math.ceil(total.value / perPage)));
const allSelected = computed(() => items.value.length > 0 && selectedIds.value.length === items.value.length);

const statuses = [
  { value: 'pending',     label: t('pending')     },
  { value: 'confirmed',   label: t('confirmed')   },
  { value: 'cancelled',   label: t('cancelled')   },
  { value: 'completed',   label: t('completed')   },
  { value: 'rescheduled', label: 'Rescheduled'    },
  { value: 'no_show',     label: 'No Show'        },
];

const detailRows = computed(() => {
  if (!detailTarget.value) return [];
  const a = detailTarget.value;
  return [
    [t('customer'),    a.customer_name],
    [t('email'),       a.customer_email],
    [t('phone'),       a.customer_phone || '—'],
    [t('provider'),    a.provider_name  || '—'],
    [t('datetime'),    fmt(a.start_datetime)],
    [t('duration'),    a.duration_minutes + ' dk'],
    [t('status'),      a.status_label],
    [t('payment'),     paymentLabel(a)],
    [t('notes'),       a.notes || '—'],
  ];
});

onMounted(async () => {
  if (isPro) {
    await loadDepartments();
    restoreDepartmentFilterFromUrl();
  }
  await load();
});

async function loadDepartments() {
  const { data, error: err } = await api.listDepartments();
  if (err) {
    error.value = err;
    return;
  }
  departments.value = data?.items ?? data ?? [];
}

async function load() {
  loading.value = true;
  error.value   = null;
  syncDepartmentFilterToUrl();
  const params  = { page: page.value, per_page: perPage, ...filters.value };
  const { data, error: err } = await api.listAppointments(params);
  loading.value = false;
  if (err) { error.value = err; return; }
  items.value = data?.items ?? [];
  total.value = data?.total ?? 0;
  selectedIds.value = [];
}

function restoreDepartmentFilterFromUrl() {
  if (!isPro) return;
  const url = new URL(window.location.href);
  const fromQuery = url.searchParams.get('department_id');
  if (fromQuery) {
    filters.value.department_id = fromQuery;
  }
}

function syncDepartmentFilterToUrl() {
  if (!isPro) return;
  const url = new URL(window.location.href);
  if (filters.value.department_id) {
    url.searchParams.set('department_id', String(filters.value.department_id));
  } else {
    url.searchParams.delete('department_id');
  }
  history.replaceState({}, '', url.toString());
}

let debounceTimer;
function debouncedLoad() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(load, 400);
}

function fmt(dt) {
  return new Date(dt).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' });
}

function isFreeAppointment(a) {
  return a?.payment_status === 'not_required' || Number(a?.payment_amount || 0) <= 0;
}

function paymentLabel(a) {
  if (a?.payment_status === 'paid') return t('paid');
  if (isFreeAppointment(a)) return t('free');
  return a?.payment_status || '—';
}

async function doConfirm(a) {
  clearAlerts();
  const { error: err } = await api.confirmAppointment(a.id);
  if (err) { error.value = err; return; }
  a.status = 'confirmed'; a.status_label = t('confirmed');
  showSuccess(t('appointmentConfirmed'));
}

async function doUnconfirm(a) {
  clearAlerts();
  const { error: err } = await api.unconfirmAppointment(a.id);
  if (err) { error.value = err; return; }
  a.status = 'pending'; a.status_label = t('pending');
  showSuccess(t('appointmentUnconfirmed'));
}

function openCancel(a) { cancelTarget.value = a; cancelReason.value = ''; }

async function doCancel() {
  clearAlerts();
  const { error: err } = await api.cancelAppointment(cancelTarget.value.id, cancelReason.value);
  if (err) { error.value = err; return; }
  cancelTarget.value.status = 'cancelled'; cancelTarget.value.status_label = t('cancelled');
  showSuccess(t('appointmentCancelled'));
  cancelTarget.value = null;
}

async function doDelete(a) {
  clearAlerts();
  if (!window.confirm(t('deleteConfirm'))) return;
  const { error: err } = await api.deleteAppointment(a.id);
  if (err) { error.value = err; return; }
  items.value = items.value.filter((item) => item.id !== a.id);
  selectedIds.value = selectedIds.value.filter((id) => id !== a.id);
  showSuccess(t('deleted'));
}

function toggleRowSelection(id) {
  if (selectedIds.value.includes(id)) {
    selectedIds.value = selectedIds.value.filter((x) => x !== id);
  } else {
    selectedIds.value = [...selectedIds.value, id];
  }
}

function toggleSelectAll(event) {
  if (event.target.checked) {
    selectedIds.value = items.value.map((item) => item.id);
  } else {
    selectedIds.value = [];
  }
}

async function runBulkAction() {
  clearAlerts();
  if (!bulkAction.value || selectedIds.value.length === 0) return;

  if (bulkAction.value === 'delete' && !window.confirm(t('deleteConfirm'))) {
    return;
  }

  bulkLoading.value = true;
  const { data, error: err } = await api.bulkAppointments(bulkAction.value, selectedIds.value);
  bulkLoading.value = false;

  if (err) {
    error.value = err;
    return;
  }

  const done = data?.done ?? [];
  if (bulkAction.value === 'delete') {
    items.value = items.value.filter((item) => !done.includes(item.id));
  } else if (bulkAction.value === 'confirm') {
    items.value = items.value.map((item) => done.includes(item.id) ? { ...item, status: 'confirmed', status_label: t('confirmed') } : item);
  } else if (bulkAction.value === 'reject') {
    items.value = items.value.map((item) => done.includes(item.id) ? { ...item, status: 'cancelled', status_label: t('cancelled') } : item);
  }

  selectedIds.value = selectedIds.value.filter((id) => !done.includes(id));
  const failedCount = (data?.failed ?? []).length;
  if (failedCount > 0) {
    error.value = `${t('bulkFailed')} (${failedCount})`;
  }
  showSuccess(t('saved'));
}

function openDetail(a) { detailTarget.value = a; }

function clearAlerts() {
  error.value = null;
  success.value = null;
}

function showSuccess(message) {
  success.value = message;
  setTimeout(() => {
    success.value = null;
  }, 2500);
}
</script>
