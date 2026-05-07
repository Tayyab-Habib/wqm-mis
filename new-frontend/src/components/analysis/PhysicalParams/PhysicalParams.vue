<script setup>
import './PhysicalParams.scss'

defineProps({
  modelValue: { type: Object, default: () => ({}) },
})
const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <fieldset class="physical-params">
    <legend class="physical-params__legend">Physical Parameters</legend>
    <slot />
  </fieldset>
</template>
