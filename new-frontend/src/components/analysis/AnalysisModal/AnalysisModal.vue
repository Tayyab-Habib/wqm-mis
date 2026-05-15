<script setup>
import './AnalysisModal.scss'
import Modal from '../../common/Modal/Modal.vue'

defineProps({
  modelValue: { type: Boolean, default: false },
  sampleId: { type: [String, Number], default: null },
})
const emit = defineEmits(['update:modelValue', 'save'])
</script>

<template>
  <Modal :model-value="modelValue" title="Analysis Entry" @update:model-value="emit('update:modelValue', $event)">
    <div class="analysis-modal">
      <slot />
    </div>
    <template #footer>
      <button class="analysis-modal__btn analysis-modal__btn--cancel" @click="emit('update:modelValue', false)">Cancel</button>
      <button v-write class="analysis-modal__btn analysis-modal__btn--save" @click="emit('save')">Save</button>
    </template>
  </Modal>
</template>
