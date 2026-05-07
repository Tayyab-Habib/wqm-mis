import { ref, reactive } from 'vue'

export function useFilters(defaults = {}) {
  const filters = reactive({ ...defaults })
  const applied = ref(false)

  function applyFilters() {
    applied.value = true
  }

  function resetFilters() {
    Object.assign(filters, defaults)
    applied.value = false
  }

  return { filters, applied, applyFilters, resetFilters }
}
