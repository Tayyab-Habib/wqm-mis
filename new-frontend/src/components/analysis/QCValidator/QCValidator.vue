<script setup>
import './QCValidator.scss'

defineProps({
  results: { type: Object, default: () => ({}) },
})
</script>

<template>
  <div class="qc-validator">
    <slot />
  </div>
</template>
