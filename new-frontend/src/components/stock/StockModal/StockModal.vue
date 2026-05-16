<script setup>
import './StockModal.scss'
import Modal from '../../common/Modal/Modal.vue'

defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: 'Stock Entry' },
})
const emit = defineEmits(['update:modelValue', 'save'])
</script>

<template>
  <Modal :model-value="modelValue" :title="title" @update:model-value="emit('update:modelValue', $event)">
    <div class="stock-modal">
      <slot />
    </div>
    <template #footer>
      <button class="stock-modal__btn stock-modal__btn--cancel" @click="emit('update:modelValue', false)">Cancel</button>
      <button v-write class="stock-modal__btn stock-modal__btn--save" @click="emit('save')">Save</button>
    </template>
  </Modal>
</template>
