<script setup>
import { ref, computed, reactive, watch, onMounted } from 'vue'
import { api } from '../../../services/api.js'
import { useQcBalance } from '../../../composables/useQcBalance.js'

// ── Queue state ───────────────────────────────────────────────────────
const loading      = ref(false)
const errorMsg     = ref('')
const successMsg   = ref('')
const queue        = ref([])
const searchText   = ref('')
const typeFilter   = ref('')
const statusFilter = ref('')

// ── Modal state ───────────────────────────────────────────────────────
const showModal    = ref(false)
const showQcModal  = ref(false)
const modalLoading = ref(false)
const saveLoading  = ref(false)
const currentRow   = ref(null)   // queue row
const sampleDetail = ref(null)   // full sample from GET /water-samples/{id}
const activeTestId = ref(null)   // WaterSampleTest.id for the active round

// ── Map queue response row ────────────────────────────────────────────
function mapRow(s) {
  // current_status is an integer enum from backend — convert to string label
  const statusMap = { 1: 'Pending', 2: 'Fit', 3: 'Unfit', 4: 'In Progress', 5: 'Closed' }
  const rawStatus = s.current_status
  const statusStr = rawStatus !== null && rawStatus !== undefined
    ? (statusMap[rawStatus] ?? String(rawStatus))
    : (s.result || 'Pending')

  const round = s.current_round || 0

  return {
    id:          s.id,
    slug:        s.slug || String(s.id),
    wss:         s.water_scheme?.name || s.waterScheme?.name || s.water_sample_address || '—',
    district:    s.district?.name || '—',
    type:        s.test_type || '—',
    point:       s.sampling_point || '—',
    by:          s.collected_by || s.created_by_user?.name || s.createdByUser?.name || '—',
    date:        s.sampled_at ? formatDate(s.sampled_at.split(' ')[0]) : '—',
    status:      statusStr,
    round,
    isRetest:    round > 0,
    isPT:        (s.collectable_type || '').toUpperCase() === 'PT' || (s.slug || '').startsWith('PT/'),
    ptProgramme: s.water_sample_address || '',
  }
}

function formatDate(dateStr) {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  if (isNaN(d)) return dateStr
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: '2-digit' }).replace(/ /g, '-')
}

async function loadQueue() {
  loading.value  = true
  errorMsg.value = ''
  try {
    const res  = await api.get('/water-samples-queue/0')
    // Handle both: data with water_samples (has results) and data without (empty)
    const payload = res.data?.data || res.data || {}
    const data = payload?.water_samples?.data
               || payload?.water_samples
               || (Array.isArray(payload) ? payload : null)
               || []
    queue.value = Array.isArray(data) ? data.map(mapRow) : []
  } catch (e) {
    errorMsg.value = 'Failed to load queue: ' + (e.response?.data?.message || e.message)
    console.error(e)
  } finally {
    loading.value = false
  }
}

const pendingCount = computed(() =>
  queue.value.filter(r => ['Pending', 'pending', 'In Progress', 'in_progress'].includes(r.status)).length
)

const filtered = computed(() => queue.value.filter(r => {
  const q  = searchText.value.toLowerCase()
  const ok1 = !q || r.slug.toLowerCase().includes(q) || r.wss.toLowerCase().includes(q)
  const ok2 = !typeFilter.value   || r.type === typeFilter.value
  const ok3 = !statusFilter.value || r.status.toLowerCase() === statusFilter.value.toLowerCase()
  return ok1 && ok2 && ok3
}))

// ── Analysis form ─────────────────────────────────────────────────────
// testMap: { [water_quality_parameter]: { test_id, value } }
const testMap   = ref({})   // populated from waterSampleDetails
const remarks   = ref('')
const isDraft   = ref(false)

// Convenience refs for QC balance (populated from testMap when chemical tests exist)
const qcInputs = reactive({
  hco3: 0, so4: 0, cl: 0, no3: 0, co3: 0, ca: 0, mg: 0, na: 0, k: 0,
})

