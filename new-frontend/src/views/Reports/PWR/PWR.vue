<script setup>
import { ref, computed, onMounted } from 'vue'
import { reportService }   from '../../../services/reportService.js'
import { dropdownService } from '../../../services/dropdownService.js'
import { exportToXLSX }    from '../../../utils/exportHelpers.js'
import { useUserStore }    from '../../../stores/useUserStore.js'

const userStore  = ref(useUserStore())
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

// ── Filters ────────────────────────────────────────────────────────────
const filters = ref({
  from_date:        '',
  to_date:          '',
  region_id:        '',
  division_id:      '',
  circle_id:        '',
  district_id:      '',
  phed_division_id: '',
  laboratory_id:    '',
  test_type:        '',   // sample type (PHE / Private / PT)
  test_id:          '',   // specific parameter
})

// Cascaded district filter
const filteredDistricts = computed(() =>
  filters.value.division_id
    ? districts.value.filter(d => String(d.division_id) === String(filters.value.division_id))
    : districts.value
)

// ── Report data ────────────────────────────────────────────────────────
const paramOverview     = ref([])
const districtBreakdown = ref([])
const kpTotals          = ref({ total_tested: 0, total_exceeding: 0, pct: 0 })

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

// ── Generate ───────────────────────────────────────────────────────────
async function generateReport() {
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
    if (filters.value.test_type)        payload.test_type        = filters.value.test_type
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

const generatedBy = computed(() => userStore.value.currentUser?.name || 'System')

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

onMounted(async () => {
  await loadDropdowns()
})
</script>

<template>
  <div>
    <!-- ── Filters ── -->
    <div class="filters" style="flex-wrap:wrap;gap:6px;margin-bottom:10px">
      <div class="fg"><label>From</label><input type="date" v-model="filters.from_date"></div>
      <div class="fg"><label>To</label><input type="date" v-model="filters.to_date"></div>

      <div class="fg">
        <label>CE Region</label>
        <select v-model="filters.region_id" @change="filters.division_id='';filters.district_id=''">
          <option value="">All CE Regions</option>
          <option v-for="r in regions" :key="r.id" :value="r.id">{{ r.name }}</option>
        </select>
      </div>

      <div class="fg">
        <label>Division</label>
        <select v-model="filters.division_id" @change="filters.district_id=''">
          <option value="">All Divisions</option>
          <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
      </div>

      <div class="fg">
        <label>PHE Circle</label>
        <select v-model="filters.circle_id">
          <option value="">All Circles</option>
          <option v-for="c in circles" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>

      <div class="fg">
        <label>District</label>
        <select v-model="filters.district_id">
          <option value="">All Districts</option>
          <option v-for="d in filteredDistricts" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
      </div>

      <div class="fg">
        <label>PHE Division</label>
        <select v-model="filters.phed_division_id">
          <option value="">All PHE Divisions</option>
          <option v-for="p in phedDivisions" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
      </div>

      <div class="fg">
        <label>Lab</label>
        <select v-model="filters.laboratory_id">
          <option value="">All Labs</option>
          <option v-for="l in laboratories" :key="l.id" :value="l.id">{{ l.name }}</option>
        </select>
      </div>

      <div class="fg">
        <label>Sample Type</label>
        <select v-model="filters.test_type">
          <option value="">All Sample Types</option>
          <option value="PHE">PHE</option>
          <option value="Private">Private</option>
          <option value="PT">PT</option>
        </select>
      </div>

      <div class="fg">
        <label>Parameter</label>
        <select v-model="filters.test_id">
          <option value="">All Parameters</option>
          <option v-for="p in parameters" :key="p.id" :value="p.id">{{ p.water_quality_parameter }}</option>
        </select>
      </div>

      <div class="tsp"></div>
      <button class="btn btn-pri btn-sm" @click="generateReport" :disabled="loading">
        {{ loading ? 'Generating...' : 'Generate' }}
      </button>
      <button v-if="generated" class="btn btn-sec btn-sm" @click="exportReport">↓ Export</button>
      <button v-if="generated" class="btn btn-sec btn-sm" @click="printReport">Print PDF</button>
    </div>

    <!-- Error -->
    <div v-if="errorMsg"
         style="background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:10px 14px;margin-bottom:10px;color:#b91c1c;font-size:12px">
      ⚠️ {{ errorMsg }}
    </div>

    <!-- Loading -->
    <div v-if="loading" style="text-align:center;padding:48px;color:var(--muted);font-size:13px">
      Loading report data...
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
            <tr v-if="!paramOverview.length">
              <td colspan="6" style="text-align:center;padding:24px;color:var(--muted)">No data found for the selected filters.</td>
            </tr>
            <tr v-for="(r, i) in paramOverview" :key="r.test_id" :class="i%2===1?'alt':''">
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
          <tfoot v-if="paramOverview.length">
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
                <span class="rag"
                  :class="kpTotals.pct > 20 ? 'r-red' : kpTotals.pct > 10 ? 'r-amber' : kpTotals.pct > 0 ? 'r-green' : 'r-grey'">
                  {{ kpTotals.pct > 20 ? 'Red' : kpTotals.pct > 10 ? 'Amber' : kpTotals.pct > 0 ? 'Green' : 'Grey' }}
                </span>
              </td>
            </tr>
          </tfoot>
        </table>
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

      <!-- Legend -->
      <div style="font-size:10.5px;color:var(--muted);margin-top:10px;line-height:1.9">
        <b>Risk Level:</b>
        <span class="rag r-green"  style="margin:0 4px">Green</span> &gt;0% exceeding (within acceptable range) &nbsp;·&nbsp;
        <span class="rag r-amber"  style="margin:0 4px">Amber</span> &gt;10% exceeding &nbsp;·&nbsp;
        <span class="rag r-red"    style="margin:0 4px">Red</span>   &gt;20% exceeding &nbsp;·&nbsp;
        <span class="rag r-grey"   style="margin:0 4px">Grey</span>  No criteria / no data
        <br>
        <b>Remarks:</b>
        <span class="rag r-red"    style="margin:0 4px">Action Required</span> &gt;20% &nbsp;·&nbsp;
        <span class="rag r-amber"  style="margin:0 4px">Monitor</span> 10–20% &nbsp;·&nbsp;
        <span class="rag r-green"  style="margin:0 4px">No Action</span> ≤10%
      </div>
    </template>

    <!-- Empty state before first generate -->
    <template v-if="!loading && !generated && !errorMsg">
      <div style="text-align:center;padding:60px 20px;color:var(--muted);font-size:13px">
        <div style="font-size:32px;margin-bottom:8px">📋</div>
        Select your filters above and click <b>Generate</b> to load the Parameter-wise Report.
      </div>
    </template>
  </div>
</template>

<style>
@media print {
  .filters, .btn, nav, aside { display: none !important; }
  .tbl-wrap, .tbl-wrap *, .cards, .cards *, .sh, .sh * { visibility: visible; }
  body { font-size: 10px; }
}
</style>
