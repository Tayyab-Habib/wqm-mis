<script setup>
import { ref, computed, onMounted } from 'vue'
import { adminService } from '../../../services/adminService.js'
import { useUserStore } from '../../../stores/useUserStore.js'

const userStore = useUserStore()

// ── State ──────────────────────────────────────────────────────────────
const loading      = ref(false)
const roles        = ref([])
const permissions  = ref([])         // all permissions (with module info)
const selectedRole = ref(null)       // role whose matrix is open
const rolePerms    = ref(new Set())  // permission ids currently checked
const matrixLoading = ref(false)
const saving       = ref(false)

// Add role modal
const showAddRole  = ref(false)
const newRoleName  = ref('')

// Inline edit role
const editingRoleId = ref(null)
const editRoleName  = ref('')

// ── Toast (matches the pattern in Topbar.vue / DiariesDispatches.vue) ─
const toast = ref({ show: false, message: '', type: 'success' })
let toastTimer = null
function showToast(message, type = 'success') {
  clearTimeout(toastTimer)
  toast.value = { show: true, message, type }
  toastTimer = setTimeout(() => { toast.value.show = false }, 4000)
}

// ── Sidebar-module map ─────────────────────────────────────────────────
// Mirrors the gating in Sidebar.vue so admins can toggle "module access"
// in one click instead of hand-picking perms. Keep this in sync with the
// sidebar definition; the labels and icons match what users actually see.
// Each module carries a `group` so the two-pane Module Access editor can
// section the toggles. Group order in the UI follows first-occurrence here.
const SIDEBAR_MODULES = [
  { group: 'Lab Operations', label: 'Sample Registration',     icon: '🧪',  perms: ['add_water_samples'] },
  { group: 'Lab Operations', label: 'Analysis Entry',          icon: '⚗️',  perms: ['add_water_sample_details', 'edit_water_sample_results'] },
  { group: 'Lab Operations', label: 'Individual Sample Report', icon: '📝', perms: ['view_individual_sample_report'] },

  { group: 'Reports', label: 'GAR (Abstract)',          icon: '📄',  perms: ['view_gar'] },
  { group: 'Reports', label: 'GSR (Summary)',           icon: '📋',  perms: ['view_gsr'] },
  { group: 'Reports', label: 'ASR (Analysis Summary)',  icon: '📊',  perms: ['view_asr'] },
  { group: 'Reports', label: 'CE-Wise Report',          icon: '🗺️',  perms: ['view_ce_wise_report'] },
  { group: 'Reports', label: 'PWR (Parameter-wise)',    icon: '🔬',  perms: ['view_pwr'] },

  { group: 'Operations', label: 'WSS Map',              icon: '🗾',  perms: ['view_water_schemes'] },
  { group: 'Operations', label: 'Invoices / Revenue',   icon: '🧾',  perms: ['view_invoices'] },
  { group: 'Operations', label: 'SBP Submissions',      icon: '🏦',  perms: ['view_sbp_submissions'] },
  { group: 'Operations', label: 'Stock / Inventory',                icon: '📦', perms: ['view_inventories'] },
  { group: 'Operations', label: 'Stock / Inventory: Manage',        icon: '✏️', perms: ['add_material', 'edit_material', 'delete_material', 'add_material_logs', 'view_laboratory_material'] },
  { group: 'Operations', label: 'Equipment Register',              icon: '🔧', perms: ['view_assets'] },
  { group: 'Operations', label: 'Equipment Register: Manage',       icon: '✏️', perms: ['add_assets', 'edit_assets', 'delete_assets', 'add_asset_logs', 'edit_asset_logs', 'view_laboratory_assets'] },
  { group: 'Operations', label: 'Equipment Register: Maintenance',  icon: '🛠️', perms: ['view_asset_maintenance_schedules', 'show_asset_maintenance_schedules', 'add_asset_maintenance_schedules', 'edit_asset_maintenance_schedules', 'delete_asset_maintenance_schedules', 'add_asset_maintenance_logs'] },
  { group: 'Operations', label: 'Demand & Issuance',    icon: '🔄',  perms: ['view_demands', 'view_inventories', 'add_inventories'] },
  { group: 'Operations', label: 'Diaries / Dispatches', icon: '📝',  perms: ['view_diaries', 'view_dispatches'] },
  { group: 'Operations', label: 'Water Scheme Details', icon: '💧',  perms: ['view_water_schemes'] },

  // ── Secretary portal screens ────────────────────────────────────────
  // Granting "Secretary Portal" gives umbrella access (lets the user enter
  // /secretary/*). Each per-screen perm below controls a single tab.
  { group: 'Secretary Portal', label: 'Secretary Portal',           icon: '🏛️', perms: ['view_secretary_portal'] },
  { group: 'Secretary Portal', label: 'Secretary Dashboard',        icon: '📊', perms: ['view_secretary_dashboard'] },
  { group: 'Secretary Portal', label: 'Secretary CE Unfit Trail',   icon: '📍', perms: ['view_secretary_ce_unfit'] },
  { group: 'Secretary Portal', label: 'Secretary Fate Decisions',   icon: '⚖️', perms: ['view_secretary_fate_decisions'] },
  { group: 'Secretary Portal', label: 'Secretary Persistent Unfit', icon: '🔴', perms: ['view_secretary_persistent_unfit'] },
  { group: 'Secretary Portal', label: 'Secretary GAR',              icon: '📄', perms: ['view_secretary_gar'] },
  { group: 'Secretary Portal', label: 'Secretary WSS Register',     icon: '💧', perms: ['view_secretary_wss_register'] },

  // ── CE portal screens ───────────────────────────────────────────────
  { group: 'CE Portal', label: 'CE Portal',          icon: '🏢', perms: ['view_ce_portal'] },
  { group: 'CE Portal', label: 'CE Dashboard',       icon: '📊', perms: ['view_ce_dashboard'] },
  { group: 'CE Portal', label: 'CE Circle Detail',   icon: '📍', perms: ['view_ce_circle_detail'] },
  { group: 'CE Portal', label: 'CE Escalated Cases', icon: '⚠️', perms: ['view_ce_escalated_cases'] },
  { group: 'CE Portal', label: 'CE Persistent Unfit',icon: '🔴', perms: ['view_ce_persistent_unfit'] },
  { group: 'CE Portal', label: 'CE GAR',             icon: '📄', perms: ['view_ce_gar'] },
  { group: 'CE Portal', label: 'CE WSS Register',    icon: '💧', perms: ['view_ce_wss_register'] },

  // ── XEN portal screens ──────────────────────────────────────────────
  // Write perms (submit_xen_retest, update_xen_settings) sit alongside view
  // perms so admin can grant read-only view of a screen and separately
  // disable the write action.
  { group: 'XEN Portal', label: 'XEN Portal',          icon: '🛠', perms: ['view_xen_portal'] },
  { group: 'XEN Portal', label: 'XEN Dashboard',       icon: '📊', perms: ['view_xen_dashboard'] },
  { group: 'XEN Portal', label: 'XEN Unfit Trail',     icon: '⚠️', perms: ['view_xen_unfit_trail'] },
  { group: 'XEN Portal', label: 'XEN Retest Samples',  icon: '🧪', perms: ['view_xen_retest_samples'] },
  { group: 'XEN Portal', label: 'XEN GSR',             icon: '📄', perms: ['view_xen_gsr'] },
  { group: 'XEN Portal', label: 'XEN ISR',             icon: '📋', perms: ['view_xen_isr'] },
  { group: 'XEN Portal', label: 'XEN WSS Register',    icon: '💧', perms: ['view_xen_wss_register'] },
  { group: 'XEN Portal', label: 'XEN Settings',        icon: '⚙️', perms: ['view_xen_settings'] },
  { group: 'XEN Portal', label: 'XEN: Submit Retest',  icon: '🔁', perms: ['submit_xen_retest'] },
  { group: 'XEN Portal', label: 'XEN: Update Settings',icon: '✏️', perms: ['update_xen_settings'] },

  // ── Quality / Compliance (KPI Framework data sources) ──────────────
  { group: 'Quality Framework', label: 'KPI Framework',             icon: '📊', perms: ['view_kpi_framework'] },
  { group: 'Quality Framework', label: 'KPI Framework: Manage',     icon: '✏️', perms: ['manage_kpi_framework'] },
  { group: 'Quality Framework', label: 'Training Register',         icon: '🎓', perms: ['view_staff_trainings'] },
  { group: 'Quality Framework', label: 'Training Register: Manage', icon: '✏️', perms: ['manage_staff_trainings'] },
  { group: 'Quality Framework', label: 'SOP Audit',                 icon: '✅', perms: ['view_audit_inspections'] },
  { group: 'Quality Framework', label: 'SOP Audit: Manage',         icon: '✏️', perms: ['manage_audit_inspections'] },
  { group: 'Quality Framework', label: 'PT Rounds',                 icon: '🧪', perms: ['view_pt_rounds'] },
  { group: 'Quality Framework', label: 'PT Rounds: Manage',         icon: '✏️', perms: ['manage_pt_rounds'] },
  { group: 'Quality Framework', label: 'PT Rounds: Submit Results', icon: '🔁', perms: ['submit_pt_results'] },
  { group: 'Quality Framework', label: 'Verification Log',          icon: '🔎', perms: ['view_verification_visits'] },
  { group: 'Quality Framework', label: 'Verification Log: Manage',  icon: '✏️', perms: ['manage_verification_visits'] },
]
// Roles whose access is router/middleware-bypassed (isUnscoped) — toggling
// module perms for them has no UI effect, so they're hidden from the grid
// to avoid confusion. Add them via the Roles tab if a custom one needs scoping.
const UNSCOPED_ROLES = new Set([
  'system-administrator', 'system-manager',
  'view-only-admin',      'general-view-account',
])

