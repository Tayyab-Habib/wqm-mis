<script setup>
import { ref, computed, onMounted } from 'vue'
import { userService } from '../../../services/userService.js'
import { api } from '../../../services/api.js'
import { exportToXLSX } from '../../../utils/exportHelpers.js'

const loading   = ref(false)
const errorMsg  = ref('')
const users     = ref([])
const roles     = ref([])
const activeTab = ref(0)

const searchText = ref('')
const roleFilter = ref('')

function mapUser(u) {
  return {
    id: u.id,
    name: u.name || '—',
    email: u.email || '—',
    phone: u.phone || '—',
    date_of_birth: u.date_of_birth ? u.date_of_birth.split(' ')[0] : '—',
    basic_pay_scale: u.basic_pay_scale ?? '—',
    designation: u.designation?.name || '—',
    date_of_joining: u.date_of_joining ? u.date_of_joining.split(' ')[0] : '—',
    district: u.district?.name || '—',
    lab: u.laboratory_user?.name || u.laboratoryUser?.name || '—',
    created_by: u.created_by_user?.name || u.createdByUser?.name || '—',
    created_at: u.created_at || '—',
    role: u.roles?.[0]?.name || '—',
    status: u.is_active ? 'Active' : 'Inactive',
    _raw: u,
  }
}

async function loadUsers() {
  loading.value = true
  errorMsg.value = ''
  try {
    const [usersRes, rolesRes] = await Promise.all([
      userService.getAll(),
      api.get('/all-roles'),
    ])
    const data = usersRes.data?.data || usersRes.data || []
    users.value = data.map(mapUser)
    roles.value = rolesRes.data?.data || rolesRes.data || []
  } catch (e) {
    errorMsg.value = 'Failed to load users'
    console.error('Users load error:', e)
  } finally {
    loading.value = false
  }
}

const filtered = computed(() => users.value.filter(u => {
  const matchSearch = !searchText.value ||
    u.name.toLowerCase().includes(searchText.value.toLowerCase()) ||
    u.lab.toLowerCase().includes(searchText.value.toLowerCase()) ||
    u.email.toLowerCase().includes(searchText.value.toLowerCase())
  const matchRole = !roleFilter.value || u.role === roleFilter.value
  return matchSearch && matchRole
}))

function exportUsers() {
  if (!filtered.value.length) { alert('No users to export.'); return }
  const exportData = filtered.value.map(u => ({
    'User ID': u.id, 'Name': u.name, 'Email': u.email,
    'Phone': u.phone, 'Designation': u.designation, 'Role': u.role,
    'Lab / Jurisdiction': u.lab, 'District': u.district,
    'Date of Birth': u.date_of_birth, 'Date of Joining': u.date_of_joining,
    'Basic Pay Scale': u.basic_pay_scale, 'Status': u.status,
    'Created By': u.created_by, 'Created At': u.created_at,
  }))
  exportToXLSX(exportData, 'Users_HR_List')
}

function roleClass(role) {
  if (!role || role === '—') return 'r-grey'
  const r = role.toLowerCase()
  if (r.includes('admin') || r.includes('administrator')) return 'r-navy'
  if (r.includes('incharge') || r.includes('in-charge') || r.includes('lab')) return 'r-blue'
  if (r.includes('analyst'))  return 'r-grey'
  if (r === 'xen')            return 'r-amber'
  if (r === 'se')             return 'r-amber'
  if (r === 'ce')             return 'r-red'
  if (r.includes('client'))   return 'r-grey'
  return 'r-grey'
}

function formatRoleName(name) {
  if (!name) return '—'
  return name.replace(/-/g, ' ').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
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
  } catch (e) { console.error('Activity log error:', e) }
  finally { trailLoading.value = false }
}

function actionClass(type) {
  return type === 'Create' ? 'r-green' : type === 'Edit' ? 'r-amber' : type === 'Delete' ? 'r-red' : 'r-grey'
}

// ── Create User Modal ─────────────────────────────────────────────────
const showCreateModal  = ref(false)
const createLoading    = ref(false)
const createErrors     = ref({})
const createSuccess    = ref('')
const editMode         = ref(false)   // false = create, true = edit
const editUserId       = ref(null)

