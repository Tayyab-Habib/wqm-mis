<script setup>
import { ref, computed, onMounted } from 'vue'
import { userService } from '../../../services/userService.js'
import { api } from '../../../services/api.js'

const loading   = ref(false)
const errorMsg  = ref('')
const users     = ref([])
const activeTab = ref(0)

const searchText = ref('')
const roleFilter = ref('')

function mapUser(u) {
  return {
    id: u.id,
    name: u.name || '—',
    designation: u.designation?.name || u.designation || '—',
    role: u.roles?.[0]?.name || '—',
    lab: u.laboratory?.name || u.laboratoryUser?.name || 'All Labs',
    params: '—',
    status: u.is_active ? 'Active' : 'Inactive',
    email: u.email,
  }
}

async function loadUsers() {
  loading.value = true
  errorMsg.value = ''
  try {
    const res = await userService.getAll()
    const data = res.data?.data || res.data || []
    users.value = data.map(mapUser)
  } catch (e) {
    errorMsg.value = 'Failed to load users'
    console.error('Users load error:', e)
  } finally {
    loading.value = false
  }
}

const filtered = computed(() => users.value.filter(u => {
  const matchSearch = !searchText.value || u.name.toLowerCase().includes(searchText.value.toLowerCase()) || u.lab.toLowerCase().includes(searchText.value.toLowerCase())
  const matchRole   = !roleFilter.value || u.role === roleFilter.value
  return matchSearch && matchRole
}))

function roleClass(role) {
  if (role === 'system-administrator') return 'r-navy'
  if (role === 'lab-incharge')         return 'r-blue'
  if (role?.includes('analyst'))       return 'r-grey'
  if (role === 'xen')                  return 'r-amber'
  return 'r-grey'
}

// ── Activity trail modal ──────────────────────────────────────────────
const showTrailModal = ref(false)
const trailUser      = ref(null)
const activityLogs   = ref([])
const trailLoading   = ref(false)

async function openTrail(user) {
  trailUser.value = user
  activityLogs.value = []
  showTrailModal.value = true
  trailLoading.value = true
  try {
    const res = await api.get(`/acitivity-logs?user_id=${user.id}`)
    activityLogs.value = res.data || []
  } catch (e) {
    console.error('Activity log error:', e)
  } finally {
    trailLoading.value = false
  }
}

function actionClass(type) {
  return type === 'Create' ? 'r-green' : type === 'Edit' ? 'r-amber' : type === 'Delete' ? 'r-red' : 'r-grey'
}

onMounted(loadUsers)
</script>

<template>
  <div>
    <div class="tabs">
      <div class="tab active">👥 User List</div>
      <div class="tab">📋 HR List</div>
    </div>

    <div class="toolbar">
      <input type="text" v-model="searchText" placeholder="🔍 Name, role, lab…">
      <select v-model="roleFilter" style="border:1px solid var(--input-border);border-radius:4px;padding:6px 8px;font-size:12px;font-family:inherit">
        <option value="">All Roles</option>
        <option>Super Admin</option><option>Lab In-charge</option><option>Analyst</option><option>XEN</option><option>SE</option><option>Client</option>
      </select>
      <div class="tsp"></div>
      <button class="btn btn-sec btn-sm">⬇ Export</button>
    </div>

    <div class="tbl-wrap">
      <table>
        <thead>
          <tr><th>User ID</th><th>Name</th><th>Designation</th><th>Role</th><th>Lab / Jurisdiction</th><th>Parameter Groups</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <tr v-for="(u, i) in filtered" :key="u.id" :class="i%2===1?'alt':''">
            <td class="mono">{{ u.id }}</td>
            <td><b>{{ u.name }}</b></td>
            <td>{{ u.designation }}</td>
            <td><span class="rag" :class="roleClass(u.role)">{{ u.role }}</span></td>
            <td>{{ u.lab }}</td>
            <td>{{ u.params }}</td>
            <td><span class="rag r-green">{{ u.status }}</span></td>
            <td>
              <button class="btn btn-sec btn-xs" @click="openTrail(u)">🕵 Activity Trail</button>
              <button class="btn btn-sec btn-xs" style="margin-left:4px">✏ Edit</button>
            </td>
          </tr>
        </tbody>
      </table>
      <div class="tbl-footer">
        <span>Showing {{ filtered.length }} of {{ users.length }} users</span>
        <div class="tsp"></div>
        <button class="btn btn-pri btn-sm">+ Create User</button>
      </div>
    </div>

    <!-- ── ACTIVITY TRAIL MODAL ── -->
    <Teleport to="body">
      <div v-if="showTrailModal" @click.self="showTrailModal = false"
           style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:3800;align-items:flex-start;justify-content:center;overflow-y:auto;padding:30px">
        <div style="background:#fff;border-radius:10px;width:100%;max-width:820px;margin:auto;box-shadow:0 8px 40px rgba(0,0,0,.28);overflow:hidden">
          <div style="background:var(--navy);color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between">
            <div>
              <div style="font-size:14px;font-weight:700">🕵 Activity Trail — {{ trailUser?.name }}</div>
              <div style="font-size:11px;opacity:.7;margin-top:2px">{{ trailUser?.role }} · {{ trailUser?.lab }}</div>
            </div>
            <button @click="showTrailModal = false" style="background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:5px;padding:5px 11px;cursor:pointer;font-size:13px">✕ Close</button>
          </div>
          <div style="overflow-x:auto;max-height:400px;overflow-y:auto">
            <table style="font-size:11.5px;width:100%">
              <thead style="position:sticky;top:0;z-index:1">
                <tr style="background:var(--navy);color:#fff">
                  <th style="padding:8px 10px">Date &amp; Time</th>
                  <th>Module</th><th>Action</th><th>Record / Target</th><th>IP Address</th><th>Details</th>
                </tr>
              </thead>
              <tbody>
                <template v-if="activityLogs?.length">
                  <tr v-for="(r, i) in activityLogs" :key="i" :class="i%2===1?'alt':''">
                    <td class="mono" style="font-size:10.5px;white-space:nowrap;padding:7px 10px">{{ r.created_at || r.dt }}</td>
                    <td style="font-size:11px;font-weight:600;color:var(--navy2)">{{ r.log_name || r.module }}</td>
                    <td><span class="rag" :class="actionClass(r.event || r.type)" style="font-size:10px">{{ r.event || r.type }}</span></td>
                    <td class="mono" style="font-size:10.5px">{{ r.subject_type || r.record }}</td>
                    <td style="font-size:10.5px;color:var(--muted)">{{ r.properties?.ip || r.ip || '—' }}</td>
                    <td style="font-size:11px">{{ r.description || r.detail }}</td>
                  </tr>
                </template>
                <tr v-else>
                  <td colspan="6" style="text-align:center;padding:20px;color:var(--muted)">No activity recorded yet.</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div style="padding:10px 20px;font-size:10.5px;color:var(--muted);border-top:1px solid var(--border);background:#fafbfc">
            Audit logs are retained for 24 months. Showing last 30 days by default.
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
