<!-- NotificationsPage.vue — Notification template editor -->
<template>
  <div class="erta-page">

    <!-- List view -->
    <template v-if="!editing">
      <div class="erta-page-header">
        <h1 class="erta-page-title">{{ t('notifications') }}</h1>
        <button class="erta-btn erta-btn--primary" @click="openNewTemplate">+ {{ t('addTemplate') }}</button>
      </div>

      <div v-if="saved"  class="erta-alert erta-alert--success">{{ t('saved') }}</div>
      <div v-if="error"  class="erta-alert erta-alert--error">{{ error }}</div>

      <details class="erta-quick-combos">
        <summary class="erta-quick-combos__summary">
          <span>{{ t('quickTemplateCombos') }}</span>
          <span class="erta-quick-combos__hint">{{ t('createCustomerAdminEmail') }}</span>
        </summary>

        <div class="erta-quick-combos__grid">
          <button
            v-for="event in EVENT_ORDER"
            :key="`quick-${event}`"
            class="erta-quick-combos__btn"
            :disabled="saving"
            @click="createCustomerAdminEmailPair(event)"
          >
            <span class="erta-quick-combos__btn-event">{{ eventLabel(event) }}</span>
            <span class="erta-quick-combos__btn-action">{{ t('createCustomerAdminEmail') }}</span>
          </button>
        </div>
      </details>

      <div v-if="loading" class="erta-loading"><span class="erta-spinner"></span></div>
      <template v-else>
        <!-- Grouped by event -->
        <div v-for="(group, event) in grouped" :key="event" class="erta-notif-group">
          <div class="erta-notif-group__title">
            <span class="erta-notif-event-icon">{{ eventIcon(event) }}</span>
            {{ eventLabel(event) }}
          </div>
          <div class="erta-table-wrap">
            <table class="erta-table">
              <thead>
                <tr>
                  <th>{{ t('channel') }}</th>
                  <th>{{ t('recipient') }}</th>
                  <th>{{ t('subject') }}</th>
                  <th>{{ t('active') }}</th>
                  <th>{{ t('actions') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="tpl in group" :key="tpl.id">
                  <td>
                    <span class="erta-badge" :class="tpl.channel === 'email' ? 'erta-badge--confirmed' : 'erta-badge--pending'">
                      {{ channelLabel(tpl.channel) }}
                    </span>
                  </td>
                  <td>{{ recipientLabel(tpl.recipient || tpl.recipient_type) }}</td>
                  <td class="erta-notif-subject">{{ tpl.subject || '—' }}</td>
                  <td>
                    <label class="erta-toggle erta-toggle--sm">
                      <input type="checkbox" :checked="tpl.is_active == 1" @change="toggleActive(tpl)" />
                      <span class="erta-toggle__slider"></span>
                    </label>
                  </td>
                  <td>
                    <button class="erta-btn erta-btn--sm erta-btn--ghost" @click="openEditor(tpl)">
                      ✏️ {{ t('edit') }}
                    </button>
                  </td>
                </tr>
                <tr v-if="!group.length">
                  <td colspan="5" class="erta-empty-cell">{{ t('noTemplates') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </template>

    <!-- Editor view -->
    <template v-else>
      <div class="erta-page-header">
        <h1 class="erta-page-title">
          {{ eventLabel(editing.event || editing.event_type) }} — {{ channelLabel(editing.channel) }} / {{ recipientLabel(editing.recipient) }}
        </h1>
        <div class="erta-header-actions">
          <button class="erta-btn erta-btn--ghost" @click="editing = null">{{ t('cancel') }}</button>
          <button class="erta-btn erta-btn--primary" :disabled="saving" @click="saveTemplate">
            <span v-if="saving" class="erta-spinner erta-spinner--sm"></span>
            {{ t('save') }}
          </button>
        </div>
      </div>

      <div v-if="saveError" class="erta-alert erta-alert--error">{{ saveError }}</div>

      <div class="erta-editor-layout">

        <!-- Left: form -->
        <div class="erta-editor-form">
          <div class="erta-form-row">
            <label class="erta-form-label">{{ t('templateGroup') }}</label>
            <select class="erta-input" v-model="editing.event">
              <option v-for="evt in EVENT_ORDER" :key="evt" :value="evt">{{ eventLabel(evt) }}</option>
            </select>
          </div>

          <div class="erta-form-row">
            <label class="erta-form-label">{{ t('templateChannel') }}</label>
            <select class="erta-input" v-model="editing.channel">
              <option value="email">{{ channelLabel('email') }}</option>
              <option value="sms" :disabled="!isPro">{{ channelLabel('sms') }}</option>
              <option value="whatsapp" :disabled="!isPro">{{ channelLabel('whatsapp') }}</option>
            </select>
          </div>

          <div class="erta-form-row">
            <label class="erta-form-label">{{ t('templateRecipient') }}</label>
            <select class="erta-input" v-model="editing.recipient">
              <option value="customer">{{ recipientLabel('customer') }}</option>
              <option value="provider">{{ recipientLabel('provider') }}</option>
              <option value="admin">{{ recipientLabel('admin') }}</option>
            </select>
          </div>

          <!-- Subject (email only) -->
          <div v-if="editing.channel === 'email'" class="erta-form-row">
            <label class="erta-form-label">{{ t('subject') }}</label>
            <input class="erta-input" v-model="editing.subject" :placeholder="t('emailSubjectPlaceholder')" />
          </div>

          <!-- Body -->
          <div class="erta-form-row erta-form-row--stacked">
            <label class="erta-form-label">{{ t('body') }}</label>
            <div class="erta-textarea-wrap">
              <textarea
                class="erta-input erta-textarea--template"
                v-model="editing.body"
                rows="14"
                @keyup="updatePreview"
              ></textarea>
              <p class="description" style="margin-top:6px">
                {{ t('templateBodyHint') }}
              </p>
            </div>
          </div>

          <!-- Active toggle -->
          <div class="erta-form-row">
            <label class="erta-form-label">{{ t('active') }}</label>
            <label class="erta-toggle">
              <input type="checkbox" v-model="editing.is_active" />
              <span class="erta-toggle__slider"></span>
            </label>
          </div>
        </div>

        <!-- Right: placeholder cheatsheet + live preview -->
        <div class="erta-editor-sidebar">

          <!-- Placeholder chips -->
          <div class="erta-placeholder-panel">
            <h4 class="erta-sidebar-title">{{ t('availablePlaceholders') }}</h4>
            <div class="erta-placeholder-list">
              <button
                v-for="ph in placeholders"
                :key="ph.token"
                class="erta-placeholder-chip"
                :title="ph.description"
                @click="insertToken(ph.token)"
              >{{ ph.token }}</button>
            </div>
            <p class="description" style="margin-top:6px;font-size:.78rem">
              {{ t('clickToInsert') }}
            </p>
          </div>

          <!-- Live preview -->
          <div class="erta-preview-panel">
            <h4 class="erta-sidebar-title">{{ t('preview') }}</h4>
            <div v-if="editing.channel === 'email'" class="erta-email-preview">
              <div class="erta-email-preview__subject">
                <strong>{{ t('subject') }}:</strong> {{ previewSubject }}
              </div>
              <div class="erta-email-preview__body" v-html="previewBody"></div>
            </div>
            <div v-else class="erta-sms-preview">
              {{ previewBody }}
            </div>
          </div>

        </div>
      </div>
    </template>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAdminApi } from '../../composables/useAdminApi.js';

const api = useAdminApi();
const t   = (k) => window.ertaAdminData?.i18n?.[k] ?? k;
const isPro = Boolean(window.ertaAdminData?.isPro);

const loading   = ref(true);
const saving    = ref(false);
const saved     = ref(false);
const error     = ref(null);
const saveError = ref(null);
const templates    = ref([]);
const placeholders = ref([]);
const editing      = ref(null);

// ── Preview state ──────────────────────────────────────────────────────────
const previewSubject = ref('');
const previewBody    = ref('');

// Sample values for live preview rendering.
const SAMPLE = {
  customer_name:       'John Doe',
  customer_email:      'john@example.com',
  customer_phone:      '+90 555 000 00 00',
  appointment_date:    '2025-06-15',
  appointment_time:    '10:00',
  provider_name:       'Dr. Smith',
  arrival_buffer:      '10',
  cancellation_reason: 'Schedule conflict',
  notes:               '',
  site_name:           window.ertaAdminData?.siteName ?? 'My Site',
  site_url:            window.location.origin,
  manage_url:          window.location.origin + '/my-appointments/?id=1',
  booking_url:         window.location.origin + '/booking/',
  admin_url:           window.location.origin + '/wp-admin/',
  zoom_link:           'https://zoom.us/j/12345678',
  zoom_password:       '123456',
  zoom_start_url:      'https://zoom.us/s/12345678',
};

// ── Event metadata ──────────────────────────────────────────────────────────
const EVENT_LABEL_KEYS = {
  appointment_pending:      'notifEventPending',
  appointment_confirmed:    'notifEventConfirmed',
  appointment_cancelled:    'notifEventCancelled',
  appointment_rescheduled:  'notifEventRescheduled',
  appointment_completed:    'notifEventCompleted',
  appointment_no_show:      'notifEventNoShow',
  appointment_reminder:     'notifEventReminder',
  appointment_reminder_24h: 'notifEventReminder24h',
  appointment_reminder_1h:  'notifEventReminder1h',
  waitlist_available:       'notifEventWaitlist',
};

const EVENT_ORDER = Object.keys(EVENT_LABEL_KEYS);

const EVENT_ICONS = {
  appointment_pending:      '🕐',
  appointment_confirmed:    '✅',
  appointment_cancelled:    '❌',
  appointment_rescheduled:  '🔄',
  appointment_completed:    '🏁',
  appointment_no_show:      '👻',
  appointment_reminder:     '🔔',
  appointment_reminder_24h: '🔔',
  appointment_reminder_1h:  '🔔',
  waitlist_available:       '📋',
};

function eventLabel(event) {
  const key = EVENT_LABEL_KEYS[event];
  return key ? t(key) : event;
}
function eventIcon(event)  { return EVENT_ICONS[event]  ?? '📧'; }

function channelLabel(channel) {
  if (channel === 'email') return t('channelEmail');
  if (channel === 'sms') return t('channelSms');
  if (channel === 'whatsapp') return t('channelWhatsapp');
  return channel;
}

function recipientLabel(recipient) {
  if (recipient === 'customer') return t('recipientCustomer');
  if (recipient === 'provider') return t('recipientProvider');
  if (recipient === 'admin') return t('recipientAdmin');
  return recipient;
}

// ── Group templates by event ────────────────────────────────────────────────
const grouped = computed(() => {
  const g = {};
  for (const event of EVENT_ORDER) {
    g[event] = templates.value.filter(t => (t.event || t.event_type) === event);
  }
  return g;
});

// ── Lifecycle ───────────────────────────────────────────────────────────────
onMounted(async () => {
  const [tplRes, phRes] = await Promise.all([
    api.getTemplates(),
    api.getPlaceholders(),
  ]);

  templates.value    = tplRes.data ?? [];
  placeholders.value = phRes.data  ?? [];
  loading.value      = false;
});

// ── Editor ───────────────────────────────────────────────────────────────────
function openEditor(tpl) {
  const cloned = JSON.parse(JSON.stringify(tpl));
  cloned.event = cloned.event || cloned.event_type;
  cloned.recipient = cloned.recipient || cloned.recipient_type;
  editing.value = cloned;
  saveError.value  = null;
  updatePreview();
}

function openNewTemplate() {
  editing.value = {
    event: 'appointment_pending',
    channel: 'email',
    recipient: 'customer',
    subject: '',
    body: '',
    is_active: 1,
  };
  saveError.value = null;
  updatePreview();
}

function updatePreview() {
  if (! editing.value) return;
  previewSubject.value = renderTemplate(editing.value.subject ?? '');
  previewBody.value    = renderTemplate(editing.value.body    ?? '');
}

function renderTemplate(str) {
  return str.replace(/\{\{(\w+)\}\}/g, (_, key) => SAMPLE[key] ?? `{{${key}}}`);
}

/** Inserts a token at the cursor position in the body textarea. */
function insertToken(token) {
  if (! editing.value) return;
  const ta = document.querySelector('.erta-textarea--template');
  if (ta) {
    const start = ta.selectionStart;
    const end   = ta.selectionEnd;
    const body  = editing.value.body ?? '';
    editing.value.body = body.slice(0, start) + token + body.slice(end);
    // Restore cursor after insertion (next tick).
    setTimeout(() => {
      ta.focus();
      ta.setSelectionRange(start + token.length, start + token.length);
      updatePreview();
    }, 0);
  } else {
    editing.value.body = (editing.value.body ?? '') + token;
    updatePreview();
  }
}

// ── Save ────────────────────────────────────────────────────────────────────
async function saveTemplate() {
  saveError.value = null;
  saving.value    = true;

  const payload = {
    ...editing.value,
    event: editing.value.event || editing.value.event_type,
    recipient: editing.value.recipient || editing.value.recipient_type,
  };

  const { data, error: err } = await api.saveTemplate(payload);
  saving.value = false;

  if (err) { saveError.value = err; return; }

  if (editing.value.id) {
    const idx = templates.value.findIndex(t => t.id === editing.value.id);
    if (idx > -1) templates.value[idx] = { ...data };
  } else {
    templates.value.push(data);
  }

  editing.value = null;
  saved.value   = true;
  setTimeout(() => (saved.value = false), 3000);
}

// ── Quick active toggle (from list) ─────────────────────────────────────────
async function toggleActive(tpl) {
  const updated = { ...tpl, is_active: tpl.is_active == 1 ? 0 : 1 };
  await api.saveTemplate({
    ...updated,
    event: updated.event || updated.event_type,
    recipient: updated.recipient || updated.recipient_type,
  });
  const idx = templates.value.findIndex(t => t.id === tpl.id);
  if (idx > -1) templates.value[idx].is_active = updated.is_active;
}

async function createCustomerAdminEmailPair(event) {
  error.value = null;
  saving.value = true;

  const recipients = ['customer', 'admin'];
  let created = 0;

  for (const recipient of recipients) {
    const exists = templates.value.some((tpl) => {
      const tplEvent = tpl.event || tpl.event_type;
      const tplRecipient = tpl.recipient || tpl.recipient_type;
      return tplEvent === event && tpl.channel === 'email' && tplRecipient === recipient;
    });

    if (exists) continue;

    const { data, error: err } = await api.saveTemplate({
      event,
      channel: 'email',
      recipient,
      subject: '',
      body: '',
      is_active: 1,
    });

    if (err) {
      error.value = err;
      continue;
    }

    templates.value.push(data);
    created += 1;
  }

  if (isPro) {
    const existsWhatsAppCustomer = templates.value.some((tpl) => {
      const tplEvent = tpl.event || tpl.event_type;
      const tplRecipient = tpl.recipient || tpl.recipient_type;
      return tplEvent === event && tpl.channel === 'whatsapp' && tplRecipient === 'customer';
    });

    if (!existsWhatsAppCustomer) {
      const { data, error: err } = await api.saveTemplate({
        event,
        channel: 'whatsapp',
        recipient: 'customer',
        subject: '',
        body: '',
        is_active: 0,
      });

      if (err) {
        error.value = err;
      } else {
        templates.value.push(data);
        created += 1;
      }
    }
  }

  saving.value = false;

  if (created > 0) {
    saved.value = true;
    setTimeout(() => (saved.value = false), 3000);
  }
}
</script>
