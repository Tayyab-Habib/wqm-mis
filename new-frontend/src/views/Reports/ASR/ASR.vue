<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRbac } from '../../../composables/useRbac.js'
import { reportService } from '../../../services/reportService.js'
import { dropdownService } from '../../../services/dropdownService.js'
import { exportToExcel } from '../../../utils/exportHelpers.js'
import { useUserStore } from '../../../stores/useUserStore.js'

const userStore = useUserStore()

// ── State ──────────────────────────────────────────────────────────────
const loading     = ref(false)
const errorMsg    = ref('')
const generated   = ref(false)
const generatedAt = ref('')

// ── Dropdowns ──────────────────────────────────────────────────────────
const regions      = ref([])
const divisions    = ref([])
const circles      = ref([])
const districts    = ref([])
const phedDivs     = ref([])
const laboratories = ref([])
const allTests     = ref([])     // every active test parameter (column source)

// ── Filters ────────────────────────────────────────────────────────────
function firstOfMonthIso() {
  const d = new Date()
  return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().split('T')[0]
}
function todayIso() {
  return new Date().toISOString().split('T')[0]
}
const filters = ref({
  from_date:        firstOfMonthIso(),
  to_date:          todayIso(),
  region_id:        '',
  division_id:      '',
  circle_id:        '',
  district_id:      '',
  phed_division_id: '',
  laboratory_id:    '',
  sample_type:      '',
})

// ── Cascaded dropdowns (Region → Division → Circle → District → PHE Div) ──
const filteredDivisions = computed(() =>
  filters.value.region_id
    ? divisions.value.filter(d => String(d.region_id) === String(filters.value.region_id))
    : divisions.value
)
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
  let list = phedDivs.value
  if (filters.value.district_id) list = list.filter(p => String(p.district_id) === String(filters.value.district_id))
  if (filters.value.circle_id)   list = list.filter(p => String(p.circle_id)   === String(filters.value.circle_id))
  return list
})
// Lab dropdown derives via circles.laboratory_id — see GAR for rationale.
const filteredLaboratories = computed(() => {
  let labIds = null
  if (filters.value.circle_id) {
    const c = circles.value.find(c => String(c.id) === String(filters.value.circle_id))
    labIds = c?.laboratory_id ? [c.laboratory_id] : []
  } else if (filters.value.region_id) {
    labIds = circles.value
      .filter(c => String(c.region_id) === String(filters.value.region_id))
      .map(c => c.laboratory_id)
      .filter(Boolean)
  }
  if (labIds === null) return laboratories.value
  const set = new Set(labIds.map(String))
  return laboratories.value.filter(l => set.has(String(l.id)))
})

// Active parameters = columns rendered in the table.
// Backend stores is_active as 0/1 (Eloquent JSON cast); coerce defensively in case
// it ever comes back as a boolean or the string "1".
const activeTests = computed(() =>
  allTests.value.filter(t => Boolean(Number(t.is_active)))
)

// ── Load dropdowns ─────────────────────────────────────────────────────
async function loadDropdowns() {
  try {
    const [regRes, divRes, cirRes, distRes, phdRes, labRes, parRes] = await Promise.all([
      dropdownService.getRegions(),
      dropdownService.getDivisions(),
      dropdownService.getCircles(),
      dropdownService.getDistricts(),
      dropdownService.getPhedDivisions(),
      dropdownService.getLaboratories(),
      dropdownService.getTestParameters(),
    ])
    regions.value      = regRes.data?.data  || regRes.data  || []
    divisions.value    = divRes.data?.data  || divRes.data  || []
    circles.value      = cirRes.data?.data  || cirRes.data  || []
    districts.value    = distRes.data?.data || distRes.data || []
    phedDivs.value     = phdRes.data?.data  || phdRes.data  || []
    laboratories.value = labRes.data?.data  || labRes.data  || []
    allTests.value     = parRes.data?.data  || parRes.data  || []
  } catch (e) { console.error('Dropdown error:', e) }
}

// ── Client-side date validation ────────────────────────────────────────
const dateError = computed(() => {
  const f = filters.value.from_date
  const t = filters.value.to_date
  if (f && t && f > t) return 'From date must be on or before To date.'
  return ''
})

