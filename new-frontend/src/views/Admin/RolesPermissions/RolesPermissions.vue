<script setup>
import { ref, computed, onMounted } from 'vue'
import { adminService } from '../../../services/adminService.js'

// ── State ──────────────────────────────────────────────────────────────
const loading      = ref(false)
const errorMsg     = ref('')
const successMsg   = ref('')
const roles        = ref([])
const permissions  = ref([])         // all permissions (with module info)
const selectedRole = ref(null)       // role whose matrix is open
const rolePerms    = ref(new Set())  // permission ids currently checked
const saving       = ref(false)

// Add role modal state
const showAddRole  = ref(false)
const newRoleName  = ref('')

// Edit role state
const editingRoleId = ref(null)
const editRoleName  = ref('')

// ── Helpers ────────────────────────────────────────────────────────────
const CRUD_PREFIXES = ['add', 'edit', 'show', 'view', 'delete']

/**
 * Convert "Director Labs" / "director-labs" / "director_labs" → display label.
 */
function displayRole(name) {
  if (!name) return '—'
  return String(name)
    .replace(/[-_]/g, ' ')
    .split(' ')
    .map(w => w.charAt(0).toUpperCase() + w.slice(1))
    .join(' ')
}

function flash(msg, isError = false) {
  if (isError) { errorMsg.value = msg; successMsg.value = '' }
  else         { successMsg.value = msg; errorMsg.value = '' }
  setTimeout(() => { errorMsg.value = ''; successMsg.value = '' }, 4000)
}

// Build a {moduleName: {add: perm|null, edit: perm|null, ...}} map for the role matrix
const matrixModules = computed(() => {
  const byMod = new Map()
  for (const p of permissions.value) {
    const modName = p.module?.name || p.module_name || 'misc'
    if (!byMod.has(modName)) byMod.set(modName, { name: modName, slots: {}, others: [] })
    const entry = byMod.get(modName)

    // Identify CRUD slot by prefix match on permission name (add_*, edit_*, etc)
    const slot = CRUD_PREFIXES.find(pre => p.name.startsWith(pre + '_'))
    if (slot && !entry.slots[slot]) {
      entry.slots[slot] = p
    } else {
      entry.others.push(p)
    }
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
    roles.value = r.data?.data || []
  } catch (e) {
    flash('Failed to load roles: ' + (e?.response?.data?.message || e.message), true)
  }
}

async function loadPermissions() {
  try {
    const p = await adminService.getPermissions()
    // Permissions endpoint may return data as array directly, or { data: [...] }
    const list = p.data?.data || p.data || []
    permissions.value = Array.isArray(list) ? list : []
  } catch (e) {
    flash('Failed to load permissions: ' + (e?.response?.data?.message || e.message), true)
  }
}

async function openMatrix(role) {
  selectedRole.value = role
  rolePerms.value = new Set()
  try {
    // GET /roles/{id} returns the role with eager-loaded permissions
    // (id + name on each). Simpler than the module-grouped endpoint.
    const r = await adminService.getRole(role.id)
    const data = r.data?.data || r.data || {}
    const perms = data?.permissions || []
    for (const p of perms) {
      const id = p?.id ?? p?.permission_id ?? p
      if (id != null) rolePerms.value.add(Number(id))
    }
  } catch (e) {
    flash('Failed to load role permissions: ' + (e?.response?.data?.message || e.message), true)
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
  // Trigger reactivity since Set mutation isn't observed by default
  rolePerms.value = new Set(rolePerms.value)
}

function hasPerm(permId) {
  return rolePerms.value.has(Number(permId))
}

async function savePermissions() {
  if (!selectedRole.value) return
  saving.value = true
  try {
    const ids = Array.from(rolePerms.value).map(Number)
    await adminService.syncRolePermissions(selectedRole.value.id, ids)
    flash(`Permissions updated for ${displayRole(selectedRole.value.name)}.`)
    closeMatrix()
  } catch (e) {
    flash('Save failed: ' + (e?.response?.data?.message || e.message), true)
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
    await adminService.createRole({ name })
    showAddRole.value = false
    newRoleName.value = ''
    flash(`Role "${displayRole(name)}" created.`)
    await loadRoles()
  } catch (e) {
    flash('Create failed: ' + (e?.response?.data?.message || e.message), true)
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
    flash('Role renamed.')
    editingRoleId.value = null
    await loadRoles()
  } catch (e) {
    flash('Update failed: ' + (e?.response?.data?.message || e.message), true)
  } finally {
    saving.value = false
  }
}

async function deleteRole(role) {
  if (!confirm(`Delete role "${displayRole(role.name)}"? Users still holding it will lose its permissions.`)) return
  try {
    await adminService.deleteRole(role.id)
    flash('Role deleted.')
    await loadRoles()
  } catch (e) {
    flash('Delete failed: ' + (e?.response?.data?.message || e.message), true)
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
    <!-- Breadcrumbs / Header ------------------------------------------ -->
    <div class="rp-breadcrumbs">
      <span>🏠</span>
      <span>›</span>
      <span>Dashboard</span>
      <span>›</span>
      <span class="active">Roles &amp; Permissions</span>
    </div>

    <!-- ── Toast ---------------------------------------------------- -->
    <div v-if="errorMsg" class="rp-toast error">{{ errorMsg }}</div>
    <div v-if="successMsg" class="rp-toast success">{{ successMsg }}</div>

    <!-- ── Roles list panel ------------------------------------------ -->
    <div class="rp-card">
      <div class="rp-card-header">
        <div class="rp-card-title">Roles &amp; Permissions</div>
        <button class="rp-btn rp-btn-pri" @click="showAddRole = true">+ Add Role</button>
      </div>

      <div v-if="loading" class="rp-empty">Loading roles…</div>
      <div v-else-if="roles.length === 0" class="rp-empty">No roles yet — click "+ Add Role" to create one.</div>

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

        <div class="rp-matrix-wrap">
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
              <tr v-for="mod in filteredModules" :key="mod.name">
                <td>{{ permissions.findIndex(p => (p.module?.name || p.module_name) === mod.name) + 1 }}</td>
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
                    <summary>›</summary>
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
          <button class="rp-btn rp-btn-pri" :disabled="saving" @click="savePermissions">
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

.rp-toast {
  position: fixed; top: 18px; right: 18px; z-index: 9999;
  padding: 10px 14px; border-radius: 8px; font-size: 13px; font-weight: 600;
  box-shadow: 0 4px 14px rgba(0,0,0,.08);
}
.rp-toast.error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
.rp-toast.success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }

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
.rp-btn-pri:hover:not(:disabled)    { background: #1d4ed8; }
.rp-btn-pri:disabled  { background: #94a3b8; cursor: not-allowed; }
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

details summary { cursor: pointer; color: #64748b; font-size: 18px; line-height: 1; user-select: none; }
details[open] summary { color: #2563eb; transform: rotate(90deg); }
</style>
