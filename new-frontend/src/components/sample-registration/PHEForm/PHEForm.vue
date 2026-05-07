<script setup>
import './PHEForm.scss'

const emit = defineEmits(['submit'])
</script>

<template>
  <form class="phe-form" @submit.prevent="emit('submit')">
    <slot />
    <button type="submit" class="phe-form__submit">Submit</button>
  </form>
</template>