// ── Toast ─────────────────────────────────────────────────────────────
const toast = ref({ show: false, message: '', type: 'success' })
let toastTimer = null

function showToast(message, type = 'success') {
  clearTimeout(toastTimer)
  toast.value = { show: true, message, type }
  toastTimer = setTimeout(() => { toast.value.show = false }, 4000)
}

// Dropdown data for the form
const allDivisions    = ref([])
const allDistricts    = ref([])
const allDesignations = ref([])
const allLaboratories = ref([])
const allRoles        = ref([])
const formDistricts   = ref([])

function emptyForm() {
  return {
    name: '', email: '', phone: '',
    image: null,
    gender: '', date_of_birth: '', employee_status: '',
    career_background: '', educational_background: '',
    division_id: '', district_id: '', date_of_joining: '',
    basic_pay_scale: '', designation_id: '', role: '',
    present_duty: '', laboratory_id: '',
    password: '', password_confirmation: '',
    // resolved automatically
    province_id: '', region_id: '', circle_id: '',
  }
}

const createForm = ref(emptyForm())

async function openCreateModal() {
  createForm.value   = emptyForm()
  createErrors.value = {}
  createSuccess.value = ''
  editMode.value     = false
  editUserId.value   = null
  showCreateModal.value = true

  if (!allDivisions.value.length) {
    try {
      const [divRes, distRes, desRes, labRes, rolRes] = await Promise.all([
        api.get('/all-divisions'),
        api.get('/all-districts'),
        api.get('/all-designations'),
        api.get('/all-laboratories'),
        api.get('/all-roles'),
      ])
      allDivisions.value    = divRes.data?.data  || divRes.data  || []
      allDistricts.value    = distRes.data?.data || distRes.data || []
      allDesignations.value = desRes.data?.data  || desRes.data  || []
      allLaboratories.value = labRes.data?.data  || labRes.data  || []
      allRoles.value        = rolRes.data?.data  || rolRes.data  || []
      allRegions.value      = regRes.data?.data  || regRes.data  || []
    } catch (e) { console.error('Create user dropdown error:', e) }
  }
}

async function openEditModal(user) {
  createErrors.value  = {}
  editMode.value      = true
  editUserId.value    = user.id
  showCreateModal.value = true

  // Load dropdowns if not yet loaded
  if (!allDivisions.value.length) {
    try {
      const [divRes, distRes, desRes, labRes, rolRes] = await Promise.all([
        api.get('/all-divisions'),
        api.get('/all-districts'),
        api.get('/all-designations'),
        api.get('/all-laboratories'),
        api.get('/all-roles'),
      ])
      allDivisions.value    = divRes.data?.data  || divRes.data  || []
      allDistricts.value    = distRes.data?.data || distRes.data || []
      allDesignations.value = desRes.data?.data  || desRes.data  || []
      allLaboratories.value = labRes.data?.data  || labRes.data  || []
      allRoles.value        = rolRes.data?.data  || rolRes.data  || []
    } catch (e) { console.error('Edit user dropdown error:', e) }
  }

  // Fetch full user detail from backend
  try {
    const res = await userService.getById(user.id)
    const u   = res.data?.data || res.data

    // Pre-populate district cascade
    const divId = u.district?.division?.id || u.division_id || ''
    formDistricts.value = allDistricts.value.filter(d => d.division_id == divId)

    createForm.value = {
      name:                   u.name                   || '',
      email:                  u.email                  || '',
      phone:                  u.phone                  || '',
      image:                  null,
      gender:                 u.gender                 || '',
      date_of_birth:          u.date_of_birth          ? u.date_of_birth.split(' ')[0] : '',
      employee_status:        u.employee_status        || '',
      career_background:      u.career_background      || '',
      educational_background: u.educational_background || '',
      division_id:            divId,
      district_id:            u.district?.id           || u.district_id || '',
      date_of_joining:        u.date_of_joining        ? u.date_of_joining.split(' ')[0] : '',
      basic_pay_scale:        u.basic_pay_scale        ?? '',
      designation_id:         u.designation?.id        || u.designation_id || '',
      role:                   u.roles?.[0]?.name       || '',
      present_duty:           u.laboratory_details?.[0]?.present_duty || u.laboratoryDetails?.[0]?.present_duty || '',
      laboratory_id:          u.laboratory_details?.[0]?.laboratory?.id || u.laboratoryDetails?.[0]?.laboratory?.id || '',
      password:               '',
      password_confirmation:  '',
      province_id:            u.district?.division?.province_id || '',
      region_id:              u.district?.division?.region_id   || '',
      circle_id:              u.district?.circle_id             || u.circle_id || '',
    }
  } catch (e) {
    console.error('Fetch user detail error:', e)
    showToast('Failed to load user details', 'error')
    showCreateModal.value = false
  }
}

