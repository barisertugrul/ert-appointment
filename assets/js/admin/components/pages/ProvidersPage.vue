<!-- ProvidersPage.vue -->
<template>
  <div class="erta-page">
    <div class="erta-page-header">
      <h1 class="erta-page-title">{{ t('providers') }}</h1>
      <button class="erta-btn erta-btn--primary" @click="openForm(null)">+ {{ t('addProvider') }}</button>
    </div>
    <div v-if="!isPro" class="erta-alert erta-alert--info">{{ t('departmentProOnly') }}</div>
    <div v-if="error" class="erta-alert erta-alert--error">{{ error }}</div>
    <div v-if="loading" class="erta-loading"><span class="erta-spinner"></span></div>
    <div v-else class="erta-table-wrap">
      <table class="erta-table">
        <thead><tr><th>#</th><th>{{ t('name') }}</th><th>{{ t('department') }}</th><th>{{ t('type') }}</th><th>{{ t('status') }}</th><th>{{ t('actions') }}</th></tr></thead>
        <tbody>
          <tr v-if="!items.length"><td colspan="6" class="erta-empty-cell">{{ t('noProviders') }}</td></tr>
          <tr v-for="p in items" :key="p.id">
            <td>#{{ p.id }}</td>
            <td>{{ p.name }}</td>
            <td>{{ p.department_name ?? '—' }}</td>
            <td>{{ typeLabel(p.type) }}</td>
            <td><span class="erta-badge" :class="p.status === 'active' ? 'erta-badge--active' : 'erta-badge--inactive'">{{ statusLabel(p.status) }}</span></td>
            <td>
              <button class="erta-btn erta-btn--sm erta-btn--ghost" @click="openForm(p)">✏️</button>
              <button class="erta-btn erta-btn--sm erta-btn--ghost" @click="openScopedSettings(p.id)">⚙️</button>
              <button class="erta-btn erta-btn--sm erta-btn--ghost" @click="toggleStatus(p)">
                {{ p.status === 'active' ? t('deactivate') : t('activate') }}
              </button>
              <button class="erta-btn erta-btn--sm erta-btn--danger" @click="del(p.id)">🗑</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <!-- Edit modal -->
    <div v-if="editing !== null" class="erta-modal-overlay" @click.self="editing = null">
      <div class="erta-modal">
        <h4>{{ editing.id ? t('editProvider') : t('addProvider') }}</h4>
        <div class="erta-form-field"><label class="erta-form-label">{{ t('name') }}</label><input class="erta-input" v-model="editing.name" /></div>
        <div class="erta-form-field"><label class="erta-form-label">{{ t('email') }}</label><input class="erta-input" type="email" v-model="editing.email" /></div>
        <div class="erta-form-field"><label class="erta-form-label">{{ t('type') }}</label>
          <select class="erta-input" v-model="editing.type">
            <option value="individual">{{ t('providerTypeIndividual') }}</option>
            <option value="unit">{{ t('providerTypeUnit') }}</option>
          </select>
        </div>
        <div class="erta-form-field">
          <label class="erta-form-label">{{ t('department') }}</label>
          <select class="erta-input" v-model="editing.department_id" :disabled="!isPro">
            <option :value="null">—</option>
            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>
        </div>
        <div class="erta-form-field">
          <label class="erta-form-label">{{ t('status') }}</label>
          <select class="erta-input" v-model="editing.status">
            <option value="active">{{ t('active') }}</option>
            <option value="inactive">{{ t('inactive') }}</option>
          </select>
        </div>
        <div class="erta-modal-actions">
          <button class="erta-btn erta-btn--ghost" @click="editing = null">{{ t('cancel') }}</button>
          <button class="erta-btn erta-btn--primary" @click="saveProvider">{{ t('save') }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue';
import { useAdminApi } from '../../composables/useAdminApi.js';
const api = useAdminApi(); const t = (k) => window.ertaAdminData?.i18n?.[k] ?? k;
const isPro = window.ertaAdminData?.isPro ?? false;
const loading = ref(true); const items = ref([]); const departments = ref([]); const editing = ref(null); const error = ref('');
onMounted(async () => {
  await Promise.all([loadProviders(), loadDepartments()]);
  loading.value = false;
});
async function loadProviders() {
  const { data, error: err } = await api.listProviders();
  if (err) { error.value = err; return; }
  items.value = data?.items ?? [];
}
async function loadDepartments() {
  const { data, error: err } = await api.listDepartments();
  if (err) { error.value = err; return; }
  departments.value = data?.items ?? data ?? [];
}
function openForm(p) {
  editing.value = p
    ? { ...p, department_id: p.department_id ?? null }
    : { name: '', email: '', type: 'individual', department_id: null };
  error.value = '';
}
function typeLabel(type) {
  if (type === 'unit') return t('providerTypeUnit');
  return t('providerTypeIndividual');
}

function statusLabel(status) {
  return t(status === 'inactive' ? 'inactive' : 'active');
}
async function saveProvider() {
  error.value = '';
  if (!editing.value?.name?.trim()) {
    error.value = t('name') + ' ' + t('required').toLowerCase();
    return;
  }
  if (isPro && editing.value.type === 'unit' && !editing.value.department_id) {
    error.value = t('departmentRequiredForUnit');
    return;
  }
  if (!isPro) {
    editing.value.department_id = null;
  }
  const payload = { ...editing.value, department_id: editing.value.department_id || null };
  const { error: err } = await api.saveProvider(payload);
  if (err) { error.value = err; return; }
  editing.value = null;
  await loadProviders();
}
async function del(id) {
  if (confirm(t('deleteConfirm'))) {
    const { error: err } = await api.deleteProvider(id);
    if (err) { error.value = err; return; }
    items.value = items.value.filter(p => p.id !== id);
  }
}

async function toggleStatus(item) {
  error.value = '';
  const nextStatus = item.status === 'active' ? 'inactive' : 'active';
  const payload = { ...item, status: nextStatus };
  const { error: err } = await api.saveProvider(payload);
  if (err) { error.value = err; return; }
  item.status = nextStatus;
}

function openScopedSettings(providerId) {
  const url = new URL(window.location.href);
  url.searchParams.set('page', 'erta-settings');
  url.searchParams.set('scope', 'provider');
  url.searchParams.set('scope_id', String(providerId));
  window.location.href = url.toString();
}
</script>
