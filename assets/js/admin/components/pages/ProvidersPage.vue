<!-- ProvidersPage.vue -->
<template>
  <div class="erta-page">
    <div class="erta-page-header">
      <h1 class="erta-page-title">{{ t('providers') }}</h1>
      <button class="erta-btn erta-btn--primary" @click="openForm(null)">+ {{ t('addProvider') }}</button>
    </div>
    <div v-if="loading" class="erta-loading"><span class="erta-spinner"></span></div>
    <div v-else class="erta-table-wrap">
      <table class="erta-table">
      <thead><tr><th>#</th><th>{{ t('name') }}</th><th>{{ t('department') }}</th><th>{{ t('type') }}</th><th>{{ t('actions') }}</th></tr></thead>
        <tbody>
          <tr v-if="!items.length"><td colspan="5" class="erta-empty-cell">{{ t('noProviders') }}</td></tr>
          <tr v-for="p in items" :key="p.id">
            <td>#{{ p.id }}</td>
            <td>{{ p.name }}</td>
            <td>{{ p.department_name ?? '—' }}</td>
            <td>{{ p.type }}</td>
            <td>
              <button class="erta-btn erta-btn--sm erta-btn--ghost" @click="openForm(p)">✏️</button>
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
        <div class="erta-form-field">
          <label class="erta-form-label">{{ t('type') }}</label>
          <select class="erta-input" v-model="editing.type">
            <option value="individual">{{ t('Individual') }}</option>
            <option value="unit">{{ t('Unit') }}</option>
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
const loading = ref(true); const items = ref([]); const editing = ref(null);
onMounted(async () => { const { data } = await api.listProviders(); items.value = data?.items ?? []; loading.value = false; });
function openForm(p) { editing.value = p ? { ...p } : { name: '', email: '', type: 'individual' }; }
async function saveProvider() { await api.saveProvider(editing.value); editing.value = null; const { data } = await api.listProviders(); items.value = data?.items ?? []; }
async function del(id) { if (confirm('Delete?')) { await api.deleteProvider(id); items.value = items.value.filter(p => p.id !== id); } }
</script>
