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
const SIDEBAR_MODULES = [
  { label: 'Sample Registration',     icon: '🧪',  perms: ['add_water_samples'] },
  { label: 'Analysis Entry',          icon: '⚗️',  perms: ['add_water_sample_details', 'edit_water_sample_results'] },
  { label: 'Individual Sample Report', icon: '📝', perms: ['view_individual_sample_report'] },
  { label: 'GAR (Abstract)',          icon: '📄',  perms: ['view_gar'] },
  { label: 'GSR (Summary)',           icon: '📋',  perms: ['view_gsr'] },
  { label: 'ASR (Analysis Summary)',  icon: '📊',  perms: ['view_asr'] },
  { label: 'CE-Wise Report',          icon: '🗺️',  perms: ['view_ce_wise_report'] },
  { label: 'PWR (Parameter-wise)',    icon: '🔬',  perms: ['view_pwr'] },
  { label: 'WSS Map',                 icon: '🗾',  perms: ['view_water_schemes'] },
  { label: 'Invoices / Revenue',      icon: '🧾',  perms: ['view_invoices'] },
  { label: 'SBP Submissions',         icon: '🏦',  perms: ['view_sbp_submissions'] },
  { label: 'Stock / Inventory',       icon: '📦',  perms: ['view_inventories'] },
  { label: 'Equipment Register',      icon: '🔧',  perms: ['view_assets'] },
  { label: 'Demand & Issuance',       icon: '🔄',  perms: ['view_demands', 'view_inventories', 'add_inventories'] },
  { label: 'Diaries / Dispatches',    icon: '📝',  perms: ['view_diaries', 'view_dispatches'] },
  { label: 'Water Scheme Details',    icon: '💧',  perms: ['view_water_schemes'] },
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

async function switchView(mode) {
  viewMode.value = mode
  if (mode !== 'modules' || rolePermSets.value.size > 0) return
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

    <!-- ── Module Access grid -------------------------------------- -->
    <div v-if="viewMode === 'modules'" class="rp-card">
      <div class="rp-card-header">
        <div>
          <div class="rp-card-title">Module Access by Role</div>
          <div class="rp-card-sub">
            Toggle which sidebar modules each role can see. Admin-tier roles
            (System Administrator, Manager, View-Only Admin, General View) bypass
            these gates and are hidden from the grid.
          </div>
        </div>
      </div>

      <div v-if="gridLoading" class="rp-grid-skel">
        <div v-for="n in 5" :key="'gs-' + n" class="rp-skel" style="height: 38px; margin: 8px 0;"></div>
      </div>

      <div v-else class="rp-grid-wrap">
        <table class="rp-grid">
          <thead>
            <tr>
              <th class="rp-grid-role-h">Role</th>
              <th v-for="m in SIDEBAR_MODULES" :key="m.label" class="rp-grid-mod-h" :title="m.perms.join(', ')">
                <div class="rp-grid-mod-icon">{{ m.icon }}</div>
                <div class="rp-grid-mod-label">{{ m.label }}</div>
              </th>
              <th class="rp-grid-save-h">Save</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in scopedRoles" :key="r.id" :class="{ 'rp-grid-row-dirty': dirtyRoles.has(r.id) }">
              <td class="rp-grid-role">{{ displayRole(r.name) }}</td>
              <td v-for="m in SIDEBAR_MODULES" :key="m.label" class="rp-grid-cell">
                <input
                  type="checkbox"
                  class="rp-grid-chk"
                  :checked="roleHasModule(r.id, m)"
                  @change="toggleModule(r.id, m)"
                />
              </td>
              <td class="rp-grid-save">
                <button
                  v-if="dirtyRoles.has(r.id)"
                  class="rp-btn rp-btn-pri rp-btn-sm"
                  :disabled="gridSaving.has(r.id)"
                  @click="saveModuleRow(r)"
                >{{ gridSaving.has(r.id) ? '⏳ Saving' : '💾 Save' }}</button>
                <span v-else class="rp-grid-save-dash">—</span>
              </td>
            </tr>
            <tr v-if="scopedRoles.length === 0">
              <td :colspan="SIDEBAR_MODULES.length + 2" class="rp-empty">No scoped roles to display.</td>
            </tr>
          </tbody>
        </table>
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

        <div class="rp-modal-search">
          <input v-model="moduleSearch" class="rp-input" placeholder="Search modules…" />
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
                <th style="width:40px">ID</th>
                <th>Module</th>
                <th v-for="col in CRUD_PREFIXES" :key="col">{{ col.charAt(0).toUpperCase() + col.slice(1) }}</th>
                <th style="width:60px">More</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(mod, idx) in filteredModules" :key="mod.name">
                <td>{{ idx + 1 }}</td>
                <td>{{ displayRole(mod.name) }}</td>
                <td v-for="col in CRUD_PREFIXES" :key="col">
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
                <td>
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
              </tr>
            </tbody>
          </table>
        </div>

        <div class="rp-modal-footer">
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
.rp-modal-footer  { display: flex; justify-content: flex-end; gap: 8px; padding: 14px 22px; border-top: 1px solid #eef1f5; background: #f8fafc; border-radius: 0 0 12px 12px; }

.rp-modal-search { padding: 12px 22px 0; }

.rp-matrix-wrap { padding: 12px 22px; overflow-x: auto; max-height: 60vh; overflow-y: auto; }
.rp-matrix      { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.rp-matrix th   { text-align: left; background: #f8fafc; color: #334155; font-weight: 700; padding: 10px 8px; border-bottom: 2px solid #e2e8f0; position: sticky; top: 0; }
.rp-matrix td   { padding: 10px 8px; border-bottom: 1px solid #f1f5f9; color: #1e293b; }
.rp-matrix tr:hover td { background: #f8fafc; }

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
</style>
