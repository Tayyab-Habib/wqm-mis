<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { kpiFrameworkService } from '../../../services/kpiFrameworkService.js'
import { useUserStore } from '../../../stores/useUserStore.js'
import { exportToExcel } from '../../../utils/exportHelpers.js'

const router    = useRouter()
const userStore = useUserStore()

// ── Toast (matches the pattern in XenSettings.vue / UsersHR.vue) ───────
const toast = ref({ show: false, message: '', type: 'success' })
let toastTimer = null
function showToast(message, type = 'success') {
  clearTimeout(toastTimer)
  toast.value = { show: true, message, type }
  toastTimer = setTimeout(() => { toast.value.show = false }, 4000)
}

// ── State ──────────────────────────────────────────────────────────────
const loading     = ref(false)
const errorMsg    = ref('')
const catalog     = ref([])   // [{id, name, target_pct, rag_green, rag_amber, manual, source_module, missing_reason}]
const labs        = ref([])   // [{id, name}]
const rows        = ref([])   // [{lab_id, kpis: {KPI-001:..}, sources: {...}, denominators: {...}, manual_periods: {...}}]
const selectedLab = ref('')   // '' = "All Labs" overview

// "Drill-down" routes per manual KPI. The user clicks "Open module" on a
// KPI card and we send them to the underlying data-entry screen.
const MODULE_ROUTES = {
  'KPI-001': '/admin/pt-rounds',
  'KPI-007': '/admin/staff-trainings',
  'KPI-008': '/admin/audit-checklist',
  'KPI-009': '/admin/verification-visits',
}

// RBAC — only show "Enter monthly value" + "Open module" buttons to users
// who can actually save. View-only roles still see the matrix.
const canManage = computed(() => {
  const u = userStore.currentUser
  if (!u) return false
  if (u.is_view_only) return false
  const perms = u.permission_names || u.permissions || []
  return u.roles?.includes('system-administrator') || perms.includes('manage_kpi_framework')
})

// ── Fetch ──────────────────────────────────────────────────────────────
async function loadMatrix() {
  loading.value = true
  errorMsg.value = ''
  try {
    // The axios response interceptor already unwraps `response.data`, so
    // `res` IS the JSON body { data: {labs, kpis, rows, meta} }. Use the
    // double-fallback (data?.data || data) so this also works if a caller
    // ever swaps in a non-unwrapped axios.
    const res = await kpiFrameworkService.matrix()
    const d   = res.data?.data || res.data || {}
    catalog.value = d.kpis || []
    labs.value    = d.labs || []
    rows.value    = d.rows || []
  } catch (e) {
    console.error('KPI matrix load failed:', e)
    errorMsg.value = 'Could not load KPI data. Try again in a moment.'
  } finally {
    loading.value = false
  }
}
onMounted(loadMatrix)

// ── Aggregation: overview ("All Labs") averages per KPI ────────────────
// For the cards above the table: when a single lab is picked we show
// that lab's values; otherwise we average across labs that have a value.
const overviewByKpi = computed(() => {
  const out = {}
  for (const k of catalog.value) {
    if (selectedLab.value) {
      const row = rows.value.find(r => Number(r.lab_id) === Number(selectedLab.value))
      out[k.id] = {
        value:  row?.kpis?.[k.id] ?? null,
        source: row?.sources?.[k.id] || 'none',
        period: row?.manual_periods?.[k.id] || null,
      }
    } else {
      const vals = rows.value.map(r => r.kpis?.[k.id]).filter(v => v != null)
      out[k.id] = {
        value:  vals.length ? Math.round(vals.reduce((a, b) => a + b, 0) / vals.length) : null,
        source: vals.length ? 'module' : 'none',  // aggregate doesn't track per-source
        period: null,
      }
    }
  }
  return out
})

function ragClass(val, k) {
  if (val == null) return 'rag-na'
  if (val >= (k.rag_green ?? 90)) return 'rag-green'
  if (val >= (k.rag_amber ?? 75)) return 'rag-amber'
  return 'rag-red'
}
function ragLabel(val, k) {
  if (val == null) return '—'
  if (val >= (k.rag_green ?? 90)) return 'Green'
  if (val >= (k.rag_amber ?? 75)) return 'Amber'
  return 'Red'
}

