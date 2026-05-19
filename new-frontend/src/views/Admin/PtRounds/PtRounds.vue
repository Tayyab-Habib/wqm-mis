<script setup>
import { ref, computed, onMounted } from 'vue'
import { qualityService } from '../../../services/qualityService.js'
import { kpiFrameworkService } from '../../../services/kpiFrameworkService.js'
import { dropdownService } from '../../../services/dropdownService.js'
import { useUserStore } from '../../../stores/useUserStore.js'
import ConfirmModal from '../../../components/common/ConfirmModal/ConfirmModal.vue'

const userStore = useUserStore()

const toast = ref({ show: false, message: '', type: 'success' })
let toastTimer = null
function showToast(message, type = 'success') {
  clearTimeout(toastTimer)
  toast.value = { show: true, message, type }
  toastTimer = setTimeout(() => { toast.value.show = false }, 4000)
}

const confirmState = ref({ show: false, title: '', message: '', confirmText: 'Confirm', variant: 'danger', action: null, busy: false })
function askConfirm({ title, message, confirmText = 'Confirm', variant = 'danger', action }) {
  confirmState.value = { show: true, title, message, confirmText, variant, action, busy: false }
}
async function onConfirmOk() {
  const fn = confirmState.value.action
  if (typeof fn !== 'function') { confirmState.value.show = false; return }
  confirmState.value.busy = true
  try { await fn() } finally { confirmState.value.show = false; confirmState.value.busy = false }
}

const loading  = ref(false)
const errorMsg = ref('')
const rounds   = ref([])
const labs     = ref([])
const tests    = ref([])

const canManage = computed(() => {
  const u = userStore.currentUser
  if (!u || u.is_view_only) return false
  const perms = u.permission_names || u.permissions || []
  return u.roles?.includes('system-administrator') || perms.includes('manage_pt_rounds')
})
const canSubmit = computed(() => {
  const u = userStore.currentUser
  if (!u || u.is_view_only) return false
  const perms = u.permission_names || u.permissions || []
  return u.roles?.includes('system-administrator') || perms.includes('submit_pt_results')
})

async function load() {
  loading.value = true
  errorMsg.value = ''
  try {
    const r = await qualityService.pt.list()
    rounds.value = r.data?.data || r.data || []
  } catch (e) {
    errorMsg.value = e.response?.data?.message || 'Could not load PT rounds.'
  } finally { loading.value = false }
}

onMounted(async () => {
  try {
    const r = await kpiFrameworkService.labs()
    labs.value = r.data?.data || r.data || []
  } catch (e) {}
  try {
    const r = await dropdownService.getTestParameters()
    tests.value = r.data?.data || r.data || []
  } catch (e) {}
  await load()
})

// ── Create round modal ─────────────────────────────────────────────────
const createOpen   = ref(false)
const createSaving = ref(false)
const createError  = ref('')
const createForm   = ref({
  code: '', name: '',
  round_date: new Date().toISOString().slice(0, 10),
  due_date:   new Date(Date.now() + 14*86400000).toISOString().slice(0, 10),
  notes: '',
  items: [{ test_id: '', reference_value: '', tolerance_pct: 15, unit: '' }],
  participant_lab_ids: [],
})
function openCreate() {
  createForm.value = {
    code: 'PT-' + new Date().toISOString().slice(0, 7),
    name: 'Proficiency Round — ' + new Date().toLocaleString('en', { month: 'long', year: 'numeric' }),
    round_date: new Date().toISOString().slice(0, 10),
    due_date:   new Date(Date.now() + 14*86400000).toISOString().slice(0, 10),
    notes: '',
    items: [{ test_id: '', reference_value: '', tolerance_pct: 15, unit: '' }],
    participant_lab_ids: labs.value.map(l => l.id),
  }
  createError.value = ''
  createOpen.value = true
}
function addItem()  { createForm.value.items.push({ test_id: '', reference_value: '', tolerance_pct: 15, unit: '' }) }
function rmItem(i)  { createForm.value.items.splice(i, 1) }
function toggleLab(id) {
  const i = createForm.value.participant_lab_ids.indexOf(id)
  if (i >= 0) createForm.value.participant_lab_ids.splice(i, 1)
  else createForm.value.participant_lab_ids.push(id)
}

