<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { reportService }   from '../../../services/reportService.js'
import { dropdownService } from '../../../services/dropdownService.js'
import { exportToXLSX }    from '../../../utils/exportHelpers.js'
import { useUserStore }    from '../../../stores/useUserStore.js'

const userStore  = useUserStore()
const loading    = ref(false)
const errorMsg   = ref('')
const generated  = ref(false)
const generatedAt = ref('')

// ── Dropdowns ──────────────────────────────────────────────────────────
const regions      = ref([])
const divisions    = ref([])
const circles      = ref([])
const districts    = ref([])
const phedDivisions = ref([])
const laboratories = ref([])
const parameters   = ref([])

// SRS-required date defaults: From = start of current financial year (Jul 1), To = today
function financialYearStart() {
  const now = new Date()
  const year = now.getMonth() >= 6 ? now.getFullYear() : now.getFullYear() - 1
  return `${year}-07-01`
}
function todayIso() {
  return new Date().toISOString().split('T')[0]
}

// ── Filters ────────────────────────────────────────────────────────────
const filters = ref({
  from_date:        financialYearStart(),
  to_date:          todayIso(),
  region_id:        '',
  division_id:      '',
  circle_id:        '',
  district_id:      '',
  phed_division_id: '',
  laboratory_id:    '',
  sample_type:      '',   // PHE / Private / PT — maps to collectable_type
  test_id:          '',   // specific parameter
})

// Cascaded dropdowns. NOTE: divisions.region_id is NULL in this DB, so we derive
// region→division indirectly via Region → Circles → Districts → Divisions.
const filteredDivisions = computed(() => {
  if (!filters.value.region_id) return divisions.value
  const regId     = String(filters.value.region_id)
  const circleIds = new Set(
    circles.value.filter(c => String(c.region_id) === regId).map(c => String(c.id))
  )
  const divisionIds = new Set(
    districts.value
      .filter(d => circleIds.has(String(d.circle_id)))
      .map(d => String(d.division_id))
  )
  return divisions.value.filter(d => divisionIds.has(String(d.id)))
})
const filteredCircles = computed(() =>
  filters.value.region_id
    ? circles.value.filter(c => String(c.region_id) === String(filters.value.region_id))
    : circles.value
)
const filteredDistricts = computed(() => {
  let list = districts.value
  if (filters.value.division_id) list = list.filter(d => String(d.division_id) === String(filters.value.division_id))
  if (filters.value.circle_id)   list = list.filter(d => String(d.circle_id)   === String(filters.value.circle_id))
  return list
})
const filteredPhedDivs = computed(() => {
  let list = phedDivisions.value
  if (filters.value.district_id) list = list.filter(p => String(p.district_id) === String(filters.value.district_id))
  if (filters.value.circle_id)   list = list.filter(p => String(p.circle_id)   === String(filters.value.circle_id))
  return list
})
const filteredLaboratories = computed(() => {
  let list = laboratories.value
  if (filters.value.district_id) list = list.filter(l => String(l.district_id) === String(filters.value.district_id))
  if (filters.value.division_id) list = list.filter(l => String(l.division_id) === String(filters.value.division_id))
  return list
})

// ── Report data ────────────────────────────────────────────────────────
const paramOverview     = ref([])
const districtBreakdown = ref([])
const kpTotals          = ref({ total_tested: 0, total_exceeding: 0, pct: 0 })

// ── View mode (per SRS §2.2 R-07) ──────────────────────────────────────
// 'all' — every active parameter (even with 0 exceeding)
// 'contamination' — only parameters where at least 1 sample exceeded its limit
const viewMode = ref('all')
const displayedParamOverview = computed(() =>
  viewMode.value === 'contamination'
    ? paramOverview.value.filter(p => p.exceeding > 0)
    : paramOverview.value
)

