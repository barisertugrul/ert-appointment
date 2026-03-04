<!-- DepartmentStep.vue -->
<template>
  <div class="erta-step-panel erta-step-departments">
    <h3 class="erta-step-title">{{ t('selectDepartment') }}</h3>
    <div class="erta-card-list">
      <button
        v-for="dept in departments"
        :key="dept.id"
        class="erta-card"
        @click="$emit('select', dept)"
      >
        <span class="erta-card__name">{{ dept.name }}</span>
        <span v-if="dept.description" class="erta-card__desc">{{ dept.description }}</span>
      </button>
    </div>
  </div>
</template>
<script setup>
defineProps({ departments: Array });
defineEmits(['select']);
const t = (k) => window.ertaData?.i18n?.[k] ?? k;
</script>