async function saveRound() {
  createSaving.value = true
  createError.value = ''
  try {
    const items = createForm.value.items
      .filter(i => i.test_id && i.reference_value !== '')
      .map(i => ({
        test_id:         Number(i.test_id),
        reference_value: Number(i.reference_value),
        tolerance_pct:   Number(i.tolerance_pct) || 15,
        unit:            i.unit || null,
      }))
    if (!items.length) throw new Error('Add at least one parameter item.')
    if (!createForm.value.participant_lab_ids.length) throw new Error('Pick at least one participating lab.')

    await qualityService.pt.create({
      code:                 createForm.value.code,
      name:                 createForm.value.name,
      round_date:           createForm.value.round_date,
      due_date:             createForm.value.due_date,
      notes:                createForm.value.notes || null,
      items,
      participant_lab_ids:  createForm.value.participant_lab_ids,
    })
    createOpen.value = false
    await load()
    showToast('PT round created', 'success')
  } catch (e) {
    createError.value = e.response?.data?.message || e.message || 'Could not create round.'
  } finally { createSaving.value = false }
}

function closeRound(id) {
  askConfirm({
    title: 'Close PT Round',
    message: 'Close this round? Submissions will no longer be accepted.',
    confirmText: 'Close Round',
    variant: 'primary',
    action: async () => {
      try {
        await qualityService.pt.close(id); await load()
        showToast('Round closed', 'success')
      } catch (e) {
        showToast(e.response?.data?.message || 'Could not close', 'error')
      }
    },
  })
}
function removeRound(id) {
  askConfirm({
    title: 'Delete PT Round',
    message: 'Delete this PT round? All submissions will be lost. This cannot be undone.',
    confirmText: 'Delete',
    variant: 'danger',
    action: async () => {
      try {
        await qualityService.pt.remove(id); await load()
        showToast('Round deleted', 'success')
      } catch (e) {
        showToast(e.response?.data?.message || 'Could not delete', 'error')
      }
    },
  })
}

// ── Submit results modal (lab-incharge or admin acting for a lab) ──────
const submitOpen   = ref(false)
const submitSaving = ref(false)
const submitError  = ref('')
const submitDetail = ref(null)   // round detail with items + participants
const submitLabId  = ref('')
const submitResults = ref({})    // { item_id: value }

async function openSubmit(roundId, labId = '') {
  submitError.value = ''
  submitLabId.value = labId || (userStore.currentUser?.laboratory?.id || '')
  try {
    const res = await qualityService.pt.show(roundId)
    submitDetail.value = res.data?.data || res.data
    // pre-fill any existing values for this lab
    const part = submitDetail.value.participants.find(p => Number(p.laboratory_id) === Number(submitLabId.value))
    submitResults.value = {}
    if (part) part.results.forEach(r => { submitResults.value[r.item_id] = r.submitted_value ?? '' })
    submitOpen.value = true
  } catch (e) {
    showToast(e.response?.data?.message || 'Could not load round', 'error')
  }
}

async function saveSubmission() {
  submitSaving.value = true
  submitError.value = ''
  try {
    if (!submitLabId.value) throw new Error('Pick a laboratory.')
    const results = submitDetail.value.items.map(i => ({
      item_id:         i.id,
      submitted_value: Number(submitResults.value[i.id]),
    })).filter(r => !Number.isNaN(r.submitted_value))
    if (!results.length) throw new Error('Enter at least one reading.')
    await qualityService.pt.submit(submitDetail.value.id, submitLabId.value, { results })
    submitOpen.value = false
    await load()
    showToast('Results submitted', 'success')
  } catch (e) {
    submitError.value = e.response?.data?.message || e.message || 'Could not submit.'
  } finally { submitSaving.value = false }
}

