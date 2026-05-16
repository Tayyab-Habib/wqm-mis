<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRbac } from '../../../composables/useRbac.js'
import { useRoute } from 'vue-router'
import { reportService }   from '../../../services/reportService.js'
import { dropdownService } from '../../../services/dropdownService.js'
import { exportToXLSX }    from '../../../utils/exportHelpers.js'
import { useUserStore }    from '../../../stores/useUserStore.js'

const userStore = useUserStore()
const route     = useRoute()
const loading   = ref(false)
const errorMsg  = ref('')
const generated = ref(false)

// ── Dropdowns ──────────────────────────────────────────────────────────
const divisions    = ref([])
const districts    = ref([])
const laboratories = ref([])
const regions      = ref([])
const circles      = ref([])
const phedDivs     = ref([])

// ── Filters ────────────────────────────────────────────────────────────
const filters = ref({
  from_date:        new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
  to_date:          new Date().toISOString().split('T')[0],
  region_id:        '',
  division_id:      '',
  circle_id:        '',
  district_id:      '',
  phed_division_id: '',
  laboratory_id:    '',
  sample_type:      '',   // matches desired_test DB values
  result:           '',
})

// ── Cascaded dropdowns (Region → Division → PHE Circle → District → PHE Division) ──
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

// ── Data ───────────────────────────────────────────────────────────────
const allRows     = ref([])
const generatedAt = ref('')

// desired_test comes as array (model accessor) or comma-separated string
function resolveDesiredTest(val) {
  if (!val) return '—'
  const list = Array.isArray(val) ? val : String(val).split(',').map(v => v.trim())
  // Each label can yield multiple letters (e.g. "Physical & Chemical" → P + C)
  const codes = new Set()
  list.forEach(item => abbreviateTest(item).forEach(c => codes.add(c)))
  const order = ['P', 'C', 'M', 'OD']
  return order.filter(c => codes.has(c)).join('') || '—'
}

// Map a single desired_test label to an array of one-letter codes
function abbreviateTest(v) {
  if (!v) return []
  const s = v.toLowerCase()
  if (s.includes('on demand')) return ['OD']
  const out = []
  if (s.includes('microbiological') || s.includes('microbial') || s.includes('bacteriological')) out.push('M')
  if (s.includes('physical')) out.push('P')
  if (s.includes('chemical'))  out.push('C')
  return out
}

function resolveResult(s) {
  if (!s) return '—'
  const v = String(s).toLowerCase()
  if (v === '1' || v === 'fit')   return 'Fit'
  if (v === '2' || v === 'unfit') return 'Unfit'
  return s
}

// ── Derive Cause + Specific Ion/Component from failing analysis details ──
// A detail "fails" when its analysis_result is outside the test's guideline range.
function detailExceeds(detail) {
  if (!detail || !detail.test) return false
  const val = parseFloat(detail.analysis_result)
  if (!isFinite(val)) return false
  const t = detail.test
  const minRaw = t.who_guideline_start ?? t.laboratory_guideline_start
  const maxRaw = t.who_guideline_end   ?? t.laboratory_guideline_end
  const min = (minRaw !== null && minRaw !== undefined && minRaw !== '') ? parseFloat(minRaw) : null
  const max = (maxRaw !== null && maxRaw !== undefined && maxRaw !== '') ? parseFloat(maxRaw) : null
  if (min !== null && isFinite(min) && val < min) return true
  if (max !== null && isFinite(max) && val > max) return true
  return false
}

// Normalize test.type → SRS-compliant Cause category
function normalizeCauseCategory(type) {
  if (!type) return null
  const s = String(type).toLowerCase()
  if (s.includes('microb') || s.includes('biolog') || s.includes('bacter')) return 'Biological'
  if (s.includes('chem'))     return 'Chemical'
  if (s.includes('phys'))     return 'Physical'
  // Fallback: capitalize first letter
  return type.charAt(0).toUpperCase() + type.slice(1).toLowerCase()
}

function deriveContamination(s) {
  const details = s.water_sample_details || s.waterSampleDetails || []
  const failing = details.filter(detailExceeds)
  if (!failing.length) return { cause: null, ion: null }

  const causes = [...new Set(failing.map(d => normalizeCauseCategory(d.test?.type)).filter(Boolean))]
  const ions = failing.map(d => {
    const name = d.test?.water_quality_parameter || 'Parameter'
    const unit = d.test?.unit || ''
    const val  = d.analysis_result
    return unit ? `${name} (${val} ${unit})` : `${name} (${val})`
  })

  return {
    cause: causes.join(', ') || null,
    ion:   ions.join(', ')   || null,
  }
}

