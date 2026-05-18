<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { settingsService } from '../../../services/settingsService.js'
import { useUserStore } from '../../../stores/useUserStore.js'
import { arrayToCSV, downloadCSV } from '../../../utils/exportHelpers.js'

const userStore = useUserStore()

/* ── Permission gates ──────────────────────────────────────────── */
const canView   = computed(() => userStore.hasPermission('view_complaint_types'))
const canAdd    = computed(() => userStore.hasPermission('add_complaint_types'))
/* Backend exposes only index/store/destroy (no PUT/PATCH), so we
   intentionally omit an Edit button — matches the screenshot too. */
const canDelete = computed(() => userStore.hasPermission('delete_complaint_types'))

/* ── State ─────────────────────────────────────────────────────── */
const loading        = ref(false)
const saving         = ref(false)
const deleting       = ref(false)
const complaintTypes = ref([])

const search     = ref('')
const nameFilter = ref('')

const page     = ref(1)
const pageSize = ref(10)

const showModal      = ref(false)
const formName       = ref('')
const formError      = ref('')

const deleteTarget   = ref(null)

/* ── Toast ─────────────────────────────────────────────────────── */
const toast = ref({ show: false, message: '', type: 'success' })
let toastTimer = null
function showToast(message, type = 'success') {
  clearTimeout(toastTimer)
  toast.value = { show: true, message, type }
  toastTimer = setTimeout(() => { toast.value.show = false }, 4000)
}

/* ── Date formatter ────────────────────────────────────────────── */
function formatDate(d) {
  if (!d) return '—'
  if (typeof d === 'string' && d.includes(',')) return d
  const dt = new Date(d)
  if (isNaN(dt.getTime())) return String(d)
  return dt.toLocaleDateString('en-GB',
    { day: '2-digit', month: 'short', year: 'numeric' }) +
    ' ' + dt.toLocaleTimeString('en-GB',
    { hour: '2-digit', minute: '2-digit', hour12: false })
}

/* ── Filtering + pagination ────────────────────────────────────── */
const filtered = computed(() => {
  let rows = complaintTypes.value
  const kw = search.value.trim().toLowerCase()
  if (kw) rows = rows.filter(r => (r.name || '').toLowerCase().includes(kw))
  const nf = nameFilter.value.trim().toLowerCase()
  if (nf) rows = rows.filter(r => (r.name || '').toLowerCase().includes(nf))
  return rows
})

const totalRows  = computed(() => filtered.value.length)
const totalPages = computed(() => Math.max(1, Math.ceil(totalRows.value / pageSize.value)))
const paged = computed(() => {
  const start = (page.value - 1) * pageSize.value
  return filtered.value.slice(start, start + pageSize.value)
})
const rangeStart = computed(() => totalRows.value === 0 ? 0 : (page.value - 1) * pageSize.value + 1)
const rangeEnd   = computed(() => Math.min(page.value * pageSize.value, totalRows.value))

const visiblePages = computed(() => {
  const total = totalPages.value
  const cur = page.value
  const window = 5
  let start = Math.max(1, cur - Math.floor(window / 2))
  let end = Math.min(total, start + window - 1)
  start = Math.max(1, end - window + 1)
  const out = []
  for (let i = start; i <= end; i++) out.push(i)
  return out
})

function gotoFirst() { page.value = 1 }
function gotoPrev()  { if (page.value > 1) page.value-- }
function gotoNext()  { if (page.value < totalPages.value) page.value++ }
function gotoLast()  { page.value = totalPages.value }
function gotoPage(n) { page.value = n }

watch([search, nameFilter, pageSize], () => { page.value = 1 })

/* ── API actions ──────────────────────────────────────────────── */
async function fetchComplaintTypes() {
  loading.value = true
  try {
    const res = await settingsService.getComplaintTypes()
    complaintTypes.value = Array.isArray(res?.data) ? res.data : []
  } catch (err) {
    console.error('Fetch complaint types error:', err)
    showToast('❌ Failed to load complaint types: ' + (err?.response?.data?.message || err.message), 'error')
  } finally {
    loading.value = false
  }
}

function openAddModal() {
  formName.value = ''
  formError.value = ''
  showModal.value = true
}

function closeModal() {
  if (saving.value) return
  showModal.value = false
}

async function submitForm() {
  formError.value = ''
  if (!formName.value.trim()) { formError.value = 'Complaint type name is required.'; return }

  saving.value = true
  try {
    await settingsService.createComplaintType({ name: formName.value.trim() })
    showToast('✅ Complaint type added successfully.')
    showModal.value = false
    await fetchComplaintTypes()
  } catch (err) {
    console.error('Create complaint type error:', err)
    const e = err?.response?.data
    formError.value = e?.errors
      ? Object.values(e.errors).flat().join('\n')
      : (e?.message || err.message || 'Failed to save complaint type.')
  } finally {
    saving.value = false
  }
}

