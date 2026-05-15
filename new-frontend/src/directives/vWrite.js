import { useUserStore } from '../stores/useUserStore.js'

/**
 * v-write directive: HIDES an action (button, link, form section) when the
 * authenticated user lacks the right to perform it.
 *
 * Three modes, evaluated in order:
 *
 *   1. v-write="'add_water_samples'"
 *      Pass a permission slug. The element is hidden if the user doesn't
 *      hold that permission (via role OR direct grant). Unscoped admin
 *      roles always pass.
 *
 *   2. v-write="['add_water_samples', 'edit_water_samples']"
 *      Pass an array — element is shown if user has ANY of these perms.
 *
 *   3. v-write   (no argument)
 *      Legacy behaviour: hide for view-only users. Kept so existing call
 *      sites (`<button v-write>...</button>`) keep working unchanged.
 *
 * The directive sets `display: none` rather than `disabled`, per the new
 * SRS UX rule: a role that can't perform an action shouldn't see the form
 * for it at all. Dummy users are NEVER affected (silent-success UX).
 *
 * Usage:
 *   <button v-write="'add_diaries'" @click="add">+ Add Diary</button>
 *   <button v-write="['edit_invoices','add_invoices']">Edit</button>
 *   <button v-write>Save</button>   <!-- legacy: hide for view-only -->
 */
function shouldHide(binding) {
  const userStore = useUserStore()
  if (userStore.isDummy) return false   // dummy: never hide, silent-success UX

  const value = binding.value
  // Mode 3 — no argument: legacy "hide for view-only"
  if (value === undefined || value === null || value === '') {
    return userStore.isViewOnly
  }
  // Mode 1 & 2 — permission name(s)
  const perms = Array.isArray(value) ? value : [value]
  // Unscoped roles always pass — they see everything.
  const UNSCOPED_ROLES = ['system-administrator', 'system-manager', 'view-only-admin', 'general-view-account']
  if (UNSCOPED_ROLES.some(r => userStore.hasRole(r))) {
    // View-only-admin still gets writes hidden by legacy isViewOnly check —
    // the role is read-only by definition.
    return userStore.isViewOnly
  }
  const hasAny = perms.some(p => userStore.hasPermission(p))
  return !hasAny
}

function apply(el, binding) {
  if (shouldHide(binding)) {
    if (el.__writeOriginalDisplay === undefined) {
      el.__writeOriginalDisplay = el.style.display || ''
    }
    el.style.display = 'none'
    el.setAttribute('aria-hidden', 'true')
  } else if (el.__writeOriginalDisplay !== undefined) {
    el.style.display = el.__writeOriginalDisplay
    el.removeAttribute('aria-hidden')
  }
}

export const vWrite = {
  mounted(el, binding)  { apply(el, binding) },
  updated(el, binding)  { apply(el, binding) },
  beforeUnmount(el) {
    // Restore any state we changed so DOM teardown is clean.
    if (el.__writeOriginalDisplay !== undefined) {
      delete el.__writeOriginalDisplay
    }
  },
}