// ── Pagination (parameter overview table) ──────────────────────────────
const currentPage = ref(1)
const pageSize    = ref(10)
const totalPages  = computed(() =>
  Math.max(1, Math.ceil(displayedParamOverview.value.length / pageSize.value))
)
const pagedParamOverview = computed(() => {
  const start = (currentPage.value - 1) * pageSize.value
  return displayedParamOverview.value.slice(start, start + pageSize.value)
})
const pageStart = computed(() =>
  displayedParamOverview.value.length === 0 ? 0 : (currentPage.value - 1) * pageSize.value + 1
)
const pageEnd = computed(() =>
  Math.min(currentPage.value * pageSize.value, displayedParamOverview.value.length)
)
function goToPage(p) {
  if (p < 1 || p > totalPages.value) return
  currentPage.value = p
}
// Reset to page 1 when the underlying list shrinks/grows past the current page
watch([viewMode, paramOverview, pageSize], () => { currentPage.value = 1 })

// ── Load dropdowns ─────────────────────────────────────────────────────
async function loadDropdowns() {
  try {
    const [regRes, divRes, cirRes, distRes, phedRes, labRes, paramRes] = await Promise.all([
      dropdownService.getRegions(),
      dropdownService.getDivisions(),
      dropdownService.getCircles(),
      dropdownService.getDistricts(),
      dropdownService.getPhedDivisions(),
      dropdownService.getLaboratories(),
      dropdownService.getTestParameters(),
    ])
    regions.value       = regRes.data?.data   || regRes.data   || []
    divisions.value     = divRes.data?.data   || divRes.data   || []
    circles.value       = cirRes.data?.data   || cirRes.data   || []
    districts.value     = distRes.data?.data  || distRes.data  || []
    phedDivisions.value = phedRes.data?.data  || phedRes.data  || []
    laboratories.value  = labRes.data?.data   || labRes.data   || []
    parameters.value    = paramRes.data?.data || paramRes.data || []
  } catch (e) { console.error('Dropdown error:', e) }
}

// ── Client-side date validation ────────────────────────────────────────
const dateError = computed(() => {
  const f = filters.value.from_date
  const t = filters.value.to_date
  if (f && t && f > t) return 'From date must be on or before To date.'
  return ''
})

// ── Generate ───────────────────────────────────────────────────────────
async function generateReport() {
  if (dateError.value) {
    errorMsg.value  = dateError.value
    generated.value = false
    loading.value   = false
    return
  }

  loading.value   = true
  errorMsg.value  = ''
  generated.value = false
  paramOverview.value     = []
  districtBreakdown.value = []

  try {
    const payload = {}
    if (filters.value.from_date)        payload.from_date        = filters.value.from_date
    if (filters.value.to_date)          payload.to_date          = filters.value.to_date
    if (filters.value.region_id)        payload.region_id        = filters.value.region_id
    if (filters.value.division_id)      payload.division_id      = filters.value.division_id
    if (filters.value.circle_id)        payload.circle_id        = filters.value.circle_id
    if (filters.value.district_id)      payload.district_id      = filters.value.district_id
    if (filters.value.phed_division_id) payload.phed_division_id = filters.value.phed_division_id
    if (filters.value.laboratory_id)    payload.laboratory_id    = filters.value.laboratory_id
    if (filters.value.sample_type)      payload.sample_type      = filters.value.sample_type
    if (filters.value.test_id)          payload.test_id          = filters.value.test_id

    const res  = await reportService.getPWRReport(payload)
    const body = res.param_overview ? res : (res.data || res)

    paramOverview.value     = body.param_overview     || []
    districtBreakdown.value = body.district_breakdown || []
    kpTotals.value          = body.kp_totals          || { total_tested: 0, total_exceeding: 0, pct: 0 }
    generatedAt.value       = new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
    generated.value = true
  } catch (e) {
    errorMsg.value = 'Failed to generate report: ' + (e.response?.data?.message || e.message)
    console.error('PWR error:', e)
  } finally {
    loading.value = false
  }
}

