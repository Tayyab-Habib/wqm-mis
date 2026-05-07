<script setup>
import './Modal.scss'

defineProps({
  title: { type: String, default: '' },
  modelValue: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue'])
const close = () => emit('update:modelValue', false)
</script>

<template>
  <Teleport to="body">
    <div v-if="modelValue" class="modal-overlay" role="dialog" aria-modal="true" @click.self="close">
      <div class="modal">
        <header class="modal__header">
          <h2 class="modal__title">{{ title }}</h2>
          <button class="modal__close" @click="close" aria-label="Close modal">✕</button>
        </header>
        <div class="modal__body">
          <slot />
        </div>
        <footer class="modal__footer" v-if="$slots.footer">
          <slot name="footer" />
        </footer>
      </div>
    </div>
  </Teleport>
</template>
