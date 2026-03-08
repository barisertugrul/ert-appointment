<template>
  <div class="erta-step-panel erta-step-slots">
    <h3 class="erta-step-title">{{ t('selectTime') }}</h3>
    <p class="erta-step-subtitle">{{ date }}</p>

    <div v-if="slots.length === 0" class="erta-no-slots">{{ t('noSlots') }}</div>

    <div class="erta-slot-grid">
      <button
        v-for="(slot, index) in slots"
        :key="`${slot.time}-${slot.provider_id || 'none'}-${index}`"
        class="erta-slot"
        :class="{ 'erta-slot--selected': selectedTime === slot.time }"
        :disabled="!slot.available"
        @click="choose(slot)"
      >
        {{ slot.time }}
      </button>
    </div>

    <div class="erta-step-actions">
      <button class="erta-btn erta-btn--secondary" @click="$emit('back')">{{ t('back') }}</button>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
const props  = defineProps({ slots: Array, date: String });
const emit   = defineEmits(['select', 'back']);
const t      = (k) => window.ertaData?.i18n?.[k] ?? k;
const selectedTime = ref('');

function choose(slot) {
  selectedTime.value = slot.time;
  emit('select', slot);
}
</script>