// ── Manual entry modal (KPI-001/007/008/009 fallback path) ─────────────
const modalOpen   = ref(false)
const modalSaving = ref(false)
const modalError  = ref('')
const modalForm   = ref({
  laboratory_id: '',
  kpi_code:      'KPI-007',
  period:        new Date().toISOString().slice(0, 7),  // YYYY-MM
  numerator:     0,
  denominator:   0,
  notes:         '',
})
const modalKpiCatalog = computed(() => catalog.value.filter(k => k.manual))

function openModal(kpiId = 'KPI-007', labId = '') {
  modalForm.value = {
    laboratory_id: labId || selectedLab.value || (labs.value[0]?.id ?? ''),
    kpi_code:      kpiId,
    period:        new Date().toISOString().slice(0, 7),
    numerator:     0,
    denominator:   0,
    notes:         '',
  }
  modalError.value = ''
  modalOpen.value  = true
}

async function saveModal() {
  modalSaving.value = true
  modalError.value  = ''
  try {
    const f = modalForm.value
    if (!f.laboratory_id) throw new Error('Pick a laboratory.')
    if (Number(f.numerator) > Number(f.denominator)) throw new Error('Numerator cannot exceed denominator.')
    await kpiFrameworkService.save({
      laboratory_id: Number(f.laboratory_id),
      kpi_code:      f.kpi_code,
      period:        f.period,
      numerator:     Number(f.numerator),
      denominator:   Number(f.denominator),
      notes:         f.notes || null,
    })
    modalOpen.value = false
    await loadMatrix()
    showToast('Monthly KPI value saved', 'success')
  } catch (e) {
    modalError.value = e.response?.data?.message || e.message || 'Could not save.'
  } finally {
    modalSaving.value = false
  }
}

const modalPreviewPct = computed(() => {
  const n = Number(modalForm.value.numerator)
  const d = Number(modalForm.value.denominator)
  if (!d || d <= 0) return null
  return Math.round((n / d) * 100)
})

// ── Export ─────────────────────────────────────────────────────────────
function exportMatrix() {
  const exportData = []
  for (const k of catalog.value) {
    const row = { 'KPI ID': k.id, 'KPI Name': k.name, 'Target': '≥' + (k.target_pct ?? '—') + '%' }
    for (const l of labs.value) {
      const r = rows.value.find(rr => Number(rr.lab_id) === Number(l.id))
      const v = r?.kpis?.[k.id]
      row[l.name] = v == null ? '—' : (v + '%')
    }
    exportData.push(row)
  }
  exportToExcel(exportData, 'KPI_Framework_Matrix', { includeTimestamp: true })
}

function gotoModule(kpiId) {
  const path = MODULE_ROUTES[kpiId]
  if (path) router.push(path)
}
</script>

