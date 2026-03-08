<template>
  <div class="erta-step-panel erta-step-calendar">
    <h3 class="erta-step-title">{{ t('selectDate') }}</h3>

    <!-- Month navigation -->
    <div class="erta-calendar-nav">
      <button class="erta-btn erta-btn--ghost" @click="prevMonth" :disabled="!canGoPrev">&#8592;</button>
      <span class="erta-calendar-month">{{ monthLabel }}</span>
      <button class="erta-btn erta-btn--ghost" @click="nextMonth">&#8594;</button>
    </div>

    <!-- Day-of-week headers -->
    <div class="erta-calendar-grid">
      <div v-for="dow in dayHeaders" :key="dow" class="erta-calendar-dow">{{ dow }}</div>

      <!-- Empty lead cells -->
      <div v-for="n in leadDays" :key="'lead-' + n" class="erta-calendar-cell erta-calendar-cell--empty"></div>

      <!-- Date cells -->
      <button
        v-for="day in daysInMonth"
        :key="day.date"
        class="erta-calendar-cell"
        :class="{
          'erta-calendar-cell--available': day.available,
          'erta-calendar-cell--unavailable': !day.available,
          'erta-calendar-cell--selected': day.date === selectedDate,
          'erta-calendar-cell--today': day.isToday,
        }"
        :disabled="!day.available"
        @click="selectDay(day)"
      >
        {{ day.dayNumber }}
      </button>
    </div>

    <div class="erta-step-actions">
      <button class="erta-btn erta-btn--secondary" @click="$emit('back')">{{ t('back') }}</button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { formatMonthYearSafe } from '../../utils/locale.js';

const props = defineProps({
  provider:      { type: Object, default: null },
  availableDates: { type: Array, default: () => [] },
});

const emit = defineEmits(['select', 'back', 'load-month']);

const t = (key) => window.ertaData?.i18n?.[key] ?? key;

const today        = new Date();
const currentYear  = ref(today.getFullYear());
const currentMonth = ref(today.getMonth() + 1); // 1-based
const selectedDate = ref('');

const dayHeaders = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];

const monthLabel = computed(() => {
  const d = new Date(currentYear.value, currentMonth.value - 1, 1);
  return formatMonthYearSafe(d);
});

const canGoPrev = computed(() => {
  return currentYear.value > today.getFullYear() ||
    (currentYear.value === today.getFullYear() && currentMonth.value > today.getMonth() + 1);
});

const leadDays = computed(() => {
  // ISO week starts on Monday; getDay() returns 0=Sun. Shift by 1.
  const firstDay = new Date(currentYear.value, currentMonth.value - 1, 1).getDay();
  return (firstDay + 6) % 7; // Monday=0
});

const daysInMonth = computed(() => {
  const count = new Date(currentYear.value, currentMonth.value, 0).getDate();
  return Array.from({ length: count }, (_, i) => {
    const n    = i + 1;
    const pad  = (v) => String(v).padStart(2, '0');
    const date = `${currentYear.value}-${pad(currentMonth.value)}-${pad(n)}`;
    const d    = new Date(currentYear.value, currentMonth.value - 1, n);
    return {
      dayNumber: n,
      date,
      isToday:   date === formatDate(today),
      available: props.availableDates.includes(date) && d >= today,
    };
  });
});

function formatDate(d) {
  return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}

function prevMonth() {
  if (!canGoPrev.value) return;
  if (currentMonth.value === 1) { currentMonth.value = 12; currentYear.value--; }
  else currentMonth.value--;
  loadMonth();
}

function nextMonth() {
  if (currentMonth.value === 12) { currentMonth.value = 1; currentYear.value++; }
  else currentMonth.value++;
  loadMonth();
}

function loadMonth() {
  emit('load-month', { year: currentYear.value, month: currentMonth.value });
}

function selectDay(day) {
  if (!day.available) return;
  selectedDate.value = day.date;
  emit('select', day.date);
}

onMounted(loadMonth);
</script>