// Defensive date parser — handles ISO ("2026-05-13T..."), SQL ("2026-05-13 10:30:00"),
// and the Laravel pretty accessor format ("10 May, 2026 14:30"). Falls back to the raw
// string if the format is unrecognized (previous code threw NaN in some browsers).
const MONTH_ABBR = { jan:0, feb:1, mar:2, apr:3, may:4, jun:5,
                     jul:6, aug:7, sep:8, oct:9, nov:10, dec:11 }
function formatDate(dt) {
  if (!dt) return '—'
  const s = String(dt).trim()

  // ISO / SQL: YYYY-MM-DD...
  const iso = s.match(/^(\d{4})-(\d{2})-(\d{2})/)
  if (iso) {
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']
    return `${iso[3]}-${months[parseInt(iso[2],10)-1]}-${iso[1].slice(-2)}`
  }
  // Pretty: "10 May, 2026 14:30"
  const pretty = s.match(/^(\d{1,2})\s+([A-Za-z]{3})[a-z]*,?\s+(\d{4})/)
  if (pretty) {
    return `${pretty[1].padStart(2,'0')}-${pretty[2]}-${pretty[3].slice(-2)}`
  }
  // Last-resort native parse
  const d = new Date(s)
  if (!Number.isNaN(d.getTime())) {
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: '2-digit' }).replace(/ /g, '-')
  }
  return s
}

function mapSampleRow(s, idx) {
  const lat = s.latitude  ? parseFloat(s.latitude).toFixed(4)  : null
  const lng = s.longitude ? parseFloat(s.longitude).toFixed(4) : null
  const { cause: derivedCause, ion: derivedIon } = deriveContamination(s)
  return {
    sn:       idx + 1,
    id:       s.slug || String(s.id),
    wss:      s.water_scheme?.name || s.waterScheme?.name || s.water_sample_address || s.sample_name || '—',
    district: s.district?.name || '—',
    division: s.division?.name || '—',
    region:   s.region?.name   || '—',
    circle:   s.circle?.name   || '—',
    phedDiv:  s.phed_division?.name || s.phedDivision?.name || '—',
    lab:      s.laboratory?.name || '—',
    date:     s.sampled_at ? formatDate(s.sampled_at) : '—',
    coords:   (lat && lng) ? `${lat}, ${lng}` : '—',
    params:   resolveDesiredTest(s.desired_test),
    result:   resolveResult(s.result),
    cause:    derivedCause || s.remarks || '—',
    ion:      derivedIon   || '—',
  }
}

// ── Load dropdowns ─────────────────────────────────────────────────────
async function loadDropdowns() {
  try {
    const [divRes, labRes, regRes, cirRes, distRes, phdRes] = await Promise.all([
      dropdownService.getDivisions(),
      dropdownService.getLaboratories(),
      dropdownService.getRegions(),
      dropdownService.getCircles(),
      dropdownService.getDistricts(),
      dropdownService.getPhedDivisions(),
    ])
    divisions.value    = divRes.data?.data  || divRes.data  || []
    laboratories.value = labRes.data?.data  || labRes.data  || []
    regions.value      = regRes.data?.data  || regRes.data  || []
    circles.value      = cirRes.data?.data  || cirRes.data  || []
    districts.value    = distRes.data?.data || distRes.data || []
    phedDivs.value     = phdRes.data?.data  || phdRes.data  || []
  } catch (e) { console.error('Dropdown error:', e) }
}

// ── Client-side date validation ────────────────────────────────────────
const dateError = computed(() => {
  const f = filters.value.from_date
  const t = filters.value.to_date
  if (f && t && f > t) return 'From date must be on or before To date.'
  return ''
})

// Request sequence — drop stale responses when filters change rapidly
let requestSeq = 0