// Keep qcInputs in sync with testMap values
watch(testMap, (map) => {
  const get = (param) => parseFloat(map[param]?.value) || 0
  qcInputs.hco3 = get('Bicarbonate')   || get('Total Alkalinity') || get('HCO3')
  qcInputs.so4  = get('Sulphates')     || get('SO4')
  qcInputs.cl   = get('Chlorides')     || get('Cl')
  qcInputs.no3  = get('Nitrates as NO3') || get('Nitrates')       || get('NO3')
  qcInputs.co3  = get('Carbonate')     || get('CO3')
  qcInputs.ca   = get('Calcium Hardness') || get('Ca')
  qcInputs.na   = get('Sodium')        || get('Na')
  qcInputs.k    = get('Potassium')     || get('K')
  // Mg = Total Hardness - Calcium Hardness
  const th = get('Total Hardness')
  const ca = get('Calcium Hardness') || get('Ca')
  qcInputs.mg   = Math.max(0, th - ca)
}, { deep: true })

const qc = useQcBalance(qcInputs)

// Derived: does this sample have chemical tests?
const hasChemical = computed(() => {
  if (!sampleDetail.value) return false
  return sampleDetail.value.water_sample_details?.some(d =>
    d.test?.type === 'Physical & Chemical' || d.test?.type === 'Chemical'
  )
})

// ── Open analysis modal ───────────────────────────────────────────────
async function openAnalysis(row) {
  currentRow.value  = row
  sampleDetail.value = null
  activeTestId.value = null
  testMap.value      = {}
  remarks.value      = ''
  isDraft.value      = false
  errorMsg.value     = ''
  showModal.value    = true
  modalLoading.value = true

  try {
    // 1. Load full sample with test details
    const res = await api.get(`/water-samples/${row.id}`)
    sampleDetail.value = res.data

    // 2. Find the active (pending/in_progress) test round
    const tests = sampleDetail.value.water_sample_tests || sampleDetail.value.tests || []
    const activeTest = tests.find(t =>
      ['Pending', 'pending', 'In Progress', 'in_progress'].includes(t.status)
    ) || tests[tests.length - 1]

    if (activeTest) {
      activeTestId.value = activeTest.id

      // 3. Call startAnalysis to mark it in_progress
      try {
        await api.patch(`/water-sample-tests/${row.id}/start`)
      } catch (e) {
        // Non-fatal — may already be in_progress
        console.warn('startAnalysis:', e.response?.data?.message || e.message)
      }
    }

    // 4. Build testMap from waterSampleDetails
    const details = sampleDetail.value.water_sample_details || []
    const map = {}
    for (const d of details) {
      const param = d.test?.water_quality_parameter
      if (param) {
        map[param] = {
          test_id: d.test_id,
          value:   d.analysis_result === 'NT' ? '' : (d.analysis_result ?? ''),
          unit:    d.test?.unit || '',
          type:    d.test?.type || '',
          criteria: d.test?.criteria,
        }
      }
    }
    testMap.value = map

  } catch (e) {
    errorMsg.value = 'Failed to load sample details: ' + (e.response?.data?.message || e.message)
    console.error(e)
  } finally {
    modalLoading.value = false
  }
}

// ── Group tests by type for display ──────────────────────────────────
const physicalTests  = computed(() => Object.entries(testMap.value).filter(([, v]) => v.type === 'Physical'))
const chemicalTests  = computed(() => Object.entries(testMap.value).filter(([, v]) =>
  v.type === 'Physical & Chemical' || v.type === 'Chemical'
))
const microbialTests = computed(() => Object.entries(testMap.value).filter(([, v]) =>
  v.type === 'Microbiological(MF)' || v.type === 'Microbiological(Kit)' || v.type?.toLowerCase().includes('micro')
))
const onDemandTests  = computed(() => Object.entries(testMap.value).filter(([, v]) => v.type === 'On Demand'))

const isMFMethod = computed(() => {
  const tests = sampleDetail.value?.water_sample_tests || sampleDetail.value?.tests || []
  const t = tests.find(t => ['Pending','pending','In Progress','in_progress'].includes(t.status))
    || tests[tests.length - 1]
  const desired = Array.isArray(t?.desired_test) ? t.desired_test : []
  return !desired.includes('Microbiological(Kit)')
})