// ── RAG helpers ────────────────────────────────────────────────────────
function ragClass(riskLevel) {
  if (!riskLevel) return 'r-grey'
  const l = riskLevel.toLowerCase()
  if (l === 'red')   return 'r-red'
  if (l === 'amber') return 'r-amber'
  if (l === 'green') return 'r-green'
  return 'r-grey'
}

function remarkClass(remarks) {
  if (!remarks) return 'r-grey'
  if (remarks === 'Action Required') return 'r-red'
  if (remarks === 'Monitor')         return 'r-amber'
  if (remarks === 'No Action')       return 'r-green'
  return 'r-grey'
}

function pctStr(val) {
  return val != null ? val.toFixed(1) + '%' : '0.0%'
}

// ── Report period label ────────────────────────────────────────────────
const reportPeriod = computed(() => {
  const from = filters.value.from_date
  const to   = filters.value.to_date
  if (!from && !to) return 'All Time'
  const fmt = (d) => d ? new Date(d).toLocaleDateString('en-GB', { month: 'short', year: 'numeric' }) : '—'
  return `${fmt(from)} – ${fmt(to)}`
})

const selectedParamName = computed(() => {
  if (!filters.value.test_id) return 'All Parameters'
  return parameters.value.find(p => String(p.id) === String(filters.value.test_id))?.water_quality_parameter || 'All Parameters'
})

const generatedBy = computed(() => userStore.currentUser?.name || 'System')

// ── Export ─────────────────────────────────────────────────────────────
function exportReport() {
  if (!paramOverview.value.length) { alert('No data to export.'); return }

  exportToXLSX(paramOverview.value.map(r => ({
    'Parameter':      r.parameter,
    'Type':           r.type,
    'Unit':           r.unit || '—',
    'WHO/NEQS Limit': r.limit || '—',
    'Total Tested':   r.tested,
    'Exceeding':      r.exceeding,
    '% Exceeding':    pctStr(r.pct),
    'Risk Level':     r.risk_level,
  })), 'PWR_Parameter_Overview')

  setTimeout(() => {
    exportToXLSX(districtBreakdown.value.map(r => ({
      'District':      r.district_name,
      'Tested':        r.total,
      'Within Limit':  r.fit,
      'Exceeding':     r.unfit,
      '% Exceeding':   pctStr(r.pct),
      'Remarks':       r.remarks,
    })), 'PWR_District_Breakdown')
  }, 400)
}

function printReport() { window.print() }

function clearFilters() {
  filters.value = {
    from_date:        financialYearStart(),
    to_date:          todayIso(),
    region_id:        '',
    division_id:      '',
    circle_id:        '',
    district_id:      '',
    phed_division_id: '',
    laboratory_id:    '',
    sample_type:      '',
    test_id:          '',
  }
}

// NOTE: PWR keeps the manual Generate button per SRS §2.2 R-07
// ("A 'Generate' button triggers the query.") — no auto-refresh watcher.

onMounted(async () => {
  await loadDropdowns()
  await generateReport()
})
</script>