function askDelete(row)  { deleteTarget.value = row }
function cancelDelete()  { if (!deleting.value) deleteTarget.value = null }

async function confirmDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await settingsService.deleteComplaintType(deleteTarget.value.id)
    showToast(`🗑 Deleted complaint type "${deleteTarget.value.name}".`)
    deleteTarget.value = null
    await fetchComplaintTypes()
  } catch (err) {
    console.error('Delete complaint type error:', err)
    showToast('❌ ' + (err?.response?.data?.message || 'Failed to delete complaint type'), 'error')
  } finally {
    deleting.value = false
  }
}

/* ── Export ────────────────────────────────────────────────────── */
function exportCsv() {
  const rows = filtered.value.map(r => ({
    'Name':       r.name,
    'Created At': formatDate(r.created_at),
  }))
  if (!rows.length) {
    showToast('Nothing to export — list is empty.', 'error')
    return
  }
  downloadCSV(arrayToCSV(rows), `complaint_types_${new Date().toISOString().slice(0, 10)}.csv`)
}

onMounted(fetchComplaintTypes)
</script>

<template>
  <div class="settings-page">
    <!-- ── Toast ───────────────────────────────────────────────── -->
    <Teleport to="body">
      <Transition name="toast-slide">
        <div v-if="toast.show"
             :style="`position:fixed;top:22px;right:24px;z-index:9999;min-width:280px;max-width:460px;
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

    <!-- ── Breadcrumbs ────────────────────────────────────────── -->
    <div class="breadcrumbs">
      <span class="bc-icon">🏠</span>
      <span class="bc-sep">›</span>
      <span>Dashboard</span>
      <span class="bc-sep">›</span>
      <span class="active">Complaint Types</span>
    </div>

    <!-- ── Main card ───────────────────────────────────────────── -->
    <section class="data-card" v-if="canView">
      <header class="card-header">
        <h1>Complaint Types</h1>
        <button v-if="canAdd" class="btn-primary" @click="openAddModal">+ Add Complaint Type</button>
      </header>

      <div class="toolbar">
        <button class="btn-export" @click="exportCsv" :disabled="loading">
          <span class="ico">↗</span> Export
        </button>
        <div class="spacer"></div>
        <input class="search-input" v-model="search" placeholder="Keyword Search" />
      </div>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Name</th>
              <th style="width:240px;">Created At</th>
              <th style="width:90px;" class="text-right">Actions</th>
            </tr>
          </thead>

          <tbody>
            <!-- Per-column filter row -->
            <tr class="filter-row">
              <td><input class="col-filter" v-model="nameFilter" placeholder="Complaint Types" /></td>
              <td></td>
              <td></td>
            </tr>

            <!-- Skeleton -->
            <template v-if="loading">
              <tr v-for="i in 5" :key="`sk-${i}`" class="sk-row">
                <td><span class="sk sk-text" style="width:30%"></span></td>
                <td><span class="sk sk-text" style="width:60%"></span></td>
                <td class="text-right"><span class="sk sk-btn"></span></td>
              </tr>
            </template>

            <!-- Empty -->
            <tr v-else-if="paged.length === 0">
              <td colspan="3" class="empty-row">
                {{ (search || nameFilter)
                    ? 'No complaint types match your search.'
                    : 'No complaint types yet. Click "Add Complaint Type" to create one.' }}
              </td>
            </tr>

            <!-- Data rows -->
            <tr v-else v-for="row in paged" :key="row.id" class="data-row">
              <td class="fw-500">{{ row.name }}</td>
              <td class="muted">{{ formatDate(row.created_at) }}</td>
              <td class="text-right">
                <button v-if="canDelete" class="btn-icon btn-del" @click="askDelete(row)" title="Delete">🗑</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <footer class="pagination">
        <div class="pager">
          <button class="pg-btn" @click="gotoFirst" :disabled="page === 1">«</button>
          <button class="pg-btn" @click="gotoPrev"  :disabled="page === 1">‹</button>
          <button v-for="n in visiblePages" :key="n"
                  class="pg-btn" :class="{ active: n === page }"
                  @click="gotoPage(n)">{{ n }}</button>
          <button class="pg-btn" @click="gotoNext" :disabled="page === totalPages">›</button>
          <button class="pg-btn" @click="gotoLast" :disabled="page === totalPages">»</button>
        </div>
        <div class="pager-info">
          <span>Showing {{ rangeStart }} to {{ rangeEnd }} of {{ totalRows }} entries</span>
          <select v-model.number="pageSize" class="page-size">
            <option :value="10">10</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
          </select>
        </div>
      </footer>
    </section>

    <div v-else class="forbidden-card">🚫 You don't have permission to view Complaint Types.</div>

    <!-- ── Add Modal ──────────────────────────────────────────── -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
          <div class="modal">
            <header class="modal-header">
              <h2>Add Complaint Type</h2>
              <button class="modal-close" @click="closeModal" :disabled="saving">✕</button>
            </header>

            <div class="modal-body">
              <label class="field-label">Name <span class="required">*</span></label>
              <input class="field-input" v-model="formName"
                     placeholder="e.g. IT" :disabled="saving" maxlength="255"
                     @keyup.enter="submitForm" />

              <div v-if="formError" class="form-error">{{ formError }}</div>
            </div>

            <footer class="modal-footer">
              <button class="btn-secondary" @click="closeModal" :disabled="saving">Cancel</button>
              <button class="btn-primary" @click="submitForm" :disabled="saving">
                {{ saving ? 'Saving…' : 'Add Complaint Type' }}
              </button>
            </footer>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ── Delete Confirmation Modal ──────────────────────────── -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="deleteTarget" class="modal-overlay" @click.self="cancelDelete">
          <div class="modal modal-sm">
            <header class="modal-header">
              <h2>Delete Complaint Type</h2>
              <button class="modal-close" @click="cancelDelete" :disabled="deleting">✕</button>
            </header>
            <div class="modal-body">
              <p>Are you sure you want to delete <strong>{{ deleteTarget.name }}</strong>?</p>
              <p class="muted small">This action cannot be undone. Complaint types referenced by existing complaints cannot be deleted.</p>
            </div>
            <footer class="modal-footer">
              <button class="btn-secondary" @click="cancelDelete" :disabled="deleting">Cancel</button>
              <button class="btn-danger" @click="confirmDelete" :disabled="deleting">
                {{ deleting ? 'Deleting…' : 'Delete' }}
              </button>
            </footer>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.settings-page { max-width: 1280px; margin: 0 auto; padding: 16px 24px 32px; color: #0f172a; }

.breadcrumbs {
  display: flex; align-items: center; gap: 8px;
  background: #fff;
  border: 1px solid #e2e8f0; border-radius: 8px;
  padding: 10px 16px; margin-bottom: 16px;
  font-size: 13px; color: #475569;
}
.bc-icon { font-size: 14px; }
.bc-sep { color: #94a3b8; }
.breadcrumbs .active { color: #0f172a; font-weight: 600; }

.data-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 1px 2px rgba(0,0,0,.03);
}
.card-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid #e2e8f0;
}
.card-header h1 { font-size: 16px; font-weight: 700; margin: 0; color: #0f172a; }

.toolbar {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 20px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
}
.toolbar .spacer { flex: 1; }
.search-input {
  width: 240px;
  padding: 8px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  font-size: 13px;
  outline: none;
  background: #fff;
}
.search-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,.1); }