// ── Generate report ───────────────────────────────────────────────────
const rawSamples = ref([])
// Request sequence — drop stale responses if a newer request fires while the
// previous one is still in flight (race condition on rapid filter changes).
let requestSeq = 0

async function generateReport() {
  // Client-side guard so we don't 422 the backend on partially-typed dates
  if (dateError.value) {
    errorMsg.value  = dateError.value
    generated.value = false
    loading.value   = false
    return
  }

  const mySeq = ++requestSeq
  loading.value   = true
  errorMsg.value  = ''

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

    const res  = await reportService.getWaterQualityAnalysis(payload)
    if (mySeq !== requestSeq) return    // a newer request was issued — drop this response

    const data = res.data?.data || res.data || []
    rawSamples.value  = Array.isArray(data) ? data : []
    generatedAt.value = new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
    generated.value   = true
  } catch (e) {
    if (mySeq !== requestSeq) return
    errorMsg.value = 'Failed to generate report: ' + (e.response?.data?.message || e.message || '')
    console.error('ASR error:', e)
  } finally {
    if (mySeq === requestSeq) loading.value = false
  }
}

// Compress `desired_test` (JSON array, comma list, or plain string) into a short code:
//   contains Physical → P, contains Chemical → C, contains Microbiological → M, On Demand → OD
//   e.g. ["Physical","Physical & Chemical","Microbiological(MF)"] → "PCM"
function formatTestType(raw) {
  if (!raw) return '—'
  let parts = []
  if (Array.isArray(raw)) {
    parts = raw
  } else {
    const str = String(raw).trim()
    if (str.startsWith('[')) {
      try { parts = JSON.parse(str) } catch { parts = [str] }
    } else {
      parts = str.split(/[,;]/)
    }
  }
  const blob = parts.join(' ').toLowerCase()
  let code = ''
  if (blob.includes('physical'))         code += 'P'
  if (blob.includes('chemical'))         code += 'C'
  if (blob.includes('microbiological') ||
      blob.includes('microbial') ||
      blob.includes('bacteriological'))  code += 'M'
  if (blob.includes('on demand') ||
      blob.includes('on-demand') ||
      blob.includes('ondemand'))         code += 'OD'
  return code || (Array.isArray(raw) ? raw.join(', ') : String(raw))
}

// Defensive date formatter — sampled_at may arrive as ISO ("2026-05-13T..."),
// SQL ("2026-05-13 12:34:56"), or the Laravel pretty accessor ("13 May, 2026 12:58").
// Previous code split on 'T' then ' ' which returned just "13" for the pretty format.
function formatSampledAt(dt) {
  if (!dt) return '—'
  const s = String(dt).trim()
  const iso = s.match(/^(\d{4})-(\d{2})-(\d{2})/)
  if (iso) {
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']
    return `${iso[3]}-${months[parseInt(iso[2],10)-1]}-${iso[1].slice(-2)}`
  }
  const pretty = s.match(/^(\d{1,2})\s+([A-Za-z]{3})[a-z]*,?\s+(\d{4})/)
  if (pretty) {
    return `${pretty[1].padStart(2,'0')}-${pretty[2]}-${pretty[3].slice(-2)}`
  }
  const d = new Date(s)
  if (!Number.isNaN(d.getTime())) {
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: '2-digit' }).replace(/ /g, '-')
  }
  return s
}

// ── Row mapping ────────────────────────────────────────────────────────
// Map each sample → { static fields, paramValues: { test_id: raw_value } }
const rows = computed(() => {
  return rawSamples.value.map((s, idx) => {
    const details = s.waterSampleDetails || s.water_sample_details || []
    const paramValues = {}
    details.forEach(d => {
      // analysis_result is the canonical field; input_result is a legacy fallback
      const val = (d.analysis_result ?? d.input_result ?? '').toString().trim()
      if (val !== '') paramValues[d.test_id] = val
    })
    return {
      sn:        idx + 1,
      id:        s.slug || String(s.id),
      wss:       s.waterScheme?.name || s.water_sample_address || '—',
      date:      formatSampledAt(s.sampled_at),
      ce:       (s.region?.name) || '—',
      lab:       s.laboratory?.name || '—',
      district:  s.district?.name || '—',
      phediv:    s.phedDivision?.name || s.phed_division?.name || '—',
      lat:       s.latitude || '—',
      lng:       s.longitude || '—',
      testType:  formatTestType(s.desired_test || s.test_type),
      result:    s.result || '—',
      paramValues,
    }
  })
})

