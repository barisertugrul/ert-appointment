<template>
  <div class="erta-page">
    <div class="erta-page-header">
      <h1 class="erta-page-title">{{ t('departments') }}</h1>
      <button class="erta-btn erta-btn--primary" @click="openForm(null)">+ {{ t('addDepartment') }}</button>
    </div>
    <div v-if="loading" class="erta-loading"><span class="erta-spinner"></span></div>
    <div v-else class="erta-table-wrap">
      <table class="erta-table">
        <thead><tr><th>#</th><th>{{ t('name') }}</th><th>{{ t('status') }}</th><th>{{ t('actions') }}</th></tr></thead>
        <tbody>
          <tr v-if="!items.length"><td colspan="4" class="erta-empty-cell">{{ t('noDepartments') }}</td></tr>
          <tr v-for="d in items" :key="d.id">
            <td>#{{ d.id }}</td><td>{{ d.name }}</td>
            <td><span class="erta-badge" :class="d.status === 'active' ? 'erta-badge--confirmed' : 'erta-badge--cancelled'">{{ d.status }}</span></td>
            <td>
              <button class="erta-btn erta-btn--sm erta-btn--ghost" @click="openForm(d)">✏️</button>
              <button class="erta-btn erta-btn--sm erta-btn--danger" @click="del(d.id)">🗑</button>
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
const loading = ref(true); const items = ref([]); const editing = ref(null);
onMounted(async () => { const { data } = await api.listDepartments(); items.value = data ?? []; loading.value = false; });
function openForm(d) { editing.value = d ? { ...d } : { name: '', description: '', status: 'active' }; }
async function saveDept() { await api.saveDepartment(editing.value); editing.value = null; const { data } = await api.listDepartments(); items.value = data ?? []; }
async function del(id) { if (confirm('Delete?')) { await api.deleteDepartment(id); items.value = items.value.filter(d => d.id !== id); } }
</script>