<template>
  <div class="pwr-page">
    <!-- ── View-mode tabs (SRS §2.2 R-07) ── -->
    <div class="pwr-tabs">
      <button class="pwr-tab" :class="{ active: viewMode === 'all' }" @click="viewMode = 'all'">All Parameters</button>
      <button class="pwr-tab" :class="{ active: viewMode === 'contamination' }" @click="viewMode = 'contamination'">Contamination Only</button>
    </div>

    <!-- ── Filters ── -->
    <div class="filters" style="flex-wrap:wrap;gap:6px;margin-bottom:10px">
      <div class="fg"><label>From</label><input type="date" v-model="filters.from_date"></div>
      <div class="fg"><label>To</label><input type="date" v-model="filters.to_date"></div>

      <div class="fg">
        <label>CE Region</label>
        <select v-model="filters.region_id"
                @change="filters.circle_id='';filters.division_id='';filters.district_id='';filters.phed_division_id='';filters.laboratory_id=''">
          <option value="">All CE Regions</option>
          <option v-for="r in regions" :key="r.id" :value="r.id">{{ r.name }}</option>
        </select>
      </div>

      <div class="fg">
        <label>Division</label>
        <select v-model="filters.division_id" @change="filters.district_id='';filters.phed_division_id='';filters.laboratory_id=''">
          <option value="">All Divisions</option>
          <option v-for="d in filteredDivisions" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
      </div>

      <div class="fg">
        <label>PHE Circle</label>
        <select v-model="filters.circle_id" @change="filters.district_id='';filters.phed_division_id='';filters.laboratory_id=''">
          <option value="">All Circles</option>
          <option v-for="c in filteredCircles" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>

      <div class="fg">
        <label>District</label>
        <select v-model="filters.district_id" @change="filters.phed_division_id='';filters.laboratory_id=''">
          <option value="">All Districts</option>
          <option v-for="d in filteredDistricts" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
      </div>

      <div class="fg">
        <label>PHE Division</label>
        <select v-model="filters.phed_division_id">
          <option value="">All PHE Divisions</option>
          <option v-for="p in filteredPhedDivs" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
      </div>

      <div class="fg">
        <label>Lab</label>
        <select v-model="filters.laboratory_id">
          <option value="">All Labs</option>
          <option v-for="l in filteredLaboratories" :key="l.id" :value="l.id">{{ l.name }}</option>
        </select>
      </div>

      <div class="fg">
        <label>Sample Type</label>
        <select v-model="filters.sample_type">
          <option value="">All Sample Types</option>
          <option value="PHE">PHE</option>
          <option value="Private">Private</option>
        </select>
      </div>

      <div class="fg">
        <label>Parameter</label>
        <select v-model="filters.test_id">
          <option value="">All Parameters</option>
          <option v-for="p in parameters" :key="p.id" :value="p.id">{{ p.water_quality_parameter }}</option>
        </select>
      </div>

      <button class="btn btn-sec btn-sm" style="align-self:flex-end" @click="clearFilters">✕ Clear Filters</button>
      <div class="tsp"></div>
      <button class="btn btn-pri btn-sm" style="align-self:flex-end" @click="generateReport" :disabled="loading || !!dateError">
        {{ loading ? 'Generating…' : 'Generate' }}
      </button>
      <button v-if="generated" class="btn btn-sec btn-sm" style="align-self:flex-end" @click="exportReport">↓ Export</button>
      <button v-if="generated" class="btn btn-sec btn-sm" style="align-self:flex-end" @click="printReport">Print PDF</button>
    </div>

    <!-- Inline date range warning -->
    <div v-if="dateError"
         style="background:#fef9c3;border:1px solid #fde047;border-radius:6px;padding:8px 12px;margin-bottom:10px;color:#854d0e;font-size:12px">
      ⚠ {{ dateError }}
    </div>

    <!-- Error -->
    <div v-if="errorMsg && !dateError"
         style="background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:10px 14px;margin-bottom:10px;color:#b91c1c;font-size:12px">
      ⚠️ {{ errorMsg }}
    </div>

    <!-- Loading — skeleton placeholders matching the real layout -->
    <div v-if="loading" class="pwr-sk">
      <!-- Banner -->
      <div class="sk sk-banner"></div>

      <!-- 3 KP stat cards -->
      <div class="sk-cards">
        <div class="sk-card">
          <div class="sk sk-lbl"></div>
          <div class="sk sk-val"></div>
        </div>
        <div class="sk-card">
          <div class="sk sk-lbl"></div>
          <div class="sk sk-val"></div>
        </div>
        <div class="sk-card">
          <div class="sk sk-lbl"></div>
          <div class="sk sk-val"></div>
        </div>
      </div>

      <!-- View 1 — Parameter Overview -->
      <div class="sk sk-section-head"></div>
      <div class="sk-tbl">
        <div class="sk-tbl-head">
          <div class="sk sk-th" v-for="i in 6" :key="'ph'+i"></div>
        </div>
        <div class="sk-tbl-row" v-for="r in 8" :key="'pr'+r">
          <div class="sk sk-td" v-for="i in 6" :key="'pc'+r+'-'+i"></div>
        </div>
      </div>

      <!-- View 2 — District Breakdown -->
      <div class="sk sk-section-head"></div>
      <div class="sk-tbl">
        <div class="sk-tbl-head">
          <div class="sk sk-th" v-for="i in 6" :key="'dh'+i"></div>
        </div>
        <div class="sk-tbl-row" v-for="r in 5" :key="'dr'+r">
          <div class="sk sk-td" v-for="i in 6" :key="'dc'+r+'-'+i"></div>
        </div>
      </div>
    </div>

    <template v-if="!loading && generated">
      <!-- ── Report banner ── -->
      <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:10px 16px;margin-bottom:12px;font-size:12px;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <span style="font-size:14px">📊</span>
        <span style="font-weight:700;color:#1e40af">
          Parameter-wise Water Quality Report &nbsp;|&nbsp; {{ reportPeriod }}
          <span v-if="filters.test_id"> &nbsp;|&nbsp; {{ selectedParamName }}</span>
        </span>
        <span style="color:#6b7280;font-size:11px;margin-left:auto">
          Generated by <b>{{ generatedBy }}</b> on {{ generatedAt }}
        </span>
      </div>

      <!-- ── KP Totals stat cards ── -->
      <div class="cards" style="grid-template-columns:repeat(3,1fr);margin-bottom:14px">
        <div class="card">
          <div class="c-lbl">TOTAL PARAMETER TESTS</div>
          <div class="c-val">{{ kpTotals.total_tested.toLocaleString() }}</div>
        </div>
        <div class="card c-red">
          <div class="c-lbl">TOTAL EXCEEDING</div>
          <div class="c-val">{{ kpTotals.total_exceeding.toLocaleString() }}</div>
        </div>
        <div class="card">
          <div class="c-lbl">OVERALL % EXCEEDING</div>
          <div class="c-val">{{ pctStr(kpTotals.pct) }}</div>
        </div>
      </div>

      <!-- ── View 1: Parameter Overview ── -->
      <div class="sh" style="margin-bottom:8px">
        <h2>View 1 — Parameter Overview</h2>
      </div>
      <div class="tbl-wrap" style="margin-bottom:18px">
        <table style="font-size:12px">
          <thead>
            <tr style="background:var(--navy);color:#fff">
              <th style="color:#fff">Parameter</th>
              <th style="color:#fff">WHO/NEQS Limit</th>
              <th style="color:#fff;text-align:center">Total Tested</th>
              <th style="color:#fff;text-align:center">Exceeding</th>
              <th style="color:#fff;text-align:center">% Exceeding</th>
              <th style="color:#fff;text-align:center">Risk Level</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!displayedParamOverview.length">
              <td colspan="6" style="text-align:center;padding:24px;color:var(--muted)">
                {{ viewMode === 'contamination' ? 'No parameter has any exceeding samples for the selected filters.' : 'No data found for the selected filters.' }}
              </td>
            </tr>
            <tr v-for="(r, i) in pagedParamOverview" :key="r.test_id" :class="i%2===1?'alt':''">
              <td style="font-weight:600">{{ r.parameter }}</td>
              <td class="mono" style="font-size:11px;color:var(--muted)">{{ r.limit || '—' }}</td>
              <td class="mono" style="text-align:center">{{ r.tested.toLocaleString() }}</td>
              <td class="mono" style="text-align:center"
                  :style="r.exceeding > 0 ? 'color:var(--red);font-weight:600' : ''">
                {{ r.exceeding.toLocaleString() }}
              </td>
              <td class="mono" style="text-align:center">{{ pctStr(r.pct) }}</td>
              <td style="text-align:center">
                <span class="rag" :class="ragClass(r.risk_level)">{{ r.risk_level }}</span>
              </td>
            </tr>
          </tbody>
          <!-- KP Total row -->
          <tfoot v-if="displayedParamOverview.length">
            <tr style="background:var(--navy2);font-weight:700">
              <td style="color:#fff">KP Total</td>
              <td></td>
              <td class="mono" style="color:#fff;text-align:center;font-weight:700">
                {{ kpTotals.total_tested.toLocaleString() }}
              </td>
              <td class="mono" style="color:#fff;text-align:center;font-weight:700">
                {{ kpTotals.total_exceeding.toLocaleString() }}
              </td>
              <td class="mono" style="color:#fff;text-align:center;font-weight:700">
                {{ pctStr(kpTotals.pct) }}
              </td>
              <td style="text-align:center">
                <!-- Match per-parameter logic + legend: Green covers 0–10% when data exists -->
                <span class="rag"
                  :class="kpTotals.total_tested === 0 ? 'r-grey' : kpTotals.pct > 20 ? 'r-red' : kpTotals.pct > 10 ? 'r-amber' : 'r-green'">
                  {{ kpTotals.total_tested === 0 ? 'Grey' : kpTotals.pct > 20 ? 'Red' : kpTotals.pct > 10 ? 'Amber' : 'Green' }}
                </span>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="displayedParamOverview.length" class="pwr-pager">
        <div class="pwr-pager-info">
          Showing <b>{{ pageStart }}</b>–<b>{{ pageEnd }}</b> of
          <b>{{ displayedParamOverview.length }}</b> parameter{{ displayedParamOverview.length === 1 ? '' : 's' }}
        </div>
        <div class="pwr-pager-controls">
          <label class="pwr-pager-size">
            Rows per page
            <select v-model.number="pageSize">
              <option :value="10">10</option>
              <option :value="20">20</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
            </select>
          </label>
          <button class="pwr-pager-btn" :disabled="currentPage === 1" @click="goToPage(1)">«</button>
          <button class="pwr-pager-btn" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">‹</button>
          <span class="pwr-pager-page">Page <b>{{ currentPage }}</b> of <b>{{ totalPages }}</b></span>
          <button class="pwr-pager-btn" :disabled="currentPage === totalPages" @click="goToPage(currentPage + 1)">›</button>
          <button class="pwr-pager-btn" :disabled="currentPage === totalPages" @click="goToPage(totalPages)">»</button>
        </div>
      </div>

      <!-- ── View 2: District-wise Breakdown ── -->
      <div class="sh" style="margin-bottom:8px">
        <h2>View 2 — District-wise Breakdown</h2>
      </div>
      <div class="tbl-wrap" style="margin-bottom:14px">
        <table style="font-size:12px">
          <thead>
            <tr style="background:var(--navy);color:#fff">
              <th style="color:#fff">District</th>
              <th style="color:#fff;text-align:center">Tested</th>
              <th style="color:#fff;text-align:center">Within Limit</th>
              <th style="color:#fff;text-align:center">Exceeding</th>
              <th style="color:#fff;text-align:center">% Exceeding</th>
              <th style="color:#fff;text-align:center">Remarks</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!districtBreakdown.length">
              <td colspan="6" style="text-align:center;padding:24px;color:var(--muted)">No district data found.</td>
            </tr>
            <tr v-for="(r, i) in districtBreakdown" :key="r.district_id" :class="i%2===1?'alt':''">
              <td style="font-weight:500">{{ r.district_name }}</td>
              <td class="mono" style="text-align:center">{{ r.total.toLocaleString() }}</td>
              <td class="mono" style="text-align:center">{{ r.fit.toLocaleString() }}</td>
              <td class="mono" style="text-align:center"
                  :style="r.unfit > 0 ? 'color:var(--red);font-weight:600' : ''">
                <span v-if="r.unfit > 0" style="text-decoration:underline;cursor:default">{{ r.unfit.toLocaleString() }}</span>
                <span v-else>{{ r.unfit }}</span>
              </td>
              <td class="mono" style="text-align:center">{{ pctStr(r.pct) }}</td>
              <td style="text-align:center">
                <span class="rag" :class="remarkClass(r.remarks)">{{ r.remarks }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    </template>
  </div>
</template>

<style>
/* View-mode tab bar (SRS §2.2 R-07: All Parameters / Contamination Only) */
.pwr-page .pwr-tabs {
  display: flex;
  align-items: stretch;
  background: #f3f4f6;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 4px;
  margin-bottom: 12px;
  gap: 2px;
  width: fit-content;
}
.pwr-page .pwr-tab {
  border: 0;
  background: transparent;
  color: #4b5563;
  font-size: 13px;
  font-weight: 600;
  padding: 7px 18px;
  border-radius: 4px;
  cursor: pointer;
  font-family: inherit;
  transition: background .15s, color .15s;
}
.pwr-page .pwr-tab:hover:not(.active) { background: #e5e7eb; }
.pwr-page .pwr-tab.active {
  background: #2563eb;
  color: #fff;
}

/* Crisp-text override scoped to PWR view: defeats global td.mono rule (DM Mono 11.5px). */
.pwr-page td.mono {
  font-family: 'DM Sans', sans-serif;
  font-variant-numeric: tabular-nums;
  font-size: 12.5px;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

/* Pagination */
.pwr-page .pwr-pager {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin: -10px 0 18px;
  padding: 8px 12px;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  font-size: 12px;
  color: #4b5563;
  flex-wrap: wrap;
}
.pwr-page .pwr-pager-info b { color: #111827; }
.pwr-page .pwr-pager-controls {
  display: flex;
  align-items: center;
  gap: 6px;
}
.pwr-page .pwr-pager-size {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-right: 8px;
}
.pwr-page .pwr-pager-size select {
  font-size: 12px;
  padding: 3px 6px;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  background: #fff;
  cursor: pointer;
}
.pwr-page .pwr-pager-btn {
  min-width: 28px;
  height: 28px;
  padding: 0 8px;
  border: 1px solid #d1d5db;
  background: #fff;
  border-radius: 4px;
  color: #374151;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}
.pwr-page .pwr-pager-btn:hover:not(:disabled) { background: #f3f4f6; }
.pwr-page .pwr-pager-btn:disabled { opacity: .4; cursor: not-allowed; }
.pwr-page .pwr-pager-page { padding: 0 6px; }
.pwr-page .pwr-pager-page b { color: #111827; }

@media print {
  .filters, .btn, .pwr-tabs, .pwr-pager, nav, aside { display: none !important; }
  .tbl-wrap, .tbl-wrap *, .cards, .cards *, .sh, .sh * { visibility: visible; }
  body { font-size: 10px; }
}

/* ── Skeleton loading (matches PWR layout) ─────────────────────────── */
.pwr-page .sk {
  background: linear-gradient(90deg, #e5e7eb 0%, #f3f4f6 50%, #e5e7eb 100%);
  background-size: 200% 100%;
  border-radius: 4px;
  animation: pwr-sk-shimmer 1.4s infinite linear;
}
@keyframes pwr-sk-shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
.pwr-page .sk-banner { height: 38px; margin-bottom: 12px; }

.pwr-page .sk-cards {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  margin-bottom: 14px;
}
.pwr-page .sk-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.pwr-page .sk-lbl { height: 10px; width: 55%; }
.pwr-page .sk-val { height: 22px; width: 40%; }

.pwr-page .sk-section-head { height: 18px; width: 240px; margin: 14px 0 10px; }

.pwr-page .sk-tbl {
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  overflow: hidden;
  margin-bottom: 14px;
}
.pwr-page .sk-tbl-head, .pwr-page .sk-tbl-row {
  display: grid;
  grid-template-columns: 2fr 1.5fr 1fr 1fr 1fr 1fr;
  gap: 8px;
  padding: 10px 12px;
}
.pwr-page .sk-tbl-head { background: #f3f4f6; border-bottom: 1px solid #e5e7eb; }
.pwr-page .sk-tbl-row + .sk-tbl-row { border-top: 1px solid #f3f4f6; }
.pwr-page .sk-th { height: 12px; }
.pwr-page .sk-td { height: 12px; }
</style>
