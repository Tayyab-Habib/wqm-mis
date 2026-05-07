import { ref, onMounted } from 'vue'
import { dashboardService } from '../services/dashboardService.js'

export function useDashboard() {
  const stats = ref(null)
  const loading = ref(false)
  const error = ref(null)

  async function fetchStats(filters = {}) {
    loading.value = true
    error.value = null
    try {
      const response = await dashboardService.getStats(filters)
      stats.value = response.data
    } catch (e) {
      error.value = e.message || 'Failed to fetch dashboard stats'
      console.error('Dashboard error:', e)
    } finally {
      loading.value = false
    }
  }

  onMounted(() => {
    fetchStats()
  })

  return { stats, loading, error, fetchStats }
}
