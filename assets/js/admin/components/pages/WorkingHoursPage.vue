<!-- WorkingHoursPage.vue — Working hours, breaks, special days per scope -->
<template>
  <div class="erta-page">
    <div class="erta-page-header">
      <h1 class="erta-page-title">{{ t('workingHours') }}</h1>
      <button
        class="erta-btn erta-btn--primary"
        :disabled="saving"
        @click="save"
      >
        <span v-if="saving" class="erta-spinner erta-spinner--sm"></span>
        {{ t('save') }}
      </button>
    </div>

    <div v-if="saved"  class="erta-alert erta-alert--success">{{ t('saved') }}</div>
    <div v-if="error"  class="erta-alert erta-alert--error">{{ error }}</div>
    <div v-if="!isPro" class="erta-alert erta-alert--info">{{ t('departmentProOnly') }}</div>

    <!-- Scope selector -->
    <div class="erta-scope-bar">
      <div class="erta-form-row erta-form-row--inline">
        <label class="erta-form-label">{{ t('editScope') }}</label>
        <select class="erta-input erta-input--narrow" v-model="scope" @change="loadScope">
          <option value="global">{{ t('global') }}</option>
          <option value="department" :disabled="!isPro">{{ t('department') }} ({{ t('proBadge') }})</option>
          <option value="provider">{{ t('provider') }}</option>
        </select>
        <label v-if="scope !== 'global'" class="erta-form-label">{{ t('selectScopeItem') }}</label>
        <select
          v-if="scope !== 'global'"
          class="erta-input erta-input--narrow"
          v-model="scopeId"
          :disabled="loadingScopeItems || !scopeItems.length"
          @change="loadScope"
        >
          <option v-if="loadingScopeItems" value="">— {{ t('loading') }} —</option>
          <option v-else-if="!scopeItems.length" value="">— Kayıt yok —</option>
          <option v-for="item in scopeItems" :key="item.id" :value="item.id">
            {{ item.name }}
          </option>
        </select>
      </div>
      <p class="erta-scope-hint">{{ scopeHint }}</p>
    </div>

    <div v-if="loading" class="erta-loading"><span class="erta-spinner"></span></div>
    <template v-else>

      <!-- ── Tabs ──────────────────────────────────────────────────────── -->
      <div class="erta-admin-tabs">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          class="erta-admin-tab"
          :class="{ 'erta-admin-tab--active': activeTab === tab.key }"
          @click="activeTab = tab.key"
        >{{ tab.label }}</button>
      </div>

      <!-- ── Working Hours ─────────────────────────────────────────────── -->
      <div v-show="activeTab === 'hours'" class="erta-wh-section">
        <p class="erta-section-hint">{{ t('workingHoursHint') }}</p>
        <div class="erta-hours-grid">
          <div
            v-for="day in workingHours"
            :key="day.day_of_week"
            class="erta-hours-row"
          >
            <div class="erta-hours-day">
              <label class="erta-toggle erta-toggle--sm">
                <input type="checkbox" v-model="day.is_open" />
                <span class="erta-toggle__slider"></span>
              </label>
              <span class="erta-hours-day__label" :class="{ 'erta-hours-day__label--closed': !day.is_open }">
                {{ dayName(day.day_of_week) }}
              </span>
            </div>
            <template v-if="day.is_open">
              <input
                type="time"
                class="erta-input erta-input--time"
                v-model="day.open_time"
              />
              <span class="erta-hours-sep">—</span>
              <input
                type="time"
                class="erta-input erta-input--time"
                v-model="day.close_time"
              />
            </template>
            <span v-else class="erta-hours-closed">{{ t('closed') }}</span>
          </div>
        </div>
      </div>

      <!-- ── Breaks ────────────────────────────────────────────────────── -->
      <div v-show="activeTab === 'breaks'" class="erta-wh-section">
        <p class="erta-section-hint">{{ t('breaksHint') }}</p>

        <div class="erta-break-list">
          <div
            v-for="(brk, idx) in breaks"
            :key="idx"
            class="erta-break-card"
          >
            <div class="erta-break-card__header">
              <input
                class="erta-input"
                v-model="brk.name"
                :placeholder="t('breakName')"
                style="flex:1"
              />
              <button
                class="erta-icon-btn erta-icon-btn--danger"
                @click="breaks.splice(idx, 1)"
              >✕</button>
            </div>
            <div class="erta-break-card__row">
              <label class="erta-fe-label">{{ t('day') }}</label>
              <select class="erta-input erta-input--narrow" v-model="brk.day_of_week">
                <option :value="null">{{ t('everyDay') }}</option>
                <option v-for="d in 7" :key="d" :value="d">{{ dayName(d) }}</option>
              </select>
              <label class="erta-fe-label" style="margin-left:12px">{{ t('time') }}</label>
              <input type="time" class="erta-input erta-input--time" v-model="brk.start_time" />
              <span class="erta-hours-sep">—</span>
              <input type="time" class="erta-input erta-input--time" v-model="brk.end_time" />
            </div>
          </div>

          <div v-if="!breaks.length" class="erta-empty-hint">{{ t('noBreaks') }}</div>
        </div>

        <button class="erta-btn erta-btn--ghost" style="margin-top:14px" @click="addBreak">
          + {{ t('addBreak') }}
        </button>
      </div>

      <!-- ── Special Days ──────────────────────────────────────────────── -->
      <div v-show="activeTab === 'special'" class="erta-wh-section">
        <p class="erta-section-hint">{{ t('specialDaysHint') }}</p>

        <div class="erta-special-list">
          <div
            v-for="(sd, idx) in specialDays"
            :key="idx"
            class="erta-special-card"
          >
            <div class="erta-special-card__row">
              <input
                type="date"
                class="erta-input erta-input--date"
                v-model="sd.date"
              />
              <input
                class="erta-input"
                v-model="sd.name"
                :placeholder="t('specialDayName')"
                style="flex:1"
              />
              <label class="erta-check-label">
                <input type="checkbox" v-model="sd.is_closed" />
                {{ t('closed') }}
              </label>
              <button class="erta-icon-btn erta-icon-btn--danger" @click="specialDays.splice(idx,1)">✕</button>
            </div>
            <div v-if="!sd.is_closed" class="erta-special-card__hours">
              <label class="erta-fe-label">{{ t('customHours') }}</label>
              <input type="time" class="erta-input erta-input--time" v-model="sd.custom_open_time"  />
              <span class="erta-hours-sep">—</span>
              <input type="time" class="erta-input erta-input--time" v-model="sd.custom_close_time" />
            </div>
          </div>

          <div v-if="!specialDays.length" class="erta-empty-hint">{{ t('noSpecialDays') }}</div>
        </div>

        <button class="erta-btn erta-btn--ghost" style="margin-top:14px" @click="addSpecialDay">
          + {{ t('addSpecialDay') }}
        </button>
      </div>

    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useAdminApi } from '../../composables/useAdminApi.js';

