import { ref, onMounted } from 'vue'
import { assetService } from '../services/assetService.js'

export function useStock() {
  const items    = ref([])
  const loading  = ref(false)
  const error    = ref(null)

  async function fetchItems() {
    loading.value = true
    error.value   = null
    try {
      const res  = await assetService.getMaterials()
      items.value = Array.isArray(res) ? res : (res.data || [])
    } catch (e) {
      error.value = e.message || 'Failed to load stock items'
      console.error('Stock error:', e)
    } finally {
      loading.value = false
    }
  }

  onMounted(fetchItems)

  return { items, loading, error, fetchItems }
}
