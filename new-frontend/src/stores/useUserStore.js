import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useUserStore = defineStore('user', () => {
  // Load user from localStorage on init
  const storedUser = localStorage.getItem('user')
  const initialUser = storedUser ? JSON.parse(storedUser) : null

  const currentUser = ref(initialUser)

  const isLoggedIn = computed(() => !!currentUser.value?.token)
  const isSuperAdmin = computed(() => currentUser.value?.role === 'system-administrator')
  const isLabIncharge = computed(() => ['system-administrator', 'lab-incharge'].includes(currentUser.value?.role))
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

  return { currentUser, isLoggedIn, isSuperAdmin, isLabIncharge, token, setUser, logout }
})
