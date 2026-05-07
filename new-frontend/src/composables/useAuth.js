import { ref } from 'vue'
import { useRouter } from 'vue-router'
import axios from '../services/axios.js'
import { useUserStore } from '../stores/useUserStore.js'

export function useAuth() {
  const router = useRouter()
  const userStore = useUserStore()
  const loading = ref(false)
  const error = ref(null)

  async function login(credentials) {
    loading.value = true
    error.value = null
    try {
      // Get CSRF cookie first
      await axios.get('/sanctum/csrf-cookie')

      const response = await axios.post('/api/login', credentials)

      if (response.data) {
        const userData = response.data
        const user = {
          id: userData.id,
          name: userData.name,
          email: userData.email,
          token: userData.token,
          role: userData.roles?.[0]?.name || 'user',
          permissions: userData.permissions || [],
          laboratory: userData.laboratory || null,
          district: userData.district || null,
        }
        userStore.setUser(user)
        router.push('/dashboard')
      }
    } catch (e) {
      error.value = e.message || 'Invalid credentials'
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    userStore.logout()
    router.push('/login')
  }

  return { login, logout, loading, error }
}
