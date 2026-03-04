<!-- ProviderStep.vue -->
<template>
  <div class="erta-step-panel erta-step-providers">
    <h3 class="erta-step-title">{{ t('selectProvider') }}</h3>
    <div class="erta-card-list">
      <button
        v-for="prov in providers"
        :key="prov.id"
        class="erta-card erta-card--provider"
        @click="$emit('select', prov)"
      >
        <img v-if="prov.avatar_url" :src="prov.avatar_url" class="erta-card__avatar" :alt="prov.name" />
        <div class="erta-card__body">
          <span class="erta-card__name">{{ prov.name }}</span>
          <span v-if="prov.description" class="erta-card__desc">{{ prov.description }}</span>
        </div>
      </button>
    </div>
    <div class="erta-step-actions">
      <button class="erta-btn erta-btn--secondary" @click="$emit('back')">{{ t('back') }}</button>
    </div>
  </div>
</template>
<script setup>
defineProps({ providers: Array, department: Object });
defineEmits(['select', 'back']);
const t = (k) => window.ertaData?.i18n?.[k] ?? k;
</script>
