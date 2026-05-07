<script setup>
import './RAGBadge.scss'

defineProps({
  status: { type: String, required: true }, // 'red' | 'amber' | 'green'
  label: { type: String, default: null },
})
</script>

<template>
  <span class="rag-badge" :class="`rag-badge--${status}`" :aria-label="label || status">
    {{ label || status.toUpperCase() }}
  </span>
</template>
