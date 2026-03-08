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
          <option value="global">{{ t('global') }}</option>
          <option value="department">{{ t('department') }}</option>
          <option value="provider">{{ t('provider') }}</option>
        </select>

        <select
          v-if="scope === 'department'"
          class="erta-input"
          v-model.number="scopeId"
          @change="onScopeIdChanged"
        >
          <option :value="0">— {{ t('selectDepartment') }} —</option>
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
          <option :value="0">— {{ t('selectProvider') }} —</option>
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
          {{ t('installationChecklist') }}
          <span class="erta-badge" :class="installation.all_ok ? 'erta-badge--confirmed' : 'erta-badge--cancelled'">
            {{ installation.all_ok ? t('ready') : t('missing') }}
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
            ? t('installationOkMessage')
            : t('installationMissingMessage') }}
        </div>

        <div class="erta-install-checklist__actions">
          <button
            class="erta-btn erta-btn--ghost erta-btn--sm"
            :disabled="checkingInstallation || repairingInstallation"
            @click="refreshInstallationChecklist"
          >
            <span v-if="checkingInstallation" class="erta-spinner erta-spinner--sm"></span>
            {{ t('recheck') }}
          </button>

          <button
            v-if="!installation.all_ok"
            class="erta-btn erta-btn--primary erta-btn--sm"
            :disabled="repairingInstallation || checkingInstallation"
            @click="repairInstallationNow"
          >
            <span v-if="repairingInstallation" class="erta-spinner erta-spinner--sm"></span>
            {{ t('repairNow') }}
          </button>
        </div>

        <div class="erta-install-checklist">
          <div v-for="item in installation.items" :key="item.key" class="erta-install-checklist__item">
            <span>{{ item.label }}</span>
            <span class="erta-badge" :class="item.ok ? 'erta-badge--confirmed' : 'erta-badge--cancelled'">
              {{ item.ok ? 'OK' : t('missingShort') }}
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
          <label>{{ t('bufferAfter') }}</label>
          <input class="erta-input" type="number" v-model.number="form.buffer_after_minutes" min="0" />
        </div>
        <div class="erta-form-row">
          <label>{{ t('arrivalBuffer') }}</label>
          <input class="erta-input" type="number" v-model.number="form.arrival_buffer" min="0" />
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
            <option value="TRY">{{ t('currencyTRY') }}</option>
            <option value="USD">{{ t('currencyUSD') }}</option>
            <option value="EUR">{{ t('currencyEUR') }}</option>
            <option value="GBP">{{ t('currencyGBP') }}</option>
          </select>
        </div>
        <div class="erta-form-row">
          <label>{{ t('bookingStartDate') }}</label>
          <input class="erta-input" type="date" v-model="form.booking_start_date" />
        </div>
        <div class="erta-form-row">
          <label>{{ t('bookingEndDate') }}</label>
          <input class="erta-input" type="date" v-model="form.booking_end_date" />
        </div>
        <div class="erta-form-row">
          <label>{{ t('arrivalReminder') }}</label>
          <label class="erta-toggle">
            <input type="checkbox" v-model="form.show_arrival_reminder" />
            <span class="erta-toggle__slider"></span>
          </label>
        </div>
        <div class="erta-form-row">
          <label>{{ t('bookingMode') }}</label>
          <select class="erta-input" v-model="form.booking_mode">
            <option value="general">{{ t('bookingModeGeneral') }}</option>
            <option value="department_no_provider">{{ t('bookingModeDeptOnly') }}</option>
            <option value="department_with_provider">{{ t('bookingModeDeptProvider') }}</option>
            <option value="provider_only">{{ t('bookingModeProvider') }}</option>
          </select>
        </div>
        <div class="erta-form-row">
          <label>{{ t('appointmentLocation') }}</label>
          <input class="erta-input" type="text" v-model="form.appointment_location" />
        </div>
        <div class="erta-form-row">
          <label>{{ t('bookingFormIntro') }}</label>
          <textarea class="erta-input" rows="3" v-model="form.booking_form_intro"></textarea>
        </div>
        <div class="erta-form-row">
          <label>{{ t('bookingFormIntroColor') }}</label>
          <input class="erta-input" type="color" v-model="form.booking_form_intro_color" />
        </div>
        <div class="erta-form-row">
          <label>{{ t('postBookingInstructions') }}</label>
          <textarea class="erta-input" rows="3" v-model="form.post_booking_instructions"></textarea>
        </div>
        <div class="erta-form-row">
          <label>{{ t('postBookingInstructionsColor') }}</label>
          <input class="erta-input" type="color" v-model="form.post_booking_instructions_color" />
        </div>
      </template>

      <!-- Payment -->
      <template v-if="activeTab === 'payment'">
        <div v-if="!isPro" class="erta-alert erta-alert--info">{{ t('proOnlyArea') }}</div>
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
            <option value="stripe">{{ t('gatewayStripe') }}</option>
            <option value="paypal">{{ t('gatewayPaypal') }}</option>
            <option value="paytr">{{ t('gatewayPaytr') }}</option>
            <option value="iyzico">{{ t('gatewayIyzico') }}</option>
          </select>
        </div>

        <!-- PayTR fields -->
        <template v-if="form.payment_gateway === 'paytr'">
          <div class="erta-form-row">
            <label>{{ t('paytrMerchantId') }}</label>
            <input class="erta-input" type="text" v-model="form.paytr_merchant_id" />
          </div>
          <div class="erta-form-row">
            <label>{{ t('paytrMerchantKey') }}</label>
            <input class="erta-input" type="password" v-model="form.paytr_merchant_key" />
          </div>
          <div class="erta-form-row">
            <label>{{ t('paytrMerchantSalt') }}</label>
            <input class="erta-input" type="password" v-model="form.paytr_merchant_salt" />
          </div>
          <div class="erta-form-row">
            <label>{{ t('paytrTestMode') }}</label>
            <label class="erta-toggle">
              <input type="checkbox" v-model="form.paytr_test_mode" />
              <span class="erta-toggle__slider"></span>
            </label>
          </div>
        </template>

        <!-- Stripe fields -->
        <template v-if="form.payment_gateway === 'stripe'">
          <div class="erta-form-row">
            <label>{{ t('stripeSecretKey') }}</label>
            <input class="erta-input" type="password" v-model="form.stripe_secret_key" />
          </div>
          <div class="erta-form-row">
            <label>{{ t('stripeWebhookSecret') }}</label>
            <input class="erta-input" type="password" v-model="form.stripe_webhook_secret" />
          </div>
        </template>

        <!-- İyzico fields -->
        <template v-if="form.payment_gateway === 'iyzico'">
          <div class="erta-form-row">
            <label>{{ t('iyzicoApiKey') }}</label>
            <input class="erta-input" type="password" v-model="form.iyzico_api_key" />
          </div>
          <div class="erta-form-row">
            <label>{{ t('iyzicoSecretKey') }}</label>
            <input class="erta-input" type="password" v-model="form.iyzico_secret_key" />
          </div>
          <div class="erta-form-row">
            <label>{{ t('iyzicoSandbox') }}</label>
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
        <div v-if="!isPro" class="erta-alert erta-alert--info">{{ t('proOnlyArea') }}</div>
        <fieldset :disabled="!isPro" class="erta-fieldset-reset" :class="{ 'erta-pro-gate': !isPro }">

        <!-- ── Google Calendar ──────────────────────────────────────────── -->
        <div class="erta-integration-card">
          <div class="erta-integration-card__header">
            <span class="erta-integration-card__icon">📅</span>
            <div>
              <h3 class="erta-integration-card__title">{{ t('googleCalendar') }}</h3>
              <p class="erta-integration-card__desc">{{ t('googleCalendarDesc') }}</p>
            </div>
            <span
              class="erta-badge"
              :class="googleConnected ? 'erta-badge--confirmed' : 'erta-badge--pending'"
            >{{ googleConnected ? t('connected') : t('notConnected') }}</span>
          </div>

          <div class="erta-integration-card__body">
            <div class="erta-form-row">
              <label>{{ t('oauthClientId') }}</label>
              <div>
                <input class="erta-input" v-model="form.google_client_id" placeholder="*.apps.googleusercontent.com" />
                <p class="description">{{ t('googleClientIdHelp') }}</p>
              </div>
            </div>
            <div class="erta-form-row">
              <label>{{ t('oauthClientSecret') }}</label>
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
              <h3 class="erta-integration-card__title">{{ t('zoom') }}</h3>
              <p class="erta-integration-card__desc">{{ t('zoomDesc') }}</p>
            </div>
            <span
              class="erta-badge"
              :class="zoomConfigured ? 'erta-badge--confirmed' : 'erta-badge--pending'"
            >{{ zoomConfigured ? t('configured') : t('notConfigured') }}</span>
          </div>

          <div class="erta-integration-card__body">
            <div class="erta-form-row">
              <label>{{ t('accountId') }}</label>
              <input class="erta-input" v-model="form.zoom_account_id" placeholder="xxxxxxxxxxxx" />
            </div>
            <div class="erta-form-row">
              <label>{{ t('clientId') }}</label>
              <input class="erta-input" v-model="form.zoom_client_id" />
            </div>
            <div class="erta-form-row">
              <label>{{ t('clientSecret') }}</label>
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

        <!-- ── SMS (Twilio / NetGSM) ───────────────────────────────────── -->
        <div class="erta-integration-card">
          <div class="erta-integration-card__header">
            <span class="erta-integration-card__icon">💬</span>
            <div>
              <h3 class="erta-integration-card__title">{{ t('smsIntegrationsTitle') }}</h3>
              <p class="erta-integration-card__desc">{{ t('smsIntegrationsDesc') }}</p>
            </div>
          </div>

          <div class="erta-integration-card__body">
            <div class="erta-form-row">
              <label>{{ t('smsProvider') }}</label>
              <select class="erta-input" v-model="form.sms_provider">
                <option value="twilio">Twilio</option>
                <option value="netgsm">NetGSM</option>
              </select>
            </div>

            <template v-if="form.sms_provider === 'twilio'">
              <div class="erta-form-row">
                <label>{{ t('twilioAccountSid') }}</label>
                <input class="erta-input" v-model="form.twilio_account_sid" />
              </div>
              <div class="erta-form-row">
                <label>{{ t('twilioAuthToken') }}</label>
                <input class="erta-input" type="password" v-model="form.twilio_auth_token" />
              </div>
              <div class="erta-form-row">
                <label>{{ t('twilioFromNumber') }}</label>
                <input class="erta-input" v-model="form.twilio_from_number" placeholder="+90555..." />
              </div>
            </template>

            <template v-else>
              <div class="erta-form-row">
                <label>{{ t('netgsmUsercode') }}</label>
                <input class="erta-input" v-model="form.netgsm_usercode" />
              </div>
              <div class="erta-form-row">
                <label>{{ t('netgsmPassword') }}</label>
                <input class="erta-input" type="password" v-model="form.netgsm_password" />
              </div>
              <div class="erta-form-row">
                <label>{{ t('netgsmHeader') }}</label>
                <input class="erta-input" v-model="form.netgsm_header" />
              </div>
            </template>
          </div>
        </div>

        <!-- ── WhatsApp (Meta Cloud API) ──────────────────────────────── -->
        <div class="erta-integration-card">
          <div class="erta-integration-card__header">
            <span class="erta-integration-card__icon">🟢</span>
            <div>
              <h3 class="erta-integration-card__title">{{ t('whatsappTitle') }}</h3>
              <p class="erta-integration-card__desc">{{ t('whatsappDesc') }}</p>
            </div>
          </div>

          <div class="erta-integration-card__body">
            <div class="erta-form-row">
              <label>{{ t('whatsappProvider') }}</label>
              <select class="erta-input" v-model="form.whatsapp_provider">
                <option value="meta_cloud">Meta Cloud API</option>
              </select>
            </div>
            <div class="erta-form-row">
              <label>{{ t('whatsappPhoneNumberId') }}</label>
              <input class="erta-input" v-model="form.whatsapp_phone_number_id" />
            </div>
            <div class="erta-form-row">
              <label>{{ t('whatsappAccessToken') }}</label>
              <input class="erta-input" type="password" v-model="form.whatsapp_access_token" />
            </div>
            <div class="erta-form-row">
              <label>{{ t('whatsappApiVersion') }}</label>
              <input class="erta-input" v-model="form.whatsapp_api_version" placeholder="v21.0" />
            </div>
          </div>
        </div>

        <!-- ── PayTR ─────────────────────────────────────────────────────── -->
        <div class="erta-integration-card">
          <div class="erta-integration-card__header">
            <span class="erta-integration-card__icon">💳</span>
            <div>
              <h3 class="erta-integration-card__title">{{ t('paytr') }}</h3>
              <p class="erta-integration-card__desc">{{ t('paytrDesc') }}</p>
            </div>
          </div>
          <div class="erta-integration-card__body">
            <div class="erta-form-row">
              <label>{{ t('merchantId') }}</label>
              <input class="erta-input" v-model="form.paytr_merchant_id" />
            </div>
            <div class="erta-form-row">
              <label>{{ t('merchantKey') }}</label>
              <input class="erta-input" type="password" v-model="form.paytr_merchant_key" />
            </div>
            <div class="erta-form-row">
              <label>{{ t('merchantSalt') }}</label>
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
  sms_provider: 'twilio',
  twilio_account_sid: '',
  twilio_auth_token: '',
  twilio_from_number: '',
  netgsm_usercode: '',
  netgsm_password: '',
  netgsm_header: '',
  whatsapp_provider: 'meta_cloud',
  whatsapp_phone_number_id: '',
  whatsapp_access_token: '',
  whatsapp_api_version: 'v21.0',
  booking_start_date: '',
  booking_end_date: '',
  arrival_buffer: 0,
  booking_mode: 'department_with_provider',
  show_arrival_reminder: false,
  appointment_location: '',
  booking_form_intro: '',
  booking_form_intro_color: '#dbeafe',
  post_booking_instructions: '',
  post_booking_instructions_color: '#f3f4f6',
  allow_general_booking: false,
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
    error.value = decodeURIComponent(urlParams.get('msg') ?? t('googleConnectionFailed'));
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
    normalizeBooleanSettings();
    installation.value = data.installation ?? null;
  }
}

function normalizeBooleanSettings() {
  const booleanKeys = [
    'auto_confirm',
    'allow_general_booking',
    'show_arrival_reminder',
    'payment_required',
    'paytr_test_mode',
    'iyzico_sandbox',
    'zoom_auto_create',
  ];

  for (const key of booleanKeys) {
    const value = form.value[key];
    if (typeof value === 'boolean') continue;

    if (typeof value === 'number') {
      form.value[key] = value === 1;
      continue;
    }

    if (typeof value === 'string') {
      const normalized = value.trim().toLowerCase();
      form.value[key] = normalized === '1' || normalized === 'true' || normalized === 'yes';
      continue;
    }

    form.value[key] = Boolean(value);
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
    error.value = t('proTabLockedMessage');
    return;
  }

  saving.value = true;
  error.value  = null;
  const currentScope = scope.value;
  const currentScopeId = currentScope === 'global' ? null : (scopeId.value || null);

  if (currentScope !== 'global' && !currentScopeId) {
    saving.value = false;
    error.value = t('selectScopeItemFirst');
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