// ── Module Access view state ───────────────────────────────────────────
const viewMode = ref('roles')                       // 'roles' | 'modules'
const rolePermSets = ref(new Map())                 // roleId -> Set<permId>
const gridLoading = ref(false)
const dirtyRoles = ref(new Set())                   // role ids with unsaved changes
const gridSaving = ref(new Set())                   // role ids currently being saved

const permNameToId = computed(() => {
  const m = new Map()
  for (const p of permissions.value) m.set(p.name, Number(p.id))
  return m
})
const scopedRoles = computed(() => roles.value.filter(r => !UNSCOPED_ROLES.has(r.name)))

// ── Module Access (two-pane editor) ────────────────────────────────────
// Left pane picks a role; right pane shows toggle switches grouped by
// `SIDEBAR_MODULES[].group`. Replaces the previous wide-checkbox-grid which
// horizontally scrolled past the viewport.
const maSelectedRoleId = ref(null)
const maSearch         = ref('')
const maCollapsed      = ref(new Set())   // group names currently collapsed

// Preserve first-occurrence order of groups in SIDEBAR_MODULES — no
// hard-coded list, so adding a new group only requires tagging modules.
const moduleGroups = computed(() => {
  const seen = []
  const seenSet = new Set()
  for (const m of SIDEBAR_MODULES) {
    if (!seenSet.has(m.group)) { seenSet.add(m.group); seen.push(m.group) }
  }
  const q = maSearch.value.trim().toLowerCase()
  return seen.map(g => ({
    name: g,
    modules: SIDEBAR_MODULES.filter(m =>
      m.group === g &&
      (!q || m.label.toLowerCase().includes(q) || m.perms.some(p => p.toLowerCase().includes(q)))
    ),
  })).filter(g => g.modules.length > 0)
})

const maSelectedRole = computed(() =>
  scopedRoles.value.find(r => r.id === maSelectedRoleId.value) || null
)

function groupOnCount(roleId, modules) {
  if (!roleId) return 0
  return modules.reduce((n, m) => n + (roleHasModule(roleId, m) ? 1 : 0), 0)
}
function groupAllOn(roleId, modules)  { return modules.length > 0 && modules.every(m => roleHasModule(roleId, m)) }
function groupAllOff(roleId, modules) { return modules.every(m => !roleHasModule(roleId, m)) }

function toggleGroup(roleId, modules, turnOn) {
  // Apply to each module that isn't already in the target state.
  for (const m of modules) {
    const has = roleHasModule(roleId, m)
    if (turnOn && !has) toggleModule(roleId, m)
    else if (!turnOn && has) toggleModule(roleId, m)
  }
}

function toggleGroupCollapsed(name) {
  const s = new Set(maCollapsed.value)
  if (s.has(name)) s.delete(name); else s.add(name)
  maCollapsed.value = s
}

async function switchView(mode) {
  viewMode.value = mode
  if (mode !== 'modules') return
  if (rolePermSets.value.size === 0) {
    gridLoading.value = true
    try {
      // Parallel fetch of each role's permission set. With ~10 roles this is
      // 10 small requests — cheap enough that the simplicity wins over adding
      // a dedicated /admin/roles-perms-bulk endpoint.
      const responses = await Promise.all(scopedRoles.value.map(r => adminService.getRole(r.id)))
      const map = new Map()
      scopedRoles.value.forEach((r, i) => {
        const data = responses[i]?.data?.data || responses[i]?.data || {}
        const ids = new Set()
        for (const p of data.permissions || []) {
          const id = p?.id ?? p?.permission_id ?? p
          if (id != null) ids.add(Number(id))
        }
        map.set(r.id, ids)
      })
      rolePermSets.value = map
    } catch (e) {
      showToast('❌ Failed to load module access: ' + (e?.response?.data?.message || e.message), 'error')
    } finally {
      gridLoading.value = false
    }
  }
  // Auto-select the first role so the right pane isn't empty on first open.
  if (!maSelectedRoleId.value && scopedRoles.value.length > 0) {
    maSelectedRoleId.value = scopedRoles.value[0].id
  }
}

// Role badge — colored circle + initials, derived from the role name so
// the avatar is deterministic without storing extra data.
function roleInitials(name) {
  if (!name) return '?'
  return String(name).replace(/[-_]/g, ' ').split(' ')
    .filter(Boolean).map(w => w[0].toUpperCase()).slice(0, 2).join('')
}
function roleHue(name) {
  // Stable hash → hue. Same role always gets the same color across reloads.
  let h = 0
  for (let i = 0; i < (name || '').length; i++) h = ((h << 5) - h + name.charCodeAt(i)) | 0
  return Math.abs(h) % 360
}
// Count of total active modules for a role (across all groups). Drives the
// "X / Y modules" line under the role name in the sidebar.
function roleEnabledCount(roleId) {
  if (!roleId) return 0
  return SIDEBAR_MODULES.reduce((n, m) => n + (roleHasModule(roleId, m) ? 1 : 0), 0)
}

function roleHasModule(roleId, mod) {
  const set = rolePermSets.value.get(roleId)
  if (!set || set.size === 0) return false
  return mod.perms.every(name => {
    const pid = permNameToId.value.get(name)
    return pid != null && set.has(pid)
  })
}

function toggleModule(roleId, mod) {
  const set = rolePermSets.value.get(roleId)
  if (!set) return
  const has = roleHasModule(roleId, mod)

  if (has) {
    // Turning OFF — only remove perms that aren't keeping another currently
    // active module alive. Without this, e.g. unchecking GAR would also
    // strip view_water_schemes from WSS Map (when those shared a perm),
    // silently un-toggling adjacent modules the admin didn't touch.
    const otherActive = SIDEBAR_MODULES.filter(m =>
      m.label !== mod.label &&
      m.perms.every(name => {
        const pid = permNameToId.value.get(name)
        return pid != null && set.has(pid)
      })
    )
    const stillNeeded = new Set()
    for (const other of otherActive) {
      for (const name of other.perms) {
        const pid = permNameToId.value.get(name)
        if (pid != null) stillNeeded.add(pid)
      }
    }
    for (const name of mod.perms) {
      const pid = permNameToId.value.get(name)
      if (pid != null && !stillNeeded.has(pid)) set.delete(pid)
    }
  } else {
    // Turning ON — add every missing required perm.
    for (const name of mod.perms) {
      const pid = permNameToId.value.get(name)
      if (pid == null) continue
      set.add(pid)
    }
  }

  // Replace the Map so Vue's reactivity picks up the deep change.
  rolePermSets.value = new Map(rolePermSets.value)
  const d = new Set(dirtyRoles.value); d.add(roleId)
  dirtyRoles.value = d
}

