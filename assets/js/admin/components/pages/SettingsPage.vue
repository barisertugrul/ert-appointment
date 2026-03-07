<template>
  <div class="erta-page">
    <div class="erta-page-header">
      <h1 class="erta-page-title">{{ t('settings') }}</h1>
      <button class="erta-btn erta-btn--primary" :disabled="saving || isActiveProTabLocked" @click="save">
        <span v-if="saving" class="erta-spinner erta-spinner--sm"></span>
        {{ t('save') }}
      </button>
    </div>

    <div v-if="saved" class="erta-alert erta-alert--success">{{ t('settingsSaved') }}</div>
    <div v-if="error"  class="erta-alert erta-alert--error">{{ error }}</div>

    <div class="erta-scope-row">
      <label>{{ t('scope') }}</label>
      <div class="erta-scope-grid">
        <select class="erta-input" v-model="scope" @change="onScopeChanged">
          <option value="global">Global</option>
          <option value="department">Department</option>
          <option value="provider">Provider</option>
        </select>

        <select
          v-if="scope === 'department'"
          class="erta-input"
          v-model.number="scopeId"
          @change="onScopeIdChanged"
        >
          <option :value="0">— Select Department —</option>
          <option v-for="item in departments" :key="item.id" :value="item.id">
            {{ item.name }}
          </option>
        </select>

        <select
          v-if="scope === 'provider'"
          class="erta-input"
          v-model.number="scopeId"
          @change="onScopeIdChanged"
        >
          <option :value="0">— Select Provider —</option>
          <option v-for="item in providers" :key="item.id" :value="item.id">
            {{ item.name }}
          </option>
        </select>
      </div>
    </div>

    <div v-if="installation" class="erta-install-checklist-wrap">
      <button
        type="button"
        class="erta-install-checklist__toggle"
        @click="checklistOpen = !checklistOpen"
      >
        <span>
          Kurulum Kontrolü
          <span class="erta-badge" :class="installation.all_ok ? 'erta-badge--confirmed' : 'erta-badge--cancelled'">
            {{ installation.all_ok ? 'Hazır' : 'Eksik Var' }}
          </span>
        </span>
        <span>{{ checklistOpen ? '▾' : '▸' }}</span>
      </button>

      <div v-show="checklistOpen" class="erta-install-checklist__panel">
        <div
          class="erta-alert"
          :class="installation.all_ok ? 'erta-alert--success' : 'erta-alert--error'"
        >
          {{ installation.all_ok
            ? 'Kurulum kontrolü başarılı: tablolar, roller ve yetkiler hazır.'
            : 'Kurulum eksikleri tespit edildi: bazı tablo/rol/yetkiler oluşturulmamış.' }}
        </div>

        <div class="erta-install-checklist__actions">
          <button
            class="erta-btn erta-btn--ghost erta-btn--sm"
            :disabled="checkingInstallation || repairingInstallation"
            @click="refreshInstallationChecklist"
          >
            <span v-if="checkingInstallation" class="erta-spinner erta-spinner--sm"></span>
            Yeniden Kontrol Et
          </button>

          <button
            v-if="!installation.all_ok"
            class="erta-btn erta-btn--primary erta-btn--sm"
            :disabled="repairingInstallation || checkingInstallation"
            @click="repairInstallationNow"
          >
            <span v-if="repairingInstallation" class="erta-spinner erta-spinner--sm"></span>
            Şimdi Onar
          </button>
        </div>

        <div class="erta-install-checklist">
          <div v-for="item in installation.items" :key="item.key" class="erta-install-checklist__item">
            <span>{{ item.label }}</span>
            <span class="erta-badge" :class="item.ok ? 'erta-badge--confirmed' : 'erta-badge--cancelled'">
              {{ item.ok ? 'OK' : 'Eksik' }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="erta-admin-tabs">
      <button v-for="tab in tabs" :key="tab.key"
        class="erta-admin-tab" :class="{ 'erta-admin-tab--active': activeTab === tab.key }"
        @click="activeTab = tab.key">
        {{ tab.label }}
        <span v-if="tab.pro && !isPro" class="erta-pro-badge">PRO</span>
      </button>
    </div>

    <div v-if="loading" class="erta-loading"><span class="erta-spinner"></span></div>

    <form v-else class="erta-settings-form" @submit.prevent="save">

      <!-- General -->
      <template v-if="activeTab === 'general'">
        <div class="erta-form-row">
          <label>{{ t('slotDuration') }}</label>
          <div>
            <select class="erta-input" v-model.number="form.slot_duration_minutes">
              <option :value="15">15 dk</option><option :value="30">30 dk</option>
              <option :value="45">45 dk</option><option :value="60">60 dk</option>
              <option :value="90">90 dk</option><option :value="120">120 dk</option>
            </select>
          </div>
        </div>
        <div class="erta-form-row">
          <label>{{ t('bufferBefore') }}</label>
          <input class="erta-input" type="number" v-model.number="form.buffer_before_minutes" min="0" />
        </div>
        <div class="erta-form-row">
          <label>{{ t('bufferAfter') }}</label>
          <input class="erta-input" type="number" v-model.number="form.buffer_after_minutes" min="0" />
        </div>
        <div class="erta-form-row">
          <label>{{ t('minNotice') }}</label>
          <div>
            <input class="erta-input" type="number" v-model.number="form.min_notice_hours" min="0" />
            <p class="description">{{ t('minNoticeDesc') }}</p>
          </div>
        </div>
        <div class="erta-form-row">
          <label>{{ t('maxAdvance') }}</label>
          <input class="erta-input" type="number" v-model.number="form.max_advance_days" min="1" />
        </div>
        <div class="erta-form-row">
          <label>{{ t('autoConfirm') }}</label>
          <label class="erta-toggle">
            <input type="checkbox" v-model="form.auto_confirm" />
            <span class="erta-toggle__slider"></span>
          </label>
        </div>
        <div class="erta-form-row">
          <label>{{ t('currency') }}</label>
          <select class="erta-input" v-model="form.currency">
            <option value="TRY">TRY — Türk Lirası</option>
            <option value="USD">USD — US Dollar</option>
            <option value="EUR">EUR — Euro</option>
            <option value="GBP">GBP — Pound</option>
          </select>
        </div>
        <div class="erta-form-row">
          <label>Booking Start Date</label>
          <input class="erta-input" type="date" v-model="form.booking_start_date" />
        </div>
        <div class="erta-form-row">
          <label>Booking End Date</label>
          <input class="erta-input" type="date" v-model="form.booking_end_date" />
        </div>
        <div class="erta-form-row">
          <label>Arrival Reminder</label>
          <label class="erta-toggle">
            <input type="checkbox" v-model="form.show_arrival_reminder" />
            <span class="erta-toggle__slider"></span>
          </label>
        </div>
        <div class="erta-form-row">
          <label>Appointment Location</label>
          <input class="erta-input" type="text" v-model="form.appointment_location" />
        </div>
        <div class="erta-form-row">
          <label>Booking Form Intro</label>
          <textarea class="erta-input" rows="3" v-model="form.booking_form_intro"></textarea>
        </div>
        <div class="erta-form-row">
          <label>Post Booking Instructions</label>
          <textarea class="erta-input" rows="3" v-model="form.post_booking_instructions"></textarea>
        </div>
        <div class="erta-form-row">
          <label>Allow General Booking</label>
          <label class="erta-toggle">
            <input type="checkbox" v-model="form.allow_general_booking" />
            <span class="erta-toggle__slider"></span>
          </label>
        </div>
        <div class="erta-form-row" v-if="form.allow_general_booking">
          <label>General Provider</label>
          <select class="erta-input" v-model.number="form.general_provider_id">
            <option :value="0">— Auto (first available) —</option>
            <option v-for="item in providers" :key="item.id" :value="item.id">
              {{ item.name }}
            </option>
          </select>
        </div>
      </template>

      <!-- Payment -->
      <template v-if="activeTab === 'payment'">
        <div v-if="!isPro" class="erta-alert erta-alert--info">Bu alan Pro sürümde aktiftir.</div>
        <fieldset :disabled="!isPro" class="erta-fieldset-reset" :class="{ 'erta-pro-gate': !isPro }">
        <div class="erta-form-row">
          <label>{{ t('paymentRequired') }}</label>
          <label class="erta-toggle">
            <input type="checkbox" v-model="form.payment_required" />
            <span class="erta-toggle__slider"></span>
          </label>
        </div>
        <div class="erta-form-row">
          <label>{{ t('paymentAmount') }}</label>
          <input class="erta-input" type="number" v-model.number="form.payment_amount" min="0" step="0.01" />
        </div>
        <div class="erta-form-row">
          <label>{{ t('paymentGateway') }}</label>
          <select class="erta-input" v-model="form.payment_gateway">
            <option value="stripe">Stripe</option>
            <option value="paypal">PayPal</option>
            <option value="paytr">PayTR</option>
            <option value="iyzico">İyzico</option>
          </select>
        </div>

        <!-- PayTR fields -->
        <template v-if="form.payment_gateway === 'paytr'">
          <div class="erta-form-row">
            <label>PayTR Merchant ID</label>
            <input class="erta-input" type="text" v-model="form.paytr_merchant_id" />
          </div>
          <div class="erta-form-row">
            <label>PayTR Merchant Key</label>
            <input class="erta-input" type="password" v-model="form.paytr_merchant_key" />
          </div>
          <div class="erta-form-row">
            <label>PayTR Merchant Salt</label>
            <input class="erta-input" type="password" v-model="form.paytr_merchant_salt" />
          </div>
          <div class="erta-form-row">
            <label>PayTR Test Modu</label>
            <label class="erta-toggle">
              <input type="checkbox" v-model="form.paytr_test_mode" />
              <span class="erta-toggle__slider"></span>
            </label>
          </div>
        </template>

        <!-- Stripe fields -->
        <template v-if="form.payment_gateway === 'stripe'">
          <div class="erta-form-row">
            <label>Stripe Secret Key</label>
            <input class="erta-input" type="password" v-model="form.stripe_secret_key" />
          </div>
          <div class="erta-form-row">
            <label>Stripe Webhook Secret</label>
            <input class="erta-input" type="password" v-model="form.stripe_webhook_secret" />
          </div>
        </template>

        <!-- İyzico fields -->
        <template v-if="form.payment_gateway === 'iyzico'">
          <div class="erta-form-row">
            <label>İyzico API Key</label>
            <input class="erta-input" type="password" v-model="form.iyzico_api_key" />
          </div>
          <div class="erta-form-row">
            <label>İyzico Secret Key</label>
            <input class="erta-input" type="password" v-model="form.iyzico_secret_key" />
          </div>
          <div class="erta-form-row">
            <label>İyzico Sandbox</label>
            <label class="erta-toggle">
              <input type="checkbox" v-model="form.iyzico_sandbox" />
              <span class="erta-toggle__slider"></span>
            </label>
          </div>
        </template>
        </fieldset>
      </template>

      <!-- Integrations (Google Calendar + Zoom + PayTR) -->
      <template v-if="activeTab === 'integrations'">
        <div v-if="!isPro" class="erta-alert erta-alert--info">Bu alan Pro sürümde aktiftir.</div>
        <fieldset :disabled="!isPro" class="erta-fieldset-reset" :class="{ 'erta-pro-gate': !isPro }">

        <!-- ── Google Calendar ──────────────────────────────────────────── -->
        <div class="erta-integration-card">
          <div class="erta-integration-card__header">
            <span class="erta-integration-card__icon">📅</span>
            <div>
              <h3 class="erta-integration-card__title">Google Calendar</h3>
              <p class="erta-integration-card__desc">{{ t('googleCalendarDesc') }}</p>
            </div>
            <span
              class="erta-badge"
              :class="googleConnected ? 'erta-badge--confirmed' : 'erta-badge--pending'"
            >{{ googleConnected ? t('connected') : t('notConnected') }}</span>
          </div>

          <div class="erta-integration-card__body">
            <div class="erta-form-row">
              <label>OAuth Client ID</label>
              <div>
                <input class="erta-input" v-model="form.google_client_id" placeholder="*.apps.googleusercontent.com" />
                <p class="description">{{ t('googleClientIdHelp') }}</p>
              </div>
            </div>
            <div class="erta-form-row">
              <label>OAuth Client Secret</label>
              <input class="erta-input" type="password" v-model="form.google_client_secret" />
            </div>
            <div class="erta-form-row">
              <label>{{ t('callbackUrl') }}</label>
              <div>
                <code class="erta-code-block">{{ restUrl }}integrations/google/callback</code>
                <p class="description">{{ t('googleCallbackHelp') }}</p>
              </div>
            </div>
          </div>

          <div class="erta-integration-card__actions">
            <template v-if="!googleConnected">
              <button class="erta-btn erta-btn--primary erta-btn--sm" @click="connectGoogle">
                🔗 {{ t('connectGoogle') }}
              </button>
            </template>
            <template v-else>
              <span class="erta-integration-ok">✓ {{ t('googleConnectedAs') }}: {{ googleUserEmail }}</span>
              <button class="erta-btn erta-btn--danger erta-btn--sm" @click="disconnectGoogle">
                {{ t('disconnect') }}
              </button>
            </template>
          </div>
        </div>

        <!-- ── Zoom ─────────────────────────────────────────────────────── -->
        <div class="erta-integration-card">
          <div class="erta-integration-card__header">
            <span class="erta-integration-card__icon">🎥</span>
            <div>
              <h3 class="erta-integration-card__title">Zoom</h3>
              <p class="erta-integration-card__desc">{{ t('zoomDesc') }}</p>
            </div>
            <span
              class="erta-badge"
              :class="zoomConfigured ? 'erta-badge--confirmed' : 'erta-badge--pending'"
            >{{ zoomConfigured ? t('configured') : t('notConfigured') }}</span>
          </div>

          <div class="erta-integration-card__body">
            <div class="erta-form-row">
              <label>Account ID</label>
              <input class="erta-input" v-model="form.zoom_account_id" placeholder="xxxxxxxxxxxx" />
            </div>
            <div class="erta-form-row">
              <label>Client ID</label>
              <input class="erta-input" v-model="form.zoom_client_id" />
            </div>
            <div class="erta-form-row">
              <label>Client Secret</label>
              <input class="erta-input" type="password" v-model="form.zoom_client_secret" />
            </div>
            <div class="erta-form-row">
              <label>{{ t('autoCreateMeeting') }}</label>
              <div>
                <label class="erta-toggle">
                  <input type="checkbox" v-model="form.zoom_auto_create" />
                  <span class="erta-toggle__slider"></span>
                </label>
                <p class="description">{{ t('autoCreateMeetingDesc') }}</p>
              </div>
            </div>
          </div>

          <div class="erta-integration-card__actions">
            <button
              class="erta-btn erta-btn--ghost erta-btn--sm"
              :disabled="zoomTesting"
              @click="testZoom"
            >
              <span v-if="zoomTesting" class="erta-spinner erta-spinner--sm"></span>
              🔬 {{ t('testConnection') }}
            </button>
            <span v-if="zoomTestResult === 'ok'"   class="erta-integration-ok">✓ {{ t('connectionOk') }}</span>
            <span v-if="zoomTestResult === 'fail'" class="erta-integration-err">✗ {{ t('connectionFailed') }}</span>
          </div>
        </div>

        <!-- ── PayTR ─────────────────────────────────────────────────────── -->
        <div class="erta-integration-card">
          <div class="erta-integration-card__header">
            <span class="erta-integration-card__icon">💳</span>
            <div>
              <h3 class="erta-integration-card__title">PayTR</h3>
              <p class="erta-integration-card__desc">{{ t('paytrDesc') }}</p>
            </div>
          </div>
          <div class="erta-integration-card__body">
            <div class="erta-form-row">
              <label>Merchant ID</label>
              <input class="erta-input" v-model="form.paytr_merchant_id" />
            </div>
            <div class="erta-form-row">
              <label>Merchant Key</label>
              <input class="erta-input" type="password" v-model="form.paytr_merchant_key" />
            </div>
            <div class="erta-form-row">
              <label>Merchant Salt</label>
              <input class="erta-input" type="password" v-model="form.paytr_merchant_salt" />
            </div>
            <div class="erta-form-row">
              <label>{{ t('testMode') }}</label>
              <label class="erta-toggle">
                <input type="checkbox" v-model="form.paytr_test_mode" />
                <span class="erta-toggle__slider"></span>
              </label>
            </div>
            <div class="erta-form-row">
              <label>{{ t('callbackUrl') }}</label>
              <code class="erta-code-block">{{ restUrl }}payment/webhook/paytr</code>
            </div>
          </div>
        </div>

        </fieldset>

      </template>

    </form>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAdminApi } from '../../composables/useAdminApi.js';

