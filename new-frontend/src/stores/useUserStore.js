import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useUserStore = defineStore('user', () => {
  // Load user from localStorage on init
  const storedUser = localStorage.getItem('user')
  const initialUser = storedUser ? JSON.parse(storedUser) : null

  const currentUser = ref(initialUser)

  const isLoggedIn = computed(() => !!currentUser.value?.token)
  // Normalize role to kebab-case-lowercase so checks survive both
  // "system-administrator" and "System Administrator" coming from the API.
  const normRole = computed(() =>
    String(currentUser.value?.role || '').toLowerCase().replace(/\s+/g, '-')
  )
  const isSuperAdmin  = computed(() => normRole.value === 'system-administrator')
  const isLabIncharge = computed(() => ['system-administrator', 'lab-incharge'].includes(normRole.value))
  const isClient      = computed(() => currentUser.value?.user_type === 'client')
  const token = computed(() => currentUser.value?.token || null)

  function setUser(user) {
    currentUser.value = user
    if (user) {
      localStorage.setItem('user', JSON.stringify(user))
    } else {
      localStorage.removeItem('user')
    }
  }

  function logout() {
    currentUser.value = null
    localStorage.removeItem('user')
  }

  return { currentUser, isLoggedIn, isSuperAdmin, isLabIncharge, isClient, token, setUser, logout }
})
