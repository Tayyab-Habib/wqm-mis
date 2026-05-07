<script setup>
import './ChemicalParams.scss'

defineProps({
  modelValue: { type: Object, default: () => ({}) },
})
const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <fieldset class="chemical-params">
    <legend class="chemical-params__legend">Chemical Parameters</legend>
    <slot />
  </fieldset>
</template>