function statusPill(s) { return s === 'closed' ? 'pill-gray' : s === 'open' ? 'pill-green' : 'pill-amber' }
function participantPill(s) { return s === 'submitted' ? 'pill-green' : 'pill-amber' }

// "Submit" eligible labs for current user (their own labs); admin can pick anyone
const submitLabOptions = computed(() => {
  const u = userStore.currentUser
  if (!u) return []
  if (u.roles?.includes('system-administrator')) return labs.value
  // Filter to user's lab(s) if available
  if (u.laboratory?.id) return labs.value.filter(l => Number(l.id) === Number(u.laboratory.id))
  return labs.value
})
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
      <span v-if="loading" class="status">Loading…</span>
      <div class="spacer"></div>
      <button v-if="canManage" class="btn btn-pri btn-sm" @click="openCreate">+ New PT Round</button>
    </div>

    <div v-if="errorMsg" class="error-bar">⚠ {{ errorMsg }}</div>

    <!-- Skeleton round-cards while loading -->
    <div v-if="loading && rounds.length === 0">
      <div v-for="n in 2" :key="'sk-' + n" class="round-card">
        <div class="round-head">
          <div style="flex:1">
            <span class="sk-bar" style="width:180px;height:16px"></span><br>
            <span class="sk-bar" style="width:260px;height:11px;margin-top:6px"></span>
          </div>
        </div>
        <div class="r-grid">
          <div><span class="sk-bar" style="height:60px"></span></div>
          <div><span class="sk-bar" style="height:60px"></span></div>
        </div>
      </div>
    </div>

    <div v-if="!loading && rounds.length === 0" class="empty-state">
      No PT rounds yet. <span v-if="canManage">Click <strong>+ New PT Round</strong> to create one.</span>
    </div>

    <div v-for="r in rounds" :key="r.id" class="round-card">
      <div class="round-head">
        <div>
          <div class="r-title">{{ r.name }}</div>
          <div class="r-meta">
            <span class="mono">{{ r.code }}</span> ·
            Round: {{ r.round_date }} · Due: {{ r.due_date }} ·
            <span class="pill" :class="statusPill(r.status)">{{ r.status }}</span>
          </div>
        </div>
        <div class="r-actions">
          <button v-if="canManage && r.status === 'open'" class="btn btn-sec btn-xs" @click="closeRound(r.id)">Close round</button>
          <button v-if="canManage" class="btn-link" @click="removeRound(r.id)">Delete</button>
        </div>
      </div>

      <div class="r-grid">
        <div class="r-items">
          <div class="r-section">Parameters ({{ r.items.length }})</div>
          <table class="mini">
            <thead><tr><th>Parameter</th><th>Reference</th><th>Tolerance</th></tr></thead>
            <tbody>
              <tr v-for="i in r.items" :key="i.id">
                <td>{{ i.parameter }}</td>
                <td class="mono">{{ i.reference_value }} {{ i.unit || '' }}</td>
                <td>±{{ i.tolerance_pct }}%</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="r-parts">
          <div class="r-section">Labs ({{ r.participants.length }})</div>
          <table class="mini">
            <thead><tr><th>Lab</th><th>Status</th><th>When</th><th></th></tr></thead>
            <tbody>
              <tr v-for="p in r.participants" :key="p.id">
                <td>{{ p.laboratory }}</td>
                <td><span class="pill" :class="participantPill(p.status)">{{ p.status }}</span></td>
                <td class="muted">{{ p.submitted_at ? new Date(p.submitted_at).toLocaleString() : '—' }}</td>
                <td>
                  <button v-if="(canSubmit || canManage) && r.status === 'open'" class="btn-link blue" @click="openSubmit(r.id, p.laboratory_id)">
                    {{ p.status === 'submitted' ? 'Update' : 'Submit' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Create round modal -->
    <div v-if="createOpen" class="modal-backdrop" @click.self="createOpen = false">
      <div class="modal modal-wide">
        <div class="modal-head">
          <h3>New PT Round</h3>
          <button class="x" @click="createOpen = false">×</button>
        </div>
        <div class="modal-body">
          <div class="form-row two-col">
            <div>
              <label>Code</label>
              <input type="text" v-model="createForm.code">
            </div>
            <div>
              <label>Name</label>
              <input type="text" v-model="createForm.name">
            </div>
          </div>
          <div class="form-row two-col">
            <div>
              <label>Round Date</label>
              <input type="date" v-model="createForm.round_date">
            </div>
            <div>
              <label>Due Date</label>
              <input type="date" v-model="createForm.due_date">
            </div>
          </div>

          <div class="section-head">Parameters</div>
          <div v-for="(it, i) in createForm.items" :key="i" class="item-row">
            <select v-model="it.test_id">
              <option value="">Pick test…</option>
              <option v-for="t in tests" :key="t.id" :value="t.id">{{ t.water_quality_parameter || t.name }} ({{ t.unit }})</option>
            </select>
            <input type="number" step="0.01" v-model.number="it.reference_value" placeholder="Reference">
            <input type="number" step="0.1" v-model.number="it.tolerance_pct" placeholder="Tolerance %">
            <input type="text" v-model="it.unit" placeholder="Unit (optional)">
            <button class="btn-link" @click="rmItem(i)" :disabled="createForm.items.length === 1">×</button>
          </div>
          <button class="btn btn-sec btn-xs" @click="addItem">+ Add parameter</button>

          <div class="section-head" style="margin-top:14px">Participating Labs ({{ createForm.participant_lab_ids.length }})</div>
          <div class="lab-grid">
            <label v-for="l in labs" :key="l.id" class="lab-check">
              <input type="checkbox" :checked="createForm.participant_lab_ids.includes(l.id)" @change="toggleLab(l.id)">
              {{ l.name }}
            </label>
          </div>

          <div class="form-row" style="margin-top:10px">
            <label>Notes</label>
            <textarea v-model="createForm.notes" rows="2"></textarea>
          </div>

          <div v-if="createError" class="modal-error">{{ createError }}</div>
        </div>
        <div class="modal-foot">
          <button class="btn btn-sec btn-sm" @click="createOpen = false">Cancel</button>
          <button class="btn btn-pri btn-sm" :disabled="createSaving" @click="saveRound">
            {{ createSaving ? 'Saving…' : 'Create Round' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Submit results modal -->
    <div v-if="submitOpen" class="modal-backdrop" @click.self="submitOpen = false">
      <div class="modal modal-wide">
        <div class="modal-head">
          <h3>Submit Results — {{ submitDetail?.code }}</h3>
          <button class="x" @click="submitOpen = false">×</button>
        </div>
        <div class="modal-body">
          <div class="form-row">
            <label>Submitting on behalf of laboratory</label>
            <select v-model="submitLabId">
              <option value="">Select…</option>
              <option v-for="l in submitLabOptions" :key="l.id" :value="l.id">{{ l.name }}</option>
            </select>
          </div>
          <table class="mini full">
            <thead>
              <tr>
                <th>Parameter</th>
                <th>Reference</th>
                <th>Tolerance</th>
                <th>Your Reading</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="i in submitDetail?.items || []" :key="i.id">
                <td>{{ i.parameter }}</td>
                <td class="mono">{{ i.reference_value }} {{ i.unit || '' }}</td>
                <td>±{{ i.tolerance_pct }}%</td>
                <td>
                  <input type="number" step="0.0001" v-model="submitResults[i.id]" style="width:120px">
                </td>
              </tr>
            </tbody>
          </table>
          <div v-if="submitError" class="modal-error">{{ submitError }}</div>
        </div>
        <div class="modal-foot">
          <button class="btn btn-sec btn-sm" @click="submitOpen = false">Cancel</button>
          <button class="btn btn-pri btn-sm" :disabled="submitSaving" @click="saveSubmission">
            {{ submitSaving ? 'Saving…' : 'Submit Readings' }}
          </button>
        </div>
      </div>
    </div>

    <ConfirmModal v-model="confirmState.show"
                  :title="confirmState.title"
                  :message="confirmState.message"
                  :busy="confirmState.busy"
                  :confirm-text="confirmState.confirmText"
                  :variant="confirmState.variant"
                  @confirm="onConfirmOk" />
  </div>
</template>

<style scoped>
.page { padding:0 }
.toolbar { display:flex; gap:8px; align-items:center; margin-bottom:12px }
.toolbar .spacer { flex:1 }
.status { color:#94a3b8; font-size:12px }
.error-bar { background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; padding:7px 11px; border-radius:5px; font-size:12px; margin-bottom:10px }
.empty-state { background:#fff; border:1px dashed #cbd5e1; border-radius:8px; padding:24px; text-align:center; color:#64748b; font-size:13px }

.round-card { background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:14px; margin-bottom:14px; box-shadow:0 1px 2px rgba(0,0,0,.04) }
.round-head { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px }
.r-title { font-weight:600; color:#0f172a }
.r-meta { font-size:11.5px; color:#64748b; margin-top:2px }
.r-actions { display:flex; gap:8px; align-items:center }
.r-grid { display:grid; grid-template-columns:1fr 1.5fr; gap:14px }
.r-section { font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:.05em; margin-bottom:4px }

table.mini { width:100%; border-collapse:collapse; font-size:12px }
table.mini th { padding:5px 8px; text-align:left; background:#f8fafc; color:#475569; font-weight:600; font-size:11px; border-bottom:1px solid #e2e8f0 }
table.mini td { padding:5px 8px; border-bottom:1px solid #f1f5f9 }
table.mini td.muted { color:#94a3b8; font-size:11px }
table.mini.full { margin-top:8px }

.mono { font-family:'DM Mono', monospace; font-size:11.5px }
.pill { padding:1px 7px; border-radius:9px; font-size:10.5px; font-weight:600 }
.pill-green { background:#d1fae5; color:#065f46 }
.pill-amber { background:#fef9c3; color:#713f12 }
.pill-gray  { background:#f1f5f9; color:#64748b }
.btn-link { background:none; border:none; color:#b91c1c; cursor:pointer; font-size:11.5px; padding:0 }
.btn-link.blue { color:#1d4ed8 }
.btn-link:hover { text-decoration:underline }

.modal-backdrop { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:50; padding:20px }
.modal { background:#fff; border-radius:10px; width:600px; max-width:95vw; max-height:90vh; box-shadow:0 20px 40px rgba(0,0,0,.25); overflow:hidden; display:flex; flex-direction:column }
.modal-wide { width:780px }
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

.section-head { font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:.05em; margin:8px 0 6px }
.item-row { display:grid; grid-template-columns:2fr 1fr 1fr 1fr 30px; gap:6px; margin-bottom:6px }
.item-row input, .item-row select { padding:5px 8px; border:1px solid #cbd5e1; border-radius:4px; font-size:12px; box-sizing:border-box }
.lab-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:4px; max-height:140px; overflow-y:auto; border:1px solid #e2e8f0; border-radius:5px; padding:8px }
.lab-check { font-size:12px; display:flex; align-items:center; gap:5px; cursor:pointer }

.sk-bar {
  display:inline-block; width:100%; height:14px; border-radius:3px;
  background: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%);
  background-size: 200% 100%; animation: pt-sk-shimmer 1.4s infinite;
}
@keyframes pt-sk-shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
.toast-slide-enter-from { transform:translateX(40px); opacity:0 }
.toast-slide-enter-active, .toast-slide-leave-active { transition:all .2s ease }
.toast-slide-leave-to { transform:translateX(40px); opacity:0 }
</style>
