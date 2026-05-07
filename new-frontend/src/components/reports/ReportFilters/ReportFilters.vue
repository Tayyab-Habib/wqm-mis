<script setup>
import './ReportFilters.scss'

const emit = defineEmits(['apply'])
</script>

<template>
  <div class="report-filters">
    <slot />
    <button class="report-filters__apply" @click="emit('apply')">Apply</button>
  </div>
</template>