const api       = useAdminApi();
const t         = (k) => window.ertaAdminData?.i18n?.[k] ?? k;
const loading   = ref(true);
const saving    = ref(false);
const saved     = ref(false);
const error     = ref(null);
const installation = ref(null);
const checklistOpen = ref(false);
const checkingInstallation = ref(false);
const repairingInstallation = ref(false);
const activeTab = ref('general');
const scope = ref('global');
const scopeId = ref(0);
const departments = ref([]);
const providers = ref([]);

// Integration state
const googleConnected  = ref(false);
const googleUserEmail  = ref('');
const zoomTesting      = ref(false);
const zoomTestResult   = ref(null);  // null | 'ok' | 'fail'
const restUrl          = window.ertaAdminData?.restUrl ?? '/wp-json/erta/v1/';
const nonce            = window.ertaAdminData?.nonce   ?? '';
const isPro            = Boolean(window.ertaAdminData?.isPro);
const isActiveProTabLocked = computed(
  () => !isPro && (activeTab.value === 'payment' || activeTab.value === 'integrations')
);

const tabs = [
  { key: 'general',      label: t('general')      },
  { key: 'payment',      label: t('payment'), pro: true },
  { key: 'integrations', label: t('integrations'), pro: true },
];

const defaultForm = {
  slot_duration_minutes: 30,
  buffer_before_minutes: 0,
  buffer_after_minutes: 0,
  min_notice_hours: 1,
  max_advance_days: 60,
  auto_confirm: false,
  currency: 'TRY',
  payment_required: false,
  payment_amount: 0,
  payment_gateway: 'paytr',
  paytr_merchant_id: '',
  paytr_merchant_key: '',
  paytr_merchant_salt: '',
  paytr_test_mode: true,
  stripe_secret_key: '',
  stripe_webhook_secret: '',
  iyzico_api_key: '',
  iyzico_secret_key: '',
  iyzico_sandbox: true,
  google_client_id: '',
  google_client_secret: '',
  zoom_account_id: '',
  zoom_client_id: '',
  zoom_client_secret: '',
  zoom_auto_create: false,
  booking_start_date: '',
  booking_end_date: '',
  show_arrival_reminder: false,
  appointment_location: '',
  booking_form_intro: '',
  post_booking_instructions: '',
  allow_general_booking: false,
  general_provider_id: 0,
};

