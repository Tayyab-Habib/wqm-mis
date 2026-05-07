<script setup>
import './FilterBar.scss'

defineProps({
  filters: { type: Array, default: () => [] },
})
const emit = defineEmits(['filter-change'])
</script>

<template>
  <div class="filter-bar">
    <slot />
  </div>
</template>