const api = useAdminApi();
const t   = (k) => window.ertaAdminData?.i18n?.[k] ?? k;
const isPro = window.ertaAdminData?.isPro ?? false;

// ── State ──────────────────────────────────────────────────────────────────
const scope      = ref('global');
const scopeId    = ref(null);
const scopeItems = ref([]);
const loadingScopeItems = ref(false);
const loading    = ref(true);
const saving     = ref(false);
const saved      = ref(false);
const error      = ref(null);
const activeTab  = ref('hours');

const workingHours = ref([]);
const breaks       = ref([]);
const specialDays  = ref([]);

const tabs = [
  { key: 'hours',   label: t('workingHours') },
  { key: 'breaks',  label: t('breaks') },
  { key: 'special', label: t('specialDays') },
];

// ── Scope hint ─────────────────────────────────────────────────────────────
const scopeHint = computed(() => {
  if (scope.value === 'global')     return t('globalScopeHint');
  if (scope.value === 'department') return t('departmentScopeHint');
  return t('providerScopeHint');
});

// ── Day names (ISO: 1=Mon … 7=Sun) ────────────────────────────────────────
const DAY_NAMES = {
  1: 'Monday', 2: 'Tuesday', 3: 'Wednesday',
  4: 'Thursday', 5: 'Friday', 6: 'Saturday', 7: 'Sunday',
};
function dayName(n) { return DAY_NAMES[n] ?? `Day ${n}`; }

