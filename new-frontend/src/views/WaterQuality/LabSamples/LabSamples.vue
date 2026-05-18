<script setup>
// ── Lab Samples (lab-incharge view) ────────────────────────────────────
// Read-only list of every sample at the in-charge's lab, with filters and
// drill-down to the Individual Sample Report. Lab-incharge does not
// register samples (junior-clerk does) nor run analysis (lab-assistant);
// this screen replaces those two menu items for the role and lets them
// oversee the lab's sample flow.
//
// Backend already scopes /search-water-sample by the requesting user's
// lab (AuthScope::waterSamples), so no explicit laboratory_id filter is
// needed in the request payload.

import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { sampleService } from '../../../services/sampleService.js'
import { useUserStore } from '../../../stores/useUserStore.js'

const router    = useRouter()
const userStore = useUserStore()

// ── State ────────────────────────────────────────────────────────────
const loading   = ref(true)
const errorMsg  = ref('')
const samples   = ref([])

// Filters
const searchText  = ref('')
const fromDate    = ref('')
const toDate      = ref('')
const typeFilter  = ref('')   // test type
const resultFilter = ref('')  // Fit / Unfit / Pending
const sampleType  = ref('')   // PHE / Private

// Toast
const toast = ref({ show: false, message: '', type: 'success' })
let toastTimer = null
function showToast(message, type = 'success') {
  clearTimeout(toastTimer)
  toast.value = { show: true, message, type }
  toastTimer = setTimeout(() => { toast.value.show = false }, 4000)
}

// ── Helpers ─────────────────────────────────────────────────────────
function formatDate(dt) {
  if (!dt) return '—'
  // Backend may return ISO ("2026-05-13T..."), SQL ("2026-05-13 10:30:00"),
  // or pretty ("13 May, 2026 09:30"); the constructor parses the first two,
  // returns NaN on pretty so we fall back to the raw string.
  const raw = String(dt).split('T')[0].split(' ')[0]
  const d = new Date(raw)
  if (isNaN(d)) return dt
  return d.toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'2-digit' }).replace(/ /g,'-')
}

function mapRow(s) {
  // Determine result label from result + current_status.
  // current_status is integer enum: 1=Pending 2=Fit 3=Unfit 4=InProgress 5=Closed
  const cs = s.current_status
  let resultLabel = 'Pending'
  if (s.result === 'Fit'   || s.result === '1' || cs === 2) resultLabel = 'Fit'
  else if (s.result === 'Unfit' || s.result === '2' || cs === 3) resultLabel = 'Unfit'
  else if (cs === 4) resultLabel = 'In Progress'
  else if (cs === 5) resultLabel = 'Closed'

  // Sample source classification: PHE samples have collectable_type=App\Models\User
  const isPhe = (s.collectable_type || '').endsWith('\\User')

  return {
    id:        s.id,
    slug:      s.slug || String(s.id),
    wss:       s.water_scheme?.name || s.waterScheme?.name || s.water_sample_address || '—',
    district:  s.district?.name || '—',
    testType:  s.test_type || '—',
    point:     s.sampling_point || '—',
    collectedBy: s.collected_by || s.created_by_user?.name || s.createdByUser?.name || '—',
    sampledAt:  s.sampled_at ? formatDate(s.sampled_at) : '—',
    result:     resultLabel,
    isPhe,
    raw:        s,
  }
}

// ── Load data ───────────────────────────────────────────────────────
async function loadSamples() {
  loading.value  = true
  errorMsg.value = ''
  try {
    // Build payload from filters (only include non-empty filters so the
    // backend's optional rule logic doesn't trip on blank strings).
    const payload = {}
    if (fromDate.value)    payload.from_date    = fromDate.value
    if (toDate.value)      payload.to_date      = toDate.value
    if (resultFilter.value) payload.result      = resultFilter.value
    if (sampleType.value === 'PHE')     payload.is_pvt_client = 0
    if (sampleType.value === 'Private') payload.is_pvt_client = 1

    const res = await sampleService.getAll(payload)
    // Response shape can be:
    //   { data: { data: [...] } }   (paginated)
    //   { data: [...] }              (collection)
    //   [...]                        (raw array)
    const raw = res?.data?.data?.data
             || res?.data?.data
             || res?.data
             || []
    samples.value = Array.isArray(raw) ? raw.map(mapRow) : []
  } catch (e) {
    errorMsg.value = 'Failed to load samples: ' + (e?.response?.data?.message || e.message)
    showToast('❌ ' + errorMsg.value, 'error')
  } finally {
    loading.value = false
  }
}

