<script setup>
import { ref, computed, onMounted } from 'vue'
import { qualityService } from '../../../services/qualityService.js'
import { kpiFrameworkService } from '../../../services/kpiFrameworkService.js'
import { useUserStore } from '../../../stores/useUserStore.js'
import ConfirmModal from '../../../components/common/ConfirmModal/ConfirmModal.vue'

const userStore = useUserStore()

// Toast (same pattern as XenSettings / UsersHR)
const toast = ref({ show: false, message: '', type: 'success' })
let toastTimer = null
function showToast(message, type = 'success') {
  clearTimeout(toastTimer)
  toast.value = { show: true, message, type }
  toastTimer = setTimeout(() => { toast.value.show = false }, 4000)
}

const confirmState = ref({ show: false, title: '', message: '', action: null, busy: false })
function askConfirm({ title, message, action }) {
  confirmState.value = { show: true, title, message, action, busy: false }
}
async function onConfirmOk() {
  const fn = confirmState.value.action
  if (typeof fn !== 'function') { confirmState.value.show = false; return }
  confirmState.value.busy = true
  try { await fn() } finally { confirmState.value.show = false; confirmState.value.busy = false }
}

const loading   = ref(false)
const errorMsg  = ref('')
const rows      = ref([])
const labs      = ref([])
const filters   = ref({ laboratory_id: '', from: '', to: '' })

const canManage = computed(() => {
  const u = userStore.currentUser
  if (!u || u.is_view_only) return false
  const perms = u.permission_names || u.permissions || []
  return u.roles?.includes('system-administrator') || perms.includes('manage_staff_trainings')
})

async function load() {
  loading.value = true
  errorMsg.value = ''
  try {
    const params = {}
    if (filters.value.laboratory_id) params.laboratory_id = filters.value.laboratory_id
    if (filters.value.from) params.from = filters.value.from
    if (filters.value.to)   params.to   = filters.value.to
    const res = await qualityService.trainings.list(params)
    rows.value = res.data?.data || res.data || []
  } catch (e) {
    errorMsg.value = e.response?.data?.message || 'Could not load training records.'
  } finally {
    loading.value = false
  }
}

async function loadLabs() {
  try {
    const res = await kpiFrameworkService.labs()
    labs.value = res.data?.data || res.data || []
  } catch (e) { /* swallow — lab list not critical for read */ }
}

onMounted(async () => { await loadLabs(); await load() })

// ── Add / Edit modal ───────────────────────────────────────────────────
const modalOpen   = ref(false)
const modalSaving = ref(false)
const modalError  = ref('')
const modalForm   = ref({
  id: null,
  laboratory_id: '',
  user_id: '',
  staff_name: '',
  training_topic: '',
  training_date: new Date().toISOString().slice(0, 10),
  valid_until: '',
  notes: '',
})
const labStaff = ref([])

function resetForm() {
  modalForm.value = {
    id: null, laboratory_id: '', user_id: '', staff_name: '',
    training_topic: '', training_date: new Date().toISOString().slice(0, 10),
    valid_until: '', notes: '',
  }
  labStaff.value = []
  modalError.value = ''
}

async function openCreate() {
  resetForm()
  modalOpen.value = true
}

async function onLabChange() {
  if (!modalForm.value.laboratory_id) { labStaff.value = []; return }
  try {
    const res = await qualityService.trainings.labStaff(modalForm.value.laboratory_id)
    labStaff.value = res.data?.data || res.data || []
  } catch (e) { labStaff.value = [] }
}

function onStaffSelect() {
  const u = labStaff.value.find(s => s.id === Number(modalForm.value.user_id))
  if (u) modalForm.value.staff_name = u.name
}

async function save() {
  modalSaving.value = true
  modalError.value = ''
  try {
    const payload = {
      laboratory_id:  Number(modalForm.value.laboratory_id),
      user_id:        modalForm.value.user_id ? Number(modalForm.value.user_id) : null,
      staff_name:     modalForm.value.staff_name,
      training_topic: modalForm.value.training_topic,
      training_date:  modalForm.value.training_date,
      valid_until:    modalForm.value.valid_until || null,
      notes:          modalForm.value.notes || null,
    }
    await qualityService.trainings.create(payload)
    modalOpen.value = false
    await load()
    showToast('Training record saved', 'success')
  } catch (e) {
    modalError.value = e.response?.data?.message || 'Could not save.'
  } finally {
    modalSaving.value = false
  }
}

