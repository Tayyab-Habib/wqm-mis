<script setup>
import './LocationCascader.scss'

defineProps({
  modelValue: { type: Object, default: () => ({}) },
})
const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <div class="location-cascader">
    <slot />
  </div>
</template>