async function saveModuleRow(role) {
  const set = rolePermSets.value.get(role.id)
  if (!set) return
  const s = new Set(gridSaving.value); s.add(role.id)
  gridSaving.value = s
  try {
    await adminService.syncRolePermissions(role.id, Array.from(set).map(Number))
    // If admin just edited a role they themselves hold, refresh their
    // session so the sidebar gates reflect immediately.
    await userStore.refreshSession({ force: true })
    const d = new Set(dirtyRoles.value); d.delete(role.id)
    dirtyRoles.value = d
    showToast(`✅ Module access updated for ${displayRole(role.name)}`)
  } catch (e) {
    showToast('❌ Save failed: ' + (e?.response?.data?.message || e.message), 'error')
  } finally {
    const s2 = new Set(gridSaving.value); s2.delete(role.id)
    gridSaving.value = s2
  }
}

// ── Helpers ────────────────────────────────────────────────────────────
const CRUD_PREFIXES = ['add', 'edit', 'show', 'view', 'delete']

function displayRole(name) {
  if (!name) return '—'
  return String(name)
    .replace(/[-_]/g, ' ')
    .split(' ')
    .map(w => w.charAt(0).toUpperCase() + w.slice(1))
    .join(' ')
}

// Build a [{name, slots: {add, edit, ...}, others: [...]}] for the matrix
const matrixModules = computed(() => {
  const byMod = new Map()
  for (const p of permissions.value) {
    const modName = p.module?.name || p.module_name || 'misc'
    if (!byMod.has(modName)) byMod.set(modName, { name: modName, slots: {}, others: [] })
    const entry = byMod.get(modName)
    const slot = CRUD_PREFIXES.find(pre => p.name.startsWith(pre + '_'))
    if (slot && !entry.slots[slot]) entry.slots[slot] = p
    else entry.others.push(p)
  }
  return Array.from(byMod.values()).sort((a, b) => a.name.localeCompare(b.name))
})

const moduleSearch = ref('')
const filteredModules = computed(() => {
  if (!moduleSearch.value.trim()) return matrixModules.value
  const q = moduleSearch.value.toLowerCase()
  return matrixModules.value.filter(m => m.name.toLowerCase().includes(q))
})

// ── Data loading ───────────────────────────────────────────────────────
async function loadRoles() {
  try {
    const r = await adminService.getRoles()
    // The axios interceptor (src/services/axios.js) unwraps response.data,
    // so `r` is already the response body { message, data: [...] }.
    // Fall back to r itself if the shape varies.
    const list = r?.data?.data || r?.data || (Array.isArray(r) ? r : [])
    roles.value = Array.isArray(list) ? list : []
  } catch (e) {
    showToast('❌ Failed to load roles: ' + (e?.response?.data?.message || e.message), 'error')
  }
}

async function loadPermissions() {
  try {
    const p = await adminService.getPermissions()
    const list = p.data?.data || p.data || []
    permissions.value = Array.isArray(list) ? list : []
  } catch (e) {
    showToast('❌ Failed to load permissions: ' + (e?.response?.data?.message || e.message), 'error')
  }
}

async function openMatrix(role) {
  selectedRole.value = role
  rolePerms.value = new Set()
  matrixLoading.value = true
  try {
    const r = await adminService.getRole(role.id)
    const data = r.data?.data || r.data || {}
    const perms = data?.permissions || []
    const next = new Set()
    for (const p of perms) {
      const id = p?.id ?? p?.permission_id ?? p
      if (id != null) next.add(Number(id))
    }
    rolePerms.value = next
  } catch (e) {
    showToast('❌ Failed to load role permissions: ' + (e?.response?.data?.message || e.message), 'error')
  } finally {
    matrixLoading.value = false
  }
}

function closeMatrix() {
  selectedRole.value = null
  rolePerms.value = new Set()
}

function togglePerm(permId) {
  const id = Number(permId)
  if (rolePerms.value.has(id)) rolePerms.value.delete(id)
  else rolePerms.value.add(id)
  rolePerms.value = new Set(rolePerms.value)
}

function hasPerm(permId) {
  return rolePerms.value.has(Number(permId))
}

// Count how many of a module's "Others" permissions are currently enabled
// for the open role. Used to colour the chevron badge so admins know there's
// active state hidden behind the accordion.
function activeOthers(others) {
  if (!others?.length) return 0
  let n = 0
  for (const p of others) if (rolePerms.value.has(Number(p.id))) n++
  return n
}

// ── Matrix row/global helpers (UI sugar, no new permissions logic) ─────

// All permission objects on a module row (CRUD slots + others), flattened.
function modulePerms(mod) {
  const out = []
  for (const k of CRUD_PREFIXES) if (mod.slots[k]) out.push(mod.slots[k])
  for (const p of (mod.others || [])) out.push(p)
  return out
}
function moduleEnabledCount(mod) {
  return modulePerms(mod).filter(p => rolePerms.value.has(Number(p.id))).length
}
function moduleTotalCount(mod) { return modulePerms(mod).length }
function moduleHasAny(mod)     { return moduleEnabledCount(mod) > 0 }
function moduleHasAll(mod)     { return moduleEnabledCount(mod) === moduleTotalCount(mod) }

// Bulk action per row: enable or disable every permission belonging to the
// module. Idempotent — only flips the ones that need flipping.
function toggleAllForModule(mod, turnOn) {
  const next = new Set(rolePerms.value)
  for (const p of modulePerms(mod)) {
    const id = Number(p.id)
    if (turnOn) next.add(id); else next.delete(id)
  }
  rolePerms.value = next
}

// Filter chips above the matrix.
const matrixFilter = ref('all')          // 'all' | 'active' | 'inactive'
const visibleModules = computed(() => {
  let list = filteredModules.value
  if (matrixFilter.value === 'active')   list = list.filter(moduleHasAny)
  if (matrixFilter.value === 'inactive') list = list.filter(m => !moduleHasAny(m))
  return list
})

// Summary stats shown in the modal footer.
const matrixSummary = computed(() => {
  let totalEnabled = 0, totalPerms = 0, modulesTouched = 0
  for (const m of matrixModules.value) {
    const perms = modulePerms(m)
    totalPerms += perms.length
    let any = 0
    for (const p of perms) if (rolePerms.value.has(Number(p.id))) any++
    totalEnabled += any
    if (any > 0) modulesTouched++
  }
  return { totalEnabled, totalPerms, modulesTouched, totalModules: matrixModules.value.length }
})

async function savePermissions() {
  if (!selectedRole.value) return
  saving.value = true
  try {
    const ids = Array.from(rolePerms.value).map(Number)
    await adminService.syncRolePermissions(selectedRole.value.id, ids)
    // If the admin just edited a role they themselves hold, refresh their
    // own session so the sidebar / button gates reflect the change without
    // forcing a logout. Force-bypass the throttle since this is a direct
    // user action with intent.
    await userStore.refreshSession({ force: true })
    showToast(`✅ Permissions updated for ${displayRole(selectedRole.value.name)}`)
    closeMatrix()
  } catch (e) {
    showToast('❌ Save failed: ' + (e?.response?.data?.message || e.message), 'error')
  } finally {
    saving.value = false
  }
}