const form = ref({ ...defaultForm });

onMounted(async () => {
  await loadScopeEntities();
  parseScopeFromUrl();
  await loadSettings();

  // Check Google Calendar connection status only when integration routes exist.
  if (isPro) {
    await refreshGoogleStatus();
  }

  // Check URL params: redirect back from Google OAuth callback.
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('google') === 'connected') {
    await refreshGoogleStatus();
    activeTab.value = 'integrations';
    // Clean up URL.
    history.replaceState({}, '', window.location.pathname + '?page=erta-settings');
  }
  if (urlParams.get('google') === 'error') {
    error.value = decodeURIComponent(urlParams.get('msg') ?? 'Google connection failed.');
    activeTab.value = 'integrations';
  }

  loading.value = false;
});

async function loadSettings() {
  const currentScope = scope.value;
  const currentScopeId = currentScope === 'global' ? null : (scopeId.value || null);

  if (currentScope !== 'global' && !currentScopeId) {
    Object.assign(form.value, defaultForm);
    return;
  }

  const { data, error: err } = await api.getSettings(currentScope, currentScopeId);
  if (err) {
    error.value = err;
    return;
  }

  if (data) {
    Object.assign(form.value, defaultForm);
    Object.assign(form.value, data.settings ?? data ?? {});
    installation.value = data.installation ?? null;
  }
}