// ── Generate report (called on mount + on Generate button) ─────────────
async function generateReport() {
  if (dateError.value) {
    errorMsg.value  = dateError.value
    generated.value = false
    loading.value   = false
    return
  }

  const mySeq = ++requestSeq
  loading.value  = true
  errorMsg.value = ''
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
    if (filters.value.result)           payload.result           = filters.value.result
    // sample_type maps to actual desired_test DB values
    if (filters.value.sample_type)      payload.sample_type      = filters.value.sample_type

    const res  = await reportService.getWaterQualityAnalysis(payload)
    if (mySeq !== requestSeq) return    // a newer request was issued — drop this response

    const data = res.data?.data || res.data || []
    allRows.value = Array.isArray(data) ? data.map(mapSampleRow) : []
    generatedAt.value = new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
    generated.value = true
  } catch (e) {
    if (mySeq !== requestSeq) return
    errorMsg.value = 'Failed to generate report: ' + (e.response?.data?.message || e.message)
    console.error('GSR error:', e)
  } finally {
    if (mySeq === requestSeq) loading.value = false
  }
}

// ── Show only completed analyses so Total = Fit + Unfit (SRS: "samples tested with full result detail") ──
const filteredRows = computed(() => allRows.value.filter(r => r.result === 'Fit' || r.result === 'Unfit'))

// ── Summary stats ──────────────────────────────────────────────────────
const totalCount       = computed(() => filteredRows.value.length)
const fitCount         = computed(() => filteredRows.value.filter(r => r.result === 'Fit').length)
const unfitCount       = computed(() => filteredRows.value.filter(r => r.result === 'Unfit').length)
const unfitPct         = computed(() => totalCount.value > 0
  ? ((unfitCount.value / totalCount.value) * 100).toFixed(1) + '%' : '—')
const districtsCovered = computed(() => new Set(filteredRows.value.map(r => r.district).filter(d => d !== '—')).size)
// Was reading r.phedDiv which mapSampleRow didn't return (always 0). Now mapSampleRow
// returns phedDiv, so this computes the real count of distinct PHE divisions in the data.
const phedDivCount     = computed(() => new Set(filteredRows.value.map(r => r.phedDiv).filter(d => d !== '—')).size)
// Distinct labs that submitted at least one sample in this period (was reading
// laboratories.value.length which counted dropdown options, not actual reporters).
const activeLabsCount  = computed(() => new Set(filteredRows.value.map(r => r.lab).filter(l => l !== '—')).size)

// ── Group rows by district ─────────────────────────────────────────────
const groupedByDistrict = computed(() => {
  const groups = {}
  filteredRows.value.forEach(r => {
    const key = r.district || 'Unknown District'
    if (!groups[key]) groups[key] = []
    groups[key].push(r)
  })
  return groups
})

// ── Report header labels ───────────────────────────────────────────────
const reportPeriod = computed(() => `${filters.value.from_date || '—'} to ${filters.value.to_date || '—'}`)
const reportLab = computed(() => {
  if (!filters.value.laboratory_id) return 'All Labs'
  return laboratories.value.find(l => l.id == filters.value.laboratory_id)?.name || 'All Labs'
})
const reportDivision = computed(() => {
  if (!filters.value.division_id) return 'All Divisions'
  return divisions.value.find(d => d.id == filters.value.division_id)?.name || 'All Divisions'
})
const reportDistrict = computed(() => {
  if (!filters.value.district_id) return 'All Districts'
  return districts.value.find(d => d.id == filters.value.district_id)?.name || 'All Districts'
})
const generatedBy = computed(() => userStore.currentUser?.name || 'System')

// ── Test type badge style (light pill matching reference UI) ─────────
function typeStyle(type) {
  if (!type || type === '—') return 'background:#f1f5f9;color:#475569;border:1px solid #cbd5e1'
  // Combined codes (e.g. PCM) get a calm blue; pure M gets a different blue; chemical-only ochre; physical-only purple
  const t = type.toUpperCase()
  if (t === 'M')                      return 'background:#dbeafe;color:#1d4ed8;border:1px solid #bfdbfe'
  if (t === 'C')                      return 'background:#fef3c7;color:#92400e;border:1px solid #fde68a'
  if (t === 'P')                      return 'background:#ede9fe;color:#6d28d9;border:1px solid #ddd6fe'
  if (t === 'OD')                     return 'background:#f3f4f6;color:#374151;border:1px solid #e5e7eb'
  // Multi-letter combinations (PC, PM, CM, PCM)
  return 'background:#cffafe;color:#0e7490;border:1px solid #a5f3fc'
}

