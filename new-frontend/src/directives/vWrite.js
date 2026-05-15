import { useUserStore } from '../stores/useUserStore.js'

/**
 * v-write directive: disables an action button (or any element) when the
 * authenticated user has view-only access. Dummy users are NOT affected —
 * the backend silently succeeds writes for them, and the dummy UX is
 * "everything looks normal but nothing persists".
 *
 * Usage:
 *   <button v-write @click="save">Save</button>
 *   <button v-write class="btn btn-pri" @click="addUser">+ Add User</button>
 *
 * Effect for view-only users:
 *   - disabled attribute set (native click blocking on buttons)
 *   - aria-disabled="true" for screen readers
 *   - title tooltip explaining why (preserves existing title if already set)
 *   - dimmed + not-allowed cursor styling
 *   - belt-and-braces capture-phase click blocker in case some other binding
 *     re-enables the element
 */
function apply(el) {
  const userStore = useUserStore()
  if (!userStore.isViewOnly) return

  el.setAttribute('disabled', '')
  el.setAttribute('aria-disabled', 'true')
  if (!el.getAttribute('title')) {
    el.setAttribute('title', 'View-only access — this action is not permitted')
  }
  el.style.cursor = 'not-allowed'
  if (!el.style.opacity) el.style.opacity = '0.55'

  if (!el.__writeGuardClick) {
    el.__writeGuardClick = (e) => {
      if (useUserStore().isViewOnly) {
        e.preventDefault()
        e.stopImmediatePropagation()
      }
    }
    el.addEventListener('click', el.__writeGuardClick, true)
  }
}

export const vWrite = {
  mounted(el)  { apply(el) },
  updated(el)  { apply(el) },
  beforeUnmount(el) {
    if (el.__writeGuardClick) {
      el.removeEventListener('click', el.__writeGuardClick, true)
      delete el.__writeGuardClick
    }
  },
}