// ── Bounds-check (mirrors PWRController's exceeding logic) ─────────────
function exceedsBounds(rawValue, test) {
  if (!test || !test.criteria) return false
  const strVal = String(rawValue).trim().toLowerCase()

  // Microbial / qualitative — any "detected/positive/present" is unfit
  if (['detected', 'positive', 'present'].includes(strVal)) return true
  if (['not detected', 'negative', 'absent', 'nil', 'none'].includes(strVal)) return false

  // Numeric bounds — only flag if value is outside a non-zero bound
  const num = parseFloat(rawValue)
  if (Number.isNaN(num)) return false

  const minRaw = test.who_guideline_start ?? test.laboratory_guideline_start
  const maxRaw = test.who_guideline_end   ?? test.laboratory_guideline_end
  const minVal = parseFloat(minRaw)
  const maxVal = parseFloat(maxRaw)

  if (!Number.isNaN(minVal) && minVal > 0 && num < minVal) return true
  if (!Number.isNaN(maxVal) && maxVal > 0 && num > maxVal) return true
  return false
}

// Per-column TOTALS (count of samples with a value for each parameter)
const totalsByTestId = computed(() => {
  const counts = {}
  activeTests.value.forEach(t => { counts[t.id] = 0 })
  rows.value.forEach(r => {
    Object.keys(r.paramValues).forEach(tid => {
      if (counts[tid] !== undefined) counts[tid]++
    })
  })
  return counts
})

const fitCount   = computed(() => rows.value.filter(r => r.result === 'Fit'   || r.result === '1').length)
const unfitCount = computed(() => rows.value.filter(r => r.result === 'Unfit' || r.result === '2').length)