async function loadScopeEntities() {
  const [deptRes, provRes] = await Promise.all([
    api.listDepartments(),
    api.listProviders(),
  ]);

  departments.value = deptRes.data?.items ?? deptRes.data ?? [];
  providers.value = provRes.data?.items ?? provRes.data ?? [];
}

function parseScopeFromUrl() {
  const params = new URLSearchParams(window.location.search);
  const nextScope = params.get('scope');
  const nextScopeId = Number(params.get('scope_id') || 0);

  if (nextScope === 'department' || nextScope === 'provider' || nextScope === 'global') {
    scope.value = nextScope;
  }

  if (scope.value === 'department' || scope.value === 'provider') {
    scopeId.value = Number.isFinite(nextScopeId) ? nextScopeId : 0;
  }
}

function updateUrlForScope() {
  const url = new URL(window.location.href);
  url.searchParams.set('page', 'erta-settings');
  url.searchParams.set('scope', scope.value);

  if (scope.value === 'global') {
    url.searchParams.delete('scope_id');
  } else {
    url.searchParams.set('scope_id', String(scopeId.value || 0));
  }

  history.replaceState({}, '', url.toString());
}

async function onScopeChanged() {
  scopeId.value = 0;
  error.value = null;
  updateUrlForScope();
  Object.assign(form.value, defaultForm);
  await loadSettings();
}