// ── Lifecycle ──────────────────────────────────────────────────────────────
onMounted(async () => {
  await loadScopeItems();
  await loadScope();
});

watch(scope, async () => {
  if (!isPro && scope.value === 'department') {
    scope.value = 'global';
    return;
  }
  scopeId.value = null;
  await loadScopeItems();
  await loadScope();
});

// ── Load helpers ───────────────────────────────────────────────────────────
async function loadScopeItems() {
  loadingScopeItems.value = true;

  if (!isPro && scope.value === 'department') {
    scope.value = 'global';
  }

  if (scope.value === 'global') {
    scopeItems.value = [];
    loadingScopeItems.value = false;
    return;
  }

  if (scope.value === 'department') {
    const { data } = await api.listDepartments();
    scopeItems.value = data?.items ?? data ?? [];
  } else {
    const { data } = await api.listProviders();
    scopeItems.value = data?.items ?? data ?? [];
  }

  if (scopeItems.value.length && !scopeId.value) {
    scopeId.value = scopeItems.value[0].id;
  }

  loadingScopeItems.value = false;
}

async function loadScope() {
  loading.value = true;
  error.value   = null;

  const sid = scope.value === 'global' ? null : scopeId.value;

  const [hoursRes, breaksRes, specialRes] = await Promise.all([
    api.getWorkingHours(scope.value, sid),
    api.getBreaks(scope.value, sid),
    api.getSpecialDays(scope.value, sid),
  ]);

  const incomingHours = Array.isArray(hoursRes.data) ? hoursRes.data : [];
  workingHours.value = incomingHours.length ? incomingHours : buildDefaultHours();
  breaks.value       = breaksRes.data ?? [];
  specialDays.value  = specialRes.data ?? [];
  loading.value      = false;
}

// ── Save ───────────────────────────────────────────────────────────────────
async function save() {
  saving.value = true;
  saved.value  = false;
  error.value  = null;

  const sid = scope.value === 'global' ? null : scopeId.value;

  const results = await Promise.all([
    api.saveWorkingHours(scope.value, sid, workingHours.value),
    api.saveBreaks(scope.value, sid, breaks.value),
    api.saveSpecialDays(scope.value, sid, specialDays.value),
  ]);

  saving.value = false;
  const err = results.find(r => r.error);
  if (err) { error.value = err.error; return; }

  saved.value = true;
  setTimeout(() => { saved.value = false; }, 3000);
}

// ── Add helpers ────────────────────────────────────────────────────────────
function addBreak() {
  breaks.value.push({
    name:        t('newBreak'),
    day_of_week: null,
    start_time:  '12:00',
    end_time:    '13:00',
  });
}

function addSpecialDay() {
  const today = new Date().toISOString().slice(0, 10);
  specialDays.value.push({
    date:              today,
    name:              '',
    is_closed:         true,
    custom_open_time:  '09:00',
    custom_close_time: '17:00',
  });
}

// ── Default working hours (Mon–Fri 09:00–17:00) ────────────────────────────
function buildDefaultHours() {
  return Array.from({ length: 7 }, (_, i) => ({
    day_of_week:  i + 1,
    is_open:      i < 5,   // Mon–Fri open
    open_time:    '09:00',
    close_time:   '17:00',
  }));
}
</script>