.btn-primary, .btn-secondary, .btn-danger, .btn-export {
  font-size: 13px; font-weight: 600;
  padding: 8px 16px;
  border-radius: 4px;
  border: 1px solid transparent;
  cursor: pointer;
  transition: background .15s, border-color .15s, opacity .15s;
}
.btn-primary { background: #2563eb; color: #fff; border-color: #2563eb; }
.btn-primary:hover:not(:disabled)   { background: #1d4ed8; border-color: #1d4ed8; }
.btn-primary:disabled               { opacity: .65; cursor: not-allowed; }

.btn-secondary { background: #fff; color: #334155; border-color: #cbd5e1; }
.btn-secondary:hover:not(:disabled) { background: #f1f5f9; }
.btn-secondary:disabled             { opacity: .65; cursor: not-allowed; }

.btn-danger  { background: #dc2626; color: #fff; border-color: #dc2626; }
.btn-danger:hover:not(:disabled)    { background: #b91c1c; }
.btn-danger:disabled                { opacity: .65; cursor: not-allowed; }

.btn-export {
  background: #2563eb; color: #fff; border-color: #2563eb;
  display: inline-flex; align-items: center; gap: 6px;
}
.btn-export:hover:not(:disabled)    { background: #1d4ed8; }
.btn-export .ico { font-size: 12px; }
.btn-export:disabled                { opacity: .65; cursor: not-allowed; }

.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.data-table thead th {
  background: #f1f5f9;
  font-weight: 600;
  color: #334155;
  text-align: left;
  padding: 12px 16px;
  border-bottom: 1px solid #e2e8f0;
  white-space: nowrap;
}
.data-table tbody td {
  padding: 12px 16px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}
.data-table .text-right { text-align: right; }
.data-table .muted      { color: #64748b; }
.data-table .fw-500     { font-weight: 500; }
.empty-row {
  text-align: center;
  color: #94a3b8;
  font-style: italic;
  padding: 40px !important;
}

.filter-row td { background: #fafbfc; padding: 8px 16px; }
.col-filter {
  width: 100%;
  padding: 6px 10px;
  border: 1px solid #cbd5e1;
  border-radius: 3px;
  font-size: 13px;
  outline: none;
  background: #fff;
}
.col-filter:focus { border-color: #3b82f6; }

.data-row:hover td { background: #f8fafc; }

.btn-icon {
  width: 30px; height: 30px;
  border: 1px solid transparent;
  border-radius: 4px;
  font-size: 14px;
  cursor: pointer;
  display: inline-flex; align-items: center; justify-content: center;
}
.btn-del  { background: #fee2e2; color: #991b1b; border-color: #fca5a5; }
.btn-del:hover  { background: #fecaca; }

.sk-row td { background: transparent !important; }
.sk {
  display: inline-block;
  background: linear-gradient(90deg, #eef2f7 0%, #f7fafc 50%, #eef2f7 100%);
  background-size: 200% 100%;
  animation: sk-shimmer 1.4s infinite ease-in-out;
  border-radius: 4px;
}
.sk-text { height: 12px; }
.sk-btn  { width: 30px; height: 24px; }
@keyframes sk-shimmer {
  0%   { background-position: -200% 0; }
  100% { background-position:  200% 0; }
}

.pagination {
  display: flex; justify-content: space-between; align-items: center;
  padding: 14px 20px;
  border-top: 1px solid #e2e8f0;
  background: #fff;
  font-size: 12.5px; color: #475569;
}
.pager { display: flex; gap: 4px; }
.pg-btn {
  min-width: 30px; height: 30px;
  border: 1px solid #cbd5e1;
  background: #fff;
  border-radius: 4px;
  font-size: 13px;
  cursor: pointer;
  color: #334155;
  padding: 0 6px;
}
.pg-btn:hover:not(:disabled) { background: #f1f5f9; }
.pg-btn.active {
  background: #dbeafe; border-color: #93c5fd; color: #1e3a8a; font-weight: 600;
}
.pg-btn:disabled { opacity: .4; cursor: not-allowed; }
.pager-info { display: flex; align-items: center; gap: 12px; }
.page-size {
  padding: 4px 8px;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  font-size: 13px;
  background: #fff;
}

.forbidden-card {
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  padding: 40px 24px;
  text-align: center;
  color: #991b1b;
  font-size: 14px;
}

.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(15, 23, 42, 0.55);
  display: flex; align-items: center; justify-content: center;
  z-index: 9000;
  padding: 16px;
}
.modal {
  background: #fff;
  border-radius: 8px;
  width: 100%;
  max-width: 460px;
  max-height: 90vh;
  display: flex; flex-direction: column;
  box-shadow: 0 24px 48px rgba(0,0,0,.22);
}
.modal-sm { max-width: 380px; }
.modal-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 14px 18px;
  border-bottom: 1px solid #e2e8f0;
  background: #1e3a5f;
  color: #fff;
  border-radius: 8px 8px 0 0;
}
.modal-header h2 { margin: 0; font-size: 15px; font-weight: 700; }
.modal-close {
  background: rgba(255,255,255,.18); border: none; color: #fff;
  width: 26px; height: 26px; border-radius: 4px; cursor: pointer;
  font-size: 13px;
}
.modal-close:hover:not(:disabled) { background: rgba(255,255,255,.3); }

.modal-body { padding: 18px; overflow-y: auto; flex: 1 1 auto; }
.modal-footer {
  display: flex; justify-content: flex-end; gap: 10px;
  padding: 12px 18px;
  border-top: 1px solid #e2e8f0;
  background: #f8fafc;
  border-radius: 0 0 8px 8px;
}

.field-label {
  display: block;
  font-size: 12.5px; font-weight: 600;
  color: #334155;
  margin-bottom: 6px;
}
.required { color: #dc2626; }
.field-input {
  width: 100%;
  padding: 9px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  font-size: 13px;
  outline: none;
  background: #fff;
}
.field-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,.1); }
.field-input:disabled { background: #f1f5f9; color: #94a3b8; cursor: not-allowed; }
.form-error {
  margin-top: 14px;
  padding: 10px 12px;
  border: 1px solid #fecaca;
  background: #fef2f2;
  color: #991b1b;
  border-radius: 4px;
  font-size: 12.5px;
  white-space: pre-line;
}
.small { font-size: 12px; }

.modal-fade-enter-active,
.modal-fade-leave-active { transition: opacity .2s; }
.modal-fade-enter-from,
.modal-fade-leave-to     { opacity: 0; }
</style>

<style>
.toast-slide-enter-active,
.toast-slide-leave-active { transition: all 0.3s ease; }
.toast-slide-enter-from,
.toast-slide-leave-to     { opacity: 0; transform: translateX(60px); }
</style>
