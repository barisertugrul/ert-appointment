<!-- FormsPage.vue — Drag-and-drop form builder -->
<template>
  <div class="erta-page">

    <!-- List view -->
    <template v-if="!editing">
      <div class="erta-page-header">
        <h1 class="erta-page-title">{{ t('forms') }}</h1>
        <button class="erta-btn erta-btn--primary" @click="openNew">+ {{ t('newForm') }}</button>
      </div>

      <div v-if="!isPro" class="erta-alert erta-alert--info">{{ t('departmentProOnly') }}</div>

      <div v-if="loading" class="erta-loading"><span class="erta-spinner"></span></div>
      <div v-else class="erta-table-wrap">
        <table class="erta-table">
          <thead>
            <tr>
              <th>#</th><th>{{ t('name') }}</th><th>{{ t('scope') }}</th>
              <th>{{ t('fields') }}</th><th>Shortcode</th><th>{{ t('actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!forms.length">
              <td colspan="6" class="erta-empty-cell">{{ t('noForms') }}</td>
            </tr>
            <tr v-for="f in forms" :key="f.id">
              <td>#{{ f.id }}</td>
              <td><strong>{{ f.name }}</strong></td>
              <td><span class="erta-badge erta-badge--scope">{{ f.scope }}</span></td>
              <td>{{ f.fields?.length ?? 0 }}</td>
              <td>
                <div style="display:flex; gap:6px; align-items:center;">
                  <input class="erta-input erta-input--mono" :value="formShortcode(f)" readonly style="max-width:240px;" />
                  <button class="erta-btn erta-btn--sm erta-btn--ghost" @click="copyShortcode(f)">Kopyala</button>
                </div>
              </td>
              <td class="erta-actions-cell">
                <button class="erta-btn erta-btn--sm erta-btn--ghost" @click="openEdit(f)">✏️ {{ t('edit') }}</button>
                <button class="erta-btn erta-btn--sm erta-btn--danger" @click="del(f.id)">🗑</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- Builder view -->
    <template v-else>
      <div class="erta-page-header">
        <h1 class="erta-page-title">
          {{ editing.id ? t('editForm') : t('newForm') }}
        </h1>
        <div class="erta-header-actions">
          <button class="erta-btn erta-btn--ghost" @click="editing = null">{{ t('cancel') }}</button>
          <button class="erta-btn erta-btn--primary" :disabled="saving" @click="saveForm">
            <span v-if="saving" class="erta-spinner erta-spinner--sm"></span>
            {{ t('save') }}
          </button>
        </div>
      </div>

      <div v-if="saveError" class="erta-alert erta-alert--error">{{ saveError }}</div>

      <!-- Meta -->
      <div class="erta-builder-meta">
        <div class="erta-form-row">
          <label class="erta-form-label">{{ t('formName') }}</label>
          <input class="erta-input" v-model="editing.name" :placeholder="t('formName')" />
        </div>
        <div class="erta-form-row">
          <label class="erta-form-label">{{ t('scope') }}</label>
          <select class="erta-input" v-model="editing.scope">
            <option value="global">{{ t('global') }}</option>
            <option value="department" :disabled="!isPro">{{ t('department') }} ({{ t('proBadge') }})</option>
            <option value="provider">{{ t('provider') }}</option>
          </select>
        </div>
      </div>

      <!-- Builder columns -->
      <div class="erta-builder-layout">

        <!-- Left: field palette -->
        <div class="erta-builder-palette">
          <h3 class="erta-palette-title">{{ t('addField') }}</h3>
          <button
            v-for="ft in fieldTypes"
            :key="ft.type"
            class="erta-palette-item"
            @click="addField(ft.type)"
          >
            <span class="erta-palette-item__icon">{{ ft.icon }}</span>
            <span class="erta-palette-item__label">{{ ft.label }}</span>
          </button>
        </div>

        <!-- Right: field list -->
        <div class="erta-builder-canvas">
          <div v-if="!editing.fields.length" class="erta-canvas-empty">
            {{ t('noFieldsYet') }}
          </div>

          <div
            v-for="(field, idx) in editing.fields"
            :key="field.id"
            class="erta-field-card"
            :class="{ 'erta-field-card--active': activeField === idx, 'erta-field-card--system': field.system }"
            @click="activeField = idx"
          >
            <!-- Card header -->
            <div class="erta-field-card__header">
              <span class="erta-field-card__drag" title="Drag to reorder">⠿</span>
              <span class="erta-field-card__type-badge">{{ fieldTypeLabel(field.type) }}</span>
              <span class="erta-field-card__label">{{ field.label || t('untitledField') }}</span>
              <div class="erta-field-card__actions">
                <button
                  v-if="idx > 0"
                  class="erta-icon-btn" title="Move up"
                  @click.stop="moveField(idx, -1)"
                >↑</button>
                <button
                  v-if="idx < editing.fields.length - 1"
                  class="erta-icon-btn" title="Move down"
                  @click.stop="moveField(idx, 1)"
                >↓</button>
                <button
                  v-if="!field.system"
                  class="erta-icon-btn erta-icon-btn--danger" title="Remove"
                  @click.stop="removeField(idx)"
                >✕</button>
              </div>
            </div>

            <!-- Expanded editor -->
            <div v-if="activeField === idx" class="erta-field-editor">

              <!-- Label -->
              <div class="erta-fe-row">
                <label class="erta-fe-label">{{ t('fieldLabel') }}</label>
                <input class="erta-input" v-model="field.label" :disabled="field.system" />
              </div>

              <!-- ID / name (read-only for system fields) -->
              <div class="erta-fe-row">
                <label class="erta-fe-label">{{ t('fieldId') }}</label>
                <input class="erta-input erta-input--mono" v-model="field.id" :disabled="field.system" />
              </div>

              <!-- Placeholder (text/email/tel/number) -->
              <div v-if="['text','email','tel','number','date'].includes(field.type)" class="erta-fe-row">
                <label class="erta-fe-label">{{ t('placeholder') }}</label>
                <input class="erta-input" v-model="field.placeholder" />
              </div>

              <!-- Options (select) -->
              <div v-if="field.type === 'select'" class="erta-fe-row">
                <label class="erta-fe-label">{{ t('options') }}</label>
                <div class="erta-options-list">
                  <div
                    v-for="(opt, oi) in field.options"
                    :key="oi"
                    class="erta-option-row"
                  >
                    <input class="erta-input" v-model="opt.label" :placeholder="t('optionLabel')" />
                    <input class="erta-input erta-input--mono" v-model="opt.value" :placeholder="t('optionValue')" style="width:120px" />
                    <button class="erta-icon-btn erta-icon-btn--danger" @click="field.options.splice(oi,1)">✕</button>
                  </div>
                  <button class="erta-btn erta-btn--sm erta-btn--ghost" @click="field.options.push({ label: '', value: '' })">
                    + {{ t('addOption') }}
                  </button>
                </div>
              </div>

              <!-- Required toggle -->
              <div class="erta-fe-row erta-fe-row--inline">
                <label class="erta-fe-label">{{ t('required') }}</label>
                <label class="erta-toggle">
                  <input type="checkbox" v-model="field.required" :disabled="field.system" />
                  <span class="erta-toggle__slider"></span>
                </label>
              </div>

              <!-- Help text -->
              <div class="erta-fe-row">
                <label class="erta-fe-label">{{ t('helpText') }}</label>
                <input class="erta-input" v-model="field.help" :placeholder="t('helpTextPlaceholder')" />
              </div>

            </div><!-- /editor -->
          </div><!-- /field-card -->
        </div><!-- /canvas -->
      </div><!-- /builder-layout -->

      <!-- Preview panel -->
      <div class="erta-builder-preview">
        <h3 class="erta-preview-title">{{ t('preview') }}</h3>
        <div class="erta-preview-fields">
          <div v-for="field in editing.fields" :key="field.id" class="erta-preview-field">
            <template v-if="field.type === 'calendar'">
              <label class="erta-form-label">{{ field.label }}</label>
              <div class="erta-datetime-badge">📅 2025-06-15 &bull; 10:00</div>
            </template>
            <template v-else-if="field.type === 'textarea'">
              <label class="erta-form-label">{{ field.label }}<span v-if="field.required" class="erta-req">*</span></label>
              <textarea class="erta-input" rows="2" :placeholder="field.placeholder" disabled></textarea>
            </template>
            <template v-else-if="field.type === 'select'">
              <label class="erta-form-label">{{ field.label }}<span v-if="field.required" class="erta-req">*</span></label>
              <select class="erta-input" disabled>
                <option>— select —</option>
                <option v-for="o in field.options" :key="o.value">{{ o.label }}</option>
              </select>
            </template>
            <template v-else-if="field.type === 'checkbox'">
              <label class="erta-check-label">
                <input type="checkbox" disabled />
                {{ field.label }}<span v-if="field.required" class="erta-req">*</span>
              </label>
            </template>
            <template v-else>
              <label class="erta-form-label">{{ field.label }}<span v-if="field.required" class="erta-req">*</span></label>
              <input class="erta-input" :type="field.type" :placeholder="field.placeholder" disabled />
            </template>
            <p v-if="field.help" class="erta-field-help">{{ field.help }}</p>
          </div>
        </div>
      </div>

    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useAdminApi } from '../../composables/useAdminApi.js';

const api     = useAdminApi();
const t       = (k) => window.ertaAdminData?.i18n?.[k] ?? k;
const isPro   = window.ertaAdminData?.isPro ?? false;

const loading    = ref(true);
const saving     = ref(false);
const saveError  = ref(null);
const forms      = ref([]);
const editing    = ref(null);
const activeField = ref(null);

// ── Field type palette ─────────────────────────────────────────────────────
const fieldTypes = [
  { type: 'text',     label: 'Text',        icon: '🔤' },
  { type: 'email',    label: 'Email',       icon: '📧' },
  { type: 'tel',      label: 'Phone',       icon: '📞' },
  { type: 'number',   label: 'Number',      icon: '🔢' },
  { type: 'date',     label: 'Date',        icon: '📅' },
  { type: 'textarea', label: 'Textarea',    icon: '📝' },
  { type: 'select',   label: 'Dropdown',    icon: '🔽' },
  { type: 'checkbox', label: 'Checkbox',    icon: '☑️'  },
  { type: 'calendar', label: 'Date & Time', icon: '🗓️'  },
];

function fieldTypeLabel(type) {
  return fieldTypes.find(f => f.type === type)?.label ?? type;
}

function formShortcode(form) {
  const formId = form?.id ?? '';
  return formId ? `[erta-booking form="${formId}"]` : '[erta-booking]';
}

async function copyShortcode(form) {
  const shortcode = formShortcode(form);
  try {
    await navigator.clipboard.writeText(shortcode);
  } catch (e) {
    const ta = document.createElement('textarea');
    ta.value = shortcode;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
  }
}

// ── Default field set ──────────────────────────────────────────────────────
const DEFAULT_FIELDS = () => [
  { id: 'customer_name',  type: 'text',     label: 'Full Name',    required: true,  system: false },
  { id: 'customer_email', type: 'email',    label: 'Email Address',required: true,  system: false },
  { id: 'customer_phone', type: 'tel',      label: 'Phone Number', required: false, system: false },
  { id: 'notes',          type: 'textarea', label: 'Notes',        required: false, system: false },
  { id: '__calendar__',   type: 'calendar', label: 'Date & Time',  required: true,  system: true  },
];

// ── Lifecycle ──────────────────────────────────────────────────────────────
onMounted(load);

async function load() {
  loading.value = true;
  const { data } = await api.getForms();
  forms.value    = data ?? [];
  loading.value  = false;
}

// ── Actions ────────────────────────────────────────────────────────────────
function openNew() {
  editing.value  = { name: '', scope: 'global', fields: DEFAULT_FIELDS() };
  activeField.value = null;
}

function openEdit(f) {
  editing.value  = JSON.parse(JSON.stringify(f));
  activeField.value = null;
}

function addField(type) {
  const field = {
    id:          'field_' + Date.now(),
    type,
    label:       fieldTypeLabel(type),
    required:    false,
    placeholder: '',
    help:        '',
    options:     type === 'select' ? [{ label: 'Option 1', value: 'opt_1' }] : undefined,
    system:      false,
  };
  if (!editing.value.fields) editing.value.fields = [];
  editing.value.fields.push(field);
  activeField.value = editing.value.fields.length - 1;
}

function removeField(idx) {
  editing.value.fields.splice(idx, 1);
  activeField.value = null;
}

function moveField(idx, dir) {
  const fields = editing.value.fields;
  const target = idx + dir;
  if (target < 0 || target >= fields.length) return;
  [fields[idx], fields[target]] = [fields[target], fields[idx]];
  activeField.value = target;
}

async function saveForm() {
  saveError.value = null;
  if (!isPro && editing.value?.scope === 'department') {
    saveError.value = t('departmentProOnly');
    return;
  }
  saving.value    = true;
  const { error } = await api.saveForm(editing.value);
  saving.value    = false;
  if (error) { saveError.value = error; return; }
  editing.value   = null;
  await load();
}

async function del(id) {
  if (! confirm(t('deleteConfirm'))) return;
  await api.deleteForm(id);
  await load();
}
</script>