// ── Role CRUD ──────────────────────────────────────────────────────────
async function createRole() {
  const name = newRoleName.value.trim().toLowerCase().replace(/\s+/g, '-')
  if (!name) return
  saving.value = true
  try {
    const r = await adminService.createRole({ name })
    // Two-stage update for reliability:
    //   1. Append the server's response immediately for instant feedback
    //   2. Re-fetch the full list to reconcile (covers cases where backend
    //      normalises the name, or another admin added something concurrently).
    // The axios interceptor unwraps response.data so `r` is the body.
    const created = r?.data?.data || r?.data
    if (created?.id) {
      roles.value = [...roles.value, created]
    }
    showAddRole.value = false
    newRoleName.value = ''
    showToast(`✅ Role "${displayRole(name)}" created`)
    // Reconcile against server (non-blocking on UX).
    loadRoles()
  } catch (e) {
    showToast('❌ Create failed: ' + (e?.response?.data?.message || e.message), 'error')
  } finally {
    saving.value = false
  }
}

function startEditRole(role) {
  editingRoleId.value = role.id
  editRoleName.value = role.name
}

async function saveEditRole() {
  if (!editingRoleId.value) return
  const name = editRoleName.value.trim().toLowerCase().replace(/\s+/g, '-')
  if (!name) return
  saving.value = true
  try {
    await adminService.updateRole(editingRoleId.value, { name })
    // Reactively update the row in place
    const idx = roles.value.findIndex(r => r.id === editingRoleId.value)
    if (idx >= 0) roles.value[idx] = { ...roles.value[idx], name }
    showToast('✅ Role renamed')
    editingRoleId.value = null
  } catch (e) {
    showToast('❌ Update failed: ' + (e?.response?.data?.message || e.message), 'error')
  } finally {
    saving.value = false
  }
}

async function deleteRole(role) {
  if (!confirm(`Delete role "${displayRole(role.name)}"? Users still holding it will lose its permissions.`)) return
  try {
    await adminService.deleteRole(role.id)
    roles.value = roles.value.filter(r => r.id !== role.id)
    showToast('✅ Role deleted')
  } catch (e) {
    showToast('❌ Delete failed: ' + (e?.response?.data?.message || e.message), 'error')
  }
}

// ── Init ───────────────────────────────────────────────────────────────
onMounted(async () => {
  loading.value = true
  await Promise.all([loadRoles(), loadPermissions()])
  loading.value = false
})
</script>