// ── Client-side filtering ───────────────────────────────────────────
// Date / result / sample-type are sent to the backend; search + test-type
// stay client-side so the user gets instant feedback as they type.
const filtered = computed(() => samples.value.filter(r => {
  const q = searchText.value.trim().toLowerCase()
  const ok1 = !q
    || r.slug.toLowerCase().includes(q)
    || r.wss.toLowerCase().includes(q)
    || r.district.toLowerCase().includes(q)
    || r.collectedBy.toLowerCase().includes(q)
  const ok2 = !typeFilter.value || r.testType === typeFilter.value
  return ok1 && ok2
}))

// Distinct test types — populates the type filter dropdown.
const testTypes = computed(() => {
  const set = new Set()
  samples.value.forEach(r => { if (r.testType && r.testType !== '—') set.add(r.testType) })
  return Array.from(set).sort()
})

// ── Actions ─────────────────────────────────────────────────────────
function viewSample(row) {
  // Open the Individual Sample Report page pre-filtered to this sample.
  // It's the only existing read-only sample detail surface; reusing it
  // beats building a dedicated modal.
  router.push({ name: 'IndividualSampleReport', query: { sample_id: row.id } })
}

function clearFilters() {
  searchText.value   = ''
  fromDate.value     = ''
  toDate.value       = ''
  typeFilter.value   = ''
  resultFilter.value = ''
  sampleType.value   = ''
  // Watcher below will fire from the reactive resets, but it's debounced —
  // call directly so the user sees an immediate refresh on Clear.
  loadSamples()
}

// Auto-refetch on any backend-side filter change (date / result / source).
// Search + Test Type stay client-side via the `filtered` computed, so they
// don't trigger a fetch. Tiny 80ms debounce only coalesces the rare case
// where two filters change in the same tick; feels instant to the user.
let filterTimer = null
watch([fromDate, toDate, resultFilter, sampleType], () => {
  if (loading.value) return  // skip while initial load is still in flight
  clearTimeout(filterTimer)
  filterTimer = setTimeout(loadSamples, 80)
})

function resultStyle(r) {
  if (r === 'Fit')         return 'background:#16a34a;color:#fff'
  if (r === 'Unfit')       return 'background:#dc2626;color:#fff'
  if (r === 'In Progress') return 'background:#d97706;color:#fff'
  if (r === 'Closed')      return 'background:#64748b;color:#fff'
  return 'background:#e2e8f0;color:#334155'
}

// ── Init ────────────────────────────────────────────────────────────
onMounted(loadSamples)
</script>

