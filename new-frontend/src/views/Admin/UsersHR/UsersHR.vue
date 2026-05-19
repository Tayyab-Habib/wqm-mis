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

// ── Stepper state ─────────────────────────────────────────────────────
const currentStep = ref(1)
const steps = [
  { n: 1, icon: '👤', title: 'Personal Info',     subtitle: 'Basic identity & contact' },
  { n: 2, icon: '💼', title: 'Employment',        subtitle: 'Career, status & joining details' },
  { n: 3, icon: '🗺️', title: 'Role & Posting',    subtitle: 'Designation, role, location' },
  { n: 4, icon: '🔒', title: 'Account Security',  subtitle: 'Set login password' },
]
const stepFields = {
  1: ['name','email','phone','gender','date_of_birth'],
  2: ['employee_status','date_of_joining','basic_pay_scale','career_background','educational_background'],
  3: ['division_id','district_id','designation_id','role','laboratory_id','present_duty'],
  4: ['password','password_confirmation'],
}
function stepHasErrors(stepN) {
  return stepFields[stepN].some(f => createErrors.value[f])
}
function goToStep(n) {
  if (n >= 1 && n <= steps.length) currentStep.value = n
}
function nextStep() { if (currentStep.value < steps.length) currentStep.value++ }
function prevStep() { if (currentStep.value > 1) currentStep.value-- }

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
  currentStep.value  = 1
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
    } catch (e) { console.error('Create user dropdown error:', e) }
  }
}

