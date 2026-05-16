<script setup>
import { ref, computed, onMounted } from 'vue'
import { adminService } from '../../../services/adminService.js'

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
    // Backend sometimes returns data:null on empty list — coerce to [].
    const list = r.data?.data
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

async function savePermissions() {
  if (!selectedRole.value) return
  saving.value = true
  try {
    const ids = Array.from(rolePerms.value).map(Number)
    await adminService.syncRolePermissions(selectedRole.value.id, ids)
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
    const created = r.data?.data
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

    <!-- ── Roles list panel ------------------------------------------ -->
    <div class="rp-card">
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

details summary { cursor: pointer; color: #64748b; font-size: 18px; line-height: 1; user-select: none; }
details[open] summary { color: #2563eb; transform: rotate(90deg); }

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
</style>

<style>
/* Global so the toast in <Teleport to="body"> picks it up — matches the
   project pattern used by DiariesDispatches / Topbar / UsersHR. */
.toast-slide-enter-active,
.toast-slide-leave-active { transition: all 0.3s ease; }
.toast-slide-enter-from,
.toast-slide-leave-to { opacity: 0; transform: translateY(-20px); }
</style>