<template>
  <div class="ls-page">
    <!-- Toast -->
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

    <!-- Page header -->
    <div class="ls-head">
      <div>
        <div class="ls-title">Water Samples</div>
        <div class="ls-sub">
          {{ userStore.currentUser?.laboratory?.name || 'Your laboratory' }}
          <span v-if="!loading">· {{ filtered.length }} of {{ samples.length }} shown</span>
        </div>
      </div>
      <button class="ls-btn ls-btn-ghost" @click="loadSamples" :disabled="loading">
        {{ loading ? '⏳' : '↺ Refresh' }}
      </button>
    </div>

    <!-- Filters -->
    <div class="ls-filters">
      <div class="ls-fg ls-fg-search">
        <label>Search</label>
        <input v-model="searchText" type="text" placeholder="🔍 Sample ID, WSS, district, collector…" />
      </div>
      <div class="ls-fg">
        <label>From</label>
        <input v-model="fromDate" type="date" />
      </div>
      <div class="ls-fg">
        <label>To</label>
        <input v-model="toDate" type="date" />
      </div>
      <div class="ls-fg">
        <label>Test Type</label>
        <select v-model="typeFilter">
          <option value="">All Types</option>
          <option v-for="t in testTypes" :key="t" :value="t">{{ t }}</option>
        </select>
      </div>
      <div class="ls-fg">
        <label>Result</label>
        <select v-model="resultFilter">
          <option value="">All Results</option>
          <option value="Fit">Fit</option>
          <option value="Unfit">Unfit</option>
        </select>
      </div>
      <div class="ls-fg">
        <label>Source</label>
        <select v-model="sampleType">
          <option value="">All Sources</option>
          <option value="PHE">PHE</option>
          <option value="Private">Private / Client</option>
        </select>
      </div>
      <div class="ls-filter-actions">
        <button class="ls-btn ls-btn-clear" @click="clearFilters">✕ Clear Filters</button>
      </div>
    </div>

    <!-- Error banner -->
    <div v-if="errorMsg" class="ls-error">⚠ {{ errorMsg }}</div>

    <!-- Table -->
    <div class="ls-table-wrap">
      <table class="ls-table">
        <thead>
          <tr>
            <th style="width:48px">Sr No</th>
            <th>Sample ID</th>
            <th>WSS / Client</th>
            <th>District</th>
            <th>Test Type</th>
            <th>Collection Point</th>
            <th>Collected By</th>
            <th>Collection Date</th>
            <th style="width:80px;text-align:center">Result</th>
            <th style="width:110px">Action</th>
          </tr>
        </thead>
        <tbody>
          <!-- Skeleton shimmer rows during initial load -->
          <template v-if="loading">
            <tr v-for="n in 8" :key="'ls-sk-' + n" class="ls-sk-row">
              <td><div class="ls-sk" style="width:24px"></div></td>
              <td><div class="ls-sk" style="width:110px"></div></td>
              <td><div class="ls-sk" style="width:160px"></div></td>
              <td><div class="ls-sk" style="width:90px"></div></td>
              <td><div class="ls-sk" style="width:80px"></div></td>
              <td><div class="ls-sk" style="width:100px"></div></td>
              <td><div class="ls-sk" style="width:110px"></div></td>
              <td><div class="ls-sk" style="width:80px"></div></td>
              <td style="text-align:center"><div class="ls-sk ls-sk-pill" style="margin:0 auto"></div></td>
              <td><div class="ls-sk ls-sk-btn"></div></td>
            </tr>
          </template>

          <!-- Empty state -->
          <tr v-else-if="!filtered.length">
            <td colspan="10" class="ls-empty">
              {{ samples.length === 0 ? 'No samples have been registered for this lab yet.' : 'No samples match the current filters.' }}
            </td>
          </tr>

          <!-- Real rows -->
          <tr v-else v-for="(row, i) in filtered" :key="row.id" :class="i % 2 === 1 ? 'alt' : ''">
            <td class="mono">{{ i + 1 }}</td>
            <td class="mono ls-fw">{{ row.slug }}</td>
            <td>{{ row.wss }}</td>
            <td>{{ row.district }}</td>
            <td>
              <span class="ls-type-pill">{{ row.testType }}</span>
            </td>
            <td>{{ row.point }}</td>
            <td>{{ row.collectedBy }}</td>
            <td class="mono">{{ row.sampledAt }}</td>
            <td style="text-align:center">
              <span class="ls-result-pill" :style="resultStyle(row.result)">{{ row.result }}</span>
            </td>
            <td>
              <button class="ls-btn ls-btn-pri ls-btn-sm" @click="viewSample(row)">👁 View</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.ls-page {
  padding: 14px 22px 30px;
  font-family: 'DM Sans', sans-serif;
}

