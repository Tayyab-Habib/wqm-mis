<script setup>
import './PTForm.scss'

const emit = defineEmits(['submit'])
</script>

<template>
  <form class="pt-form" @submit.prevent="emit('submit')">
    <slot />
    <button type="submit" class="pt-form__submit">Submit</button>
  </form>
</template>
