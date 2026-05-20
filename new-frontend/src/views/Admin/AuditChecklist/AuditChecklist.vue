<script setup>
import { ref, computed, onMounted } from 'vue'
import { qualityService } from '../../../services/qualityService.js'
import { kpiFrameworkService } from '../../../services/kpiFrameworkService.js'
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

const tab = ref('inspections')  // 'inspections' | 'items'

const labs = ref([])

const canManage = computed(() => {
  const u = userStore.currentUser
  if (!u || u.is_view_only) return false
  const perms = u.permission_names || u.permissions || []
  return u.roles?.includes('system-administrator') || perms.includes('manage_audit_inspections')
})

// ── Items tab ──────────────────────────────────────────────────────────
const items       = ref([])
const itemsLoading = ref(false)
const itemModalOpen   = ref(false)
const itemModalSaving = ref(false)
const itemModalError  = ref('')
const itemForm    = ref({ id: null, question: '', category: '', position: 0, is_active: true })

async function loadItems() {
  itemsLoading.value = true
  try {
    const r = await qualityService.audit.items.list()
    items.value = r.data?.data || r.data || []
  }
  finally { itemsLoading.value = false }
}
function openItemCreate() { itemForm.value = { id: null, question: '', category: '', position: items.value.length, is_active: true }; itemModalError.value = ''; itemModalOpen.value = true }
function openItemEdit(it) { itemForm.value = { ...it }; itemModalError.value = ''; itemModalOpen.value = true }

async function saveItem() {
  itemModalSaving.value = true
  itemModalError.value = ''
  try {
    const payload = {
      question: itemForm.value.question,
      category: itemForm.value.category || null,
      position: Number(itemForm.value.position) || 0,
      is_active: !!itemForm.value.is_active,
    }
    if (itemForm.value.id) await qualityService.audit.items.update(itemForm.value.id, payload)
    else                   await qualityService.audit.items.create(payload)
    itemModalOpen.value = false
    await loadItems()
    showToast(itemForm.value.id ? 'Checklist item updated' : 'Checklist item added', 'success')
  } catch (e) {
    itemModalError.value = e.response?.data?.message || 'Could not save.'
  } finally { itemModalSaving.value = false }
}

function removeItem(id) {
  askConfirm({
    title: 'Remove Checklist Item',
    message: 'Remove this checklist item? Historical answers are preserved.',
    action: async () => {
      try {
        await qualityService.audit.items.remove(id)
        await loadItems()
        showToast('Checklist item removed', 'success')
      } catch (e) {
        showToast(e.response?.data?.message || 'Could not delete', 'error')
      }
    },
  })
}

// ── Inspections tab ────────────────────────────────────────────────────
const inspections        = ref([])
const inspLoading        = ref(false)
const inspFilters        = ref({ laboratory_id: '', from: '', to: '' })
const inspModalOpen      = ref(false)
const inspModalSaving    = ref(false)
const inspModalError     = ref('')
const inspForm           = ref({
  laboratory_id: '',
  inspection_date: new Date().toISOString().slice(0, 10),
  notes: '',
  answers: [],  // [{item_id, answer}]
})

async function loadInspections() {
  inspLoading.value = true
  try {
    const params = {}
    if (inspFilters.value.laboratory_id) params.laboratory_id = inspFilters.value.laboratory_id
    if (inspFilters.value.from) params.from = inspFilters.value.from
    if (inspFilters.value.to)   params.to   = inspFilters.value.to
    const r = await qualityService.audit.inspections.list(params)
    inspections.value = r.data?.data || r.data || []
  } finally { inspLoading.value = false }
}

async function openInspCreate() {
  if (!items.value.length) await loadItems()
  const active = items.value.filter(i => i.is_active)
  if (!active.length) { showToast('Add at least one active checklist item first', 'error'); tab.value = 'items'; return }
  inspForm.value = {
    laboratory_id: '',
    inspection_date: new Date().toISOString().slice(0, 10),
    notes: '',
    answers: active.map(i => ({ item_id: i.id, question: i.question, category: i.category, answer: 'pass', notes: '' })),
  }
  inspModalError.value = ''
  inspModalOpen.value = true
}

const inspPreview = computed(() => {
  const pass = inspForm.value.answers.filter(a => a.answer === 'pass').length
  const fail = inspForm.value.answers.filter(a => a.answer === 'fail').length
  const total = pass + fail
  if (total === 0) return null
  return Math.round((pass / total) * 100)
})