function remove(id) {
  askConfirm({
    title: 'Delete Training Record',
    message: 'Delete this training record? This cannot be undone.',
    action: async () => {
      try {
        await qualityService.trainings.remove(id)
        await load()
        showToast('Training record deleted', 'success')
      } catch (e) {
        showToast(e.response?.data?.message || 'Could not delete', 'error')
      }
    },
  })
}
</script>

<template>
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

  <div class="page">
    <div class="toolbar">
      <select v-model="filters.laboratory_id" @change="load">
        <option value="">All Labs</option>
        <option v-for="l in labs" :key="l.id" :value="l.id">{{ l.name }}</option>
      </select>
      <input type="date" v-model="filters.from" @change="load" placeholder="From">
      <input type="date" v-model="filters.to" @change="load" placeholder="To">
      <span v-if="loading" class="status">Loading…</span>
      <div class="spacer"></div>
      <button v-if="canManage" class="btn btn-pri btn-sm" @click="openCreate">+ Add Training</button>
    </div>

    <div v-if="errorMsg" class="error-bar">⚠ {{ errorMsg }}</div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Laboratory</th>
            <th>Staff</th>
            <th>Topic</th>
            <th>Trained</th>
            <th>Valid Until</th>
            <th>Status</th>
            <th>Recorded By</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading && rows.length === 0" v-for="n in 6" :key="'sk-' + n">
            <td v-for="i in 8" :key="i"><span class="sk-bar"></span></td>
          </tr>
          <tr v-if="!loading && rows.length === 0">
            <td colspan="8" class="empty">No training records.</td>
          </tr>
          <tr v-for="r in rows" :key="r.id">
            <td>{{ r.laboratory }}</td>
            <td>{{ r.staff_name }}</td>
            <td>{{ r.training_topic }}</td>
            <td>{{ r.training_date }}</td>
            <td>{{ r.valid_until }}</td>
            <td>
              <span class="pill" :class="r.is_valid ? 'pill-green' : 'pill-red'">
                {{ r.is_valid ? 'Valid' : 'Expired' }}
              </span>
            </td>
            <td>{{ r.created_by || '—' }}</td>
            <td>
              <button v-if="canManage" class="btn-link" @click="remove(r.id)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add modal -->
    <div v-if="modalOpen" class="modal-backdrop" @click.self="modalOpen = false">
      <div class="modal">
        <div class="modal-head">
          <h3>Add Training Record</h3>
          <button class="x" @click="modalOpen = false">×</button>
        </div>
        <div class="modal-body">
          <div class="form-row">
            <label>Laboratory</label>
            <select v-model="modalForm.laboratory_id" @change="onLabChange">
              <option value="">Select…</option>
              <option v-for="l in labs" :key="l.id" :value="l.id">{{ l.name }}</option>
            </select>
          </div>
          <div class="form-row">
            <label>Staff Member (optional — picks user)</label>
            <select v-model="modalForm.user_id" @change="onStaffSelect" :disabled="!labStaff.length">
              <option value="">— Free-text below —</option>
              <option v-for="s in labStaff" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div class="form-row">
            <label>Staff Name</label>
            <input type="text" v-model="modalForm.staff_name" placeholder="e.g. Ahmed Khan">
          </div>
          <div class="form-row">
            <label>Training Topic</label>
            <input type="text" v-model="modalForm.training_topic" placeholder="e.g. Sample Collection SOP">
          </div>
          <div class="form-row two-col">
            <div>
              <label>Training Date</label>
              <input type="date" v-model="modalForm.training_date">
            </div>
            <div>
              <label>Valid Until (default +12 mo)</label>
              <input type="date" v-model="modalForm.valid_until">
            </div>
          </div>
          <div class="form-row">
            <label>Notes</label>
            <textarea v-model="modalForm.notes" rows="2"></textarea>
          </div>
          <div v-if="modalError" class="modal-error">{{ modalError }}</div>
        </div>
        <div class="modal-foot">
          <button class="btn btn-sec btn-sm" @click="modalOpen = false">Cancel</button>
          <button class="btn btn-pri btn-sm" :disabled="modalSaving" @click="save">
            {{ modalSaving ? 'Saving…' : 'Save' }}
          </button>
        </div>
      </div>
    </div>

    <ConfirmModal v-model="confirmState.show"
                  :title="confirmState.title"
                  :message="confirmState.message"
                  :busy="confirmState.busy"
                  confirm-text="Delete"
                  variant="danger"
                  @confirm="onConfirmOk" />
  </div>
