<template>
  <div class="erta-step-panel erta-step-form">
    <h3 class="erta-step-title">{{ t('fillDetails') }}</h3>
    <div
      v-if="introText"
      class="erta-info-box"
      :style="infoBoxStyle"
      v-text="introText"
    ></div>

    <!-- Booking summary bar -->
    <div class="erta-summary-bar">
      <span v-if="summary.provider">📋 {{ summary.provider }}</span>
      <span>📅 {{ summary.date }}</span>
      <span>🕐 {{ summary.time }}</span>
    </div>

    <form class="erta-form" @submit.prevent="handleSubmit" novalidate>

      <template v-for="field in visibleFields" :key="field.id">

        <!-- Calendar placeholder: shows selected date/time, read-only -->
        <div v-if="field.type === 'calendar'" class="erta-form-field erta-form-field--calendar">
          <label class="erta-form-label">{{ field.label }}</label>
          <div class="erta-datetime-badge">
            <span class="erta-datetime-part erta-datetime-part--date">
              <span class="erta-datetime-icon" aria-hidden="true">📅</span>
              <span>{{ summary.date }}</span>
            </span>
            <span class="erta-datetime-sep" aria-hidden="true">&bull;</span>
            <span class="erta-datetime-part erta-datetime-part--time">
              <span class="erta-datetime-icon" aria-hidden="true">🕐</span>
              <span>{{ summary.time }}</span>
            </span>
          </div>
        </div>

        <!-- Textarea -->
        <div v-else-if="field.type === 'textarea'" class="erta-form-field">
          <label :for="field.id" class="erta-form-label">
            {{ field.label }}<span v-if="field.required" class="erta-req">*</span>
          </label>
          <textarea
            :id="field.id"
            class="erta-input"
            :class="{ 'erta-input--error': errors[field.id] }"
            :value="fieldValue(field.id, '')"
            @input="updateField(field.id, $event.target.value)"
            rows="3"
          ></textarea>
          <span v-if="errors[field.id]" class="erta-field-error">{{ errors[field.id] }}</span>
        </div>

        <!-- Select -->
        <div v-else-if="field.type === 'select'" class="erta-form-field">
          <label :for="field.id" class="erta-form-label">
            {{ field.label }}<span v-if="field.required" class="erta-req">*</span>
          </label>
          <select
            :id="field.id"
            class="erta-input"
            :value="fieldValue(field.id, '')"
            @change="updateField(field.id, $event.target.value)"
          >
            <option value="">— {{ t('select') }} —</option>
            <option v-for="opt in field.options" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </option>
          </select>
          <span v-if="errors[field.id]" class="erta-field-error">{{ errors[field.id] }}</span>
        </div>

        <!-- Checkbox -->
        <div v-else-if="field.type === 'checkbox'" class="erta-form-field erta-form-field--check">
          <label class="erta-check-label">
            <input
              type="checkbox"
              :checked="Boolean(fieldValue(field.id, false))"
              @change="updateField(field.id, $event.target.checked)"
              :required="field.required"
            />
            {{ field.label }}<span v-if="field.required" class="erta-req">*</span>
          </label>
          <span v-if="errors[field.id]" class="erta-field-error">{{ errors[field.id] }}</span>
        </div>

        <!-- text / email / tel / number / date -->
        <div v-else class="erta-form-field">
          <label :for="field.id" class="erta-form-label">
            {{ field.label }}<span v-if="field.required" class="erta-req">*</span>
          </label>
          <input
            :id="field.id"
            :type="field.type"
            class="erta-input"
            :class="{ 'erta-input--error': errors[field.id] }"
            :placeholder="field.placeholder ?? ''"
            :value="fieldValue(field.id, '')"
            @input="updateField(field.id, $event.target.value)"
            @blur="validateField(field)"
          />
          <span v-if="errors[field.id]" class="erta-field-error">{{ errors[field.id] }}</span>
        </div>

      </template>

      <div class="erta-step-actions">
        <button type="button" class="erta-btn erta-btn--ghost" @click="$emit('back')">
          {{ t('back') }}
        </button>
        <button type="submit" class="erta-btn erta-btn--primary" :disabled="submitting">
          <span v-if="submitting" class="erta-spinner erta-spinner--sm"></span>
          {{ submitButtonLabel }}
        </button>
      </div>

    </form>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
  form:       { type: Object,  default: null },
  summary:    { type: Object,  required: true },
  introText:  { type: String,  default: '' },
  introColor: { type: String,  default: '' },
  modelValue: { type: Object,  default: () => ({}) },
  submitting: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'submit', 'back']);

const t = (k) => window.ertaData?.i18n?.[k] ?? k;

// Local copy of form data.
const localData = ref({ ...props.modelValue });
const errors    = ref({});

const infoBoxStyle = computed(() => {
  if (!props.introColor) {
    return {};
  }

  return {
    '--erta-info-box-bg': props.introColor,
  };
});

function sameFormData(a, b) {
  const left = a ?? {};
  const right = b ?? {};

  const leftKeys = Object.keys(left);
  const rightKeys = Object.keys(right);

  if (leftKeys.length !== rightKeys.length) return false;

  for (const key of leftKeys) {
    if (left[key] !== right[key]) return false;
  }

  return true;
}

watch(
  () => props.modelValue,
  (val) => {
    if (!sameFormData(val, localData.value)) {
      localData.value = { ...(val ?? {}) };
    }
  },
  { immediate: true }
);

function fieldValue(id, fallback = '') {
  const value = localData.value[id];
  return value ?? fallback;
}

function updateField(id, value) {
  if (localData.value[id] === value) return;

  localData.value = {
    ...localData.value,
    [id]: value,
  };

  emit('update:modelValue', { ...localData.value });
}

// Default fields used when no custom form is configured.
const defaultFields = [
  { id: 'customer_name',  type: 'text',     label: t('fullName'),     required: true  },
  { id: 'customer_email', type: 'email',    label: t('emailAddress'), required: true  },
  { id: 'customer_phone', type: 'tel',      label: t('phoneNumber'),  required: false },
  { id: 'notes',          type: 'textarea', label: t('notes'),        required: false },
  {
    id: '__calendar__',
    type: 'calendar',
    label: t('datetime'),
    required: true,
    system: true,
    placeholder: true,
  },
];

const visibleFields = computed(() => {
  const fields = props.form?.fields?.length ? props.form.fields : defaultFields;
  // Ensure the calendar placeholder appears; add it at the end if admin forgot it.
  const hasCalendar = fields.some(f => f.type === 'calendar');
  return hasCalendar ? fields : [...fields, defaultFields.find(f => f.type === 'calendar')];
});

const submitButtonLabel = computed(() => {
  const overrideText = String(props.form?.submit_button_text ?? '').trim();
  return overrideText || t('book');
});

// ── Validation ─────────────────────────────────────────────────────────────

function validateField(field) {
  const val = localData.value[field.id];

  if (field.required && (!val || String(val).trim() === '')) {
    errors.value[field.id] = t('required');
    return false;
  }

  if (field.type === 'email' && val) {
    const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
    if (!ok) { errors.value[field.id] = t('invalidEmail'); return false; }
  }

  delete errors.value[field.id];
  return true;
}

function validateAll() {
  let valid = true;
  for (const field of visibleFields.value) {
    if (field.type === 'calendar') continue;
    if (!validateField(field)) valid = false;
  }
  return valid;
}

function handleSubmit() {
  if (!validateAll()) return;
  emit('submit');
}
</script>