// ── Auto-calc TDS from EC ─────────────────────────────────────────────
const ecValue  = computed(() => parseFloat(testMap.value['EC']?.value || testMap.value['Electrical Conductivity']?.value) || 0)
const tdsAuto  = computed(() => (ecValue.value * 0.6).toFixed(0))

// Sync TDS auto-calc into testMap
watch(tdsAuto, (val) => {
  if (testMap.value['TDS']) testMap.value['TDS'].value = val
  if (testMap.value['Total Dissolved Solids']) testMap.value['Total Dissolved Solids'].value = val
})

// Auto-calc Mg Hardness
const thValue  = computed(() => parseFloat(testMap.value['Total Hardness']?.value) || 0)
const caValue  = computed(() => parseFloat(testMap.value['Calcium Hardness']?.value) || 0)
const mgAuto   = computed(() => Math.max(0, thValue.value - caValue.value).toFixed(0))

watch(mgAuto, (val) => {
  if (testMap.value['Magnesium Hardness']) testMap.value['Magnesium Hardness'].value = val
  if (testMap.value['Mg Hardness'])        testMap.value['Mg Hardness'].value = val
})

// ── Build analysis_results payload ───────────────────────────────────
function buildPayload() {
  const analysis_results = Object.values(testMap.value)
    .filter(v => v.test_id)
    .map(v => ({
      test_id:         v.test_id,
      analysis_result: v.value === '' ? 'NT' : String(v.value),
    }))

  return {
    analysis_results,
    is_draft: isDraft.value,
    remarks:  remarks.value || 'N/A',
  }
}

// ── Save flow ─────────────────────────────────────────────────────────
async function saveAnalysis() {
  if (hasChemical.value) {
    showQcModal.value = true
  } else {
    await submitResults()
  }
}

async function submitResults() {
  if (!currentRow.value) return
  saveLoading.value = true
  errorMsg.value    = ''
  try {
    const payload = buildPayload()
    await api.put(`/water-sample-tests/${currentRow.value.id}/analyze`, payload)
    successMsg.value  = `✅ Analysis saved for ${currentRow.value.slug}`
    showQcModal.value = false
    showModal.value   = false
    await loadQueue()
  } catch (e) {
    const errs = e.response?.data?.errors
    errorMsg.value = errs
      ? Object.values(errs).flat().join(' | ')
      : (e.response?.data?.message || e.message || 'Error saving analysis')
    console.error('Submit error:', e.response?.data || e)
  } finally {
    saveLoading.value = false
  }
}

async function saveDraft() {
  isDraft.value = true
  await submitResults()
  isDraft.value = false
}

async function sendForReanalysis() {
  if (!currentRow.value) return
  saveLoading.value = true
  try {
    // Force result as Unfit — QC failed, sample needs re-collection
    const payload = { ...buildPayload(), is_draft: false, force_unfit: true }
    await api.put(`/water-sample-tests/${currentRow.value.id}/analyze`, payload)
    successMsg.value  = `❌ Sample ${currentRow.value.slug} marked Unfit — sent to trail`
    showQcModal.value = false
    showModal.value   = false
    await loadQueue()
  } catch (e) {
    errorMsg.value = e.response?.data?.message || e.message || 'Error'
  } finally {
    saveLoading.value = false
  }
}

async function overrideAndAccept() {
  if (!currentRow.value) return
  saveLoading.value = true
  try {
    // Force Fit — Override & Accept, current_status = 2
    const payload = { ...buildPayload(), is_draft: false, force_fit: true }
    await api.put(`/water-sample-tests/${currentRow.value.id}/analyze`, payload)
    successMsg.value  = `✅ Analysis accepted for ${currentRow.value.slug} — marked Done`
    showQcModal.value = false
    showModal.value   = false
    await loadQueue()
  } catch (e) {
    errorMsg.value = e.response?.data?.message || e.message || 'Error'
  } finally {
    saveLoading.value = false
  }
}

// ── Helpers ───────────────────────────────────────────────────────────
function statusClass(status) {
  const s = status?.toLowerCase()
  if (s === 'fit')         return 'r-green'
  if (s === 'unfit')       return 'r-red'
  if (s === 'in_progress' || s === 'in progress') return 'r-amber'
  return 'r-blue'
}