// ── Export ─────────────────────────────────────────────────────────────
function exportReport() {
  if (!filteredRows.value.length) { alert('No data to export.'); return }
  exportToXLSX(filteredRows.value.map(r => ({
    'S#':                       r.sn,
    'Sample ID':                r.id,
    'WSS / Client Name':        r.wss,
    'Sampling Date':            r.date,
    'Lab':                      r.lab,
    'District':                 r.district,
    'Latitude / Longitude':     r.coords,
    'Parameters Tested':        r.params,
    'Result':                   r.result,
    'Cause':                    r.cause,
    'Specific Ion / Component': r.ion,
  })), 'GSR_General_Summary_Report')
}

function printReport() { window.print() }

function clearFilters() {
  filters.value = {
    from_date:        new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
    to_date:          new Date().toISOString().split('T')[0],
    region_id:        '',
    division_id:      '',
    circle_id:        '',
    district_id:      '',
    phed_division_id: '',
    laboratory_id:    '',
    sample_type:      '',
    result:           '',
  }
}

// ── Auto-refresh on filter change (debounced) ─────────────────────────
let filterTimer = null
watch(filters, () => {
  if (!generated.value) return     // skip until first load completes
  if (dateError.value) return      // skip while date range is invalid
  clearTimeout(filterTimer)
  filterTimer = setTimeout(generateReport, 350)
}, { deep: true })

// Seed filters from URL query params (drill-down from GAR pushes
// district_id / laboratory_id / region_id / from_date / to_date / etc.)
function seedFromQuery() {
  const q = route.query || {}
  const KEYS = ['from_date','to_date','region_id','division_id','circle_id',
                'district_id','phed_division_id','laboratory_id','sample_type','result']
  KEYS.forEach(k => {
    if (q[k] !== undefined && q[k] !== '') filters.value[k] = String(q[k])
  })
}

// RBAC: pre-select + lock filters at the user's hierarchy scope
const rbac = useRbac()
function applyRbacLocks() {
  if (rbac.regionId.value)       filters.value.region_id        = String(rbac.regionId.value)
  if (rbac.circleId.value)       filters.value.circle_id        = String(rbac.circleId.value)
  if (rbac.phedDivisionId.value) filters.value.phed_division_id = String(rbac.phedDivisionId.value)
  if (rbac.laboratoryId.value)   filters.value.laboratory_id    = String(rbac.laboratoryId.value)
}

onMounted(async () => {
  seedFromQuery()
  applyRbacLocks()
  await loadDropdowns()
  await generateReport()
})
</script>