</template>

<style scoped>
.page { padding:0 }
.toolbar { display:flex; gap:8px; align-items:center; margin-bottom:12px; flex-wrap:wrap }
.toolbar select, .toolbar input { padding:6px 9px; border:1px solid #cbd5e1; border-radius:5px; font-size:12.5px }
.toolbar .spacer { flex:1 }
.status { color:#94a3b8; font-size:12px }
.error-bar { background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; padding:7px 11px; border-radius:5px; font-size:12px; margin-bottom:10px }

.table-wrap { background:#fff; border:1px solid #e2e8f0; border-radius:7px; overflow-x:auto }
.table-wrap table { width:100%; border-collapse:collapse; font-size:12.5px }
.table-wrap thead tr { background:var(--navy, #1e293b); color:#fff }
.table-wrap th { padding:8px 10px; text-align:left; font-weight:600 }
.table-wrap td { padding:7px 10px; border-bottom:1px solid #f1f5f9 }
.table-wrap td.empty { padding:24px; text-align:center; color:#94a3b8 }
.pill { padding:1px 7px; border-radius:9px; font-size:10.5px; font-weight:600 }
.pill-green { background:#d1fae5; color:#065f46 }
.pill-red   { background:#fee2e2; color:#991b1b }
.btn-link { background:none; border:none; color:#b91c1c; cursor:pointer; font-size:11.5px; padding:0 }
.btn-link:hover { text-decoration:underline }

.modal-backdrop { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:50; padding:20px }
.modal { background:#fff; border-radius:10px; width:520px; max-width:92vw; max-height:90vh; box-shadow:0 20px 40px rgba(0,0,0,.25); overflow:hidden; display:flex; flex-direction:column }
.modal-head { display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border-bottom:1px solid #e2e8f0; background:#f8fafc; flex-shrink:0 }
.modal-head h3 { margin:0; font-size:14px }
.modal-head .x { background:none; border:none; font-size:22px; cursor:pointer; color:#64748b }
.modal-body { padding:14px 16px; overflow-y:auto; flex:1 1 auto }
.form-row { margin-bottom:10px }
.form-row label { display:block; font-size:11.5px; font-weight:600; color:#475569; margin-bottom:3px }
.form-row input, .form-row select, .form-row textarea { width:100%; padding:6px 9px; border:1px solid #cbd5e1; border-radius:5px; font-size:13px; box-sizing:border-box }
.form-row.two-col { display:grid; grid-template-columns:1fr 1fr; gap:10px }
.modal-error { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; padding:6px 10px; border-radius:5px; font-size:12px; margin-top:6px }
.modal-foot { display:flex; justify-content:flex-end; gap:8px; padding:10px 16px; border-top:1px solid #e2e8f0; background:#f8fafc; flex-shrink:0 }

/* Skeleton + toast */
.sk-bar {
  display:inline-block; width:100%; height:14px; border-radius:3px;
  background: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%);
  background-size: 200% 100%; animation: st-sk-shimmer 1.4s infinite;
}
@keyframes st-sk-shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
.toast-slide-enter-from { transform:translateX(40px); opacity:0 }
.toast-slide-enter-active, .toast-slide-leave-active { transition:all .2s ease }
.toast-slide-leave-to { transform:translateX(40px); opacity:0 }
</style>
