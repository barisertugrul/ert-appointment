<template>
  <div class="erta-admin-wrap">

    <!-- Sidebar nav -->
    <nav class="erta-sidebar">
      <div class="erta-sidebar__brand">📅 ERT Appointment</div>
      <ul class="erta-nav">
        <li v-for="item in navItems" :key="item.page">
          <button
            class="erta-nav__item"
            :class="{ 'erta-nav__item--active': currentPage === item.page }"
            @click="navigate(item.page)"
          >
            <span class="erta-nav__icon">{{ item.icon }}</span>
            {{ item.label }}
            <span v-if="(item.pro || item.proBadgeOnly) && !isPro" class="erta-pro-badge">PRO</span>
          </button>
        </li>
        <li v-if="!isPro">
          <a
            class="erta-nav__item"
            :href="buyProUrl"
            target="_blank"
            rel="noopener noreferrer"
          >
            <span class="erta-nav__icon">🚀</span>
            {{ t('buyPro') }}
            <span class="erta-pro-badge">PRO</span>
          </a>
        </li>
      </ul>
    </nav>

    <!-- Content area -->
    <main class="erta-admin-content">

      <component
        :is="currentComponent"
        :key="currentPage"
      />

    </main>
  </div>
</template>

<script setup>
import { ref, computed, defineAsyncComponent } from 'vue';

const props = defineProps({ page: { type: String, default: 'erta-dashboard' } });

const isPro       = window.ertaAdminData?.isPro ?? false;
const buyProUrl   = window.ertaAdminData?.buyProUrl ?? 'https://www.ertyazilim.com/ert-appointment-pro/#buy';
const t           = (k) => window.ertaAdminData?.i18n?.[k] ?? k;
const currentPage = ref(props.page);

const navItems = [
  { page: 'erta-dashboard',     icon: '📊', label: t('dashboard')     },
  { page: 'erta-appointments',  icon: '📋', label: t('appointments')  },
  { page: 'erta-departments',   icon: '🏢', label: t('departments'), proBadgeOnly: true },
  { page: 'erta-providers',     icon: '👤', label: t('providers'), proBadgeOnly: true },
  { page: 'erta-forms',          icon: '📝', label: t('forms')         },
  { page: 'erta-working-hours', icon: '🕐', label: t('workingHours')  },
  { page: 'erta-notifications', icon: '🔔', label: t('notifications') },
  { page: 'erta-reports',       icon: '📈', label: t('reports'), pro: true },
  { page: 'erta-settings',      icon: '⚙️',  label: t('settings')     },
];

// Lazy-load each page component.
const pages = {
  'erta-dashboard':     defineAsyncComponent(() => import('./pages/DashboardPage.vue')),
  'erta-appointments':  defineAsyncComponent(() => import('./pages/AppointmentsPage.vue')),
  'erta-departments':   defineAsyncComponent(() => import('./pages/DepartmentsPage.vue')),
  'erta-providers':     defineAsyncComponent(() => import('./pages/ProvidersPage.vue')),
  'erta-forms':          defineAsyncComponent(() => import('./pages/FormsPage.vue')),
  'erta-working-hours':  defineAsyncComponent(() => import('./pages/WorkingHoursPage.vue')),
  'erta-notifications':  defineAsyncComponent(() => import('./pages/NotificationsPage.vue')),
  'erta-reports':       defineAsyncComponent(() => import('./pages/ReportsPage.vue')),
  'erta-settings':      defineAsyncComponent(() => import('./pages/SettingsPage.vue')),
};

const currentComponent = computed(() => pages[currentPage.value] ?? pages['erta-dashboard']);

function navigate(page) {
  // Pro gate: redirect to settings with upgrade notice.
  const item = navItems.find(n => n.page === page);
  if (item?.pro && !isPro) {
    currentPage.value = 'erta-settings'; // show settings with Pro upsell
    return;
  }
  currentPage.value = page;
  // Update URL without page reload (WP admin context).
  const url = new URL(window.location.href);
  url.searchParams.set('page', page);
  history.replaceState({}, '', url.toString());
}
</script>
