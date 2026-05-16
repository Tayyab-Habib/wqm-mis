import { computed } from 'vue'
import { useUserStore } from '../stores/useUserStore.js'

/**
 * Convenience composable for RBAC checks in components.
 *
 * Usage in a Vue file:
 *   const { canWrite, can, hasRole } = useRbac()
 *   <button v-if="canWrite" @click="save">Save</button>
 *   <button v-if="can('add_water_samples')" @click="add">Add Sample</button>
 *   <button v-if="hasRole('system-administrator')" @click="…">SA only</button>
 *
 * Notes:
 *  - canWrite is false for view-only users (banner + middleware already cover them);
 *    use it to *hide* write buttons rather than show error toasts.
 *  - can(permissionName) returns true if the user has that Spatie permission OR is SA.
 *  - All getters are computed → reactive, no manual refresh needed.
 */
export function useRbac() {
  const userStore = useUserStore()

  const canWrite  = computed(() => !userStore.isViewOnly && !userStore.isDummy)
  const isAdmin   = computed(() => userStore.isSuperAdmin)
  const isManager = computed(() => userStore.hasAnyRole('system-administrator', 'system-manager'))
  const isLabRole = computed(() => userStore.hasAnyRole('lab-incharge', 'junior-clerk', 'laboratory-assistant'))
  const isHierarchyRole = computed(() => userStore.hasAnyRole('chief-engineer', 'superintending-engineer', 'xen'))

  function can(permissionName)  { return userStore.hasPermission(permissionName) }
  function hasRole(name)        { return userStore.hasRole(name) }
  function hasAnyRole(...names) { return userStore.hasAnyRole(...names) }

  // Roles that bypass scope (mirror AuthScope::UNSCOPED_ROLES on the backend).
  // For these, all exposed scope ids are null so filter-locks
  // (`:disabled="!!rbac.regionId.value"`) don't fire — even though the user
  // row carries hierarchy ids from the seeder.
  const isUnscoped = computed(() => userStore.hasAnyRole(
    'system-administrator', 'system-manager',
    'view-only-admin', 'general-view-account',
  ))

  // Per-role lock rules:
  //   chief-engineer            → region locked
  //   superintending-engineer   → region + circle locked
  //   xen                       → region + circle + phed_division locked
  //   lab-incharge/clerk/asst   → laboratory locked
  //   SA/manager/view-only/genview → nothing locked
  const scopedRegionId = computed(() => {
    if (isUnscoped.value) return null
    if (hasAnyRole('chief-engineer', 'superintending-engineer', 'xen')) return userStore.regionId
    return null
  })
  const scopedCircleId = computed(() => {
    if (isUnscoped.value) return null
    if (hasAnyRole('superintending-engineer', 'xen')) return userStore.circleId
    return null
  })
  const scopedPhedDivisionId = computed(() => {
    if (isUnscoped.value) return null
    if (hasRole('xen')) return userStore.phedDivisionId
    return null
  })
  const scopedDistrictId   = computed(() => isUnscoped.value ? null : userStore.districtId)
  const scopedLaboratoryId = computed(() => {
    if (isUnscoped.value) return null
    if (isLabRole.value) return userStore.laboratoryId
    return null
  })

  return {
    // computed booleans
    canWrite, isAdmin, isManager, isLabRole, isHierarchyRole, isUnscoped,
    // function helpers
    can, hasRole, hasAnyRole,
    // banners/raw flags
    isViewOnly: computed(() => userStore.isViewOnly),
    isDummy:    computed(() => userStore.isDummy),
    isClient:   computed(() => userStore.isClient),
    // hierarchy scope ids — null for unscoped roles so filter-locks don't fire
    regionId:       scopedRegionId,
    circleId:       scopedCircleId,
    phedDivisionId: scopedPhedDivisionId,
    districtId:     scopedDistrictId,
    laboratoryId:   scopedLaboratoryId,
  }
}