async function openEditModal(user) {
  createErrors.value  = {}
  editMode.value      = true
  editUserId.value    = user.id
  currentStep.value   = 1
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
    // Jump to the earliest step that has a validation error so the user can see it
    for (const s of steps) {
      if (stepHasErrors(s.n)) { currentStep.value = s.n; break }
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

    <!-- ── CREATE USER MODAL (step-by-step wizard) ── -->
    <Teleport to="body">
      <div v-if="showCreateModal" @click.self="showCreateModal = false" class="cu-overlay">
        <div class="cu-modal">

          <!-- Header -->
          <div class="cu-header">
            <div>
              <div class="cu-title">{{ editMode ? '✏ Edit User' : '👤 Create New User' }}</div>
              <div class="cu-subtitle">{{ editMode ? 'Update user details across the steps — password is optional.' : 'Complete each step to add a new user. Fields marked * are required.' }}</div>
            </div>
            <button class="cu-close" @click="showCreateModal = false" aria-label="Close">✕</button>
          </div>

          <!-- Stepper -->
          <div class="cu-stepper">
            <div
              v-for="s in steps" :key="s.n"
              class="cu-step"
              :class="{
                'is-active': currentStep === s.n,
                'is-done':   currentStep > s.n,
                'has-error': stepHasErrors(s.n) && currentStep !== s.n,
              }"
              @click="goToStep(s.n)"
            >
              <div class="cu-step-circle">
                <span v-if="currentStep > s.n && !stepHasErrors(s.n)">✓</span>
                <span v-else-if="stepHasErrors(s.n) && currentStep !== s.n">!</span>
                <span v-else>{{ s.n }}</span>
              </div>
              <div class="cu-step-label">
                <div class="cu-step-title">{{ s.icon }} {{ s.title }}</div>
                <div class="cu-step-sub">{{ s.subtitle }}</div>
              </div>
              <div v-if="s.n < steps.length" class="cu-step-bar" :class="{ 'is-filled': currentStep > s.n }"></div>
            </div>
          </div>

          <!-- Body -->
          <div class="cu-body">

            <!-- Success -->
            <div v-if="createSuccess" class="cu-alert cu-alert-ok">{{ createSuccess }}</div>
            <!-- General error -->
            <div v-if="createErrors._general" class="cu-alert cu-alert-err">{{ createErrors._general[0] }}</div>

            <!-- ── Step 1: Personal Info ── -->
            <div v-show="currentStep === 1" class="cu-step-content">
              <div class="cu-section-head">
                <div class="cu-section-title">👤 Personal Information</div>
                <div class="cu-section-sub">Who is this user? Add their name, contact details, and a profile photo.</div>
              </div>
              <div class="cu-grid cu-grid-3">
                <div class="cu-field">
                  <label>Full Name <span class="req">*</span></label>
                  <input type="text" v-model="createForm.name" placeholder="e.g. Ahmed Khan" :class="{ 'has-err': createErrors.name }">
                  <span v-if="createErrors.name" class="cu-err">{{ createErrors.name[0] }}</span>
                </div>
                <div class="cu-field">
                  <label>Email <span class="req">*</span></label>
                  <input type="email" v-model="createForm.email" placeholder="user@example.com" :class="{ 'has-err': createErrors.email }">
                  <span v-if="createErrors.email" class="cu-err">{{ createErrors.email[0] }}</span>
                </div>
                <div class="cu-field">
                  <label>Phone <span class="req">*</span></label>
                  <input type="text" v-model="createForm.phone" placeholder="03XX-XXXXXXX" :class="{ 'has-err': createErrors.phone }">
                  <span v-if="createErrors.phone" class="cu-err">{{ createErrors.phone[0] }}</span>
                </div>
              </div>
              <div class="cu-grid cu-grid-3">
                <div class="cu-field">
                  <label>Profile Image</label>
                  <input type="file" accept="image/png,image/jpg,image/bmp" @change="onImageChange" class="cu-file">
                  <span class="cu-hint">PNG, JPG or BMP. Optional.</span>
                </div>
                <div class="cu-field">
                  <label>Gender <span class="req">*</span></label>
                  <select v-model="createForm.gender" :class="{ 'has-err': createErrors.gender }">
                    <option value="">Select Gender</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                    <option value="other">Other</option>
                  </select>
                  <span v-if="createErrors.gender" class="cu-err">{{ createErrors.gender[0] }}</span>
                </div>
                <div class="cu-field">
                  <label>Date of Birth <span class="req">*</span></label>
                  <input type="date" v-model="createForm.date_of_birth" :class="{ 'has-err': createErrors.date_of_birth }">
                  <span v-if="createErrors.date_of_birth" class="cu-err">{{ createErrors.date_of_birth[0] }}</span>
                </div>
              </div>
            </div>

            <!-- ── Step 2: Employment ── -->
            <div v-show="currentStep === 2" class="cu-step-content">
              <div class="cu-section-head">
                <div class="cu-section-title">💼 Employment Details</div>
                <div class="cu-section-sub">Employment status, joining date, pay scale, and background notes.</div>
              </div>
              <div class="cu-grid cu-grid-3">
                <div class="cu-field">
                  <label>Employee Status <span class="req">*</span></label>
                  <select v-model="createForm.employee_status" :class="{ 'has-err': createErrors.employee_status }">
                    <option value="">Select Status</option>
                    <option value="permanent">Permanent</option>
                    <option value="contractual">Contractual</option>
                    <option value="other">Other</option>
                  </select>
                  <span v-if="createErrors.employee_status" class="cu-err">{{ createErrors.employee_status[0] }}</span>
                </div>
                <div class="cu-field">
                  <label>Date of Joining <span class="req">*</span></label>
                  <input type="date" v-model="createForm.date_of_joining" :class="{ 'has-err': createErrors.date_of_joining }">
                  <span v-if="createErrors.date_of_joining" class="cu-err">{{ createErrors.date_of_joining[0] }}</span>
                </div>
                <div class="cu-field">
                  <label>Basic Pay Scale <span class="req">*</span></label>
                  <input type="number" v-model="createForm.basic_pay_scale" min="0" placeholder="e.g. 17" :class="{ 'has-err': createErrors.basic_pay_scale }">
                  <span v-if="createErrors.basic_pay_scale" class="cu-err">{{ createErrors.basic_pay_scale[0] }}</span>
                </div>
              </div>
              <div class="cu-grid cu-grid-2">
                <div class="cu-field">
                  <label>Career Background <span class="req">*</span></label>
                  <textarea v-model="createForm.career_background" rows="4" placeholder="Brief summary of prior roles, experience, postings…" :class="{ 'has-err': createErrors.career_background }"></textarea>
                  <span v-if="createErrors.career_background" class="cu-err">{{ createErrors.career_background[0] }}</span>
                </div>
                <div class="cu-field">
                  <label>Educational Background <span class="req">*</span></label>
                  <textarea v-model="createForm.educational_background" rows="4" placeholder="Degrees, certifications, institutes…" :class="{ 'has-err': createErrors.educational_background }"></textarea>
                  <span v-if="createErrors.educational_background" class="cu-err">{{ createErrors.educational_background[0] }}</span>
                </div>
              </div>
            </div>

            <!-- ── Step 3: Role & Posting ── -->
            <div v-show="currentStep === 3" class="cu-step-content">
              <div class="cu-section-head">
                <div class="cu-section-title">🗺️ Role & Posting</div>
                <div class="cu-section-sub">Where is this user posted, and what role/permissions do they get?</div>
              </div>
              <div class="cu-grid cu-grid-2">
                <div class="cu-field">
                  <label>Division <span class="req">*</span></label>
                  <select v-model="createForm.division_id" @change="onCreateDivisionChange" :class="{ 'has-err': createErrors.division_id }">
                    <option value="">Select Division</option>
                    <option v-for="d in allDivisions" :key="d.id" :value="d.id">{{ d.name }}</option>
                  </select>
                  <span v-if="createErrors.division_id" class="cu-err">{{ createErrors.division_id[0] }}</span>
                </div>
                <div class="cu-field">
                  <label>District <span class="req">*</span></label>
                  <select v-model="createForm.district_id" @change="onCreateDistrictChange" :disabled="!createForm.division_id" :class="{ 'has-err': createErrors.district_id }">
                    <option value="">{{ createForm.division_id ? 'Select District' : 'Pick a division first' }}</option>
                    <option v-for="d in formDistricts" :key="d.id" :value="d.id">{{ d.name }}</option>
                  </select>
                  <span v-if="createErrors.district_id" class="cu-err">{{ createErrors.district_id[0] }}</span>
                </div>
              </div>
              <div class="cu-grid cu-grid-2">
                <div class="cu-field">
                  <label>Designation <span class="req">*</span></label>
                  <select v-model="createForm.designation_id" :class="{ 'has-err': createErrors.designation_id }">
                    <option value="">Select Designation</option>
                    <option v-for="d in allDesignations" :key="d.id" :value="d.id">{{ d.name }}</option>
                  </select>
                  <span v-if="createErrors.designation_id" class="cu-err">{{ createErrors.designation_id[0] }}</span>
                </div>
                <div class="cu-field">
                  <label>System Role <span class="req">*</span></label>
                  <select v-model="createForm.role" :class="{ 'has-err': createErrors.role }">
                    <option value="">Select Role</option>
                    <option v-for="r in allRoles" :key="r.id" :value="r.name">{{ formatRoleName(r.name) }}</option>
                  </select>
                  <span v-if="createErrors.role" class="cu-err">{{ createErrors.role[0] }}</span>
                </div>
              </div>
              <div class="cu-grid cu-grid-2">
                <div class="cu-field">
                  <label>Laboratory</label>
                  <select v-model="createForm.laboratory_id" :class="{ 'has-err': createErrors.laboratory_id }">
                    <option value="">Select Laboratory</option>
                    <option v-for="l in allLaboratories" :key="l.id" :value="l.id">{{ l.name }}</option>
                  </select>
                  <span v-if="createErrors.laboratory_id" class="cu-err">{{ createErrors.laboratory_id[0] }}</span>
                  <span v-else class="cu-hint">Required only for lab-scoped roles.</span>
                </div>
                <div class="cu-field">
                  <label>Present Duty</label>
                  <input type="text" v-model="createForm.present_duty" placeholder="e.g. Bacteriology Section" :class="{ 'has-err': createErrors.present_duty }">
                  <span v-if="createErrors.present_duty" class="cu-err">{{ createErrors.present_duty[0] }}</span>
                </div>
              </div>
            </div>

            <!-- ── Step 4: Account Security ── -->
            <div v-show="currentStep === 4" class="cu-step-content">
              <div class="cu-section-head">
                <div class="cu-section-title">🔒 Account Security</div>
                <div class="cu-section-sub">
                  {{ editMode
                    ? 'Leave the password fields blank to keep the existing credentials. Fill them only if you want to reset.'
                    : 'Set a strong login password. Minimum 8 characters with mixed case, a number, and a symbol.' }}
                </div>
              </div>
              <div class="cu-grid cu-grid-2">
                <div class="cu-field">
                  <label>
                    Password
                    <span v-if="!editMode" class="req">*</span>
                    <span v-else class="cu-hint-inline">(blank = keep current)</span>
                  </label>
                  <input type="password" v-model="createForm.password" placeholder="Min 8 chars, e.g. Strong@123" :class="{ 'has-err': createErrors.password }">
                  <span v-if="createErrors.password" class="cu-err">{{ createErrors.password[0] }}</span>
                </div>
                <div class="cu-field">
                  <label>Confirm Password <span v-if="!editMode" class="req">*</span></label>
                  <input type="password" v-model="createForm.password_confirmation" placeholder="Re-enter password" :class="{ 'has-err': createErrors.password_confirmation }">
                  <span v-if="createErrors.password_confirmation" class="cu-err">{{ createErrors.password_confirmation[0] }}</span>
                </div>
              </div>

              <!-- Review summary -->
              <div class="cu-review">
                <div class="cu-review-title">📋 Review Before Submitting</div>
                <div class="cu-review-grid">
                  <div><span>Name</span><b>{{ createForm.name || '—' }}</b></div>
                  <div><span>Email</span><b>{{ createForm.email || '—' }}</b></div>
                  <div><span>Phone</span><b>{{ createForm.phone || '—' }}</b></div>
                  <div><span>Gender</span><b>{{ createForm.gender || '—' }}</b></div>
                  <div><span>Employee Status</span><b>{{ createForm.employee_status || '—' }}</b></div>
                  <div><span>Pay Scale</span><b>{{ createForm.basic_pay_scale || '—' }}</b></div>
                  <div><span>Designation</span><b>{{ allDesignations.find(d => d.id == createForm.designation_id)?.name || '—' }}</b></div>
                  <div><span>Role</span><b>{{ formatRoleName(createForm.role) }}</b></div>
                  <div><span>Division</span><b>{{ allDivisions.find(d => d.id == createForm.division_id)?.name || '—' }}</b></div>
                  <div><span>District</span><b>{{ allDistricts.find(d => d.id == createForm.district_id)?.name || '—' }}</b></div>
                  <div><span>Laboratory</span><b>{{ allLaboratories.find(l => l.id == createForm.laboratory_id)?.name || '—' }}</b></div>
                  <div><span>Joining Date</span><b>{{ createForm.date_of_joining || '—' }}</b></div>
                </div>
              </div>
            </div>

          </div>

          <!-- Footer -->
          <div class="cu-footer">
            <div class="cu-footer-left">
              <span class="cu-step-counter">Step {{ currentStep }} of {{ steps.length }}</span>
            </div>
            <div class="cu-footer-right">
              <button class="btn btn-sec" @click="showCreateModal = false">Cancel</button>
              <button v-if="currentStep > 1" class="btn btn-sec" @click="prevStep">← Back</button>
              <button v-if="currentStep < steps.length" class="btn btn-pri" @click="nextStep">Next →</button>
              <button v-else v-write class="btn btn-pri" @click="submitCreateUser" :disabled="createLoading">
                {{ createLoading ? (editMode ? '⏳ Saving…' : '⏳ Creating…') : (editMode ? '💾 Save Changes' : '✔ Create User') }}
              </button>
            </div>
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

/* ── Create User wizard ────────────────────────────────────────────── */
.cu-overlay {
  position: fixed; inset: 0;
  background: rgba(15, 23, 42, .55);
  z-index: 4000;
  display: flex; align-items: flex-start; justify-content: center;
  overflow-y: auto;
  padding: 24px 12px;
  backdrop-filter: blur(2px);
}
.cu-modal {
  background: #fff;
  border-radius: 12px;
  width: 100%;
  max-width: 960px;
  margin: auto;
  box-shadow: 0 20px 60px rgba(0, 0, 0, .35);
  overflow: hidden;
  display: flex; flex-direction: column;
  max-height: calc(100vh - 48px);
}

/* Header */
.cu-header {
  background: linear-gradient(135deg, var(--navy, #0f2945) 0%, #1e3a5f 100%);
  color: #fff;
  padding: 16px 24px;
  display: flex; align-items: center; justify-content: space-between;
  flex-shrink: 0;
}
.cu-title    { font-size: 15px; font-weight: 700; letter-spacing: .2px; }
.cu-subtitle { font-size: 11.5px; opacity: .72; margin-top: 3px; }
.cu-close {
  background: rgba(255, 255, 255, .15);
  border: none;
  color: #fff;
  border-radius: 6px;
  padding: 6px 12px;
  cursor: pointer;
  font-size: 14px;
  transition: background .15s;
}
.cu-close:hover { background: rgba(255, 255, 255, .28); }

/* Stepper */
.cu-stepper {
  display: flex;
  align-items: stretch;
  padding: 18px 24px 16px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  gap: 0;
  flex-shrink: 0;
}
.cu-step {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  position: relative;
  min-width: 0;
  user-select: none;
}
.cu-step-circle {
  width: 32px; height: 32px;
  border-radius: 50%;
  background: #e2e8f0;
  color: #64748b;
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 700;
  flex-shrink: 0;
  border: 2px solid transparent;
  transition: all .2s;
}
.cu-step.is-active .cu-step-circle {
  background: var(--navy, #0f2945);
  color: #fff;
  border-color: var(--navy, #0f2945);
  box-shadow: 0 0 0 4px rgba(15, 41, 69, .15);
}
.cu-step.is-done .cu-step-circle {
  background: #10b981;
  color: #fff;
}
.cu-step.has-error .cu-step-circle {
  background: #ef4444;
  color: #fff;
}
.cu-step-label   { min-width: 0; flex: 1; }
.cu-step-title   { font-size: 12px; font-weight: 700; color: #334155; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cu-step.is-active .cu-step-title { color: var(--navy, #0f2945); }
.cu-step-sub     { font-size: 10.5px; color: #64748b; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cu-step-bar {
  position: absolute;
  top: 16px; right: -50%;
  width: 100%; height: 2px;
  background: #e2e8f0;
  z-index: 0;
}
.cu-step-bar.is-filled { background: #10b981; }

/* Body */
.cu-body {
  padding: 24px 28px;
  overflow-y: auto;
  flex: 1;
}
.cu-step-content { animation: cuFadeIn .22s ease; }
@keyframes cuFadeIn {
  from { opacity: 0; transform: translateY(4px); }
  to   { opacity: 1; transform: translateY(0); }
}

.cu-section-head { margin-bottom: 18px; padding-bottom: 12px; border-bottom: 1px dashed #e2e8f0; }
.cu-section-title { font-size: 14px; font-weight: 700; color: var(--navy, #0f2945); }
.cu-section-sub   { font-size: 12px; color: #64748b; margin-top: 4px; line-height: 1.5; }

/* Field grid */
.cu-grid { display: grid; gap: 14px 18px; margin-bottom: 14px; }
.cu-grid-2 { grid-template-columns: 1fr 1fr; }
.cu-grid-3 { grid-template-columns: 1fr 1fr 1fr; }

.cu-field { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
.cu-field label {
  font-size: 11.5px;
  font-weight: 600;
  color: #334155;
  letter-spacing: .15px;
}
.cu-field .req { color: #ef4444; font-weight: 700; }
.cu-field input[type="text"],
.cu-field input[type="email"],
.cu-field input[type="password"],
.cu-field input[type="number"],
.cu-field input[type="date"],
.cu-field select,
.cu-field textarea {
  width: 100%;
  padding: 8px 11px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 12.5px;
  font-family: inherit;
  background: #fff;
  color: #0f172a;
  box-sizing: border-box;
  transition: border-color .15s, box-shadow .15s, background .15s;
}
.cu-field textarea { resize: vertical; min-height: 80px; line-height: 1.5; }
.cu-field input:focus,
.cu-field select:focus,
.cu-field textarea:focus {
  outline: none;
  border-color: var(--navy, #0f2945);
  box-shadow: 0 0 0 3px rgba(15, 41, 69, .12);
}
.cu-field input:disabled,
.cu-field select:disabled {
  background: #f1f5f9;
  color: #94a3b8;
  cursor: not-allowed;
}
.cu-field .has-err {
  border-color: #ef4444 !important;
  background: #fef2f2;
}
.cu-field .has-err:focus {
  box-shadow: 0 0 0 3px rgba(239, 68, 68, .12);
}
.cu-file { padding: 6px 8px !important; cursor: pointer; }

.cu-err  { font-size: 11px; color: #dc2626; font-weight: 500; }
.cu-hint { font-size: 10.5px; color: #94a3b8; font-style: italic; }
.cu-hint-inline { font-size: 10.5px; color: #94a3b8; font-weight: 400; margin-left: 4px; }

/* Alerts */
.cu-alert {
  border-radius: 6px;
  padding: 10px 14px;
  margin-bottom: 16px;
  font-size: 12.5px;
  line-height: 1.5;
}
.cu-alert-ok  { background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; }
.cu-alert-err { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }

/* Review summary on step 4 */
.cu-review {
  margin-top: 22px;
  padding: 16px 18px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}
.cu-review-title { font-size: 12px; font-weight: 700; color: var(--navy, #0f2945); margin-bottom: 10px; }
.cu-review-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px 16px;
}
.cu-review-grid > div { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.cu-review-grid span { font-size: 10.5px; color: #64748b; text-transform: uppercase; letter-spacing: .3px; }
.cu-review-grid b    { font-size: 12px; color: #0f172a; font-weight: 600; word-break: break-word; }

/* Footer */
.cu-footer {
  padding: 14px 24px;
  border-top: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  background: #fafbfc;
  flex-shrink: 0;
}
.cu-footer-left  { font-size: 11.5px; color: #64748b; font-weight: 600; }
.cu-footer-right { display: flex; gap: 8px; }
.cu-step-counter {
  background: #e2e8f0;
  padding: 5px 11px;
  border-radius: 99px;
  color: #475569;
  font-size: 11px;
  letter-spacing: .3px;
}

/* Responsive — stack stepper labels on small screens */
@media (max-width: 720px) {
  .cu-modal      { max-height: calc(100vh - 24px); }
  .cu-stepper    { flex-direction: row; padding: 14px 14px 12px; gap: 4px; overflow-x: auto; }
  .cu-step       { flex: 0 0 auto; }
  .cu-step-label { display: none; }
  .cu-step-bar   { display: none; }
  .cu-body       { padding: 18px 16px; }
  .cu-grid-2,
  .cu-grid-3     { grid-template-columns: 1fr; }
  .cu-review-grid { grid-template-columns: 1fr 1fr; }
  .cu-footer     { flex-direction: column-reverse; align-items: stretch; }
  .cu-footer-right { justify-content: flex-end; }
}
</style>