// ── Banner / metadata helpers ──────────────────────────────────────────
function lookupName(list, id) {
  if (!id) return null
  const found = list.find(x => String(x.id) === String(id))
  return found?.name || null
}
const banner = computed(() => ({
  period:     `${fmtDate(filters.value.from_date)} to ${fmtDate(filters.value.to_date)}`,
  lab:        lookupName(laboratories.value, filters.value.laboratory_id)    || 'All Labs',
  region:     lookupName(regions.value,      filters.value.region_id)        || 'All Regions',
  phedDiv:    lookupName(phedDivs.value,     filters.value.phed_division_id) || 'All PHE Divisions',
  district:   lookupName(districts.value,    filters.value.district_id)      || 'All Districts',
  generatedBy: userStore.currentUser?.name || 'System',
  generatedOn: generatedAt.value,
  paramCount: activeTests.value.length,
}))
function fmtDate(iso) {
  if (!iso) return '—'
  const d = new Date(iso)
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

// ── Export ─────────────────────────────────────────────────────────────
function exportReport() {
  if (!rows.value.length) { alert('No data to export. Please generate the report first.'); return }

  const headers = {
    'S#':              r => r.sn,
    'Sample ID':       r => r.id,
    'WSS / Client':    r => r.wss,
    'Sampling Date':   r => r.date,
    'PHE Region':      r => r.ce,
    'Laboratory':      r => r.lab,
    'District':        r => r.district,
    'PHE Division':    r => r.phediv,
    'Latitude':        r => r.lat,
    'Longitude':       r => r.lng,
    'Test Type':       r => r.testType,
  }
  activeTests.value.forEach(t => {
    headers[t.water_quality_parameter] = r => r.paramValues[t.id] ?? ''
  })
  headers['Result'] = r => r.result

  const rowsOut = rows.value.map(r => {
    const o = {}
    Object.entries(headers).forEach(([key, fn]) => { o[key] = fn(r) })
    return o
  })

  exportToExcel(rowsOut, 'ASR_Analysis_Summary_Report', { includeTimestamp: true })
}

function printReport() { window.print() }

function clearFilters() {
  filters.value = {
    from_date:        firstOfMonthIso(),
    to_date:          todayIso(),
    region_id:        '',
    division_id:      '',
    circle_id:        '',
    district_id:      '',
    phed_division_id: '',
    laboratory_id:    '',
    sample_type:      '',
  }
}

// ── Auto-regenerate on filter change (debounced) ───────────────────────
// No "Generate" button — the report updates as filters change.
// Skip if the date range is invalid (user is mid-typing).
let filterDebounce = null
let dropdownsReady = false
watch(filters, () => {
  if (!dropdownsReady) return
  if (dateError.value) return
  clearTimeout(filterDebounce)
  filterDebounce = setTimeout(() => { generateReport() }, 350)
}, { deep: true })

// RBAC: pre-select + lock filters at the user's hierarchy scope
const rbac = useRbac()
function applyRbacLocks() {
  if (rbac.regionId.value)       filters.value.region_id        = String(rbac.regionId.value)
  if (rbac.circleId.value)       filters.value.circle_id        = String(rbac.circleId.value)
  if (rbac.phedDivisionId.value) filters.value.phed_division_id = String(rbac.phedDivisionId.value)
  if (rbac.laboratoryId.value)   filters.value.laboratory_id    = String(rbac.laboratoryId.value)
}

onMounted(async () => {
  await loadDropdowns()
  applyRbacLocks()
  dropdownsReady = true
  await generateReport()
})
</script>

<template>
  <div class="asr-page">
    <!-- ── Filter bar ── -->
    <div class="filters" style="flex-wrap:wrap;gap:6px;margin-bottom:10px">
      <div class="fg"><label>From</label><input type="date" v-model="filters.from_date"></div>
      <div class="fg"><label>To</label><input type="date" v-model="filters.to_date"></div>

      <div class="fg">
        <label>CE Region</label>
        <select v-model="filters.region_id" :disabled="!!rbac.regionId.value"
                @change="filters.division_id='';filters.circle_id='';filters.district_id='';filters.phed_division_id='';filters.laboratory_id=''">
          <option value="">All CE Regions</option>
          <option v-for="r in regions" :key="r.id" :value="r.id">{{ r.name }}</option>
        </select>
      </div>

      <div class="fg">
        <label>Division</label>
        <select v-model="filters.division_id"
                @change="filters.district_id='';filters.phed_division_id='';filters.laboratory_id=''">
          <option value="">All Divisions</option>
          <option v-for="d in filteredDivisions" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
      </div>

      <div class="fg">
        <label>PHE Circle</label>
        <select v-model="filters.circle_id" :disabled="!!rbac.circleId.value"
                @change="filters.district_id='';filters.phed_division_id='';filters.laboratory_id=''">
          <option value="">All Circles</option>
          <option v-for="c in filteredCircles" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>

      <div class="fg">
        <label>District</label>
        <select v-model="filters.district_id"
                @change="filters.phed_division_id='';filters.laboratory_id=''">
          <option value="">All Districts</option>
          <option v-for="d in filteredDistricts" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
      </div>

      <div class="fg">
        <label>PHE Division</label>
        <select v-model="filters.phed_division_id" :disabled="!!rbac.phedDivisionId.value">
          <option value="">All PHE Divisions</option>
          <option v-for="p in filteredPhedDivs" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
      </div>

      <div class="fg">
        <label>Lab</label>
        <select v-model="filters.laboratory_id" :disabled="!!rbac.laboratoryId.value">
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

      <button class="btn btn-sec btn-sm" style="align-self:flex-end" @click="clearFilters">✕ Clear</button>
      <div class="tsp"></div>
      <button v-if="generated" class="btn btn-sec btn-sm" style="align-self:flex-end" @click="exportReport">↓ Export .xlsx</button>
      <button v-if="generated" class="btn btn-sec btn-sm" style="align-self:flex-end" @click="printReport">🖨 Print PDF</button>
    </div>

    <!-- ── Inline date range warning ── -->
    <div v-if="dateError"
         style="background:#fef9c3;border:1px solid #fde047;border-radius:6px;padding:8px 12px;margin-bottom:10px;color:#854d0e;font-size:12px">
      ⚠ {{ dateError }}
    </div>

    <!-- ── Error ── -->
    <div v-if="errorMsg && !dateError"
         style="background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:10px 14px;margin-bottom:10px;color:#b91c1c;font-size:12px">
      ⚠️ {{ errorMsg }}
    </div>

    <!-- ── Skeleton loading ── -->
    <div v-if="loading" class="asr-sk">
      <div class="sk sk-banner"></div>
      <div class="sk sk-section"></div>
      <div class="sk-tbl">
        <div class="sk-tbl-head">
          <div class="sk sk-th" v-for="i in 12" :key="'sh'+i"></div>
        </div>
        <div class="sk-tbl-row" v-for="r in 8" :key="'sr'+r">
          <div class="sk sk-td" v-for="i in 12" :key="'sc'+r+'-'+i"></div>
        </div>
      </div>
    </div>

    <!-- ── Report body ── -->
    <template v-if="!loading && generated">
      <div class="asr-report-card">
        <!-- Govt. header -->
        <div class="asr-gov-header">
          <div class="asr-gov-line1">Government of Khyber Pakhtunkhwa</div>
          <div class="asr-gov-line2">Public Health Engineering Department</div>
          <div class="asr-gov-title">Analysis Summary Report (ASR)</div>
          <div class="asr-gov-sub">{{ banner.lab }}</div>
        </div>

        <!-- Metadata banner -->
        <div class="asr-meta">
          <span>📋 Period: <b>{{ banner.period }}</b></span>
          <span>|&nbsp; Lab: <b>{{ banner.lab }}</b></span>
          <span>|&nbsp; Region: <b>{{ banner.region }}</b></span>
          <span>|&nbsp; PHE Division: <b>{{ banner.phedDiv }}</b></span>
          <span>|&nbsp; District: <b>{{ banner.district }}</b></span>
          <span>|&nbsp; Generated By: <b>{{ banner.generatedBy }}</b></span>
          <span>|&nbsp; Generated On: <b>{{ banner.generatedOn }}</b></span>
        </div>

        <div class="asr-section-head">
          Parameter-wise Analysis Results ({{ banner.paramCount }} parameters)
        </div>

        <!-- Table -->
        <div class="asr-tbl-wrap">
          <table class="asr-tbl">
            <thead>
              <tr class="asr-th-group">
                <th colspan="11" style="text-align:left">Sample Identification &amp; Location</th>
                <th :colspan="activeTests.length" style="text-align:center">Parameter Values</th>
                <th rowspan="2" style="text-align:center;vertical-align:middle">Result</th>
              </tr>
              <tr class="asr-th-cell">
                <th>S#</th>
                <th>Sample ID</th>
                <th>WSS / Client</th>
                <th>Sampling Date</th>
                <th>PHE Region</th>
                <th>Laboratory</th>
                <th>District</th>
                <th>PHE Div.</th>
                <th>Lat.</th>
                <th>Long.</th>
                <th class="asr-th-divider">Test Type</th>
                <th v-for="t in activeTests" :key="'p'+t.id"
                    :class="['asr-param-th', 'asr-type-' + (t.type || 'other').toLowerCase().replace(/[^a-z]/g,'')]">
                  {{ t.water_quality_parameter }}
                  <div v-if="t.unit" class="asr-unit">{{ t.unit }}</div>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!rows.length">
                <td :colspan="12 + activeTests.length" style="text-align:center;padding:24px;color:var(--muted)">
                  No samples found for the selected filters.
                </td>
              </tr>
              <tr v-for="(r, i) in rows" :key="r.id"
                  :class="i%2===1 ? 'asr-row-alt' : ''"
                  :style="(r.result === 'Unfit' || r.result === '2') ? 'background:#fff3f3' : ''">
                <td>{{ r.sn }}</td>
                <td class="mono nowrap">{{ r.id }}</td>
                <td class="nowrap">{{ r.wss }}</td>
                <td class="nowrap">{{ r.date }}</td>
                <td>{{ r.ce }}</td>
                <td>{{ r.lab }}</td>
                <td>{{ r.district }}</td>
                <td>{{ r.phediv }}</td>
                <td class="mono asr-coord">{{ r.lat }}</td>
                <td class="mono asr-coord">{{ r.lng }}</td>
                <td class="asr-th-divider"><span class="rag r-blue" style="font-size:9.5px">{{ r.testType }}</span></td>
                <td v-for="t in activeTests" :key="'v'+r.id+'-'+t.id"
                    :class="['asr-param-cell', exceedsBounds(r.paramValues[t.id], t) ? 'asr-exceed' : '']">
                  {{ r.paramValues[t.id] || '' }}
                </td>
                <td><span class="rag" :class="(r.result==='Fit' || r.result==='1') ? 'r-green' : (r.result==='Unfit' || r.result==='2') ? 'r-red' : 'r-grey'">
                  {{ (r.result==='1') ? 'Fit' : (r.result==='2') ? 'Unfit' : r.result }}
                </span></td>
              </tr>
            </tbody>
            <tfoot v-if="rows.length">
              <tr class="asr-totals">
                <td colspan="11" style="color:#fff">TOTALS ({{ rows.length }} samples)</td>
                <td v-for="t in activeTests" :key="'tot'+t.id" style="text-align:center;color:#fff">
                  {{ totalsByTestId[t.id] || 0 }}
                </td>
                <td style="color:#fff;text-align:center">
                  <span class="rag r-green" style="margin-right:4px">✓ {{ fitCount }}</span>
                  <span class="rag r-red">✗ {{ unfitCount }}</span>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>

        <div class="asr-legend">
          <div>
            <span class="asr-legend-swatch"></span>
            Red-highlighted cell = parameter value exceeds WHO / NEQS guideline limit.
            &nbsp;·&nbsp; Blank cell = parameter not tested for this sample.
            &nbsp;·&nbsp; Per-column total = number of samples with a recorded value.
          </div>
          <div style="margin-top:4px">
            <b>Test Type:</b>
            PCM = Physical + Chemical + Microbial &nbsp;·&nbsp;
            PC = Physical + Chemical &nbsp;·&nbsp;
            M = Microbial only &nbsp;·&nbsp;
            P = Physical only &nbsp;·&nbsp;
            C = Chemical only
          </div>
          <div style="margin-top:4px;color:#475569">
            <em>Parameter columns auto-update as new tests are added in Settings → Parameter Configuration.</em>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.asr-page { font-size: 12px; }

.asr-report-card {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 20px 24px;
  box-shadow: 0 2px 10px rgba(0,0,0,.06);
}

/* ── Government header ─────────────────────────────────────────────── */
.asr-gov-header {
  text-align: center;
  border-bottom: 2px solid var(--navy);
  padding-bottom: 12px;
  margin-bottom: 14px;
}
.asr-gov-line1 {
  font-size: 11.5px;
  font-weight: 700;
  color: var(--navy);
  letter-spacing: .04em;
  text-transform: uppercase;
}
.asr-gov-line2 { font-size: 11px; color: var(--navy2); margin: 2px 0; }
.asr-gov-title { font-size: 13px; font-weight: 700; color: var(--navy); margin: 4px 0; }
.asr-gov-sub   { font-size: 11px; color: var(--muted); }

/* ── Metadata banner ───────────────────────────────────────────────── */
.asr-meta {
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 5px;
  padding: 8px 14px;
  margin-bottom: 12px;
  font-size: 11px;
  display: flex;
  flex-wrap: wrap;
  gap: 4px 10px;
  align-items: center;
}
.asr-meta b { color: var(--navy); }

.asr-section-head {
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .05em;
  color: var(--navy2);
  margin-bottom: 6px;
}

/* ── Table ─────────────────────────────────────────────────────────── */
.asr-tbl-wrap {
  overflow-x: auto;
  border: 1px solid #d0d7e0;
  border-radius: 5px;
}
.asr-tbl {
  font-size: 10.5px;
  border-collapse: collapse;
  width: 100%;
  min-width: 1800px;
}
.asr-th-group  { background: #1a2e4a; color: #fff; }
.asr-th-group th { color: #fff; padding: 5px 8px; border-right: 2px solid #fff; }
.asr-th-cell   { background: #2a3f5f; color: #fff; font-size: 9.5px; }
.asr-th-cell th { color: #fff; padding: 4px 6px; white-space: nowrap; text-align: center; }

.asr-th-divider { border-right: 2px solid #6a8fc0; }
.asr-param-th   { background: #2a3f5f; }
.asr-unit       { font-size: 8.5px; opacity: .75; font-weight: 400; margin-top: 1px; }

/* Header tint by param type (matches the design language) */
.asr-type-physical          { color: #add8ff !important; }
.asr-type-physical .asr-unit{ color: #add8ff; }
.asr-type-chemical          { color: #ffd699 !important; }
.asr-type-chemical .asr-unit{ color: #ffd699; }
.asr-type-ondemand          { color: #ffd699 !important; }
.asr-type-ondemand .asr-unit{ color: #ffd699; }
.asr-type-microbiologicalkit, .asr-type-microbiologicalmf { color: #ffaaaa !important; }
.asr-type-microbiologicalkit .asr-unit, .asr-type-microbiologicalmf .asr-unit { color: #ffaaaa; }

.asr-tbl tbody td { padding: 3px 6px; }
.asr-row-alt      { background: #f5f5f5; }
.asr-coord        { font-size: 9.5px; }
.nowrap           { white-space: nowrap; }

/* Override global td.mono (DM Mono ~11.5px renders blurry on small text). */
.asr-page .asr-tbl td.mono {
  font-family: 'DM Sans', sans-serif;
  font-variant-numeric: tabular-nums;
  font-size: 11px;
  letter-spacing: 0;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

.asr-param-cell {
  text-align: center;
  font-variant-numeric: tabular-nums;
}
.asr-exceed {
  background: #ffcccc !important;
  font-weight: 700;
  color: #991b1b;
}

.asr-totals  { background: #1a2e4a; color: #fff; font-weight: 700; font-size: 9.5px; }
.asr-totals td { padding: 5px 8px; }

.asr-legend {
  margin-top: 10px;
  font-size: 10.5px;
  color: var(--muted);
  line-height: 1.8;
}
.asr-legend-swatch {
  display: inline-block;
  width: 14px; height: 14px;
  background: #ffcccc;
  border: 1px solid #f5c6c6;
  border-radius: 2px;
  vertical-align: middle;
  margin-right: 4px;
}

/* ── Skeleton ─────────────────────────────────────────────────────── */
.asr-sk .sk {
  background: linear-gradient(90deg, #e5e7eb 0%, #f3f4f6 50%, #e5e7eb 100%);
  background-size: 200% 100%;
  border-radius: 4px;
  animation: asr-shimmer 1.4s infinite linear;
}
@keyframes asr-shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
.asr-sk .sk-banner  { height: 80px; margin-bottom: 12px; }
.asr-sk .sk-section { height: 14px; width: 280px; margin-bottom: 8px; }
.asr-sk .sk-tbl     { border: 1px solid #e5e7eb; border-radius: 5px; overflow: hidden; }
.asr-sk .sk-tbl-head, .asr-sk .sk-tbl-row {
  display: grid;
  grid-template-columns: repeat(12, 1fr);
  gap: 8px;
  padding: 8px 12px;
}
.asr-sk .sk-tbl-head { background: #f3f4f6; border-bottom: 1px solid #e5e7eb; }
.asr-sk .sk-tbl-row + .sk-tbl-row { border-top: 1px solid #f3f4f6; }
.asr-sk .sk-th, .asr-sk .sk-td { height: 12px; }

/* ── Print ─────────────────────────────────────────────────────────── */
/* A3 landscape so the wide parameter table actually fits. Zoom shrinks
   further on Chromium/Edge; on Firefox the user may need to choose A3 in
   the print dialog. */
@page {
  size: A3 landscape;
  margin: 8mm;
}
@media print {
  .filters, .btn, nav, aside, .asr-sk { display: none !important; }

  /* Hide topbar/sidebar wrappers if present (defensive) */
  .app-topbar, .app-sidebar, .topbar, .sidebar { display: none !important; }

  body { font-size: 9px; background: #fff !important; }

  .asr-report-card { box-shadow: none; border: 0; padding: 0; }
  .asr-tbl-wrap    { overflow: visible !important; border: 0 !important; }

  /* Shrink the table to fit a printable width */
  .asr-tbl {
    min-width: 0 !important;
    width: 100%;
    font-size: 7px;
    table-layout: auto;
    zoom: 0.78;                  /* Chromium / Edge / Safari */
  }
  .asr-tbl tbody td,
  .asr-tbl thead th { padding: 1.5px 3px; word-break: break-word; }

  .asr-th-cell th .asr-unit { font-size: 6px; }
  .asr-coord                { font-size: 6.5px; }

  /* Keep red-exceed background visible in print */
  .asr-exceed { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
  .asr-totals, .asr-th-group, .asr-th-cell {
    -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;
  }
}
</style>
