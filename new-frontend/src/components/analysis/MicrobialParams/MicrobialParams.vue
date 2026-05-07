<script setup>
import './MicrobialParams.scss'

defineProps({
  modelValue: { type: Object, default: () => ({}) },
})
const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <fieldset class="microbial-params">
    <legend class="microbial-params__legend">Microbial Parameters</legend>
    <slot />
  </fieldset>
</template>