async function saveInsp() {
  inspModalSaving.value = true
  inspModalError.value = ''
  try {
    if (!inspForm.value.laboratory_id) throw new Error('Pick a laboratory.')
    await qualityService.audit.inspections.create({
      laboratory_id:   Number(inspForm.value.laboratory_id),
      inspection_date: inspForm.value.inspection_date,
      notes:           inspForm.value.notes || null,
      answers:         inspForm.value.answers.map(a => ({ item_id: a.item_id, answer: a.answer, notes: a.notes || null })),
    })
    inspModalOpen.value = false
    await loadInspections()
    showToast('Inspection recorded', 'success')
  } catch (e) {
    inspModalError.value = e.response?.data?.message || e.message || 'Could not save.'
  } finally { inspModalSaving.value = false }
}

function removeInsp(id) {
  askConfirm({
    title: 'Delete Inspection',
    message: 'Delete this inspection? This cannot be undone.',
    action: async () => {
      try {
        await qualityService.audit.inspections.remove(id)
        await loadInspections()
        showToast('Inspection deleted', 'success')
      } catch (e) {
        showToast(e.response?.data?.message || 'Could not delete', 'error')
      }
    },
  })
}

function scoreClass(p) {
  if (p == null) return 'pill-gray'
  if (p >= 100)  return 'pill-green'
  if (p >= 90)   return 'pill-amber'
  return 'pill-red'
}