<template>
  <div>
    <!-- ── Filters ── -->
    <div class="filters" style="flex-wrap:wrap;gap:6px;margin-bottom:10px">
      <div class="fg"><label>From</label><input type="date" v-model="filters.from_date"></div>
      <div class="fg"><label>To</label><input type="date" v-model="filters.to_date"></div>

      <div class="fg">
        <label>Region (CE)</label>
        <select v-model="filters.region_id" :disabled="!!rbac.regionId.value"
                @change="filters.circle_id='';filters.division_id='';filters.district_id='';filters.phed_division_id='';filters.laboratory_id=''">
          <option value="">All Regions</option>
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
        <select v-model="filters.circle_id" :disabled="!!rbac.circleId.value"
                @change="filters.district_id='';filters.phed_division_id='';filters.laboratory_id=''">
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

      <!-- sample_type values match actual desired_test DB values -->
      <div class="fg">
        <label>Sample Type</label>
        <select v-model="filters.sample_type">
          <option value="">All Types</option>
          <option value="Physical">Physical (P)</option>
          <option value="Physical &amp; Chemical">Physical &amp; Chemical (PC)</option>
          <option value="Microbiological">Microbiological (M)</option>
          <option value="On Demand">On Demand</option>
        </select>
      </div>

      <div class="fg">
        <label>Result</label>
        <select v-model="filters.result">
          <option value="">All</option>
          <option value="Fit">Fit</option>
          <option value="Unfit">Unfit</option>
        </select>
      </div>

      <button class="btn btn-sec btn-sm" style="align-self:flex-end" @click="clearFilters">✕ Clear Filters</button>
      <div class="tsp"></div>
      <button class="btn btn-sec btn-sm" style="align-self:flex-end" @click="exportReport">Export .xlsx</button>
      <button class="btn btn-sec btn-sm" style="align-self:flex-end" @click="printReport">Print PDF</button>
    </div>

    <!-- Inline date range warning -->
    <div v-if="dateError"
         style="background:#fef9c3;border:1px solid #fde047;border-radius:6px;padding:8px 12px;margin-bottom:10px;color:#854d0e;font-size:12px">
      ⚠ {{ dateError }}
    </div>

    <!-- Error -->
    <div v-if="errorMsg && !dateError"
         style="background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:10px 14px;margin-bottom:10px;color:#b91c1c;font-size:12px">
      {{ errorMsg }}
    </div>

    <!-- Skeleton loading on first load -->
    <div v-if="loading" class="gsr-sk">
      <!-- Banner -->
      <div class="sk sk-banner"></div>
      <!-- 6 stat cards -->
      <div class="sk-cards">
        <div class="sk-card" v-for="i in 6" :key="'sc'+i">
          <div class="sk sk-lbl"></div>
          <div class="sk sk-val"></div>
        </div>
      </div>
      <!-- Section head + table skeleton -->
      <div class="sk sk-section-head"></div>
      <div class="sk-tbl">
        <div class="sk-tbl-head">
          <div class="sk sk-th" v-for="i in 11" :key="'gh'+i"></div>
        </div>
        <div class="sk-tbl-row" v-for="r in 8" :key="'gr'+r">
          <div class="sk sk-td" v-for="i in 11" :key="'gc'+r+'-'+i"></div>
        </div>
      </div>
    </div>

    <template v-if="generated && !loading">
      <!-- ── Report header banner ── -->
      <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:10px 16px;margin-bottom:8px;font-size:12px">
        <div style="font-weight:700;color:#166534;margin-bottom:3px">
          General Summary Report (GSR) &nbsp;|&nbsp; Period: {{ reportPeriod }} &nbsp;|&nbsp; {{ reportLab }} &nbsp;|&nbsp; Annexure-2
        </div>
        <div style="color:#6b7280;font-size:11px">
          Generated By: <b>{{ generatedBy }}</b> &nbsp;·&nbsp;
          Generated On: <b>{{ generatedAt }}</b> &nbsp;·&nbsp;
          Lab: <b>{{ reportLab }}</b> &nbsp;·&nbsp;
          Division: <b>{{ reportDivision }}</b> &nbsp;·&nbsp;
          District: <b>{{ reportDistrict }}</b>
        </div>
      </div>

      <!-- ── Summary stat cards ── -->
      <div class="cards" style="grid-template-columns:repeat(6,1fr);margin-bottom:14px">
        <div class="card">
          <div class="c-lbl">Total Samples</div>
          <div class="c-val">{{ totalCount.toLocaleString() }}</div>
          <div class="c-sub">Tested</div>
        </div>
        <div class="card c-green">
          <div class="c-lbl">Fit</div>
          <div class="c-val">{{ fitCount.toLocaleString() }}</div>
          <div class="c-sub">Potable</div>
        </div>
        <div class="card c-red">
          <div class="c-lbl">% Unfit</div>
          <div class="c-val">{{ unfitPct }}</div>
          <div class="c-sub">{{ unfitCount }} Contaminated</div>
        </div>
        <div class="card">
          <div class="c-lbl">Districts Covered</div>
          <div class="c-val">{{ districtsCovered }}</div>
        </div>
        <div class="card">
          <div class="c-lbl">PHE Divisions</div>
          <div class="c-val">{{ phedDivCount }}</div>
        </div>
        <div class="card">
          <div class="c-lbl">Active Labs</div>
          <div class="c-val">{{ activeLabsCount }}</div>
        </div>
      </div>

      <!-- ── Sample-wise Results table ── -->
      <div class="sh" style="margin-bottom:8px"><h2>Sample-wise Results</h2></div>

      <div class="tbl-wrap" style="overflow-x:auto">
        <table class="gsr-table" style="font-size:12.5px;min-width:1100px;border-collapse:collapse;width:100%;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;text-rendering:optimizeLegibility">
          <thead>
            <tr style="background:#f3f4f6;color:#1f2937">
              <th style="text-align:left;padding:10px 12px;font-weight:600;font-size:11.5px;color:#374151;border-bottom:1px solid #e5e7eb;width:36px">S#</th>
              <th style="text-align:left;padding:10px 12px;font-weight:600;font-size:11.5px;color:#374151;border-bottom:1px solid #e5e7eb">Sample ID</th>
              <th style="text-align:left;padding:10px 12px;font-weight:600;font-size:11.5px;color:#374151;border-bottom:1px solid #e5e7eb">WSS / Client Name</th>
              <th style="text-align:left;padding:10px 12px;font-weight:600;font-size:11.5px;color:#374151;border-bottom:1px solid #e5e7eb">Sampling Date</th>
              <th style="text-align:left;padding:10px 12px;font-weight:600;font-size:11.5px;color:#374151;border-bottom:1px solid #e5e7eb">Lab</th>
              <th style="text-align:left;padding:10px 12px;font-weight:600;font-size:11.5px;color:#374151;border-bottom:1px solid #e5e7eb">Latitude / Longitude</th>
              <th style="text-align:center;padding:10px 12px;font-weight:600;font-size:11.5px;color:#374151;border-bottom:1px solid #e5e7eb">Parameters Tested</th>
              <th style="text-align:center;padding:10px 12px;font-weight:600;font-size:11.5px;color:#374151;border-bottom:1px solid #e5e7eb">Result</th>
              <th style="text-align:left;padding:10px 12px;font-weight:600;font-size:11.5px;color:#374151;border-bottom:1px solid #e5e7eb">Cause</th>
              <th style="text-align:left;padding:10px 12px;font-weight:600;font-size:11.5px;color:#374151;border-bottom:1px solid #e5e7eb">Specific Ion / Component</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!filteredRows.length">
              <td colspan="10" style="text-align:center;padding:28px;color:var(--muted)">
                No samples found for the selected filters.
              </td>
            </tr>

            <template v-for="(rows, district) in groupedByDistrict" :key="district">
              <!-- District header row -->
              <tr>
                <td colspan="10"
                    style="background:#ffffff;padding:8px 14px;border-bottom:1px solid #e5e7eb;border-top:2px solid #1e3a8a">
                  <span style="font-size:13px;margin-right:6px">📍</span>
                  <span style="color:#1e3a8a;font-weight:700;font-size:11.5px;text-transform:uppercase;letter-spacing:.05em">{{ district }} District</span>
                  <span style="font-weight:400;color:#9ca3af;font-size:11px;margin-left:10px">
                    ({{ rows.length }} sample{{ rows.length !== 1 ? 's' : '' }} —
                    Fit: {{ rows.filter(r => r.result === 'Fit').length }},
                    Unfit: {{ rows.filter(r => r.result === 'Unfit').length }})
                  </span>
                </td>
              </tr>

              <!-- Sample rows -->
              <tr v-for="r in rows" :key="r.id" style="border-bottom:1px solid #f1f5f9">
                <td style="padding:9px 12px;color:#4b5563;font-size:12.5px;text-align:center;font-variant-numeric:tabular-nums">{{ r.sn }}</td>
                <td style="padding:9px 12px;font-size:12.5px;font-weight:600;color:#0f172a;font-variant-numeric:tabular-nums;letter-spacing:0.01em">{{ r.id }}</td>
                <td style="padding:9px 12px;font-size:12.5px;color:#111827">{{ r.wss }}</td>
                <td style="padding:9px 12px;font-size:12.5px;color:#111827;white-space:nowrap;font-variant-numeric:tabular-nums">{{ r.date }}</td>
                <td style="padding:9px 12px;font-size:12.5px;color:#111827">{{ r.lab }}</td>
                <td style="padding:9px 12px;font-size:12.5px;color:#111827;font-variant-numeric:tabular-nums;white-space:nowrap">{{ r.coords }}</td>
                <td style="padding:9px 12px;text-align:center">
                  <span :style="typeStyle(r.params) + ';display:inline-block;padding:3px 10px;border-radius:12px;font-size:11.5px;font-weight:600;line-height:1'">{{ r.params }}</span>
                </td>
                <td style="padding:9px 12px;text-align:center">
                  <span v-if="r.result === 'Fit'"
                        style="display:inline-block;padding:3px 12px;border-radius:12px;font-size:11.5px;font-weight:600;background:#dcfce7;color:#166534;border:1px solid #bbf7d0">Fit</span>
                  <span v-else-if="r.result === 'Unfit'"
                        style="display:inline-block;padding:3px 12px;border-radius:12px;font-size:11.5px;font-weight:600;background:#fee2e2;color:#991b1b;border:1px solid #fecaca">Unfit</span>
                  <span v-else style="color:#6b7280">—</span>
                </td>
                <td style="padding:9px 12px;font-size:12.5px;color:#111827;max-width:160px" :title="r.cause">
                  <div style="max-height:80px;overflow-y:auto;line-height:1.45">{{ r.cause }}</div>
                </td>
                <td style="padding:9px 12px;font-size:12.5px;color:#111827;max-width:240px" :title="r.ion">
                  <div style="max-height:80px;overflow-y:auto;line-height:1.45;white-space:normal;word-break:break-word">{{ r.ion }}</div>
                </td>
              </tr>
            </template>
          </tbody>

          <tfoot>
            <tr style="background:var(--navy)">
              <td colspan="10" style="padding:10px 14px">
                <div style="display:flex;align-items:center;justify-content:flex-end;gap:10px">
                  <span style="color:#fff;font-weight:700;font-size:12.5px">TOTALS ({{ totalCount.toLocaleString() }} samples)</span>
                  <span style="display:inline-flex;align-items:center;background:#16a34a;color:#fff;border-radius:12px;padding:3px 12px;font-size:11.5px;font-weight:600;line-height:1.4">Fit: {{ fitCount }}</span>
                  <span style="display:inline-flex;align-items:center;background:#dc2626;color:#fff;border-radius:12px;padding:3px 12px;font-size:11.5px;font-weight:600;line-height:1.4">Unfit: {{ unfitCount }}</span>
                </div>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Legend -->
      <div style="font-size:10.5px;color:var(--muted);margin-top:8px;line-height:1.9">
        <b>Test Type:</b>
        P = Physical &nbsp;·&nbsp;
        PC = Physical &amp; Chemical &nbsp;·&nbsp;
        M = Microbiological &nbsp;·&nbsp;
        OD = On Demand<br>
        <b>Cause:</b>
        Biological = microbial contamination &nbsp;·&nbsp;
        Chemical = ionic/dissolved &nbsp;·&nbsp;
        Physical = turbidity/colour/odour &nbsp;·&nbsp;
        — = sample is Fit
      </div>
    </template>
  </div>
