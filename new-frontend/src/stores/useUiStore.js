import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useUiStore = defineStore('ui', () => {
  const sidebarCollapsed = ref(false)
  const activeModal = ref(null)

  function toggleSidebar() { sidebarCollapsed.value = !sidebarCollapsed.value }
  function openModal(name) { activeModal.value = name }
  function closeModal() { activeModal.value = null }

  return { sidebarCollapsed, activeModal, toggleSidebar, openModal, closeModal }
})
