<script setup>
import './LoadingSpinner.scss'

defineProps({
  size: { type: String, default: 'md' }, // 'sm' | 'md' | 'lg'
})
</script>

<template>
  <div class="loading-spinner" :class="`loading-spinner--${size}`" role="status" aria-label="Loading">
    <div class="loading-spinner__ring" />
  </div>
</template>