<template>
  <div class="rp-page">
    <!-- ── Toast notification (matches project pattern) ── -->
    <Teleport to="body">
      <Transition name="toast-slide">
        <div v-if="toast.show"
             :style="`position:fixed;top:22px;right:24px;z-index:9999;min-width:300px;max-width:460px;
                      background:${toast.type === 'success' ? '#065f46' : '#991b1b'};
                      color:#fff;border-radius:8px;padding:14px 18px;
                      box-shadow:0 6px 32px rgba(0,0,0,.28);font-size:13px;display:flex;align-items:flex-start;gap:10px`">
          <span style="flex:1;line-height:1.5">{{ toast.message }}</span>
          <button @click="toast.show = false"
                  style="background:rgba(255,255,255,.2);border:none;color:#fff;border-radius:4px;
                         padding:2px 8px;cursor:pointer;font-size:13px;margin-left:4px">✕</button>
        </div>
      </Transition>
    </Teleport>

    <!-- Breadcrumbs -->
    <div class="rp-breadcrumbs">
      <span>🏠</span>
      <span>›</span>
      <span>Dashboard</span>
      <span>›</span>
      <span class="active">Roles &amp; Permissions</span>
    </div>

    <!-- ── Tabs: Roles | Module Access ------------------------------- -->
    <div class="rp-tabs">
      <button class="rp-tab" :class="{ active: viewMode === 'roles' }" @click="switchView('roles')">
        🔐 Roles &amp; Permissions
      </button>
      <button class="rp-tab" :class="{ active: viewMode === 'modules' }" @click="switchView('modules')">
        📦 Module Access
      </button>
    </div>

    <!-- ── Module Access — two-pane editor ------------------------- -->
    <div v-if="viewMode === 'modules'" class="rp-card ma-card">
      <div class="rp-card-header">
        <div>
          <div class="rp-card-title">Module Access by Role</div>
          <div class="rp-card-sub">
            Pick a role on the left, toggle module access on the right.
            Admin-tier roles (System Administrator, Manager, View-Only Admin,
            General View) bypass these gates and are hidden.
          </div>
        </div>
      </div>

      <!-- Loading skeleton -->
      <div v-if="gridLoading" class="ma-shell">
        <div class="ma-sidebar">
          <div v-for="n in 6" :key="'sks-' + n" class="rp-skel" style="height:36px;margin:6px 0"></div>
        </div>
        <div class="ma-main">
          <div v-for="n in 8" :key="'skm-' + n" class="rp-skel" style="height:44px;margin:8px 0"></div>
        </div>
      </div>

      <div v-else-if="scopedRoles.length === 0" class="rp-empty">
        No scoped roles to display.
      </div>

      <div v-else class="ma-shell">
        <!-- LEFT: role picker -->
        <aside class="ma-sidebar">
          <div class="ma-sidebar-h">
            <span>Roles</span>
            <span class="ma-sidebar-h-count">{{ scopedRoles.length }}</span>
          </div>
          <button
            v-for="r in scopedRoles"
            :key="r.id"
            class="ma-role"
            :class="{ active: maSelectedRoleId === r.id, dirty: dirtyRoles.has(r.id) }"
            @click="maSelectedRoleId = r.id"
          >
            <span
              class="ma-role-avatar"
              :style="{
                background: `hsl(${roleHue(r.name)}, 65%, 92%)`,
                color:      `hsl(${roleHue(r.name)}, 55%, 32%)`,
              }"
            >{{ roleInitials(r.name) }}</span>
            <span class="ma-role-info">
              <span class="ma-role-name">{{ displayRole(r.name) }}</span>
              <span class="ma-role-meta">
                <span class="ma-role-count">{{ roleEnabledCount(r.id) }}</span>
                / {{ SIDEBAR_MODULES.length }} modules
                <span v-if="dirtyRoles.has(r.id)" class="ma-role-flag">● unsaved</span>
              </span>
            </span>
            <span v-if="maSelectedRoleId === r.id" class="ma-role-caret">›</span>
          </button>
        </aside>

        <!-- RIGHT: grouped switches for selected role -->
        <section class="ma-main">
          <div v-if="!maSelectedRole" class="ma-empty">
            <div style="font-size:32px;margin-bottom:6px">👈</div>
            Pick a role on the left to edit its module access.
          </div>

          <template v-else>
            <!-- Toolbar: search + save -->
            <div class="ma-toolbar">
              <input
                v-model="maSearch"
                class="ma-search"
                placeholder="🔎 Search modules…"
              />
              <div class="ma-toolbar-spacer"></div>
              <div v-if="dirtyRoles.has(maSelectedRole.id)" class="ma-dirty-pill">● Unsaved changes</div>
              <button
                class="rp-btn rp-btn-pri"
                :disabled="!dirtyRoles.has(maSelectedRole.id) || gridSaving.has(maSelectedRole.id)"
                @click="saveModuleRow(maSelectedRole)"
              >
                {{ gridSaving.has(maSelectedRole.id) ? '⏳ Saving…' : '💾 Save Changes' }}
              </button>
            </div>

            <!-- Group sections -->
            <div v-for="g in moduleGroups" :key="g.name" class="ma-group">
              <div class="ma-group-head" @click="toggleGroupCollapsed(g.name)">
                <span class="ma-group-caret">{{ maCollapsed.has(g.name) ? '▸' : '▾' }}</span>
                <span class="ma-group-name">{{ g.name }}</span>
                <span class="ma-group-count">{{ groupOnCount(maSelectedRole.id, g.modules) }} / {{ g.modules.length }}</span>
                <span class="ma-group-actions" @click.stop>
                  <button
                    class="ma-group-btn"
                    :disabled="groupAllOn(maSelectedRole.id, g.modules)"
                    @click="toggleGroup(maSelectedRole.id, g.modules, true)"
                  >Enable all</button>
                  <button
                    class="ma-group-btn"
                    :disabled="groupAllOff(maSelectedRole.id, g.modules)"
                    @click="toggleGroup(maSelectedRole.id, g.modules, false)"
                  >Disable all</button>
                </span>
              </div>

              <div v-if="!maCollapsed.has(g.name)" class="ma-group-body">
                <label
                  v-for="m in g.modules"
                  :key="m.label"
                  class="ma-row"
                  :class="{ on: roleHasModule(maSelectedRole.id, m) }"
                  :title="m.perms.join(', ')"
                >
                  <span class="ma-row-icon">{{ m.icon }}</span>
                  <span class="ma-row-text">
                    <span class="ma-row-label">{{ m.label }}</span>
                    <span class="ma-row-perms">{{ m.perms.join(' · ') }}</span>
                  </span>
                  <!-- Toggle switch -->
                  <span class="ma-switch">
                    <input
                      type="checkbox"
                      :checked="roleHasModule(maSelectedRole.id, m)"
                      @change="toggleModule(maSelectedRole.id, m)"
                    />
                    <span class="ma-switch-track"><span class="ma-switch-knob"></span></span>
                  </span>
                </label>
              </div>
            </div>

            <div v-if="moduleGroups.length === 0" class="ma-empty">
              No modules match “{{ maSearch }}”.
            </div>
          </template>
        </section>
      </div>
    </div>

    <!-- ── Roles list panel (existing view) ------------------------- -->
    <div v-else class="rp-card">
      <div class="rp-card-header">
        <div class="rp-card-title">Roles &amp; Permissions</div>
        <button class="rp-btn rp-btn-pri" @click="showAddRole = true">+ Add Role</button>
      </div>

      <!-- Skeleton loading: 4 shimmer rows while the role list loads. -->
      <div v-if="loading" class="rp-roles-list">
        <div v-for="n in 4" :key="'sk-' + n" class="rp-role-row rp-skel-row">
          <div class="rp-skel rp-skel-name"></div>
          <div class="rp-skel rp-skel-btn"></div>
        </div>
      </div>

      <div v-else-if="roles.length === 0" class="rp-empty">
        No roles yet — click "+ Add Role" to create one.
      </div>

      <div v-else class="rp-roles-list">
        <div v-for="role in roles" :key="role.id" class="rp-role-row">
          <div class="rp-role-name">
            <template v-if="editingRoleId === role.id">
              <input v-model="editRoleName" class="rp-input" @keyup.enter="saveEditRole" />
            </template>
            <template v-else>
              {{ displayRole(role.name) }}
            </template>
          </div>
          <div class="rp-role-actions">
            <template v-if="editingRoleId === role.id">
              <button class="rp-btn rp-btn-pri rp-btn-sm" :disabled="saving" @click="saveEditRole">Save</button>
              <button class="rp-btn rp-btn-ghost rp-btn-sm" @click="editingRoleId = null">Cancel</button>
            </template>
            <template v-else>
              <button class="rp-btn rp-btn-pri" @click="openMatrix(role)">🔐 Permissions</button>
              <button class="rp-btn rp-btn-ghost rp-btn-sm" title="Rename" @click="startEditRole(role)">✎</button>
              <button class="rp-btn rp-btn-danger rp-btn-sm" title="Delete" @click="deleteRole(role)">🗑</button>
            </template>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Permission matrix modal ---------------------------------- -->
    <div v-if="selectedRole" class="rp-modal-backdrop" @click.self="closeMatrix">
      <div class="rp-modal">
        <div class="rp-modal-header">
          <div>
            <div class="rp-modal-eyebrow">Permissions</div>
            <div class="rp-modal-title">{{ displayRole(selectedRole.name) }}</div>
          </div>
          <button class="rp-modal-close" @click="closeMatrix">✕</button>
        </div>

        <!-- Search + filter chips -->
        <div class="rp-modal-search">
          <input v-model="moduleSearch" class="rp-input" placeholder="🔎 Search modules…" />
          <div class="rp-filter-chips">
            <button class="rp-chip" :class="{ active: matrixFilter === 'all' }"      @click="matrixFilter = 'all'">All <span class="rp-chip-n">{{ matrixModules.length }}</span></button>
            <button class="rp-chip" :class="{ active: matrixFilter === 'active' }"   @click="matrixFilter = 'active'">With access <span class="rp-chip-n">{{ matrixSummary.modulesTouched }}</span></button>
            <button class="rp-chip" :class="{ active: matrixFilter === 'inactive' }" @click="matrixFilter = 'inactive'">No access <span class="rp-chip-n">{{ matrixSummary.totalModules - matrixSummary.modulesTouched }}</span></button>
          </div>
        </div>

        <!-- Skeleton matrix while permissions load for the selected role -->
        <div v-if="matrixLoading" class="rp-matrix-wrap">
          <div v-for="n in 6" :key="'mskel-' + n" class="rp-matrix-skel">
            <div class="rp-skel" style="width:40px;height:14px"></div>
            <div class="rp-skel" style="flex:1;height:14px"></div>
            <div v-for="m in 5" :key="m" class="rp-skel" style="width:36px;height:20px;border-radius:10px"></div>
          </div>
        </div>

        <div v-else class="rp-matrix-wrap">
          <table class="rp-matrix">
            <thead>
              <tr>
                <th class="col-id">#</th>
                <th class="col-mod">Module</th>
                <th class="col-act"><span class="th-ic" title="Add">＋</span> Add</th>
                <th class="col-act"><span class="th-ic" title="Edit">✎</span> Edit</th>
                <th class="col-act"><span class="th-ic" title="Show / detail">👁</span> Show</th>
                <th class="col-act"><span class="th-ic" title="View / list">📋</span> View</th>
                <th class="col-act"><span class="th-ic" title="Delete">🗑</span> Delete</th>
                <th class="col-more">Extra</th>
                <th class="col-bulk">Bulk</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="visibleModules.length === 0">
                <td colspan="9" class="rp-matrix-empty">
                  No modules match the current filter.
                </td>
              </tr>
              <tr
                v-for="(mod, idx) in visibleModules"
                :key="mod.name"
                :class="{ 'row-active': moduleHasAny(mod), 'row-full': moduleHasAll(mod) }"
              >
                <td class="col-id">{{ idx + 1 }}</td>
                <td class="col-mod">
                  <span class="mod-name">{{ displayRole(mod.name) }}</span>
                  <span
                    class="mod-count"
                    :class="{ all: moduleHasAll(mod), some: moduleHasAny(mod) && !moduleHasAll(mod) }"
                  >{{ moduleEnabledCount(mod) }} / {{ moduleTotalCount(mod) }}</span>
                </td>
                <td v-for="col in CRUD_PREFIXES" :key="col" class="col-act">
                  <label v-if="mod.slots[col]" class="rp-switch">
                    <input
                      type="checkbox"
                      :checked="hasPerm(mod.slots[col].id)"
                      @change="togglePerm(mod.slots[col].id)"
                    />
                    <span class="rp-switch-slider"></span>
                  </label>
                  <span v-else class="rp-na">—</span>
                </td>
                <td class="col-more">
                  <details v-if="mod.others.length > 0">
                    <summary>
                      <span class="rp-others-chev">›</span>
                      <span
                        class="rp-others-count"
                        :class="{ 'rp-others-count-active': activeOthers(mod.others) > 0 }"
                        :title="`${mod.others.length} extra permission(s)` + (activeOthers(mod.others) ? `, ${activeOthers(mod.others)} enabled` : '')"
                      >
                        {{ mod.others.length }}<template v-if="activeOthers(mod.others) > 0"> · {{ activeOthers(mod.others) }}</template>
                      </span>
                    </summary>
                    <div class="rp-others">
                      <label v-for="p in mod.others" :key="p.id" class="rp-other-row">
                        <input
                          type="checkbox"
                          :checked="hasPerm(p.id)"
                          @change="togglePerm(p.id)"
                        />
                        {{ displayRole(p.name) }}
                      </label>
                    </div>
                  </details>
                  <span v-else class="rp-na">—</span>
                </td>
                <td class="col-bulk">
                  <div class="rp-bulk-btns">
                    <button
                      class="rp-bulk-btn on"
                      :disabled="moduleHasAll(mod)"
                      :title="`Enable all ${moduleTotalCount(mod)} permissions for ${displayRole(mod.name)}`"
                      @click="toggleAllForModule(mod, true)"
                    >All</button>
                    <button
                      class="rp-bulk-btn off"
                      :disabled="!moduleHasAny(mod)"
                      :title="`Disable all permissions for ${displayRole(mod.name)}`"
                      @click="toggleAllForModule(mod, false)"
                    >None</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="rp-modal-footer">
          <div class="rp-footer-summary">
            <strong>{{ matrixSummary.totalEnabled }}</strong> / {{ matrixSummary.totalPerms }} permissions ·
            <strong>{{ matrixSummary.modulesTouched }}</strong> / {{ matrixSummary.totalModules }} modules
          </div>
          <button class="rp-btn rp-btn-ghost" @click="closeMatrix">Cancel</button>
          <button class="rp-btn rp-btn-pri" :disabled="saving || matrixLoading" @click="savePermissions">
            {{ saving ? 'Saving…' : 'Save Permissions' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ── Add Role modal ------------------------------------------- -->
    <div v-if="showAddRole" class="rp-modal-backdrop" @click.self="showAddRole = false">
      <div class="rp-modal" style="max-width:480px">
        <div class="rp-modal-header">
          <div>
            <div class="rp-modal-eyebrow">New Role</div>
            <div class="rp-modal-title">Create Role</div>
          </div>
          <button class="rp-modal-close" @click="showAddRole = false">✕</button>
        </div>
        <div style="padding: 16px 22px">
          <label class="rp-field-label">Role name</label>
          <input
            v-model="newRoleName"
            class="rp-input"
            placeholder="e.g. Senior Clerk"
            @keyup.enter="createRole"
          />
          <div class="rp-help">Will be saved as a kebab-case slug (e.g. <code>senior-clerk</code>).</div>
        </div>
        <div class="rp-modal-footer">
          <button class="rp-btn rp-btn-ghost" @click="showAddRole = false">Cancel</button>
          <button class="rp-btn rp-btn-pri" :disabled="saving || !newRoleName.trim()" @click="createRole">
            {{ saving ? 'Creating…' : 'Create' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.rp-page { padding: 14px 24px 36px; font-family: 'DM Sans', sans-serif; }

.rp-breadcrumbs {
  display: flex; gap: 8px; align-items: center;
  font-size: 13px; color: #64748b; margin-bottom: 14px;
  background: #fff; padding: 10px 14px; border-radius: 8px;
  border: 1px solid #e2e8f0;
}
.rp-breadcrumbs .active { color: #0f172a; font-weight: 600; }

.rp-card {
  background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
  padding: 18px 22px; box-shadow: 0 1px 2px rgba(0,0,0,.02);
}
.rp-card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.rp-card-title  { font-size: 16px; font-weight: 700; color: #0f172a; }

.rp-empty { padding: 30px 0; text-align: center; color: #94a3b8; font-size: 13px; }

.rp-roles-list { display: flex; flex-direction: column; gap: 10px; }
.rp-role-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 16px; border: 1px solid #e2e8f0; border-radius: 8px;
  background: #fff;
}
.rp-role-name    { font-size: 13.5px; font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: .03em; }
.rp-role-actions { display: flex; gap: 6px; align-items: center; }

.rp-btn {
  border: 1px solid transparent; border-radius: 6px; padding: 7px 14px;
  font-size: 12.5px; font-weight: 600; cursor: pointer; display: inline-flex;
  align-items: center; gap: 6px; transition: all .12s;
}
.rp-btn-sm { padding: 5px 10px; font-size: 11.5px; }
.rp-btn-pri    { background: #2563eb; color: #fff; }
.rp-btn-pri:hover:not(:disabled) { background: #1d4ed8; }
.rp-btn-pri:disabled { background: #94a3b8; cursor: not-allowed; }
.rp-btn-ghost  { background: #f1f5f9; color: #334155; }
.rp-btn-ghost:hover  { background: #e2e8f0; }
.rp-btn-danger { background: #fee2e2; color: #991b1b; }
.rp-btn-danger:hover { background: #fecaca; }

/* Modal */
.rp-modal-backdrop {
  position: fixed; inset: 0; background: rgba(15,23,42,.45);
  display: flex; align-items: flex-start; justify-content: center;
  padding: 40px 16px; z-index: 1000; overflow-y: auto;
}
.rp-modal {
  background: #fff; border-radius: 12px; max-width: 900px; width: 100%;
  box-shadow: 0 20px 60px rgba(15,23,42,.25);
  display: flex; flex-direction: column;
}
.rp-modal-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 22px; border-bottom: 1px solid #eef1f5;
}
.rp-modal-eyebrow { font-size: 10.5px; color: #64748b; letter-spacing: .06em; text-transform: uppercase; }
.rp-modal-title   { font-size: 16px; font-weight: 700; color: #0f172a; margin-top: 2px; }
.rp-modal-close   { background: transparent; border: none; font-size: 18px; color: #64748b; cursor: pointer; padding: 4px 8px; border-radius: 6px; }
.rp-modal-close:hover { background: #f1f5f9; color: #0f172a; }
.rp-modal-footer  { display: flex; align-items: center; justify-content: flex-end; gap: 8px; padding: 14px 22px; border-top: 1px solid #eef1f5; background: #f8fafc; border-radius: 0 0 12px 12px; }

.rp-modal-search { padding: 12px 22px 0; display: flex; flex-direction: column; gap: 8px; }

/* Filter chips above the matrix */
.rp-filter-chips { display: flex; gap: 6px; flex-wrap: wrap; }
.rp-chip {
  display: inline-flex; align-items: center; gap: 6px;
  background: #fff; border: 1px solid #cbd5e1; color: #475569;
  font-size: 11.5px; font-weight: 600;
  padding: 5px 11px; border-radius: 999px; cursor: pointer;
  transition: background .12s, border-color .12s, color .12s;
}
.rp-chip:hover { border-color: #94a3b8; background: #f8fafc; }
.rp-chip.active {
  background: #1e293b; border-color: #1e293b; color: #fff;
}
.rp-chip-n {
  background: #e2e8f0; color: #334155;
  font-size: 10px; padding: 1px 6px; border-radius: 8px; font-weight: 700;
}
.rp-chip.active .rp-chip-n { background: #475569; color: #fff; }

.rp-matrix-wrap { padding: 12px 22px; overflow-x: auto; max-height: 60vh; overflow-y: auto; }
.rp-matrix      { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.rp-matrix th   { text-align: center; background: #f8fafc; color: #334155; font-weight: 700; padding: 10px 8px; border-bottom: 2px solid #e2e8f0; position: sticky; top: 0; z-index: 1; white-space: nowrap; }
.rp-matrix th.col-id, .rp-matrix th.col-mod { text-align: left; }
.rp-matrix td   { padding: 10px 8px; border-bottom: 1px solid #f1f5f9; color: #1e293b; text-align: center; vertical-align: middle; }
.rp-matrix td.col-id, .rp-matrix td.col-mod { text-align: left; }
.rp-matrix tr:hover td { background: #f8fafc; }

/* Row tinting based on activity. Even a single active perm = pale green;
   every perm enabled = stronger green. Helps admins eyeball the row state. */
.rp-matrix tr.row-active td      { background: #f0fdf4; }
.rp-matrix tr.row-active:hover td { background: #dcfce7; }
.rp-matrix tr.row-full   td       { background: #ecfccb; }
.rp-matrix tr.row-full:hover td   { background: #d9f99d; }

/* Column sizing */
.rp-matrix th.col-id,  .rp-matrix td.col-id   { width: 36px; color: #94a3b8; font-size: 11px; font-family: 'DM Mono', monospace; }
.rp-matrix th.col-mod, .rp-matrix td.col-mod  { min-width: 180px; }
.rp-matrix th.col-act, .rp-matrix td.col-act  { width: 64px; }
.rp-matrix th.col-more,.rp-matrix td.col-more { width: 70px; }
.rp-matrix th.col-bulk,.rp-matrix td.col-bulk { width: 92px; }
.th-ic { display: inline-block; margin-right: 3px; font-size: 13px; vertical-align: middle; }

/* Module name + count badge */
.col-mod .mod-name { display: inline-block; font-weight: 600; color: #0f172a; }
.col-mod .mod-count {
  display: inline-block; margin-left: 7px;
  background: #e2e8f0; color: #475569;
  font-size: 10.5px; font-weight: 700; padding: 1px 7px;
  border-radius: 9px; font-family: 'DM Mono', monospace; min-width: 32px; text-align: center;
}
.col-mod .mod-count.some { background: #dbeafe; color: #1d4ed8; }
.col-mod .mod-count.all  { background: #bbf7d0; color: #166534; }

/* Row bulk button pair — small, paired, only active when something to do */
.rp-bulk-btns { display: inline-flex; gap: 0; border: 1px solid #cbd5e1; border-radius: 5px; overflow: hidden; }
.rp-bulk-btn  {
  background: #fff; border: none; color: #334155;
  padding: 4px 9px; font-size: 10.5px; font-weight: 700;
  cursor: pointer; transition: background .12s, color .12s;
  border-right: 1px solid #cbd5e1;
}
.rp-bulk-btn:last-child { border-right: none; }
.rp-bulk-btn:hover:not(:disabled) { background: #f1f5f9; }
.rp-bulk-btn.on:hover:not(:disabled)  { background: #16a34a; color: #fff; }
.rp-bulk-btn.off:hover:not(:disabled) { background: #dc2626; color: #fff; }
.rp-bulk-btn:disabled { color: #cbd5e1; cursor: not-allowed; background: #f8fafc; }

.rp-matrix-empty { text-align: center; color: #94a3b8; padding: 40px 20px; font-size: 13px; }

/* Footer summary on the left */
.rp-modal-footer .rp-footer-summary {
  margin-right: auto; font-size: 11.5px; color: #64748b;
}
.rp-modal-footer .rp-footer-summary strong { color: #0f172a; font-weight: 700; }

.rp-na { color: #cbd5e1; font-size: 11px; }

/* iOS-style toggle */
.rp-switch { position: relative; display: inline-block; width: 36px; height: 20px; cursor: pointer; }
.rp-switch input { opacity: 0; width: 0; height: 0; }
.rp-switch-slider {
  position: absolute; inset: 0; background: #cbd5e1; border-radius: 20px; transition: background .15s;
}
.rp-switch-slider::before {
  content: ''; position: absolute; left: 2px; top: 2px;
  width: 16px; height: 16px; background: #fff; border-radius: 50%;
  transition: transform .15s; box-shadow: 0 1px 3px rgba(0,0,0,.18);
}
.rp-switch input:checked + .rp-switch-slider { background: #2563eb; }
.rp-switch input:checked + .rp-switch-slider::before { transform: translateX(16px); }

.rp-others { padding: 8px 12px; background: #f8fafc; border-radius: 6px; margin-top: 6px; }
.rp-other-row { display: flex; gap: 8px; align-items: center; padding: 4px 0; font-size: 12px; color: #334155; cursor: pointer; }

.rp-input {
  width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px;
  font-size: 13px; outline: none; transition: border-color .12s;
}
.rp-input:focus { border-color: #2563eb; }

.rp-field-label { display: block; font-size: 11.5px; font-weight: 600; color: #475569; margin-bottom: 5px; text-transform: uppercase; letter-spacing: .04em; }
.rp-help        { font-size: 11.5px; color: #94a3b8; margin-top: 6px; }
.rp-help code   { background: #f1f5f9; padding: 1px 5px; border-radius: 3px; font-family: monospace; font-size: 11px; }

details summary {
  cursor: pointer;
  color: #64748b;
  line-height: 1;
  user-select: none;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  list-style: none;
}
details summary::-webkit-details-marker { display: none; }
.rp-others-chev { font-size: 18px; display: inline-block; transition: transform .12s; }
details[open] .rp-others-chev { transform: rotate(90deg); color: #2563eb; }

/* Count pill — shows hidden permission count, turns blue when any are enabled. */
.rp-others-count {
  display: inline-flex; align-items: center;
  background: #f1f5f9; color: #64748b;
  border-radius: 999px; padding: 2px 8px;
  font-size: 10.5px; font-weight: 700;
  min-width: 18px; justify-content: center;
  transition: background .12s, color .12s;
}
.rp-others-count-active {
  background: #dbeafe; color: #1d4ed8;
}

/* ── Skeleton loading rows ───────────────────────────────────────── */
.rp-skel {
  background: linear-gradient(90deg, #f1f5f9 0%, #e2e8f0 50%, #f1f5f9 100%);
  background-size: 200% 100%;
  animation: rp-shimmer 1.4s infinite ease-in-out;
  border-radius: 6px;
}
.rp-skel-row { animation: none; }
.rp-skel-name { width: 220px; height: 14px; }
.rp-skel-btn  { width: 130px; height: 30px; border-radius: 6px; }
.rp-matrix-skel { display: flex; gap: 16px; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9; }

@keyframes rp-shimmer {
  0%   { background-position: -200% 0; }
  100% { background-position: 200% 0; }
}

/* ── Tabs + Module Access grid ──────────────────────────────────────── */
.rp-tabs { display: flex; gap: 4px; margin-bottom: 14px; border-bottom: 1px solid #e2e8f0; }
.rp-tab {
  background: transparent; border: none; padding: 9px 16px;
  font-size: 13px; font-weight: 700; color: #64748b; cursor: pointer;
  border-bottom: 2.5px solid transparent; margin-bottom: -1px;
  transition: color .15s ease, border-color .15s ease;
}
.rp-tab:hover { color: #1e293b; }
.rp-tab.active { color: #2563eb; border-bottom-color: #2563eb; }

.rp-card-sub { font-size: 12px; color: #64748b; margin-top: 3px; max-width: 720px; line-height: 1.45; }

.rp-grid-wrap { overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px; }
.rp-grid { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 12px; }
.rp-grid th, .rp-grid td { border-bottom: 1px solid #f1f5f9; padding: 8px 10px; vertical-align: middle; }
.rp-grid thead th {
  background: #1e293b; color: #fff; font-weight: 700;
  position: sticky; top: 0; z-index: 2;
  text-align: center; font-size: 11px;
}
.rp-grid-mod-h { min-width: 96px; max-width: 110px; padding: 6px 4px; }
.rp-grid-mod-icon { font-size: 15px; line-height: 1; margin-bottom: 3px; }
.rp-grid-mod-label { font-size: 10px; line-height: 1.2; font-weight: 600; opacity: .9; word-break: break-word; }
.rp-grid-role-h, .rp-grid-save-h { text-align: left; }
.rp-grid-role-h {
  position: sticky; left: 0; z-index: 3;
  background: #1e293b; min-width: 180px; text-align: left;
}
.rp-grid-role {
  position: sticky; left: 0; z-index: 1; background: #fff;
  font-weight: 700; color: #0f172a; min-width: 180px;
  border-right: 1px solid #e2e8f0;
}
.rp-grid-row-dirty .rp-grid-role { background: #fefce8; }
.rp-grid-cell { text-align: center; background: #fff; }
.rp-grid-row-dirty .rp-grid-cell { background: #fefce8; }
.rp-grid-chk { width: 16px; height: 16px; cursor: pointer; accent-color: #2563eb; }
.rp-grid-save { text-align: center; min-width: 96px; background: #fff; }
.rp-grid-row-dirty .rp-grid-save { background: #fefce8; }
.rp-grid-save-dash { color: #cbd5e1; }
.rp-grid-skel { padding: 16px; }
</style>

<style>
/* Global so the toast in <Teleport to="body"> picks it up — matches the
   project pattern used by DiariesDispatches / Topbar / UsersHR. */
.toast-slide-enter-active,
.toast-slide-leave-active { transition: all 0.3s ease; }
.toast-slide-enter-from,
.toast-slide-leave-to { opacity: 0; transform: translateY(-20px); }

/* ── Module Access two-pane editor ──────────────────────────────────── */
.ma-card { padding: 0; overflow: hidden; }
.ma-card .rp-card-header { padding: 16px 20px; border-bottom: 1px solid #e2e8f0; margin-bottom: 0; }
.ma-shell {
  display: grid;
  grid-template-columns: 280px 1fr;
}
.ma-sidebar {
  border-right: 1px solid #e2e8f0;
  background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
  padding: 12px 10px;
  overflow-y: auto;
  max-height: 72vh;
  min-height: 480px;
}
.ma-sidebar-h {
  display: flex; align-items: center; justify-content: space-between;
  font-size: 10.5px; font-weight: 700; color: #64748b;
  text-transform: uppercase; letter-spacing: .08em;
  padding: 4px 10px 12px;
}
.ma-sidebar-h-count {
  background: #cbd5e1; color: #334155;
  font-size: 10px; padding: 1px 7px; border-radius: 8px;
  font-weight: 700;
}

/* Role row — avatar + name + meta + caret. Selected row pops with navy
   background + light shadow; hover adds a subtle indent feel. */
.ma-role {
  display: flex; align-items: center; gap: 10px;
  width: 100%; text-align: left;
  background: #fff; border: 1px solid #e2e8f0;
  padding: 10px 11px; border-radius: 8px;
  cursor: pointer; margin-bottom: 6px;
  transition: transform .12s, box-shadow .12s, border-color .12s, background .12s;
}
.ma-role:hover {
  border-color: #94a3b8;
  box-shadow: 0 1px 4px rgba(15, 23, 42, .06);
  transform: translateX(2px);
}
.ma-role.active {
  background: #1e293b; border-color: #1e293b;
  box-shadow: 0 4px 14px rgba(15, 23, 42, .25);
}
.ma-role-avatar {
  flex-shrink: 0;
  width: 34px; height: 34px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 12.5px; font-weight: 700;
  letter-spacing: .02em;
}
.ma-role.active .ma-role-avatar {
  box-shadow: 0 0 0 2px rgba(255,255,255,.4);
}
.ma-role-info { flex: 1; min-width: 0; }
.ma-role-name {
  display: block; font-size: 13px; font-weight: 600; color: #0f172a;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.ma-role.active .ma-role-name { color: #fff; }
.ma-role-meta {
  display: block; font-size: 10.5px; color: #64748b; margin-top: 2px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.ma-role.active .ma-role-meta { color: #cbd5e1; }
.ma-role-count {
  font-weight: 700; color: #1e293b; font-family: 'DM Mono', monospace;
}
.ma-role.active .ma-role-count { color: #fde68a; }
.ma-role-flag {
  display: inline-block; margin-left: 6px;
  color: #ca8a04; font-weight: 700;
}
.ma-role.active .ma-role-flag { color: #fde047; }
.ma-role-caret {
  color: #fff; font-size: 22px; font-weight: 300; flex-shrink: 0;
  margin-right: -2px;
}

.ma-main {
  padding: 16px 20px;
  overflow-y: auto;
  max-height: 72vh;
  min-height: 480px;
}
.ma-empty {
  text-align: center; color: #94a3b8; font-size: 13px;
  padding: 40px 20px;
}

.ma-toolbar {
  display: flex; align-items: center; gap: 10px;
  margin-bottom: 14px;
  padding-bottom: 12px;
  border-bottom: 1px solid #f1f5f9;
}
.ma-search {
  flex: 0 1 320px;
  padding: 7px 11px;
  border: 1px solid #cbd5e1; border-radius: 6px;
  font-size: 12.5px;
}
.ma-search:focus { outline: none; border-color: #1e293b; }
.ma-toolbar-spacer { flex: 1; }
.ma-dirty-pill {
  font-size: 11px; font-weight: 600;
  color: #854d0e; background: #fef3c7;
  border: 1px solid #fde68a; padding: 3px 9px; border-radius: 9px;
}

.ma-group {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  margin-bottom: 10px;
  overflow: hidden;
}
.ma-group-head {
  display: flex; align-items: center; gap: 8px;
  padding: 9px 12px;
  background: #f8fafc;
  cursor: pointer;
  user-select: none;
  border-bottom: 1px solid #e2e8f0;
}
.ma-group-head:hover { background: #f1f5f9; }
.ma-group-caret { color: #64748b; font-size: 11px; width: 12px; flex-shrink: 0; }
.ma-group-name  { font-weight: 700; color: #0f172a; font-size: 13px; flex: 1; }
.ma-group-count {
  font-size: 11px; font-weight: 600; color: #475569;
  background: #e2e8f0; padding: 2px 8px; border-radius: 9px;
}
.ma-group-actions { display: flex; gap: 4px; margin-left: 8px; }
.ma-group-btn {
  background: transparent; border: 1px solid #cbd5e1;
  color: #475569; font-size: 10.5px; font-weight: 600;
  padding: 3px 8px; border-radius: 4px; cursor: pointer;
}
.ma-group-btn:hover:not(:disabled) { background: #1e293b; color: #fff; border-color: #1e293b; }
.ma-group-btn:disabled { opacity: .35; cursor: not-allowed; }

.ma-group-body { padding: 4px 0; }

.ma-row {
  display: flex; align-items: center; gap: 12px;
  padding: 9px 14px;
  cursor: pointer;
  border-bottom: 1px solid #f8fafc;
  transition: background .12s;
}
.ma-row:last-child { border-bottom: none; }
.ma-row:hover { background: #f8fafc; }
.ma-row.on { background: #f0fdf4; }
.ma-row.on:hover { background: #dcfce7; }
.ma-row-icon { font-size: 16px; flex-shrink: 0; width: 22px; text-align: center; }
.ma-row-text { flex: 1; min-width: 0; }
.ma-row-label { display: block; font-size: 13px; font-weight: 500; color: #0f172a; }
.ma-row-perms {
  display: block;
  font-size: 10.5px; color: #94a3b8;
  font-family: 'DM Mono', monospace;
  margin-top: 1px;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}

/* iOS-style toggle switch */
.ma-switch { position: relative; flex-shrink: 0; }
.ma-switch input { position: absolute; opacity: 0; pointer-events: none; }
.ma-switch-track {
  display: block; width: 38px; height: 22px;
  background: #cbd5e1; border-radius: 11px;
  transition: background .18s;
  position: relative;
}
.ma-switch-knob {
  position: absolute; top: 2px; left: 2px;
  width: 18px; height: 18px;
  background: #fff; border-radius: 50%;
  box-shadow: 0 1px 3px rgba(0,0,0,.18);
  transition: transform .18s;
}
.ma-switch input:checked + .ma-switch-track { background: #16a34a; }
.ma-switch input:checked + .ma-switch-track .ma-switch-knob { transform: translateX(16px); }

@media (max-width: 880px) {
  .ma-shell { grid-template-columns: 1fr; }
  .ma-sidebar { border-right: none; border-bottom: 1px solid #e2e8f0; max-height: 220px; }
  .ma-main { max-height: none; }
}
</style>