async function onScopeIdChanged() {
  error.value = null;
  updateUrlForScope();
  Object.assign(form.value, defaultForm);
  await loadSettings();
}

async function refreshInstallationChecklist() {
  checkingInstallation.value = true;
  const { data, error: err } = await api.getSettings('global', null);
  checkingInstallation.value = false;

  if (err) {
    error.value = err;
    return;
  }

  installation.value = data?.installation ?? null;
}

async function repairInstallationNow() {
  repairingInstallation.value = true;
  error.value = null;

  const { data, error: err } = await api.repairInstallation();
  repairingInstallation.value = false;

  if (err) {
    error.value = err;
    return;
  }

  installation.value = data?.installation ?? null;
  if (installation.value?.all_ok) {
    saved.value = true;
    setTimeout(() => (saved.value = false), 3000);
  }
}

// ── Save ───────────────────────────────────────────────────────────────────
async function save() {
  if (isActiveProTabLocked.value) {
    error.value = 'Bu sekme Pro sürümde düzenlenebilir.';
    return;
  }

  saving.value = true;
  error.value  = null;
  const currentScope = scope.value;
  const currentScopeId = currentScope === 'global' ? null : (scopeId.value || null);

  if (currentScope !== 'global' && !currentScopeId) {
    saving.value = false;
    error.value = 'Please select a scope item first.';
    return;
  }

  const { error: err } = await api.saveSettings(currentScope, currentScopeId, form.value);
  saving.value = false;
  if (err) { error.value = err; return; }
  saved.value = true;
  setTimeout(() => (saved.value = false), 3000);
}

