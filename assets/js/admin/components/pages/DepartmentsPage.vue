<template>
  <div class="erta-page">
    <div class="erta-page-header">
      <h1 class="erta-page-title">
        {{ t('departments') }}
        <span v-if="!isPro" class="erta-pro-badge">{{ t('proBadge') }}</span>
      </h1>
      <button class="erta-btn erta-btn--primary" :disabled="!isPro" @click="openForm(null)">+ {{ t('addDepartment') }}</button>
    </div>
    <div v-if="!isPro" class="erta-alert erta-alert--info">{{ t('departmentsProOnly') }}</div>
    <div v-if="error" class="erta-alert erta-alert--error">{{ error }}</div>
    <div v-if="loading" class="erta-loading"><span class="erta-spinner"></span></div>
    <div v-else class="erta-table-wrap" :class="{ 'erta-pro-gate': !isPro }">
      <table class="erta-table">
        <thead><tr><th>#</th><th>{{ t('name') }}</th><th>{{ t('status') }}</th><th>{{ t('actions') }}</th></tr></thead>
        <tbody>
          <tr v-if="!items.length"><td colspan="4" class="erta-empty-cell">{{ t('noDepartments') }}</td></tr>
          <tr v-for="d in items" :key="d.id">
            <td>#{{ d.id }}</td><td>{{ d.name }}</td>
            <td><span class="erta-badge" :class="d.status === 'active' ? 'erta-badge--active' : 'erta-badge--inactive'">{{ statusLabel(d.status) }}</span></td>
            <td>
              <button class="erta-btn erta-btn--sm erta-btn--ghost" :disabled="!isPro" @click="openForm(d)">✏️</button>
              <button class="erta-btn erta-btn--sm erta-btn--ghost" :disabled="!isPro" @click="openScopedSettings(d.id)">⚙️</button>
              <button class="erta-btn erta-btn--sm erta-btn--ghost" :disabled="!isPro" @click="toggleStatus(d)">
                {{ d.status === 'active' ? t('deactivate') : t('activate') }}
              </button>
              <button class="erta-btn erta-btn--sm erta-btn--danger" :disabled="!isPro" @click="del(d.id)">🗑</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-if="editing !== null" class="erta-modal-overlay" @click.self="editing = null">
      <div class="erta-modal">
        <h4>{{ editing.id ? t('editDepartment') : t('addDepartment') }}</h4>
        <div class="erta-form-field"><label class="erta-form-label">{{ t('name') }}</label><input class="erta-input" v-model="editing.name" /></div>
        <div class="erta-form-field"><label class="erta-form-label">{{ t('description') }}</label><textarea class="erta-input" v-model="editing.description" rows="2"></textarea></div>
        <div class="erta-form-field">
          <label class="erta-form-label">{{ t('status') }}</label>
          <select class="erta-input" v-model="editing.status">
            <option value="active">{{ t('active') }}</option>
            <option value="inactive">{{ t('inactive') }}</option>
          </select>
        </div>
        <div class="erta-modal-actions">
          <button class="erta-btn erta-btn--ghost" @click="editing = null">{{ t('cancel') }}</button>
          <button class="erta-btn erta-btn--primary" @click="saveDept">{{ t('save') }}</button>
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
const loading = ref(true); const items = ref([]); const editing = ref(null); const error = ref('');
onMounted(async () => {
  await reloadDepartments();
  loading.value = false;
});

function statusLabel(status) {
  return t(status === 'inactive' ? 'inactive' : 'active');
}
function openForm(d) {
  if (!isPro) {
    error.value = t('departmentsProOnly');
    return;
  }
  editing.value = d ? { ...d } : { name: '', description: '', status: 'active' };
}
async function saveDept() {
  if (!isPro) {
    error.value = t('departmentsProOnly');
    return;
  }
  error.value = '';
  const { error: err } = await api.saveDepartment(editing.value);
  if (err) { error.value = err; return; }
  editing.value = null;
  await reloadDepartments();
}
async function del(id) {
  if (!isPro) {
    error.value = t('departmentsProOnly');
    return;
  }
  if (confirm(t('deleteConfirm'))) {
    error.value = '';
    const { error: err } = await api.deleteDepartment(id);
    if (err) { error.value = err; return; }
    items.value = items.value.filter(d => d.id !== id);
  }
}

async function toggleStatus(item) {
  if (!isPro) {
    error.value = t('departmentsProOnly');
    return;
  }
  error.value = '';
  const nextStatus = item.status === 'active' ? 'inactive' : 'active';
  const payload = { ...item, status: nextStatus };
  const { error: err } = await api.saveDepartment(payload);
  if (err) { error.value = err; return; }
  item.status = nextStatus;
}
async function reloadDepartments() {
  const { data, error: err } = await api.listDepartments();
  if (err) { error.value = err; return; }
  items.value = data?.items ?? data ?? [];
}

function openScopedSettings(departmentId) {
  const url = new URL(window.location.href);
  url.searchParams.set('page', 'erta-settings');
  url.searchParams.set('scope', 'department');
  url.searchParams.set('scope_id', String(departmentId));
  window.location.href = url.toString();
}
</script>