<template>
  <!-- Toast (top-right slide-in) -->
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

  <div class="kpi-fw">
    <!-- Toolbar -->
    <div class="toolbar">
      <select v-model="selectedLab">
        <option value="">All Labs (average)</option>
        <option v-for="l in labs" :key="l.id" :value="l.id">{{ l.name }}</option>
      </select>
      <span v-if="loading" class="status">Loading…</span>
      <div class="spacer"></div>
      <button v-if="canManage" class="btn btn-pri btn-sm" @click="openModal()">+ Enter Monthly Value</button>
      <button class="btn btn-sec btn-sm" @click="exportMatrix">⬇ Export</button>
      <button class="btn btn-sec btn-sm" @click="loadMatrix" title="Refresh">↻</button>
    </div>

    <div v-if="errorMsg" class="error-bar">⚠ {{ errorMsg }}</div>

    <!-- KPI Cards Row (overview) — skeleton while loading -->
    <div v-if="loading && catalog.length === 0" class="kpi-row">
      <div v-for="n in 9" :key="'sk-' + n" class="kpi-card kpi-card-sk">
        <span class="sk-bar sk-bar-sm"></span>
        <span class="sk-bar sk-bar-md"></span>
        <span class="sk-bar sk-bar-xl"></span>
        <span class="sk-bar sk-bar-md"></span>
        <span class="sk-bar sk-bar-sm"></span>
      </div>
    </div>

    <div v-else class="kpi-row">
      <div v-for="kpi in catalog" :key="kpi.id" class="kpi-card" :class="ragClass(overviewByKpi[kpi.id]?.value, kpi)">
        <div class="kpi-card-head">
          <span class="kpi-id">{{ kpi.id }}</span>
          <span v-if="kpi.manual" class="kpi-badge manual" title="Module backed; admin can enter monthly fallback when no data">manual</span>
          <span v-else class="kpi-badge auto" title="Auto-computed from operational data">auto</span>
        </div>
        <div class="kpi-name">{{ kpi.name }}</div>
        <div class="kpi-val">
          {{ overviewByKpi[kpi.id]?.value == null ? '—' : overviewByKpi[kpi.id].value + '%' }}
        </div>
        <div class="kpi-target">
          Target: ≥{{ kpi.target_pct }}% · <span class="rag-pill" :class="ragClass(overviewByKpi[kpi.id]?.value, kpi)">{{ ragLabel(overviewByKpi[kpi.id]?.value, kpi) }}</span>
        </div>
        <div class="kpi-bar">
          <div class="kpi-fill" :style="{ width: (overviewByKpi[kpi.id]?.value ?? 0) + '%' }"></div>
        </div>
        <div class="kpi-foot">
          <span class="kpi-src" :title="overviewByKpi[kpi.id]?.source === 'manual' ? ('Manual entry · ' + (overviewByKpi[kpi.id].period || '')) : ''">
            <template v-if="overviewByKpi[kpi.id]?.source === 'module'">● module</template>
            <template v-else-if="overviewByKpi[kpi.id]?.source === 'manual'">◐ manual {{ overviewByKpi[kpi.id].period }}</template>
            <template v-else>○ no data</template>
          </span>
          <span class="kpi-actions">
            <button v-if="kpi.manual && canManage" class="btn-link" @click="openModal(kpi.id)">Enter</button>
            <button v-if="kpi.manual && canManage" class="btn-link" @click="gotoModule(kpi.id)">Open module</button>
          </span>
        </div>
      </div>
    </div>

    <!-- Lab × KPI Matrix -->
    <div class="matrix-box">
      <div class="matrix-head">
        <h3>Per-Lab Matrix</h3>
        <div class="matrix-legend">
          <span class="lg-dot rag-green"></span> Green
          <span class="lg-dot rag-amber"></span> Amber
          <span class="lg-dot rag-red"></span> Red
          <span class="lg-dot rag-na"></span> No data
        </div>
      </div>
      <div class="matrix-scroll">
        <table>
          <thead>
            <tr>
              <th class="left">KPI ID</th>
              <th class="left">KPI Name</th>
              <th>Target</th>
              <th v-for="l in labs" :key="l.id" :title="l.name">{{ l.name }}</th>
            </tr>
          </thead>
          <tbody>
            <!-- Skeleton rows while data is loading -->
            <tr v-if="loading && catalog.length === 0" v-for="n in 9" :key="'sk-mr-' + n">
              <td class="left mono"><span class="sk-bar" style="width:48px"></span></td>
              <td class="left"><span class="sk-bar" style="width:180px"></span></td>
              <td><span class="sk-bar" style="width:46px"></span></td>
              <td v-for="(_, ci) in (labs.length || 8)" :key="ci"><span class="sk-bar" style="width:48px"></span></td>
            </tr>
            <tr v-if="!loading && rows.length === 0">
              <td :colspan="3 + labs.length" class="empty">No KPI data available.</td>
            </tr>
            <tr v-for="kpi in catalog" :key="kpi.id">
              <td class="left mono">{{ kpi.id }}</td>
              <td class="left">{{ kpi.name }}</td>
              <td class="muted">≥{{ kpi.target_pct }}%</td>
              <td v-for="l in labs" :key="l.id" class="cell">
                <span class="cell-pill" :class="ragClass(rows.find(r => Number(r.lab_id) === Number(l.id))?.kpis?.[kpi.id], kpi)">
                  {{ rows.find(r => Number(r.lab_id) === Number(l.id))?.kpis?.[kpi.id] == null
                       ? '—'
                       : rows.find(r => Number(r.lab_id) === Number(l.id)).kpis[kpi.id] + '%' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Manual Entry Modal -->
    <div v-if="modalOpen" class="modal-backdrop" @click.self="modalOpen = false">
      <div class="modal">
        <div class="modal-head">
          <h3>Enter Monthly KPI Value</h3>
          <button class="x" @click="modalOpen = false">×</button>
        </div>
        <div class="modal-body">
          <div class="form-row">
            <label>Laboratory</label>
            <select v-model="modalForm.laboratory_id">
              <option value="">Select…</option>
              <option v-for="l in labs" :key="l.id" :value="l.id">{{ l.name }}</option>
            </select>
          </div>
          <div class="form-row">
            <label>KPI</label>
            <select v-model="modalForm.kpi_code">
              <option v-for="k in modalKpiCatalog" :key="k.id" :value="k.id">{{ k.id }} — {{ k.name }}</option>
            </select>
          </div>
          <div class="form-row">
            <label>Period (YYYY-MM)</label>
            <input type="month" v-model="modalForm.period">
          </div>
          <div class="form-row two-col">
            <div>
              <label>Numerator</label>
              <input type="number" min="0" v-model.number="modalForm.numerator">
            </div>
            <div>
              <label>Denominator</label>
              <input type="number" min="0" v-model.number="modalForm.denominator">
            </div>
          </div>
          <div class="form-row">
            <label>Preview</label>
            <div class="preview-pct">{{ modalPreviewPct == null ? '—' : modalPreviewPct + '%' }}</div>
          </div>
          <div class="form-row">
            <label>Notes (optional)</label>
            <textarea v-model="modalForm.notes" rows="3"></textarea>
          </div>
          <div v-if="modalError" class="modal-error">{{ modalError }}</div>
        </div>
        <div class="modal-foot">
          <button class="btn btn-sec btn-sm" @click="modalOpen = false">Cancel</button>
          <button class="btn btn-pri btn-sm" :disabled="modalSaving" @click="saveModal">
            {{ modalSaving ? 'Saving…' : 'Save' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.kpi-fw { padding: 0 }

.toolbar { display:flex; align-items:center; gap:10px; margin-bottom:14px; flex-wrap:wrap }
.toolbar select { padding:6px 10px; border:1px solid #d1d5db; border-radius:6px; font-size:13px }
.toolbar .spacer { flex:1 }
.status { color:#94a3b8; font-size:12px }

.error-bar { background:#fee2e2; border:1px solid #fca5a5; border-radius:6px; padding:8px 12px; color:#991b1b; font-size:12px; margin-bottom:12px }

/* KPI cards */
.kpi-row { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:12px; margin-bottom:18px }
.kpi-card { background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:12px 14px; box-shadow:0 1px 2px rgba(0,0,0,.04) }
.kpi-card-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:4px }
.kpi-id { font-family:'DM Mono', monospace; font-size:11px; color:#64748b }
.kpi-badge { font-size:9.5px; padding:1px 6px; border-radius:9px; text-transform:uppercase; letter-spacing:.04em }
.kpi-badge.manual { background:#fef3c7; color:#854d0e; border:1px solid #fde68a }
.kpi-badge.auto   { background:#dbeafe; color:#1e40af; border:1px solid #bfdbfe }
.kpi-name { font-size:13px; font-weight:600; color:#0f172a; margin-bottom:4px }
.kpi-val  { font-size:24px; font-weight:800; line-height:1; margin:4px 0 6px }
.kpi-card.rag-green .kpi-val { color:#15803d }
.kpi-card.rag-amber .kpi-val { color:#a16207 }
.kpi-card.rag-red   .kpi-val { color:#b91c1c }
.kpi-card.rag-na    .kpi-val { color:#94a3b8 }
.kpi-target { font-size:11px; color:#64748b; margin-bottom:6px }
.rag-pill { padding:1px 7px; border-radius:9px; font-size:10.5px; font-weight:600 }
.rag-pill.rag-green { background:#d1fae5; color:#065f46 }
.rag-pill.rag-amber { background:#fef9c3; color:#713f12 }
.rag-pill.rag-red   { background:#fee2e2; color:#991b1b }
.rag-pill.rag-na    { background:#f1f5f9; color:#64748b }
.kpi-bar { background:#f1f5f9; border-radius:3px; height:5px; overflow:hidden; margin-bottom:8px }
.kpi-fill { height:100%; transition:width .4s }
.kpi-card.rag-green .kpi-fill { background:#16a34a }
.kpi-card.rag-amber .kpi-fill { background:#ca8a04 }
.kpi-card.rag-red   .kpi-fill { background:#dc2626 }
.kpi-card.rag-na    .kpi-fill { background:#cbd5e1 }
.kpi-foot { display:flex; justify-content:space-between; align-items:center; font-size:10.5px }
.kpi-src  { color:#94a3b8 }
.kpi-actions { display:flex; gap:8px }
.btn-link { background:none; border:none; color:#1d4ed8; cursor:pointer; font-size:11px; padding:0 }
.btn-link:hover { text-decoration:underline }

/* Matrix */
.matrix-box { background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:14px }
.matrix-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:8px }
.matrix-head h3 { margin:0; font-size:14px; color:#0f172a }
.matrix-legend { font-size:11px; color:#64748b; display:flex; gap:10px; align-items:center }
.lg-dot { display:inline-block; width:10px; height:10px; border-radius:3px; margin-right:3px; vertical-align:middle }
.lg-dot.rag-green { background:#d1fae5; border:1px solid #6ee7b7 }
.lg-dot.rag-amber { background:#fef9c3; border:1px solid #fde047 }
.lg-dot.rag-red   { background:#fee2e2; border:1px solid #fca5a5 }
.lg-dot.rag-na    { background:#f1f5f9; border:1px solid #cbd5e1 }
.matrix-scroll { overflow-x:auto }
.matrix-box table { width:100%; border-collapse:collapse; font-size:12px }
.matrix-box thead tr { background:var(--navy, #1e293b); color:#fff }
.matrix-box th { padding:7px 8px; text-align:center; font-weight:600; white-space:nowrap }
.matrix-box th.left { text-align:left }
.matrix-box td { padding:6px 8px; text-align:center; border-bottom:1px solid #f1f5f9 }
.matrix-box td.left { text-align:left; font-weight:500 }
.matrix-box td.mono { font-family:'DM Mono', monospace; font-size:11px; color:#64748b; white-space:nowrap }
.matrix-box td.muted { color:#64748b; font-size:11px }
.matrix-box td.empty { padding:18px; text-align:center; color:#94a3b8 }
.cell-pill { display:inline-block; padding:2px 8px; border-radius:4px; font-weight:700; font-size:11.5px; min-width:44px }
.cell-pill.rag-green { background:#d1fae5; color:#065f46; border:1px solid #6ee7b7 }
.cell-pill.rag-amber { background:#fef9c3; color:#713f12; border:1px solid #fde047 }
.cell-pill.rag-red   { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5 }
.cell-pill.rag-na    { background:#f1f5f9; color:#94a3b8; border:1px solid #e2e8f0 }

/* Modal — flex column + scrollable body so the footer (save button) stays
   pinned and visible even when the form is taller than the viewport. */
.modal-backdrop { position:fixed; inset:0; background:rgba(15, 23, 42, .55); display:flex; align-items:center; justify-content:center; z-index:50; padding:20px }
.modal { background:#fff; border-radius:10px; width:480px; max-width:92vw; max-height:90vh; box-shadow:0 20px 40px rgba(0,0,0,.25); overflow:hidden; display:flex; flex-direction:column }
.modal-head { display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border-bottom:1px solid #e2e8f0; background:#f8fafc; flex-shrink:0 }
.modal-head h3 { margin:0; font-size:14px }
.modal-head .x { background:none; border:none; font-size:22px; cursor:pointer; color:#64748b }
.modal-body { padding:14px 16px; overflow-y:auto; flex:1 1 auto }
.form-row { margin-bottom:10px }
.form-row label { display:block; font-size:11.5px; font-weight:600; color:#475569; margin-bottom:3px }
.form-row input, .form-row select, .form-row textarea { width:100%; padding:6px 9px; border:1px solid #cbd5e1; border-radius:5px; font-size:13px }
.form-row.two-col { display:grid; grid-template-columns:1fr 1fr; gap:10px }
.preview-pct { padding:6px 9px; background:#f1f5f9; border-radius:5px; font-weight:700; font-size:14px; color:#0f172a }
.modal-error { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; padding:6px 10px; border-radius:5px; font-size:12px; margin-top:6px }
.modal-foot { display:flex; justify-content:flex-end; gap:8px; padding:10px 16px; border-top:1px solid #e2e8f0; background:#f8fafc; flex-shrink:0 }

/* Skeleton loading (matches SecSkelRow pattern — local @keyframes because
   scoped styles don't reach child components in Vue's CSS scoping). */
.kpi-card-sk { display:flex; flex-direction:column; gap:6px; min-height:130px }
.sk-bar {
  display:inline-block;
  background: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%);
  background-size: 200% 100%;
  animation: kpi-sk-shimmer 1.4s infinite;
  border-radius: 3px;
  height: 14px;
}
.sk-bar-sm { width:60%; height:11px }
.sk-bar-md { width:80%; height:13px }
.sk-bar-xl { width:50%; height:22px }
@keyframes kpi-sk-shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* Toast slide transition */
.toast-slide-enter-from { transform:translateX(40px); opacity:0 }
.toast-slide-enter-active, .toast-slide-leave-active { transition:all .2s ease }
.toast-slide-leave-to { transform:translateX(40px); opacity:0 }
</style>