// ── Google Calendar ────────────────────────────────────────────────────────
async function refreshGoogleStatus() {
  if (!isPro) return;
  try {
    const res  = await fetch(`${restUrl}integrations/google/status`, {
      headers: { 'X-WP-Nonce': nonce },
    });
    const data = await res.json();
    googleConnected.value = data?.connected ?? false;
    googleUserEmail.value  = data?.email    ?? '';
  } catch { googleConnected.value = false; }
}

async function connectGoogle() {
  if (!isPro) return;
  // First save credentials so the backend can use them for the OAuth URL.
  await save();
  // Fetch the auth URL from the backend (it includes the signed state parameter).
  const res  = await fetch(`${restUrl}integrations/google/auth`, {
    headers: { 'X-WP-Nonce': nonce },
  });
  const data = await res.json().catch(() => ({}));
  if (data?.url) {
    window.location.href = data.url;
  } else {
    error.value = t('googleAuthUrlFailed');
  }
}

async function disconnectGoogle() {
  if (!isPro) return;
  await fetch(`${restUrl}integrations/google/disconnect`, {
    method: 'DELETE',
    headers: { 'X-WP-Nonce': nonce },
  });
  googleConnected.value = false;
  googleUserEmail.value  = '';
}

// ── Zoom ───────────────────────────────────────────────────────────────────
async function testZoom() {
  if (!isPro) return;
  zoomTesting.value   = true;
  zoomTestResult.value = null;
  try {
    const res  = await fetch(`${restUrl}integrations/zoom/test`, {
      headers: { 'X-WP-Nonce': nonce },
    });
    const data = await res.json().catch(() => ({}));
    zoomTestResult.value = data?.ok ? 'ok' : 'fail';
  } catch {
    zoomTestResult.value = 'fail';
  }
  zoomTesting.value = false;
}
</script>