</template>

<style>
/* ── Skeleton loading (matches GSR layout: banner + 6 cards + table) ─── */
.gsr-sk .sk {
  background: linear-gradient(90deg, #e5e7eb 0%, #f3f4f6 50%, #e5e7eb 100%);
  background-size: 200% 100%;
  border-radius: 4px;
  animation: gsr-sk-shimmer 1.4s infinite linear;
}
@keyframes gsr-sk-shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
.gsr-sk .sk-banner    { height: 50px; margin-bottom: 12px; }
.gsr-sk .sk-cards {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 12px;
  margin-bottom: 14px;
}
.gsr-sk .sk-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.gsr-sk .sk-lbl { height: 10px; width: 60%; }
.gsr-sk .sk-val { height: 22px; width: 45%; }
.gsr-sk .sk-section-head { height: 16px; width: 220px; margin: 6px 0 10px; }
.gsr-sk .sk-tbl {
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  overflow: hidden;
}
.gsr-sk .sk-tbl-head, .gsr-sk .sk-tbl-row {
  display: grid;
  grid-template-columns: 0.4fr 1.2fr 1.5fr 0.8fr 1fr 1fr 1fr 0.7fr 0.7fr 1.2fr 1.4fr;
  gap: 8px;
  padding: 9px 12px;
}
.gsr-sk .sk-tbl-head { background: #f3f4f6; border-bottom: 1px solid #e5e7eb; }
.gsr-sk .sk-tbl-row + .sk-tbl-row { border-top: 1px solid #f3f4f6; }
.gsr-sk .sk-th, .gsr-sk .sk-td { height: 12px; }

/* ── Print rules (A3 landscape, preserve colors) ─────────────────────── */
@page {
  size: A3 landscape;
  margin: 8mm;
}
@media print {
  .filters, .btn, nav, aside, .gsr-sk { display: none !important; }
  body { font-size: 9px; background: #fff !important; }

  .tbl-wrap { overflow: visible !important; border: 0 !important; }
  .tbl-wrap table { font-size: 8px !important; table-layout: auto; min-width: 0 !important; }
  .tbl-wrap td, .tbl-wrap th { padding: 2px 4px !important; word-break: break-word; }

  /* Keep result badges and header backgrounds visible on paper */
  .cards .card, thead tr, tfoot tr, [class*="r-red"], [class*="r-green"], [class*="r-amber"] {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
}
</style>
