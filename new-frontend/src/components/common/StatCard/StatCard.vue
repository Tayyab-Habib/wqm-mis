<script setup>
import './StatCard.scss'

defineProps({
  title: { type: String, required: true },
  value: { type: [String, Number], required: true },
  icon: { type: String, default: '' },
  trend: { type: String, default: null }, // 'up' | 'down'
})
</script>

<template>
  <div class="stat-card">
    <div class="stat-card__icon" v-if="icon">{{ icon }}</div>
    <div class="stat-card__body">
      <p class="stat-card__title">{{ title }}</p>
      <p class="stat-card__value">{{ value }}</p>
    </div>
    <span v-if="trend" class="stat-card__trend" :class="`stat-card__trend--${trend}`">
      {{ trend === 'up' ? '▲' : '▼' }}
    </span>
  </div>
</template>