function onCreateDivisionChange() {
  createForm.value.district_id = ''
  createForm.value.province_id = ''
  createForm.value.region_id   = ''
  createForm.value.circle_id   = ''
  formDistricts.value = allDistricts.value.filter(d => d.division_id == createForm.value.division_id)
}

function onCreateDistrictChange() {
  const district = allDistricts.value.find(d => d.id == createForm.value.district_id)
  if (!district) return

  // circle_id comes from the district directly
  createForm.value.circle_id = district.circle_id || ''

  // province_id and region_id both come from the division
  const division = allDivisions.value.find(d => d.id == district.division_id)
  createForm.value.region_id   = division?.region_id   || ''
  createForm.value.province_id = division?.province_id || ''
}

function onImageChange(e) {
  createForm.value.image = e.target.files[0] || null
}

async function submitCreateUser() {
  createErrors.value  = {}
  createLoading.value = true

  try {
    const fd = new FormData()
    const fields = [
      'name','email','phone','gender','date_of_birth','employee_status',
      'career_background','educational_background','division_id','district_id',
      'province_id','region_id','circle_id','date_of_joining','basic_pay_scale',
      'designation_id','role','present_duty','laboratory_id',
      'password','password_confirmation',
    ]
    fields.forEach(f => {
      if (createForm.value[f] !== '' && createForm.value[f] !== null && createForm.value[f] !== undefined) {
        fd.append(f, createForm.value[f])
      }
    })
    if (createForm.value.image) fd.append('image', createForm.value.image)

    let res
    if (editMode.value) {
      // Laravel doesn't support FormData with PUT — use POST + _method spoofing
      fd.append('_method', 'PUT')
      res = await api.post(`/users/${editUserId.value}`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    } else {
      res = await api.post('/users', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    }

    const userName = res.data?.data?.name || createForm.value.name
    showCreateModal.value = false
    showToast(editMode.value
      ? `✅ User "${userName}" updated successfully!`
      : `✅ User "${userName}" created successfully!`
    )
    await loadUsers()
  } catch (e) {
    createErrors.value = e.response?.data?.errors || {}
    if (!Object.keys(createErrors.value).length) {
      createErrors.value._general = [e.response?.data?.message || (editMode.value ? 'Failed to update user' : 'Failed to create user')]
    }
    console.error('User save error:', e.response?.data || e)
    console.table(e.response?.data?.errors || {})
  } finally {
    createLoading.value = false
  }
}

onMounted(loadUsers)
</script>

<template>
  <div>
    <!-- ── Toast notification ── -->
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

    <div class="tabs">
      <div class="tab" :class="{ active: activeTab === 0 }" @click="activeTab = 0">👥 User List</div>
      <div class="tab" :class="{ active: activeTab === 1 }" @click="activeTab = 1">📋 HR List</div>
    </div>

    <div v-if="activeTab === 0">
    <div class="toolbar">
      <input type="text" v-model="searchText" placeholder="🔍 Name, role, lab…">
      <select v-model="roleFilter" style="border:1px solid var(--input-border);border-radius:4px;padding:6px 8px;font-size:12px;font-family:inherit">
        <option value="">All Roles</option>
        <option v-for="role in roles" :key="role.id" :value="role.name">{{ formatRoleName(role.name) }}</option>
      </select>
      <div class="tsp"></div>
      <button class="btn btn-sec btn-sm" @click="exportUsers">⬇ Export</button>
      <button v-write class="btn btn-pri btn-sm" style="margin-left:6px" @click="openCreateModal">+ Create User</button>
    </div>

    <div class="tbl-wrap">
      <table>
        <thead>
          <tr><th>User ID</th><th>Name</th><th>Designation</th><th>Role</th><th>Lab / Jurisdiction</th><th>District</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <tr v-for="(u, i) in filtered" :key="u.id" :class="i%2===1?'alt':''">
            <td class="mono">{{ u.id }}</td>
            <td><b>{{ u.name }}</b></td>
            <td>{{ u.designation }}</td>
            <td><span class="rag" :class="roleClass(u.role)">{{ u.role }}</span></td>
            <td>{{ u.lab }}</td>
            <td>{{ u.district }}</td>
            <td><span class="rag" :class="u.status === 'Active' ? 'r-green' : 'r-red'">{{ u.status }}</span></td>
            <td>
              <button class="btn btn-sec btn-xs" @click="openTrail(u)">🕵 Activity Trail</button>
              <button v-write class="btn btn-sec btn-xs" style="margin-left:4px" @click="openEditModal(u)">✏ Edit</button>
            </td>
          </tr>
        </tbody>
      </table>
      <div class="tbl-footer">
        <span>Showing {{ filtered.length }} of {{ users.length }} users</span>
      </div>
    </div>
    </div>

    <!-- ── HR LIST TAB ── -->
    <div v-if="activeTab === 1">
      <div class="toolbar">
        <input type="text" v-model="searchText" placeholder="🔍 Name, email, phone…">
        <select v-model="roleFilter" style="border:1px solid var(--input-border);border-radius:4px;padding:6px 8px;font-size:12px;font-family:inherit">
          <option value="">All Roles</option>
          <option v-for="role in roles" :key="role.id" :value="role.name">{{ formatRoleName(role.name) }}</option>
        </select>
        <div class="tsp"></div>
        <button class="btn btn-sec btn-sm" @click="exportUsers">⬇ Export</button>
      </div>

      <div class="tbl-wrap">
        <div v-if="loading" style="text-align:center;padding:32px;color:var(--muted);font-size:13px">⏳ Loading…</div>
        <table v-else style="font-size:11.5px">
          <thead>
            <tr>
              <th>S#</th>
              <th>Name</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Date of Birth</th>
              <th>Basic Pay Scale</th>
              <th>Designation</th>
              <th>Date of Joining</th>
              <th>District</th>
              <th>Laboratory</th>
              <th>Created By</th>
              <th>Created At</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!filtered.length">
              <td colspan="12" style="text-align:center;padding:28px;color:var(--muted)">No records found.</td>
            </tr>
            <tr v-for="(u, i) in filtered" :key="u.id" :class="i%2===1?'alt':''">
              <td class="mono" style="color:var(--muted)">{{ i + 1 }}</td>
              <td><b>{{ u.name }}</b></td>
              <td style="color:var(--blue)">{{ u.email }}</td>
              <td class="mono">{{ u.phone }}</td>
              <td>{{ u.date_of_birth }}</td>
              <td class="mono" style="text-align:center">{{ u.basic_pay_scale }}</td>
              <td>{{ u.designation }}</td>
              <td>{{ u.date_of_joining }}</td>
              <td>{{ u.district }}</td>
              <td>{{ u.lab }}</td>
              <td style="color:var(--muted);font-size:11px">{{ u.created_by }}</td>
              <td style="color:var(--muted);font-size:11px">{{ u.created_at }}</td>
            </tr>
          </tbody>
        </table>
        <div class="tbl-footer">
          <span>Showing {{ filtered.length }} of {{ users.length }} records</span>
        </div>
      </div>
    </div>

    <!-- ── ACTIVITY TRAIL MODAL ── -->
    <Teleport to="body">      <div v-if="showTrailModal" @click.self="showTrailModal = false"
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

    <!-- ── CREATE USER MODAL ── -->
    <Teleport to="body">
      <div v-if="showCreateModal" @click.self="showCreateModal = false"
           style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:4000;align-items:flex-start;justify-content:center;overflow-y:auto;padding:24px 12px">
        <div style="background:#fff;border-radius:10px;width:100%;max-width:900px;margin:auto;box-shadow:0 8px 48px rgba(0,0,0,.3);overflow:hidden">

          <!-- Header -->
          <div style="background:var(--navy);color:#fff;padding:14px 22px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:1">
            <div>
              <div style="font-size:14px;font-weight:700">{{ editMode ? '✏ Edit User' : '👤 Create New User' }}</div>
              <div style="font-size:11px;opacity:.65;margin-top:2px">{{ editMode ? 'Update user details below — password is optional' : 'Fill in all required fields — starred fields are mandatory' }}</div>
            </div>
            <button @click="showCreateModal = false" style="background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:5px;padding:5px 14px;cursor:pointer;font-size:14px">✕</button>
          </div>

          <div style="padding:22px 26px">

            <!-- Success -->
            <div v-if="createSuccess" style="background:#d1fae5;border:1px solid #6ee7b7;border-radius:5px;padding:10px 14px;margin-bottom:14px;font-size:12.5px;color:#065f46">
              {{ createSuccess }}
            </div>
            <!-- General error -->
            <div v-if="createErrors._general" style="background:#fee2e2;border:1px solid #fca5a5;border-radius:5px;padding:10px 14px;margin-bottom:14px;font-size:12.5px;color:#991b1b">
              {{ createErrors._general[0] }}
            </div>
            <!-- All validation errors summary -->
            <div v-if="Object.keys(createErrors).filter(k => k !== '_general').length"
                 style="background:#fff3cd;border:1px solid #f4c236;border-radius:5px;padding:10px 14px;margin-bottom:14px;font-size:12px;color:#7a4f00">
              <div style="font-weight:700;margin-bottom:6px">⚠ Please fix the following errors:</div>
              <ul style="margin:0;padding-left:18px">
                <li v-for="(msgs, field) in createErrors" :key="field" v-if="field !== '_general'">
                  <b>{{ field.replace(/_/g,' ') }}:</b> {{ Array.isArray(msgs) ? msgs[0] : msgs }}
                </li>
              </ul>
            </div>

            <!-- ── Row 1: Name / Email / Phone ── -->
            <div class="form-grid" style="grid-template-columns:1fr 1fr 1fr;margin-bottom:14px">
              <div class="fg2">
                <label>Name <span style="color:var(--red)">*</span></label>
                <input type="text" v-model="createForm.name" placeholder="Enter Name" :style="createErrors.name?'border-color:var(--red)':''">
                <span v-if="createErrors.name" style="font-size:11px;color:var(--red)">{{ createErrors.name[0] }}</span>
              </div>
              <div class="fg2">
                <label>Email <span style="color:var(--red)">*</span></label>
                <input type="email" v-model="createForm.email" placeholder="Enter Email" :style="createErrors.email?'border-color:var(--red)':''">
                <span v-if="createErrors.email" style="font-size:11px;color:var(--red)">{{ createErrors.email[0] }}</span>
              </div>
              <div class="fg2">
                <label>Phone <span style="color:var(--red)">*</span></label>
                <input type="text" v-model="createForm.phone" placeholder="Enter Phone (10-11 digits)" :style="createErrors.phone?'border-color:var(--red)':''">
                <span v-if="createErrors.phone" style="font-size:11px;color:var(--red)">{{ createErrors.phone[0] }}</span>
              </div>
            </div>

            <!-- ── Row 2: Image / Gender / DOB / Employee Status ── -->
            <div class="form-grid" style="grid-template-columns:1fr 1fr 1fr 1fr;margin-bottom:14px">
              <div class="fg2">
                <label>Image</label>
                <input type="file" accept="image/png,image/jpg,image/bmp" @change="onImageChange" style="font-size:12px;padding:5px 8px;border:1px solid var(--border);border-radius:4px;width:100%;box-sizing:border-box">
              </div>
              <div class="fg2">
                <label>Gender <span style="color:var(--red)">*</span></label>
                <select v-model="createForm.gender" :style="createErrors.gender?'border-color:var(--red)':''">
                  <option value="">Select Gender</option>
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                  <option value="other">Other</option>
                </select>
                <span v-if="createErrors.gender" style="font-size:11px;color:var(--red)">{{ createErrors.gender[0] }}</span>
              </div>
              <div class="fg2">
                <label>Date of Birth <span style="color:var(--red)">*</span></label>
                <input type="date" v-model="createForm.date_of_birth" :style="createErrors.date_of_birth?'border-color:var(--red)':''">
                <span v-if="createErrors.date_of_birth" style="font-size:11px;color:var(--red)">{{ createErrors.date_of_birth[0] }}</span>
              </div>
              <div class="fg2">
                <label>Employee Status <span style="color:var(--red)">*</span></label>
                <select v-model="createForm.employee_status" :style="createErrors.employee_status?'border-color:var(--red)':''">
                  <option value="">Select Employee Status</option>
                  <option value="permanent">Permanent</option>
                  <option value="contractual">Contractual</option>
                  <option value="other">Other</option>
                </select>
                <span v-if="createErrors.employee_status" style="font-size:11px;color:var(--red)">{{ createErrors.employee_status[0] }}</span>
              </div>
            </div>

            <!-- ── Row 3: Career / Educational Background ── -->
            <div class="form-grid" style="grid-template-columns:1fr 1fr;margin-bottom:14px">
              <div class="fg2">
                <label>Career Background <span style="color:var(--red)">*</span></label>
                <textarea v-model="createForm.career_background" rows="3" placeholder="Career Background" :style="createErrors.career_background?'border-color:var(--red)':''"></textarea>
                <span v-if="createErrors.career_background" style="font-size:11px;color:var(--red)">{{ createErrors.career_background[0] }}</span>
              </div>
              <div class="fg2">
                <label>Educational Background <span style="color:var(--red)">*</span></label>
                <textarea v-model="createForm.educational_background" rows="3" placeholder="Educational Background" :style="createErrors.educational_background?'border-color:var(--red)':''"></textarea>
                <span v-if="createErrors.educational_background" style="font-size:11px;color:var(--red)">{{ createErrors.educational_background[0] }}</span>
              </div>
            </div>

            <!-- ── Row 4: Division / District / Date of Joining ── -->
            <div class="form-grid" style="grid-template-columns:1fr 1fr 1fr;margin-bottom:14px">
              <div class="fg2">
                <label>Division <span style="color:var(--red)">*</span></label>
                <select v-model="createForm.division_id" @change="onCreateDivisionChange" :style="createErrors.division_id?'border-color:var(--red)':''">
                  <option value="">Select Division</option>
                  <option v-for="d in allDivisions" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
                <span v-if="createErrors.division_id" style="font-size:11px;color:var(--red)">{{ createErrors.division_id[0] }}</span>
              </div>
              <div class="fg2">
                <label>District <span style="color:var(--red)">*</span></label>
                <select v-model="createForm.district_id" @change="onCreateDistrictChange" :disabled="!createForm.division_id" :style="createErrors.district_id?'border-color:var(--red)':''">
                  <option value="">Select District</option>
                  <option v-for="d in formDistricts" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
                <span v-if="createErrors.district_id" style="font-size:11px;color:var(--red)">{{ createErrors.district_id[0] }}</span>
              </div>
              <div class="fg2">
                <label>Date of Joining <span style="color:var(--red)">*</span></label>
                <input type="date" v-model="createForm.date_of_joining" :style="createErrors.date_of_joining?'border-color:var(--red)':''">
                <span v-if="createErrors.date_of_joining" style="font-size:11px;color:var(--red)">{{ createErrors.date_of_joining[0] }}</span>
              </div>
            </div>

            <!-- ── Row 5: Basic Pay Scale / Designation / Role ── -->
            <div class="form-grid" style="grid-template-columns:1fr 1fr 1fr;margin-bottom:14px">
              <div class="fg2">
                <label>Basic Pay Scale <span style="color:var(--red)">*</span></label>
                <input type="number" v-model="createForm.basic_pay_scale" min="0" placeholder="Select Basic Pay Scale" :style="createErrors.basic_pay_scale?'border-color:var(--red)':''">
                <span v-if="createErrors.basic_pay_scale" style="font-size:11px;color:var(--red)">{{ createErrors.basic_pay_scale[0] }}</span>
              </div>
              <div class="fg2">
                <label>Designation <span style="color:var(--red)">*</span></label>
                <select v-model="createForm.designation_id" :style="createErrors.designation_id?'border-color:var(--red)':''">
                  <option value="">Select Designation</option>
                  <option v-for="d in allDesignations" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
                <span v-if="createErrors.designation_id" style="font-size:11px;color:var(--red)">{{ createErrors.designation_id[0] }}</span>
              </div>
              <div class="fg2">
                <label>Role <span style="color:var(--red)">*</span></label>
                <select v-model="createForm.role" :style="createErrors.role?'border-color:var(--red)':''">
                  <option value="">Select Role</option>
                  <option v-for="r in allRoles" :key="r.id" :value="r.name">{{ formatRoleName(r.name) }}</option>
                </select>
                <span v-if="createErrors.role" style="font-size:11px;color:var(--red)">{{ createErrors.role[0] }}</span>
              </div>
            </div>

            <!-- ── Row 6: Present Duty / Laboratory ── -->
            <div class="form-grid" style="grid-template-columns:1fr 1fr;margin-bottom:14px">
              <div class="fg2">
                <label>Present Duty</label>
                <input type="text" v-model="createForm.present_duty" placeholder="Enter Present Duty" :style="createErrors.present_duty?'border-color:var(--red)':''">
                <span v-if="createErrors.present_duty" style="font-size:11px;color:var(--red)">{{ createErrors.present_duty[0] }}</span>
              </div>
              <div class="fg2">
                <label>Laboratory</label>
                <select v-model="createForm.laboratory_id" :style="createErrors.laboratory_id?'border-color:var(--red)':''">
                  <option value="">Select Laboratory</option>
                  <option v-for="l in allLaboratories" :key="l.id" :value="l.id">{{ l.name }}</option>
                </select>
                <span v-if="createErrors.laboratory_id" style="font-size:11px;color:var(--red)">{{ createErrors.laboratory_id[0] }}</span>
              </div>
            </div>

            <!-- ── Row 7: Password / Confirm Password ── -->
            <div class="form-grid" style="grid-template-columns:1fr 1fr;margin-bottom:6px">
              <div class="fg2">
                <label>Password <span v-if="!editMode" style="color:var(--red)">*</span><span v-else style="font-size:10px;color:var(--muted)"> (leave blank to keep current)</span></label>
                <input type="password" v-model="createForm.password" placeholder="Enter Password (min 8 chars, mixed case + symbol)" :style="createErrors.password?'border-color:var(--red)':''">
                <span v-if="createErrors.password" style="font-size:11px;color:var(--red)">{{ createErrors.password[0] }}</span>
              </div>
              <div class="fg2">
                <label>Confirm Password <span v-if="!editMode" style="color:var(--red)">*</span></label>
                <input type="password" v-model="createForm.password_confirmation" placeholder="Enter Confirmed Password" :style="createErrors.password_confirmation?'border-color:var(--red)':''">
                <span v-if="createErrors.password_confirmation" style="font-size:11px;color:var(--red)">{{ createErrors.password_confirmation[0] }}</span>
              </div>
            </div>

          </div>

          <!-- Footer -->
          <div style="padding:14px 26px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px;background:#fafbfc">
            <button class="btn btn-sec" @click="showCreateModal = false">↩ Back</button>
            <button v-write class="btn btn-pri" @click="submitCreateUser" :disabled="createLoading">
              {{ createLoading ? (editMode ? '⏳ Saving…' : '⏳ Creating…') : (editMode ? '💾 Save Changes' : '✔ Create') }}
            </button>
          </div>

        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.toast-slide-enter-active,
.toast-slide-leave-active {
  transition: all 0.3s ease;
}
.toast-slide-enter-from,
.toast-slide-leave-to {
  opacity: 0;
  transform: translateX(60px);
}
</style>
