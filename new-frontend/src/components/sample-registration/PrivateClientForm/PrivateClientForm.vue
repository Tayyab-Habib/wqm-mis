<script setup>
import './PrivateClientForm.scss'

const emit = defineEmits(['submit'])
</script>

<template>
  <form class="private-client-form" @submit.prevent="emit('submit')">
    <slot />
    <button type="submit" class="private-client-form__submit">Submit</button>
  </form>
</template>