/* Header */
.ls-head {
  display: flex; align-items: flex-end; justify-content: space-between;
  margin-bottom: 14px; gap: 12px; flex-wrap: wrap;
}
.ls-title { font-size: 18px; font-weight: 700; color: #0f172a; }
.ls-sub   { font-size: 12px; color: #64748b; margin-top: 3px; }

/* Filters */
.ls-filters {
  display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;
  background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;
  padding: 12px 14px; margin-bottom: 12px;
}
.ls-fg        { display: flex; flex-direction: column; gap: 4px; min-width: 130px; }
.ls-fg-search { flex: 1; min-width: 220px; }
.ls-fg label  { font-size: 10.5px; font-weight: 600; color: #64748b; letter-spacing: .03em; text-transform: uppercase; }
.ls-fg input, .ls-fg select {
  border: 1px solid #cbd5e1; border-radius: 5px; padding: 6px 9px;
  font-size: 12.5px; font-family: inherit; background: #fff; outline: none;
  transition: border-color .12s;
}
.ls-fg input:focus, .ls-fg select:focus { border-color: #2563eb; }
.ls-filter-actions { display: flex; gap: 6px; }

/* Buttons */
.ls-btn {
  border: 1px solid transparent; border-radius: 5px; padding: 7px 14px;
  font-size: 12px; font-weight: 600; cursor: pointer;
  display: inline-flex; align-items: center; gap: 5px; transition: all .12s;
  font-family: inherit;
}
.ls-btn-sm    { padding: 5px 10px; font-size: 11.5px; }
.ls-btn-pri   { background: #2563eb; color: #fff; }
.ls-btn-pri:hover:not(:disabled) { background: #1d4ed8; }
.ls-btn-pri:disabled { background: #94a3b8; cursor: not-allowed; }
.ls-btn-ghost { background: #f1f5f9; color: #334155; }
.ls-btn-ghost:hover:not(:disabled) { background: #e2e8f0; }
.ls-btn-clear {
  background: #fff; color: #dc2626; border: 1px solid #fca5a5;
}
.ls-btn-clear:hover:not(:disabled) { background: #fef2f2; border-color: #dc2626; }

/* Error banner */
.ls-error {
  background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b;
  padding: 9px 14px; border-radius: 6px; font-size: 12.5px; margin-bottom: 10px;
}

/* Table */
.ls-table-wrap {
  background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;
  overflow-x: auto;
}
.ls-table       { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.ls-table thead { background: #0f172a; }
.ls-table thead th {
  color: #fff; padding: 10px 12px; text-align: left;
  font-size: 11.5px; font-weight: 700; white-space: nowrap;
}
.ls-table tbody td { padding: 9px 12px; border-bottom: 1px solid #f1f5f9; color: #1e293b; }
.ls-table tbody tr:hover td { background: #f8fafc; }
.ls-table tbody tr.alt td   { background: #fbfcfe; }
.ls-table tbody tr.alt:hover td { background: #f4f6fa; }

.mono   { font-family: 'DM Mono', monospace; font-variant-numeric: tabular-nums; }
.ls-fw  { font-weight: 600; color: #0f172a; }

.ls-empty { text-align: center; padding: 30px 12px; color: #94a3b8; font-style: italic; }

/* Pills */
.ls-type-pill {
  display: inline-block; background: #dbeafe; color: #1d4ed8;
  border-radius: 11px; padding: 2px 8px; font-size: 11px; font-weight: 600;
}
.ls-result-pill {
  display: inline-block; border-radius: 11px; padding: 2px 9px;
  font-size: 10.5px; font-weight: 600; white-space: nowrap;
}

/* Skeleton */
.ls-sk-row { background: transparent !important; }
.ls-sk-row:hover td { background: transparent !important; }
.ls-sk {
  display: inline-block; height: 12px; border-radius: 4px;
  background: linear-gradient(90deg, #eef2f7 0%, #f7fafc 50%, #eef2f7 100%);
  background-size: 200% 100%;
  animation: ls-shimmer 1.4s infinite ease-in-out;
}
.ls-sk-pill { width: 50px; height: 16px; border-radius: 10px; }
.ls-sk-btn  { width: 70px; height: 22px; border-radius: 4px; }
@keyframes ls-shimmer {
  0%   { background-position: -200% 0; }
  100% { background-position:  200% 0; }
}
</style>

<style>
/* Toast transition lives globally so the Teleport target picks it up. */
.toast-slide-enter-active,
.toast-slide-leave-active { transition: all 0.3s ease; }
.toast-slide-enter-from,
.toast-slide-leave-to     { opacity: 0; transform: translateX(60px); }
</style>