onMounted(async () => {
  try {
    const r = await kpiFrameworkService.labs()
    labs.value = r.data?.data || r.data || []
  } catch (e) {}
  await loadItems()
  await loadInspections()
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
    <div class="tabs">
      <button :class="{ active: tab==='inspections' }" @click="tab='inspections'">Inspections</button>
      <button :class="{ active: tab==='items' }" @click="tab='items'">Checklist Items</button>
    </div>

    <!-- INSPECTIONS TAB -->
    <div v-if="tab==='inspections'">
      <div class="toolbar">
        <select v-model="inspFilters.laboratory_id" @change="loadInspections">
          <option value="">All Labs</option>
          <option v-for="l in labs" :key="l.id" :value="l.id">{{ l.name }}</option>
        </select>
        <input type="date" v-model="inspFilters.from" @change="loadInspections">
        <input type="date" v-model="inspFilters.to" @change="loadInspections">
        <span v-if="inspLoading" class="status">Loading…</span>
        <div class="spacer"></div>
        <button v-if="canManage" class="btn btn-pri btn-sm" @click="openInspCreate">+ Record Inspection</button>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Lab</th>
              <th>Inspection Date</th>
              <th>Inspector</th>
              <th>Pass</th>
              <th>Fail</th>
              <th>N/A</th>
              <th>Score</th>
              <th style="width:110px">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="inspLoading && inspections.length === 0" v-for="n in 6" :key="'sk-i-' + n">
              <td v-for="k in 8" :key="k"><span class="sk-bar"></span></td>
            </tr>
            <tr v-if="!inspLoading && inspections.length === 0">
              <td colspan="8" class="empty">No inspections recorded.</td>
            </tr>
            <tr v-for="i in inspections" :key="i.id">
              <td>{{ i.laboratory }}</td>
              <td>{{ i.inspection_date }}</td>
              <td>{{ i.inspector || '—' }}</td>
              <td class="num">{{ i.pass_count }}</td>
              <td class="num">{{ i.fail_count }}</td>
              <td class="num">{{ i.na_count }}</td>
              <td><span class="pill" :class="scoreClass(i.score_pct)">{{ i.score_pct == null ? '—' : i.score_pct + '%' }}</span></td>
              <td style="white-space:nowrap">
                <button v-if="canManage" class="btn btn-clear btn-xs" @click="removeInsp(i.id)">🗑 Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ITEMS TAB -->
    <div v-if="tab==='items'">
      <div class="toolbar">
        <span class="status">{{ items.length }} items defined ({{ items.filter(i=>i.is_active).length }} active)</span>
        <div class="spacer"></div>
        <button v-if="canManage" class="btn btn-pri btn-sm" @click="openItemCreate">+ Add Item</button>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th style="width:50px">#</th>
              <th>Question</th>
              <th>Category</th>
              <th>Active</th>
              <th style="width:160px">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="itemsLoading && items.length === 0" v-for="n in 5" :key="'sk-it-' + n">
              <td v-for="k in 5" :key="k"><span class="sk-bar"></span></td>
            </tr>
            <tr v-if="!itemsLoading && items.length === 0">
              <td colspan="5" class="empty">No checklist items. Add some to enable inspections.</td>
            </tr>
            <tr v-for="(it, i) in items" :key="it.id">
              <td>{{ i + 1 }}</td>
              <td>{{ it.question }}</td>
              <td>{{ it.category || '—' }}</td>
              <td><span class="pill" :class="it.is_active ? 'pill-green' : 'pill-gray'">{{ it.is_active ? 'Active' : 'Inactive' }}</span></td>
              <td style="white-space:nowrap">
                <div style="display:inline-flex;gap:4px;align-items:center">
                  <button v-if="canManage" class="btn btn-sec btn-xs" @click="openItemEdit(it)">✏ Edit</button>
                  <button v-if="canManage" class="btn btn-clear btn-xs" @click="removeItem(it.id)">🗑 Delete</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Item modal -->
    <div v-if="itemModalOpen" class="modal-backdrop" @click.self="itemModalOpen = false">
      <div class="modal">
        <div class="modal-head">
          <h3>{{ itemForm.id ? 'Edit' : 'Add' }} Checklist Item</h3>
          <button class="x" @click="itemModalOpen = false">×</button>
        </div>
        <div class="modal-body">
          <div class="form-row">
            <label>Question</label>
            <textarea v-model="itemForm.question" rows="2" placeholder="e.g. Reagents within expiry"></textarea>
          </div>
          <div class="form-row two-col">
            <div>
              <label>Category (optional)</label>
              <input type="text" v-model="itemForm.category" placeholder="e.g. Sample Handling">
            </div>
            <div>
              <label>Position</label>
              <input type="number" min="0" v-model.number="itemForm.position">
            </div>
          </div>
          <div class="form-row">
            <label><input type="checkbox" v-model="itemForm.is_active"> Active</label>
          </div>
          <div v-if="itemModalError" class="modal-error">{{ itemModalError }}</div>
        </div>
        <div class="modal-foot">
          <button class="btn btn-sec btn-sm" @click="itemModalOpen = false">Cancel</button>
          <button class="btn btn-pri btn-sm" :disabled="itemModalSaving" @click="saveItem">
            {{ itemModalSaving ? 'Saving…' : 'Save' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Inspection modal -->
    <div v-if="inspModalOpen" class="modal-backdrop" @click.self="inspModalOpen = false">
      <div class="modal modal-wide">
        <div class="modal-head">
          <h3>Record Inspection</h3>
          <button class="x" @click="inspModalOpen = false">×</button>
        </div>
        <div class="modal-body">
          <div class="form-row two-col">
            <div>
              <label>Laboratory</label>
              <select v-model="inspForm.laboratory_id">
                <option value="">Select…</option>
                <option v-for="l in labs" :key="l.id" :value="l.id">{{ l.name }}</option>
              </select>
            </div>
            <div>
              <label>Inspection Date</label>
              <input type="date" v-model="inspForm.inspection_date">
            </div>
          </div>

          <div class="checklist">
            <div class="checklist-head">
              <span>Checklist ({{ inspForm.answers.length }} items)</span>
              <span class="preview-pill" :class="scoreClass(inspPreview)">
                Preview: {{ inspPreview == null ? '—' : inspPreview + '%' }}
              </span>
            </div>
            <div v-for="(a, idx) in inspForm.answers" :key="a.item_id" class="check-row">
              <div class="q">
                <span class="q-num">{{ idx + 1 }}.</span>
                <div>
                  <div>{{ a.question }}</div>
                  <div v-if="a.category" class="q-cat">{{ a.category }}</div>
                </div>
              </div>
              <div class="ans">
                <label><input type="radio" :value="'pass'" v-model="a.answer"> Pass</label>
                <label><input type="radio" :value="'fail'" v-model="a.answer"> Fail</label>
                <label><input type="radio" :value="'na'" v-model="a.answer"> N/A</label>
              </div>
            </div>
          </div>

          <div class="form-row">
            <label>Notes</label>
            <textarea v-model="inspForm.notes" rows="2"></textarea>
          </div>
          <div v-if="inspModalError" class="modal-error">{{ inspModalError }}</div>
        </div>
        <div class="modal-foot">
          <button class="btn btn-sec btn-sm" @click="inspModalOpen = false">Cancel</button>
          <button class="btn btn-pri btn-sm" :disabled="inspModalSaving" @click="saveInsp">
            {{ inspModalSaving ? 'Saving…' : 'Save' }}
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
.tabs { display:flex; gap:4px; margin-bottom:12px; border-bottom:1px solid #e2e8f0 }
.tabs button { background:none; border:none; padding:8px 14px; cursor:pointer; font-size:13px; color:#64748b; border-bottom:2px solid transparent }
.tabs button.active { color:#0f172a; border-bottom-color:var(--navy, #1e293b); font-weight:600 }

.toolbar { display:flex; gap:8px; align-items:center; margin-bottom:12px; flex-wrap:wrap }
.toolbar select, .toolbar input { padding:6px 9px; border:1px solid #cbd5e1; border-radius:5px; font-size:12.5px }
.toolbar .spacer { flex:1 }
.status { color:#94a3b8; font-size:12px }

.table-wrap { background:#fff; border:1px solid #e2e8f0; border-radius:7px; overflow-x:auto }
.table-wrap table { width:100%; border-collapse:collapse; font-size:12.5px }
.table-wrap thead tr { background:var(--navy, #1e293b); color:#fff }
.table-wrap th { padding:8px 10px; text-align:left; font-weight:600 }
.table-wrap td { padding:7px 10px; border-bottom:1px solid #f1f5f9 }
.table-wrap td.num   { text-align:right; font-family:'DM Mono', monospace }
.table-wrap td.empty { padding:24px; text-align:center; color:#94a3b8 }
.pill { padding:1px 7px; border-radius:9px; font-size:10.5px; font-weight:600 }
.pill-green { background:#d1fae5; color:#065f46 }
.pill-amber { background:#fef9c3; color:#713f12 }
.pill-red   { background:#fee2e2; color:#991b1b }
.pill-gray  { background:#f1f5f9; color:#64748b }
.btn-link { background:none; border:none; color:#b91c1c; cursor:pointer; font-size:11.5px; padding:0; margin-right:8px }
.btn-link.blue { color:#1d4ed8 }
.btn-link:hover { text-decoration:underline }

.modal-backdrop { position:fixed; inset:0; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; z-index:50; padding:20px }
.modal { background:#fff; border-radius:10px; width:520px; max-width:92vw; max-height:90vh; box-shadow:0 20px 40px rgba(0,0,0,.25); overflow:hidden; display:flex; flex-direction:column }
.modal-wide { width:780px }
.modal-head { display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border-bottom:1px solid #e2e8f0; background:#f8fafc }
.modal-head h3 { margin:0; font-size:14px }
.modal-head .x { background:none; border:none; font-size:22px; cursor:pointer; color:#64748b }
.modal-body { padding:14px 16px; overflow-y:auto; flex:1 }
.form-row { margin-bottom:10px }
.form-row label { display:block; font-size:11.5px; font-weight:600; color:#475569; margin-bottom:3px }
.form-row input, .form-row select, .form-row textarea { width:100%; padding:6px 9px; border:1px solid #cbd5e1; border-radius:5px; font-size:13px; box-sizing:border-box }
.form-row.two-col { display:grid; grid-template-columns:1fr 1fr; gap:10px }
.modal-error { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; padding:6px 10px; border-radius:5px; font-size:12px; margin-top:6px }
.modal-foot { display:flex; justify-content:flex-end; gap:8px; padding:10px 16px; border-top:1px solid #e2e8f0; background:#f8fafc; flex-shrink:0 }

.checklist { border:1px solid #e2e8f0; border-radius:6px; margin-bottom:10px }
.checklist-head { display:flex; justify-content:space-between; align-items:center; padding:8px 12px; background:#f8fafc; border-bottom:1px solid #e2e8f0; font-size:12px; font-weight:600; color:#475569 }
.preview-pill { padding:2px 8px; border-radius:9px; font-size:11.5px }
.check-row { display:flex; align-items:center; justify-content:space-between; padding:8px 12px; border-bottom:1px solid #f1f5f9; gap:10px }
.check-row:last-child { border-bottom:none }
.q { display:flex; gap:8px; flex:1 }
.q-num { color:#64748b; font-family:'DM Mono', monospace; font-size:11px }
.q-cat { font-size:10.5px; color:#94a3b8 }
.ans { display:flex; gap:10px; flex-shrink:0 }
.ans label { display:flex; align-items:center; gap:4px; font-size:12px; cursor:pointer }

.sk-bar {
  display:inline-block; width:100%; height:14px; border-radius:3px;
  background: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%);
  background-size: 200% 100%; animation: ac-sk-shimmer 1.4s infinite;
}
@keyframes ac-sk-shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
.toast-slide-enter-from { transform:translateX(40px); opacity:0 }
.toast-slide-enter-active, .toast-slide-leave-active { transition:all .2s ease }
.toast-slide-leave-to { transform:translateX(40px); opacity:0 }
</style>
