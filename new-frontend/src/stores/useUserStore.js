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

  // ── RBAC extensions ──────────────────────────────────────────────────
  // Permissions are an array of permission slugs ("view_water_samples" etc.)
  // attached by the backend login response. Default to [] so .includes() works.
  const permissions = computed(() => currentUser.value?.permissions || [])
  const isViewOnly  = computed(() => !!currentUser.value?.is_view_only || normRole.value === 'view-only-admin')
  const isDummy     = computed(() => !!currentUser.value?.is_dummy)
  const allowedModules = computed(() => currentUser.value?.allowed_modules || null)

  // Hierarchy scope getters (used by some report drill-downs)
  const regionId         = computed(() => currentUser.value?.region_id ?? null)
  const circleId         = computed(() => currentUser.value?.circle_id ?? null)
  const phedDivisionId   = computed(() => currentUser.value?.phed_division_id ?? null)
  const districtId       = computed(() => currentUser.value?.district_id ?? null)
  const laboratoryId     = computed(() => currentUser.value?.laboratory?.id ?? currentUser.value?.laboratory_id ?? null)

  function hasPermission(name) {
    if (!name) return true
    // SA bypass — always allow
    if (isSuperAdmin.value) return true
    return permissions.value.includes(name)
  }

  function hasRole(name) {
    if (!name) return false
    return normRole.value === String(name).toLowerCase()
  }

  function hasAnyRole(...names) {
    return names.some(n => hasRole(n))
  }

  /**
   * For the General View Account role: returns true if the named module
   * is in users.allowed_modules. Other roles always pass.
   */
  function canSeeModule(moduleSlug) {
    if (normRole.value !== 'general-view-account') return true
    if (!Array.isArray(allowedModules.value)) return false
    return allowedModules.value.includes(moduleSlug)
  }

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

  return {
    currentUser, isLoggedIn,
    isSuperAdmin, isLabIncharge, isClient, isViewOnly, isDummy,
    permissions, allowedModules,
    regionId, circleId, phedDivisionId, districtId, laboratoryId,
    hasPermission, hasRole, hasAnyRole, canSeeModule,
    token, setUser, logout,
  }
})