function statusLabel(status) {
  const map = {
    'pending':     '⏳ Pending',
    'in_progress': '🔬 In Progress',
    'in progress': '🔬 In Progress',
    'fit':         '✅ Fit',
    'unfit':       '❌ Unfit',
    'completed':   '✅ Done',
  }
  return map[status?.toLowerCase()] || status
}

onMounted(loadQueue)
</script>

<template>
  <div>
    <!-- Messages -->
    <div v-if="successMsg" style="background:#d1fae5;border:1px solid #6ee7b7;border-radius:5px;padding:10px 14px;margin-bottom:10px;font-size:13px;color:#065f46">
      {{ successMsg }} <button @click="successMsg=''" style="float:right;background:none;border:none;cursor:pointer;font-size:13px">✕</button>
    </div>
    <div v-if="errorMsg && !showModal" style="background:#fee2e2;border:1px solid #fca5a5;border-radius:5px;padding:10px 14px;margin-bottom:10px;font-size:13px;color:#991b1b">
      {{ errorMsg }} <button @click="errorMsg=''" style="float:right;background:none;border:none;cursor:pointer;font-size:13px">✕</button>
    </div>

    <!-- Queue header -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;flex-wrap:wrap;gap:8px">
      <div>
        <h2 style="margin:0 0 4px;font-size:15px;font-weight:700">Pending Analysis Queue</h2>
        <span style="font-size:11px;background:#dbeafe;color:#1e40af;border:1px solid #93c5fd;border-radius:10px;padding:2px 10px;font-weight:600">
          Awaiting: {{ pendingCount }}
        </span>
      </div>
      <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
        <select v-model="typeFilter" style="border:1px solid var(--input-border);border-radius:4px;padding:5px 8px;font-size:11.5px;font-family:inherit">
          <option value="">All Test Types</option>
          <option>PCM</option><option>PC</option><option>M</option><option>P</option><option>C</option><option>PT</option>
        </select>
        <div style="position:relative">
          <span style="position:absolute;left:8px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:12px">🔍</span>
          <input type="text" v-model="searchText" placeholder="Sample ID or WSS…"
            style="border:1px solid var(--input-border);border-radius:4px;padding:5px 10px 5px 26px;font-size:11.5px;font-family:inherit;width:190px">
        </div>
        <button class="btn btn-sec btn-sm" @click="loadQueue" :disabled="loading">
          {{ loading ? '⏳' : '↺ Refresh' }}
        </button>
      </div>
    </div>

    <!-- Queue table -->
    <div class="tbl-wrap" style="margin-bottom:14px">
      <div v-if="loading" style="text-align:center;padding:30px;color:var(--muted);font-size:13px">⏳ Loading queue…</div>
      <table v-else style="font-size:12px">
        <thead>
          <tr style="background:var(--navy);color:#fff">
            <th style="color:#fff">Sample ID</th>
            <th style="color:#fff">WSS / Client</th>
            <th style="color:#fff">District</th>
            <th style="color:#fff;text-align:center">Test Type</th>
            <th style="color:#fff">Collection Point</th>
            <th style="color:#fff">Collected By</th>
            <th style="color:#fff">Collection Date</th>
            <th style="color:#fff">Action</th>
          </tr>
        </thead>
        <tbody>
          <!-- Empty state row -->
          <tr v-if="!filtered.length">
            <td colspan="8" style="text-align:center;padding:32px;color:var(--muted);font-size:13px">
              {{ loading ? '' : 'No samples in queue. Register a sample first.' }}
            </td>
          </tr>

          <tr v-for="(row, i) in filtered" :key="row.id"
              :class="i % 2 === 1 ? 'alt' : ''"
              :style="row.isPT ? 'background:#f5f0ff' : ''">

            <!-- Sample ID -->
            <td class="mono" style="font-size:11.5px" :style="row.isPT ? 'color:#7c3aed;font-weight:700' : ''">
              <template v-if="row.isPT">
                <span style="font-size:10px;margin-right:4px">🧫</span>
                <span style="font-size:10px;background:#ede9fe;color:#6d28d9;border-radius:3px;padding:1px 5px;margin-right:4px;font-weight:600">PT Sample</span>
              </template>
              {{ row.slug }}
            </td>

            <!-- WSS / Client -->
            <td>
              <template v-if="row.isPT">
                <span style="font-size:11px;color:#7c3aed">{{ row.ptProgramme || row.wss }}</span>
              </template>
              <template v-else>{{ row.wss }}</template>
            </td>

            <!-- District -->
            <td>{{ row.isPT ? '—' : row.district }}</td>

            <!-- Test Type badge -->
            <td style="text-align:center">
              <span class="rag"
                :style="row.isPT
                  ? 'background:#7c3aed;color:#fff;border-color:#7c3aed'
                  : row.type === 'PCM' ? 'background:#1d4ed8;color:#fff;border-color:#1d4ed8'
                  : row.type === 'M'   ? 'background:#0891b2;color:#fff;border-color:#0891b2'
                  : row.type === 'PC'  ? 'background:#0d9488;color:#fff;border-color:#0d9488'
                  : 'background:#6b7280;color:#fff;border-color:#6b7280'">
                {{ row.isPT ? 'PT' : row.type }}
              </span>
            </td>

            <!-- Collection Point -->
            <td>{{ row.isPT ? 'N/A — Blind' : row.point }}</td>

            <!-- Collected By -->
            <td>{{ row.by }}</td>

            <!-- Collection Date -->
            <td style="white-space:nowrap">{{ row.date }}</td>

            <!-- Action -->
            <td style="white-space:nowrap">
              <template v-if="row.isPT">
                <button class="btn btn-xs"
                  style="background:#7c3aed;color:#fff;border:none;font-size:11px"
                  @click="openAnalysis(row)">
                  🧫 Enter PT Results
                </button>
              </template>
              <template v-else-if="['pending','in_progress','in progress'].includes(row.status?.toLowerCase())">
                <button class="btn btn-pri btn-xs" @click="openAnalysis(row)"
                  style="background:#1d4ed8;border-color:#1d4ed8;font-size:11px">
                  {{ row.isRetest ? `▶ Retest R${row.round}` : '▶ Start Analysis' }}
                </button>
              </template>
              <template v-else-if="row.status?.toLowerCase() === 'unfit'">
                <button class="btn btn-xs" style="background:#dc2626;color:#fff;border:none;font-size:11px" @click="openAnalysis(row)">🔄 Re-Analyse</button>
              </template>
              <template v-else>
                <span class="rag r-green" style="font-size:11px">✅ Done</span>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ── ANALYSIS MODAL ── -->
    <Teleport to="body">
      <div v-if="showModal" @click.self="showModal = false"
           style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:3200;align-items:flex-start;justify-content:center;overflow-y:auto;padding:16px">
        <div style="background:#fff;border-radius:8px;width:100%;max-width:1340px;padding:20px 26px;position:relative;margin:auto">

          <button @click="showModal = false"
            style="position:absolute;top:14px;right:16px;background:rgba(0,0,0,.07);border:none;border-radius:5px;padding:4px 10px;cursor:pointer;font-size:13px">✕</button>

          <h2 style="margin-bottom:4px">🔬 Analysis Entry</h2>

          <!-- Error inside modal -->
          <div v-if="errorMsg" style="background:#fee2e2;border:1px solid #fca5a5;border-radius:5px;padding:8px 14px;margin-bottom:10px;font-size:12.5px;color:#991b1b">
            {{ errorMsg }}
          </div>

          <!-- Loading state -->
          <div v-if="modalLoading" style="text-align:center;padding:40px;color:var(--muted)">
            ⏳ Loading sample details…
          </div>

          <template v-else-if="sampleDetail">
            <!-- Sample info bar -->
            <div style="background:var(--sky2);border:1px solid var(--sky);border-radius:5px;padding:8px 14px;margin-bottom:14px;font-size:12px;color:var(--navy2);display:flex;gap:16px;flex-wrap:wrap">
              <span>🔖 <b>{{ currentRow?.slug }}</b></span>
              <span>🏗 {{ currentRow?.wss }}</span>
              <span>📍 {{ currentRow?.district }}</span>
              <span>🧪 Test Type: <b>{{ currentRow?.type }}</b></span>
              <span>📅 Sampled: {{ currentRow?.date }}</span>
            </div>

            <!-- 3-column parameter grid -->
            <div style="display:grid;grid-template-columns:1fr 1.8fr 1fr;gap:14px;align-items:start;margin-bottom:14px">

              <!-- Physical -->
              <div v-if="physicalTests.length" style="background:#f4f8ff;border:1px solid #c5d8f5;border-radius:7px;padding:14px">
                <div style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--blue);margin-bottom:10px;padding-bottom:5px;border-bottom:2px solid var(--sky)">
                  🔵 Physical Parameters
                </div>
                <div style="display:grid;grid-template-columns:1fr;gap:8px">
                  <div v-for="[param, info] in physicalTests" :key="param" class="fg2">
                    <label>{{ param }} <span v-if="info.unit" style="color:var(--muted);font-size:10px">({{ info.unit }})</span></label>
                    <!-- TDS is auto-calculated -->
                    <template v-if="param.toLowerCase().includes('tds') || param.toLowerCase().includes('total dissolved')">
                      <div style="border:1px solid var(--input-border);border-radius:4px;padding:6px 9px;background:var(--sky2);color:var(--navy2);font-weight:600">
                        {{ tdsAuto }} mg/L <span style="font-size:10px;color:var(--muted)">(auto: EC × 0.6)</span>
                      </div>
                    </template>
                    <!-- Taste / Odour as select -->
                    <template v-else-if="param.toLowerCase() === 'taste'">
                      <select v-model="testMap[param].value">
                        <option value="Agreeable">Agreeable</option>
                        <option value="Disagreeable">Disagreeable</option>
                      </select>
                    </template>
                    <template v-else-if="param.toLowerCase() === 'odour'">
                      <select v-model="testMap[param].value">
                        <option value="None">None</option>
                        <option value="Earthy">Earthy</option>
                        <option value="Chlorinous">Chlorinous</option>
                        <option value="Sulphurous">Sulphurous</option>
                        <option value="Musty">Musty</option>
                      </select>
                    </template>
                    <template v-else>
                      <input type="number" step="any" v-model="testMap[param].value" placeholder="NT">
                    </template>
                  </div>
                </div>
              </div>
              <div v-else style="background:#f4f8ff;border:1px dashed #c5d8f5;border-radius:7px;padding:14px;color:var(--muted);font-size:12px;text-align:center">
                No physical tests for this sample
              </div>

              <!-- Chemical -->
              <div v-if="chemicalTests.length" style="background:#fff8f2;border:1px solid #f4c08a;border-radius:7px;padding:14px">
                <div style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#b45000;margin-bottom:10px;padding-bottom:5px;border-bottom:2px solid #f4a236">
                  🟠 Chemical Parameters
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                  <div v-for="[param, info] in chemicalTests" :key="param" class="fg2">
                    <label>{{ param }} <span v-if="info.unit" style="color:var(--muted);font-size:10px">({{ info.unit }})</span></label>
                    <!-- Mg Hardness auto-calc -->
                    <template v-if="param.toLowerCase().includes('magnesium') || param.toLowerCase() === 'mg hardness'">
                      <div style="border:1px solid var(--input-border);border-radius:4px;padding:6px 9px;background:var(--sky2);color:var(--navy2)">
                        {{ mgAuto }} mg/L <span style="font-size:10px;color:var(--muted)">(auto: TH − Ca)</span>
                      </div>
                    </template>
                    <template v-else>
                      <input type="number" step="any" v-model="testMap[param].value" placeholder="NT">
                    </template>
                  </div>
                </div>
              </div>
              <div v-else style="background:#fff8f2;border:1px dashed #f4c08a;border-radius:7px;padding:14px;color:var(--muted);font-size:12px;text-align:center">
                No chemical tests for this sample
              </div>

              <!-- Right column: Microbial + On Demand + Remarks -->
              <div style="display:flex;flex-direction:column;gap:12px">

                <!-- Microbial -->
                <div v-if="microbialTests.length" style="background:#fff5f5;border:1px solid #f5c6c6;border-radius:7px;padding:14px">
                  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;padding-bottom:5px;border-bottom:2px solid #f5c6c6">
                    <div style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#b71c1c">🔴 Microbiological</div>
                    <span style="font-size:10.5px;font-weight:600;padding:2px 9px;border-radius:10px"
                      :style="isMFMethod ? 'background:#e3f0ff;color:#1a4a8a' : 'background:#fce4e4;color:#b71c1c'">
                      {{ isMFMethod ? '📊 MF Method' : '🧪 Kit Method' }}
                    </span>
                  </div>
                  <div style="display:grid;grid-template-columns:1fr;gap:8px">
                    <div v-for="[param, info] in microbialTests" :key="param" class="fg2">
                      <label>{{ param }} <span v-if="info.unit" style="color:var(--muted);font-size:10px">({{ info.unit }})</span></label>
                      <!-- Kit method: pos/neg -->
                      <template v-if="!isMFMethod">
                        <select v-model="testMap[param].value"
                          :style="testMap[param].value === '+ve' ? 'color:#c0392b;font-weight:700' : testMap[param].value === '-ve' ? 'color:#1a7a3f;font-weight:700' : ''">
                          <option value="">— Select —</option>
                          <option value="-ve">−Ve (Absent / Safe)</option>
                          <option value="+ve">+Ve (Present / Unsafe)</option>
                        </select>
                      </template>
                      <template v-else>
                        <input type="number" step="any" min="0" v-model="testMap[param].value" placeholder="0">
                      </template>
                    </div>
                  </div>
                </div>

                <!-- On Demand tests -->
                <div v-if="onDemandTests.length" style="background:#f0fdf4;border:1px solid #86efac;border-radius:7px;padding:14px">
                  <div style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#166534;margin-bottom:8px">🟢 On Demand Tests</div>
                  <div style="display:grid;grid-template-columns:1fr;gap:8px">
                    <div v-for="[param, info] in onDemandTests" :key="param" class="fg2">
                      <label>{{ param }} <span v-if="info.unit" style="color:var(--muted);font-size:10px">({{ info.unit }})</span></label>
                      <input type="number" step="any" v-model="testMap[param].value" placeholder="NT">
                    </div>
                  </div>
                </div>

                <!-- Remarks -->
                <div style="background:#f9f9f9;border:1px solid var(--border);border-radius:7px;padding:14px">
                  <div style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin-bottom:8px">📝 Remarks</div>
                  <textarea v-model="remarks" rows="4"
                    style="width:100%;resize:vertical;font-family:inherit;font-size:12px;padding:6px 9px;border:1px solid var(--input-border);border-radius:4px;box-sizing:border-box"
                    placeholder="Any observations or notes…"></textarea>
                </div>
              </div>
            </div>

            <!-- Live QC Balance Panel -->
            <div v-if="chemicalTests.length" style="border:2px solid #e2e8f0;border-radius:8px;padding:12px 16px;margin-bottom:12px;background:#f8fafc">
              <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--navy2)">⚖ Cation–Anion Balance QC</div>
                <div style="font-size:12px;font-weight:700;padding:3px 14px;border-radius:12px"
                  :style="qc.pass.value ? 'background:#16a34a;color:#fff' : 'background:#dc2626;color:#fff'">
                  {{ qc.pass.value ? '✔ QC PASS' : '✖ QC FAIL' }} — {{ qc.absPct.value.toFixed(2) }}%
                </div>
              </div>
              <div style="display:flex;gap:10px;font-size:12px">
                <div style="flex:1;background:#e0f2fe;border-radius:5px;padding:6px 12px;text-align:center">
                  <span style="color:#0369a1;font-weight:600">Σ Anions</span>
                  <span style="font-weight:800;color:#0c4a6e;margin-left:8px">{{ qc.anions.value.total.toFixed(3) }} meq/L</span>
                </div>
                <div style="flex:1;background:#f3e8ff;border-radius:5px;padding:6px 12px;text-align:center">
                  <span style="color:#7c3aed;font-weight:600">Σ Cations</span>
                  <span style="font-weight:800;color:#4c1d95;margin-left:8px">{{ qc.cations.value.total.toFixed(3) }} meq/L</span>
                </div>
                <div style="flex:1;background:#f1f5f9;border-radius:5px;padding:6px 12px;text-align:center">
                  <span style="color:var(--muted);font-weight:600">% Diff</span>
                  <span style="font-weight:800;font-size:16px;margin-left:8px"
                    :style="qc.pass.value ? 'color:#16a34a' : 'color:#dc2626'">{{ qc.pct.value.toFixed(2) }}%</span>
                  <span style="font-size:10px;color:var(--muted);margin-left:4px">threshold: &lt;3%</span>
                </div>
              </div>
            </div>

            <!-- Actions -->
            <div style="display:flex;gap:8px;border-top:1px solid var(--border);padding-top:12px;align-items:center;flex-wrap:wrap">
              <button class="btn btn-pri" @click="saveAnalysis" :disabled="saveLoading">
                {{ saveLoading ? '⏳ Saving…' : '💾 Save & Run QC Check' }}
              </button>
              <button class="btn btn-sec" @click="saveDraft" :disabled="saveLoading" style="font-size:11.5px">
                📋 Save as Draft
              </button>
              <button class="btn btn-sec" @click="showModal = false">✕ Cancel</button>
              <span v-if="chemicalTests.length" style="font-size:11px;color:var(--muted);margin-left:6px">
                {{ qc.pass.value ? '✔ Balance within limit — ready to save' : '⚠ Balance exceeds 3% — QC check will flag this' }}
              </span>
            </div>
          </template>
        </div>
      </div>
    </Teleport>

    <!-- ── QC RESULT MODAL ── -->
    <Teleport to="body">
      <div v-if="showQcModal"
           style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:3400;align-items:center;justify-content:center">
        <div style="background:#fff;border-radius:12px;width:100%;max-width:560px;overflow:hidden;box-shadow:0 16px 60px rgba(0,0,0,.35);margin:16px">
          <div :style="qc.pass.value ? 'background:linear-gradient(135deg,#14532d,#16a34a)' : 'background:linear-gradient(135deg,#7f1d1d,#dc2626)'"
               style="padding:20px 28px;display:flex;align-items:center;gap:16px;color:#fff">
            <div style="font-size:48px">{{ qc.pass.value ? '✅' : '❌' }}</div>
            <div>
              <div style="font-size:20px;font-weight:800;margin-bottom:3px">{{ qc.pass.value ? 'QC Check Passed' : 'QC Check Failed' }}</div>
              <div style="font-size:13px;opacity:.85">
                {{ qc.pass.value
                  ? 'Cation–anion balance is within the ±3% tolerance.'
                  : 'Cation–anion balance exceeds the ±3% threshold.' }}
              </div>
            </div>
          </div>
          <div style="padding:20px 28px">
            <div :style="qc.pass.value ? 'background:#f0fdf4;border:1px solid #86efac' : 'background:#fef2f2;border:1px solid #fca5a5'"
                 style="border-radius:8px;padding:14px 18px;margin-bottom:20px;font-size:12.5px;line-height:1.7">
              <b>{{ qc.pass.value ? '✔ PASS — Balance Acceptable' : '✖ FAIL — Re-Analysis Recommended' }}</b><br>
              % difference: <b>{{ qc.absPct.value.toFixed(2) }}%</b>
              {{ qc.pass.value ? '(within ±3% tolerance)' : '(exceeds ±3% threshold)' }}
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap">
              <button v-if="!qc.pass.value" class="btn" style="background:#dc2626;color:#fff;border-color:#dc2626;font-weight:700"
                @click="sendForReanalysis" :disabled="saveLoading">
                🔄 Send for Re-Analysis
              </button>
              <button v-if="!qc.pass.value" class="btn btn-sec" style="font-size:11px"
                @click="overrideAndAccept" :disabled="saveLoading">
                ⚠ Override &amp; Accept
              </button>
              <button v-if="qc.pass.value" class="btn" style="background:#16a34a;color:#fff;border-color:#16a34a;font-weight:700"
                @click="submitResults" :disabled="saveLoading">
                {{ saveLoading ? '⏳ Saving…' : '✅ Accept & Submit' }}
              </button>
              <button class="btn btn-sec" @click="showQcModal = false">Close</button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
