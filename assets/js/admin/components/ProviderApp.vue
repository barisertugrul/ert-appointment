<template>
  <div class="erta-admin-wrap">
    <nav class="erta-sidebar">
      <div class="erta-sidebar__brand">📅 {{ t('myPanel') }}</div>
      <ul class="erta-nav">
        <li v-for="item in navItems" :key="item.page">
          <button class="erta-nav__item" :class="{ 'erta-nav__item--active': currentPage === item.page }" @click="currentPage = item.page">
            <span class="erta-nav__icon">{{ item.icon }}</span>{{ item.label }}
          </button>
        </li>
      </ul>
    </nav>
    <main class="erta-admin-content">
      <component :is="currentComponent" :key="currentPage" />
    </main>
  </div>
</template>
<script setup>
import { ref, computed, defineAsyncComponent } from 'vue';
const props = defineProps({ page: String, userId: Number });
const t = (k) => window.ertaAdminData?.i18n?.[k] ?? k;
const currentPage = ref(props.page ?? 'erta-provider-dashboard');
const navItems = [
  { page: 'erta-provider-dashboard', icon: '📊', label: t('dashboard')          },
  { page: 'erta-provider-upcoming',  icon: '📋', label: t('upcomingAppointments') },
  { page: 'erta-provider-hours',     icon: '🕐', label: t('workingHours')        },
  { page: 'erta-provider-calendar',  icon: '📅', label: t('calendar')            },
];
const pages = {
  'erta-provider-dashboard': defineAsyncComponent(() => import('./pages/DashboardPage.vue')),
  'erta-provider-upcoming':  defineAsyncComponent(() => import('./pages/AppointmentsPage.vue')),
};
const currentComponent = computed(() => pages[currentPage.value] ?? pages['erta-provider-dashboard']);
</script>
