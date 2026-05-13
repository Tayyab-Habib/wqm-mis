<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { reportService } from '../../../services/reportService.js'
import { dropdownService } from '../../../services/dropdownService.js'
import { exportToXLSX } from '../../../utils/exportHelpers.js'

const router = useRouter()

// Drill-down: open the GSR pre-filtered for a specific district (and optionally lab)
function openGSR(districtId, labId = null) {
  if (!districtId) return
  const query = {
    district_id: districtId,
    from_date:   filters.value.from_date,
    to_date:     filters.value.to_date,
  }
  if (labId)                       query.laboratory_id    = labId
  if (filters.value.region_id)     query.region_id        = filters.value.region_id
  if (filters.value.circle_id)     query.circle_id        = filters.value.circle_id
  if (filters.value.division_id)   query.division_id      = filters.value.division_id
  if (filters.value.phed_division_id) query.phed_division_id = filters.value.phed_division_id
  router.push({ name: 'GSR', query })
}

const loading    = ref(false)
const errorMsg   = ref('')
const divisions  = ref([])
const laboratories = ref([])
const regions    = ref([])
const circles    = ref([])
const districts  = ref([])
const phedDivs   = ref([])
const rawSamples = ref([])
const generated  = ref(false)

const filters = ref({
  from_date:       new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
  to_date:         new Date().toISOString().split('T')[0],
  region_id:       '',
  division_id:     '',
  circle_id:       '',
  district_id:     '',
  phed_division_id:'',
  laboratory_id:   '',
  sample_type:     '',
})

// Cascaded dropdowns per SRS hierarchy: Region → Division → PHE Circle → District → PHE Division
const filteredDivisions = computed(() =>
  filters.value.region_id
    ? divisions.value.filter(d => d.region_id == filters.value.region_id)
    : divisions.value
)
const filteredCircles = computed(() =>
  filters.value.region_id
    ? circles.value.filter(c => c.region_id == filters.value.region_id)
    : circles.value
)
const filteredDistricts = computed(() => {
  let list = districts.value
  if (filters.value.division_id) list = list.filter(d => d.division_id == filters.value.division_id)
  if (filters.value.circle_id)   list = list.filter(d => d.circle_id == filters.value.circle_id)
  return list
})
const filteredPhedDivs = computed(() => {
  let list = phedDivs.value
  if (filters.value.district_id) list = list.filter(p => p.district_id == filters.value.district_id)
  if (filters.value.circle_id)   list = list.filter(p => p.circle_id == filters.value.circle_id)
  return list
})
const filteredLaboratories = computed(() => {
  let list = laboratories.value
  if (filters.value.district_id) list = list.filter(l => l.district_id == filters.value.district_id)
  if (filters.value.division_id) list = list.filter(l => l.division_id == filters.value.division_id)
  return list
})

// ── Load dropdowns ────────────────────────────────────────────────────
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

// ── Client-side date validation ───────────────────────────────────────
const dateError = computed(() => {
  const f = filters.value.from_date
  const t = filters.value.to_date
  if (f && t && f > t) return 'From date must be on or before To date.'
  return ''
})

// ── Generate report ───────────────────────────────────────────────────
// Request sequence — drop stale responses if a newer request fires while the
// previous one is still in flight (race condition on rapid filter changes).
let requestSeq = 0

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
    if (filters.value.division_id)      payload.division_id      = filters.value.division_id
    if (filters.value.district_id)      payload.district_id      = filters.value.district_id
    if (filters.value.laboratory_id)    payload.laboratory_id    = filters.value.laboratory_id
    if (filters.value.region_id)        payload.region_id        = filters.value.region_id
    if (filters.value.circle_id)        payload.circle_id        = filters.value.circle_id
    if (filters.value.phed_division_id) payload.phed_division_id = filters.value.phed_division_id
    if (filters.value.sample_type)      payload.sample_type      = filters.value.sample_type

    const res  = await reportService.getWaterQualityAnalysis(payload)
    if (mySeq !== requestSeq) return   // a newer request was issued — drop this response

    const data = res.data?.data || res.data || []
    rawSamples.value = Array.isArray(data) ? data : []
    generated.value  = true
  } catch (e) {
    if (mySeq !== requestSeq) return
    errorMsg.value = 'Failed to generate report: ' + (e.response?.data?.message || e.message)
    console.error('GAR error:', e)
  } finally {
    if (mySeq === requestSeq) loading.value = false
  }
}

// ── Aggregate raw samples into lab-wise rows ──────────────────────────
const labRows = computed(() => {
  if (!rawSamples.value.length) return []

  const labMap = {}
  rawSamples.value.forEach(s => {
    const labId   = s.laboratory_id || 0
    const labName = s.laboratory?.name || `Lab ${labId}`
    if (!labMap[labId]) {
      labMap[labId] = {
        id:       `lab-${labId}`,
        labId,
        lab:      labName,
        regionSet:   new Set(),
        divisionSet: new Set(),
        districtSet: new Set(),
        tested:   0, fit: 0, unfit: 0,
        districtRows: {},
      }
    }
    const row = labMap[labId]
    row.tested++
    const distName = s.district?.name || 'Unknown'
    const distId   = s.district_id || s.district?.id || null
    const divName  = s.division?.name || null
    const regName  = s.region?.name   || null
    if (regName)  row.regionSet.add(regName)
    if (divName)  row.divisionSet.add(divName)
    row.districtSet.add(distName)
    if (!row.districtRows[distName]) row.districtRows[distName] = { district: distName, districtId: distId, tested: 0, fit: 0, unfit: 0 }
    const dr = row.districtRows[distName]
    dr.tested++
    if (s.result === 'Fit' || s.result === '1') { row.fit++; dr.fit++ }
    else if (s.result === 'Unfit' || s.result === '2') { row.unfit++; dr.unfit++ }
  })

  return Object.values(labMap)
    .map(r => ({
      ...r,
      regions:       [...r.regionSet].sort().join(', ')   || '—',
      divisions:     [...r.divisionSet].sort().join(', ') || '—',
      districtNames: [...r.districtSet].sort().join(', '),
      districtCount: r.districtSet.size,
      districtList:  Object.values(r.districtRows).sort((a, b) => a.district.localeCompare(b.district)),
      pct:           r.tested > 0 ? ((r.unfit / r.tested) * 100).toFixed(1) : '0.0',
      rag:           r.unfit / (r.tested || 1) > 0.2 ? 'r-red' : r.unfit / (r.tested || 1) > 0.1 ? 'r-amber' : 'r-green',
      ragLabel:      r.unfit / (r.tested || 1) > 0.2 ? 'High' : r.unfit / (r.tested || 1) > 0.1 ? 'Moderate' : 'Good',
    }))
    .sort((a, b) => a.lab.localeCompare(b.lab))   // stable alphabetical lab order
})

// ── KP-level totals ───────────────────────────────────────────────────
const totals = computed(() => {
  const distSet = new Set()
  rawSamples.value.forEach(s => { if (s.district?.name) distSet.add(s.district.name) })
  return {
    tested:           labRows.value.reduce((s, r) => s + r.tested, 0),
    fit:              labRows.value.reduce((s, r) => s + r.fit, 0),
    unfit:            labRows.value.reduce((s, r) => s + r.unfit, 0),
    labs:             labRows.value.length,
    districtsCovered: distSet.size,
  }
})

// ── District-wise grouped by division ────────────────────────────────
const districtByDivision = computed(() => {
  const divMap = {}
  rawSamples.value.forEach(s => {
    const divName  = s.division?.name || 'Unknown Division'
    const distName = s.district?.name || 'Unknown'
    const distId   = s.district_id || s.district?.id || null
    if (!divMap[divName]) divMap[divName] = {}
    if (!divMap[divName][distName]) divMap[divName][distName] = { district: distName, districtId: distId, phedDiv: s.phed_division?.name || s.phedDivision?.name || '—', tested: 0, fit: 0, unfit: 0 }
    const dr = divMap[divName][distName]
    dr.tested++
    if (s.result === 'Fit' || s.result === '1') dr.fit++
    else if (s.result === 'Unfit' || s.result === '2') dr.unfit++
  })
  return Object.entries(divMap).map(([div, districts]) => ({
    division: div,
    rows: Object.values(districts).map(d => ({
      ...d,
      pct: d.tested > 0 ? ((d.unfit / d.tested) * 100).toFixed(1) : '0.0',
      rag: d.unfit / (d.tested || 1) > 0.2 ? 'r-red' : d.unfit / (d.tested || 1) > 0.1 ? 'r-amber' : 'r-green',
    })),
    subtotal: Object.values(districts).reduce((acc, d) => ({
      tested: acc.tested + d.tested, fit: acc.fit + d.fit, unfit: acc.unfit + d.unfit
    }), { tested: 0, fit: 0, unfit: 0 }),
  }))
})

// ── Month-wise Abstract ───────────────────────────────────────────────
const MONTHS = ['Jul','Aug','Sep','Oct','Nov','Dec','Jan','Feb','Mar','Apr','May','Jun']

// Parse a sampled_at that may arrive as ISO ("2026-05-13T..."),
// SQL ("2026-05-13 10:30:00"), or the Laravel accessor pretty form
// ("07 May, 2026 09:30"). Returns a 0-11 calendar month index, or null.
const MONTH_ABBR_TO_IDX = { jan:0, feb:1, mar:2, apr:3, may:4, jun:5,
                            jul:6, aug:7, sep:8, oct:9, nov:10, dec:11 }
function parseCalMonth(stamp) {
  if (!stamp) return null
  const s = String(stamp).trim()
  // ISO / SQL: YYYY-MM-DD...
  const iso = s.match(/^\d{4}-(\d{2})-\d{2}/)
  if (iso) return parseInt(iso[1], 10) - 1
  // Pretty: "07 May, 2026 09:30"
  const pretty = s.match(/\b([A-Za-z]{3})/)
  if (pretty) {
    const m = MONTH_ABBR_TO_IDX[pretty[1].toLowerCase()]
    if (m !== undefined) return m
  }
  // Last resort — let the engine try
  const d = new Date(s)
  return Number.isNaN(d.getTime()) ? null : d.getMonth()
}

const monthWiseData = computed(() => {
  if (!rawSamples.value.length) return { rows: [], totals: {} }

  // Build district → month → {T,F,U}
  const distMap = {}
  rawSamples.value.forEach(s => {
    const distName = s.district?.name || 'Unknown'
    const divName  = s.division?.name || '—'
    const phedDiv  = s.phed_division?.name || s.phedDivision?.name || '—'
    const calMonth = parseCalMonth(s.sampled_at)
    if (calMonth === null) return
    // Fiscal year month index: Jul=0 ... Jun=11
    const fiscalIdx = calMonth >= 6 ? calMonth - 6 : calMonth + 6
    const monthKey  = MONTHS[fiscalIdx]

    if (!distMap[distName]) {
      distMap[distName] = {
        district: distName, division: divName, phedDiv,
        netTotal: { T: 0, F: 0, U: 0 },
        months: {},
      }
      MONTHS.forEach(m => { distMap[distName].months[m] = { T: 0, F: 0, U: 0 } })
    }
    const dr = distMap[distName]
    dr.netTotal.T++
    dr.months[monthKey].T++
    if (s.result === 'Fit' || s.result === '1') { dr.netTotal.F++; dr.months[monthKey].F++ }
    else if (s.result === 'Unfit' || s.result === '2') { dr.netTotal.U++; dr.months[monthKey].U++ }
  })

  // Group by division
  const divGroups = {}
  Object.values(distMap).forEach(d => {
    if (!divGroups[d.division]) divGroups[d.division] = []
    divGroups[d.division].push(d)
  })

  // KP totals per month
  const kpTotals = { netTotal: { T: 0, F: 0, U: 0 }, months: {} }
  MONTHS.forEach(m => { kpTotals.months[m] = { T: 0, F: 0, U: 0 } })
  Object.values(distMap).forEach(d => {
    kpTotals.netTotal.T += d.netTotal.T
    kpTotals.netTotal.F += d.netTotal.F
    kpTotals.netTotal.U += d.netTotal.U
    MONTHS.forEach(m => {
      kpTotals.months[m].T += d.months[m].T
      kpTotals.months[m].F += d.months[m].F
      kpTotals.months[m].U += d.months[m].U
    })
  })

  return { divGroups, kpTotals }
})

function cellStyle(u, t) {
  if (!t) return ''
  const pct = u / t
  if (pct > 0.2) return 'background:#ffd6d6'   // High — red tint
  if (pct > 0.1) return 'background:#fff3cd'   // Moderate — amber tint
  return ''
}
const expandedLabs = ref({})
function toggleLab(id) { expandedLabs.value[id] = !expandedLabs.value[id] }
function expandAll()   { labRows.value.forEach(r => expandedLabs.value[r.id] = true) }
function collapseAll() { expandedLabs.value = {} }

// ── Export ────────────────────────────────────────────────────────────
function exportReport() {
  if (!labRows.value.length) { alert('No data to export.'); return }
  const data = labRows.value.map(r => ({
    'Laboratory': r.lab,
    'CE Region': r.regions,
    'Division': r.divisions,
    'Districts Covered': r.districtNames,
    'Total Tested': r.tested,
    'Fit': r.fit,
    'Unfit': r.unfit,
    '% Unfit': r.pct + '%',
    'RAG Status': r.ragLabel,
  }))
  exportToXLSX(data, 'GAR_General_Abstract_Report')
}

function printReport() { window.print() }

function clearFilters() {
  filters.value = {
    from_date:       new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
    to_date:         new Date().toISOString().split('T')[0],
    region_id:       '',
    division_id:     '',
    circle_id:       '',
    district_id:     '',
    phed_division_id:'',
    laboratory_id:   '',
    sample_type:     '',
  }
}

// ── Helpers ───────────────────────────────────────────────────────────
function pctBar(fit, total) {
  return total > 0 ? ((fit / total) * 100).toFixed(1) : 0
}

// Banner reflects the actual filter selection (was hardcoded "All Regions · All Labs")
function lookupName(list, id) {
  if (!id) return null
  const f = list.find(x => String(x.id) === String(id))
  return f?.name || null
}
const bannerSummary = computed(() => {
  const parts = []
  parts.push(lookupName(regions.value,      filters.value.region_id)        || 'All CE Regions')
  parts.push(lookupName(laboratories.value, filters.value.laboratory_id)    || 'All Labs')
  if (filters.value.district_id)      parts.push(lookupName(districts.value,    filters.value.district_id))
  if (filters.value.phed_division_id) parts.push(lookupName(phedDivs.value,     filters.value.phed_division_id))
  if (filters.value.division_id)      parts.push(lookupName(divisions.value,    filters.value.division_id))
  if (filters.value.circle_id)        parts.push(lookupName(circles.value,      filters.value.circle_id))
  return parts.filter(Boolean).join(' · ')
})

// RAG class for the KP Grand Total row (was hardcoded "—")
const totalsRag = computed(() => {
  if (!totals.value.tested) return { cls: 'r-grey', label: '—' }
  const ratio = totals.value.unfit / totals.value.tested
  if (ratio > 0.2) return { cls: 'r-red',   label: 'High' }
  if (ratio > 0.1) return { cls: 'r-amber', label: 'Moderate' }
  return { cls: 'r-green', label: 'Good' }
})

// ── Auto-refresh on filter change (debounced) ─────────────────────────
let filterTimer = null
watch(filters, () => {
  if (!generated.value) return        // skip until first load completes
  if (dateError.value) return         // skip while date range is invalid
  clearTimeout(filterTimer)
  filterTimer = setTimeout(generateReport, 350)
}, { deep: true })

onMounted(async () => {
  await loadDropdowns()
  await generateReport()  // auto-generate with current month on load
})
</script>

<template>
  <div class="gar-page">
    <!-- Filters -->
    <div class="filters" style="margin-bottom:10px;flex-wrap:wrap;gap:6px">
      <div class="fg"><label>From</label><input type="date" v-model="filters.from_date"></div>
      <div class="fg"><label>To</label><input type="date" v-model="filters.to_date"></div>
      <div class="fg">
        <label>CE Region</label>
        <select v-model="filters.region_id" @change="filters.circle_id='';filters.division_id='';filters.district_id='';filters.phed_division_id='';filters.laboratory_id=''">
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
          <option value="PHE">PHE / WSS</option>
          <option value="Private">Private Client</option>
        </select>
      </div>
      <button class="btn btn-sec btn-sm" style="align-self:flex-end" @click="clearFilters">✕ Clear Filters</button>
      <div class="tsp"></div>
      <span v-if="loading" style="font-size:11px;color:var(--muted);align-self:flex-end;padding-bottom:6px">Updating…</span>
      <button class="btn btn-sec btn-sm" style="align-self:flex-end" @click="exportReport">Export .xlsx</button>
      <button class="btn btn-sec btn-sm" style="align-self:flex-end" @click="printReport">Print PDF</button>
    </div>

    <!-- Inline date range warning -->
    <div v-if="dateError"
         style="background:#fef9c3;border:1px solid #fde047;border-radius:6px;padding:8px 12px;margin-bottom:10px;color:#854d0e;font-size:12px">
      ⚠ {{ dateError }}
    </div>

    <div v-if="errorMsg && !dateError" style="background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:10px 14px;margin-bottom:10px;color:#b91c1c;font-size:12px">
      {{ errorMsg }}
    </div>

    <div class="abar green" style="margin-bottom:12px">
      General Abstract Report (GAR) | {{ bannerSummary }} | Annexure-1
    </div>

    <!-- KP-Level Summary Cards -->
    <div class="cards" style="grid-template-columns:repeat(6,1fr);margin-bottom:14px">
      <div class="card">
        <div class="c-lbl">Total Tested</div>
        <div class="c-val">{{ totals.tested.toLocaleString() }}</div>
      </div>
      <div class="card c-green">
        <div class="c-lbl">Fit</div>
        <div class="c-val">{{ totals.fit.toLocaleString() }}</div>
      </div>
      <div class="card c-red">
        <div class="c-lbl">Unfit</div>
        <div class="c-val">{{ totals.unfit.toLocaleString() }}</div>
      </div>
      <div class="card c-amber">
        <div class="c-lbl">% Unfit</div>
        <div class="c-val">{{ totals.tested > 0 ? ((totals.unfit/totals.tested)*100).toFixed(1) + '%' : '—' }}</div>
      </div>
      <div class="card">
        <div class="c-lbl">Labs Reporting</div>
        <div class="c-val">{{ totals.labs }}</div>
      </div>
      <div class="card">
        <div class="c-lbl" :title="'Distinct districts that submitted at least one sample in this period'">Districts with Samples</div>
        <div class="c-val">{{ totals.districtsCovered }}</div>
      </div>
    </div>

    <!-- Lab-wise Abstract -->
    <div class="sh" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
      <h2>Lab-wise Abstract</h2>
      <div style="display:flex;gap:6px">
        <button class="btn btn-sec btn-xs" @click="expandAll">Expand All</button>
        <button class="btn btn-sec btn-xs" @click="collapseAll">Collapse All</button>
      </div>
    </div>

    <div class="tbl-wrap" style="margin-bottom:18px">
      <!-- Skeleton loading state -->
      <div v-if="loading" class="gar-sk">
        <div class="sk-tbl">
          <div class="sk-tbl-head">
            <div class="sk sk-th" v-for="i in 11" :key="'gh'+i"></div>
          </div>
          <div class="sk-tbl-row" v-for="r in 6" :key="'gr'+r">
            <div class="sk sk-td" v-for="i in 11" :key="'gc'+r+'-'+i"></div>
          </div>
        </div>
      </div>
      <table v-else style="font-size:12px">
        <thead>
          <tr style="background:var(--navy);color:#fff">
            <th style="width:24px;color:#fff"></th>
            <th style="color:#fff">Laboratory</th>
            <th style="color:#fff">CE Region</th>
            <th style="color:#fff">Division</th>
            <th style="color:#fff;max-width:220px">Districts Covered</th>
            <th style="color:#fff;text-align:center">Tested</th>
            <th style="color:#fff;text-align:center">Fit</th>
            <th style="color:#fff;text-align:center">Unfit</th>
            <th style="color:#fff;text-align:center">% Unfit</th>
            <th style="color:#fff">Fit vs Unfit</th>
            <th style="color:#fff;text-align:center">RAG</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!labRows.length && generated">
            <td colspan="11" style="text-align:center;padding:24px;color:var(--muted)">No data found for the selected filters.</td>
          </tr>

          <template v-for="row in labRows" :key="row.id">
            <!-- Lab row -->
            <tr style="cursor:pointer;background:#f0f7ff" @click="toggleLab(row.id)">
              <td style="font-size:11px;color:var(--blue);text-align:center;padding:6px">
                {{ expandedLabs[row.id] ? '▼' : '▶' }}
              </td>
              <td style="font-weight:700">{{ row.lab }}</td>
              <td>{{ row.regions }}</td>
              <td>{{ row.divisions }}</td>
              <td :title="row.districtNames" style="max-width:220px;white-space:normal;font-size:11px;line-height:1.4">{{ row.districtNames }}</td>
              <td class="mono" style="text-align:center;font-weight:700">{{ row.tested }}</td>
              <td class="mono" style="text-align:center;color:var(--green)">{{ row.fit }}</td>
              <td class="mono" style="text-align:center" :style="row.rag === 'r-red' ? 'color:var(--red);font-weight:700' : ''">{{ row.unfit }}</td>
              <td class="mono" style="text-align:center">{{ row.pct }}%</td>
              <td style="min-width:120px">
                <div style="display:flex;align-items:center;gap:6px">
                  <div style="flex:1;height:8px;background:#e2e8f0;border-radius:4px;overflow:hidden">
                    <div style="height:100%;background:#16a34a;border-radius:4px;transition:width .3s"
                         :style="{ width: pctBar(row.fit, row.tested) + '%' }"></div>
                  </div>
                  <span style="font-size:10px;white-space:nowrap;color:var(--muted)">{{ row.fit }}/{{ row.unfit }}</span>
                </div>
              </td>
              <td style="text-align:center">
                <span class="rag" :class="row.rag">{{ row.ragLabel }}</span>
              </td>
            </tr>

            <!-- Expanded district breakdown -->
            <tr v-if="expandedLabs[row.id]">
              <td colspan="11" style="padding:0;background:#fafcff">
                <table style="width:100%;font-size:11.5px;border-top:1px solid var(--sky)">
                  <thead>
                    <tr style="background:#e8f0fe">
                      <th style="padding:5px 24px;text-align:left;color:var(--navy2)">District</th>
                      <th style="padding:5px 10px;text-align:center;color:var(--navy2)">Tested</th>
                      <th style="padding:5px 10px;text-align:center;color:var(--navy2)">Fit</th>
                      <th style="padding:5px 10px;text-align:center;color:var(--navy2)">Unfit</th>
                      <th style="padding:5px 10px;text-align:center;color:var(--navy2)">% Unfit</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(d, di) in row.districtList" :key="d.district"
                        :class="di%2===1?'alt':''">
                      <td style="padding:4px 24px">
                        <a v-if="d.districtId" href="#" @click.prevent="openGSR(d.districtId, row.labId)"
                           style="color:#1d4ed8;text-decoration:none;font-weight:500"
                           :title="`Open GSR filtered to ${d.district} for ${row.lab}`">{{ d.district }}</a>
                        <span v-else>{{ d.district }}</span>
                      </td>
                      <td style="padding:4px 10px;text-align:center;font-family:monospace">{{ d.tested }}</td>
                      <td style="padding:4px 10px;text-align:center;font-family:monospace;color:var(--green)">{{ d.fit }}</td>
                      <td style="padding:4px 10px;text-align:center;font-family:monospace" :style="d.unfit > 0 ? 'color:var(--red)' : ''">{{ d.unfit }}</td>
                      <td style="padding:4px 10px;text-align:center;font-family:monospace">
                        {{ d.tested > 0 ? ((d.unfit/d.tested)*100).toFixed(1) : '0.0' }}%
                      </td>
                    </tr>
                  </tbody>
                </table>
              </td>
            </tr>
          </template>
        </tbody>

        <!-- KP Grand Total -->
        <tfoot>
          <tr style="background:var(--navy);color:#fff;font-weight:700">
            <td></td>
            <td style="color:#fff;padding:8px 10px">KP TOTAL — All Labs</td>
            <td style="color:#fff"></td>
            <td style="color:#fff"></td>
            <td style="color:#fff;text-align:center">{{ totals.districtsCovered }}</td>
            <td class="mono" style="color:#fff;text-align:center">{{ totals.tested.toLocaleString() }}</td>
            <td class="mono" style="color:#4ade80;text-align:center">{{ totals.fit.toLocaleString() }}</td>
            <td class="mono" style="color:#f87171;text-align:center">{{ totals.unfit.toLocaleString() }}</td>
            <td class="mono" style="color:#fff;text-align:center">
              {{ totals.tested > 0 ? ((totals.unfit/totals.tested)*100).toFixed(1) + '%' : '—' }}
            </td>
            <td>
              <div style="display:flex;align-items:center;gap:6px">
                <div style="flex:1;height:8px;background:rgba(255,255,255,.2);border-radius:4px;overflow:hidden">
                  <div style="height:100%;background:#4ade80;border-radius:4px"
                       :style="{ width: pctBar(totals.fit, totals.tested) + '%' }"></div>
                </div>
                <span style="font-size:10px;color:#fff">{{ totals.fit }}/{{ totals.unfit }}</span>
              </div>
            </td>
            <td style="text-align:center"><span class="rag" :class="totalsRag.cls">{{ totalsRag.label }}</span></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- District-wise breakdown grouped by Division -->
    <div v-if="districtByDivision.length" class="sh" style="margin-bottom:8px">
      <h2>District-wise Breakdown (by Division)</h2>
    </div>

    <div v-if="districtByDivision.length" class="tbl-wrap">
      <table style="font-size:12px">
        <thead>
          <tr style="background:var(--navy);color:#fff">
            <th style="color:#fff">S#</th>
            <th style="color:#fff">District</th>
            <th style="color:#fff">PHE Division</th>
            <th style="color:#fff;text-align:center">Tested</th>
            <th style="color:#fff;text-align:center">Fit</th>
            <th style="color:#fff;text-align:center">Unfit</th>
            <th style="color:#fff;text-align:center">% Unfit</th>
            <th style="color:#fff;text-align:center">RAG</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="divGroup in districtByDivision" :key="divGroup.division">
            <!-- Division header -->
            <tr style="background:#1a2e4a">
              <td colspan="8" style="color:#fff;font-weight:700;font-size:11px;padding:6px 12px;text-transform:uppercase;letter-spacing:.05em">
                {{ divGroup.division }} Division
              </td>
            </tr>
            <!-- District rows -->
            <tr v-for="(d, di) in divGroup.rows" :key="d.district"
                :class="di%2===1?'alt':''">
              <td class="mono" style="color:var(--muted);font-size:11px">{{ di + 1 }}</td>
              <td>
                <a v-if="d.districtId" href="#" @click.prevent="openGSR(d.districtId)"
                   style="color:#1d4ed8;text-decoration:none;font-weight:500"
                   :title="`Open GSR filtered to ${d.district}`">{{ d.district }}</a>
                <span v-else>{{ d.district }}</span>
              </td>
              <td style="color:var(--muted);font-size:11px">{{ d.phedDiv }}</td>
              <td class="mono" style="text-align:center">{{ d.tested }}</td>
              <td class="mono" style="text-align:center;color:var(--green)">{{ d.fit }}</td>
              <td class="mono" style="text-align:center" :style="d.unfit > 0 ? 'color:var(--red)' : ''">{{ d.unfit }}</td>
              <td class="mono" style="text-align:center">{{ d.pct }}%</td>
              <td style="text-align:center"><span class="rag" :class="d.rag">{{ d.rag === 'r-green' ? 'Good' : d.rag === 'r-amber' ? 'Moderate' : 'High' }}</span></td>
            </tr>
            <!-- Division subtotal -->
            <tr style="background:#f0f4ff;font-weight:700">
              <td></td>
              <td style="color:var(--navy2)">{{ divGroup.division }} Subtotal</td>
              <td></td>
              <td class="mono" style="text-align:center">{{ divGroup.subtotal.tested }}</td>
              <td class="mono" style="text-align:center;color:var(--green)">{{ divGroup.subtotal.fit }}</td>
              <td class="mono" style="text-align:center;color:var(--red)">{{ divGroup.subtotal.unfit }}</td>
              <td class="mono" style="text-align:center">
                {{ divGroup.subtotal.tested > 0 ? ((divGroup.subtotal.unfit/divGroup.subtotal.tested)*100).toFixed(1) : '0.0' }}%
              </td>
              <td></td>
            </tr>
          </template>

          <!-- KP Grand Total -->
          <tr style="background:var(--navy);color:#fff;font-weight:700">
            <td></td>
            <td style="color:#fff">KP GRAND TOTAL</td>
            <td></td>
            <td class="mono" style="color:#fff;text-align:center">{{ totals.tested }}</td>
            <td class="mono" style="color:#4ade80;text-align:center">{{ totals.fit }}</td>
            <td class="mono" style="color:#f87171;text-align:center">{{ totals.unfit }}</td>
            <td class="mono" style="color:#fff;text-align:center">
              {{ totals.tested > 0 ? ((totals.unfit/totals.tested)*100).toFixed(1) + '%' : '—' }}
            </td>
            <td></td>
          </tr>
        </tbody>
      </table>
    </div>
    <!-- Month-wise Abstract -->
    <div v-if="rawSamples.length" class="sh" style="margin-top:18px;margin-bottom:8px">
      <h2>Month-wise Abstract</h2>
      <div class="cnt" style="font-size:11px">
        T = Tested &nbsp;·&nbsp; F = Fit &nbsp;·&nbsp; U = Unfit &nbsp;·&nbsp;
        <span style="background:#fff3cd;padding:1px 6px;border-radius:3px;font-size:10px">Moderate (10–20%)</span>
        &nbsp;
        <span style="background:#ffd6d6;padding:1px 6px;border-radius:3px;font-size:10px">High (&gt;20%)</span>
      </div>
    </div>

    <div v-if="rawSamples.length" class="tbl-wrap" style="overflow-x:auto">
      <table style="font-size:10.5px;min-width:1400px;border-collapse:collapse">
        <thead>
          <tr style="background:var(--navy);color:#fff">
            <th style="color:#fff;padding:6px 8px;white-space:nowrap;position:sticky;left:0;background:var(--navy);z-index:2">#</th>
            <th style="color:#fff;padding:6px 8px;white-space:nowrap;position:sticky;left:28px;background:var(--navy);z-index:2">District</th>
            <th style="color:#fff;padding:6px 8px;white-space:nowrap;position:sticky;left:140px;background:var(--navy);z-index:2">PHE Div.</th>
            <th colspan="3" style="color:#fff;padding:6px 8px;text-align:center;border-left:1px solid rgba(255,255,255,.2)">Net Total</th>
            <th v-for="m in MONTHS" :key="m" colspan="3"
                style="color:#fff;padding:6px 8px;text-align:center;border-left:1px solid rgba(255,255,255,.2)">{{ m }}</th>
          </tr>
          <tr style="background:#2a3f5f;color:#fff;font-size:10px">
            <th style="color:#fff;position:sticky;left:0;background:#2a3f5f;z-index:2"></th>
            <th style="color:#fff;position:sticky;left:28px;background:#2a3f5f;z-index:2"></th>
            <th style="color:#fff;position:sticky;left:140px;background:#2a3f5f;z-index:2"></th>
            <template v-for="_ in 13" :key="_">
              <th style="color:#add8ff;padding:3px 5px;text-align:center;border-left:1px solid rgba(255,255,255,.1)">T</th>
              <th style="color:#90ee90;padding:3px 5px;text-align:center">F</th>
              <th style="color:#ffaaaa;padding:3px 5px;text-align:center">U</th>
            </template>
          </tr>
        </thead>
        <tbody>
          <template v-for="(rows, divName) in monthWiseData.divGroups" :key="divName">
            <!-- Division header -->
            <tr style="background:#1a2e4a">
              <td colspan="100" style="color:#fff;font-weight:700;font-size:10.5px;padding:5px 10px;text-transform:uppercase;letter-spacing:.05em">
                {{ divName }} Division
              </td>
            </tr>
            <!-- District rows -->
            <tr v-for="(d, di) in rows" :key="d.district"
                :class="di%2===1?'alt':''">
              <td class="mono" style="color:var(--muted);font-size:10px;padding:4px 6px;position:sticky;left:0;background:inherit;z-index:1">{{ di+1 }}</td>
              <td style="padding:4px 8px;font-weight:600;white-space:nowrap;position:sticky;left:28px;background:inherit;z-index:1">{{ d.district }}</td>
              <td style="padding:4px 8px;color:var(--muted);font-size:10px;white-space:nowrap;position:sticky;left:140px;background:inherit;z-index:1">{{ d.phedDiv }}</td>
              <!-- Net Total -->
              <td class="mono" style="text-align:center;padding:3px 5px;border-left:1px solid #e2e8f0;font-weight:700">{{ d.netTotal.T }}</td>
              <td class="mono" style="text-align:center;padding:3px 5px;color:var(--green)">{{ d.netTotal.F }}</td>
              <td class="mono" style="text-align:center;padding:3px 5px" :style="d.netTotal.U > 0 ? 'color:var(--red);font-weight:700' : ''">{{ d.netTotal.U }}</td>
              <!-- Monthly columns -->
              <template v-for="m in MONTHS" :key="m">
                <td class="mono" style="text-align:center;padding:3px 5px;border-left:1px solid #e2e8f0"
                    :style="cellStyle(d.months[m].U, d.months[m].T)">{{ d.months[m].T || '' }}</td>
                <td class="mono" style="text-align:center;padding:3px 5px;color:var(--green)"
                    :style="cellStyle(d.months[m].U, d.months[m].T)">{{ d.months[m].F || '' }}</td>
                <td class="mono" style="text-align:center;padding:3px 5px"
                    :style="d.months[m].U > 0 ? cellStyle(d.months[m].U, d.months[m].T) + ';color:var(--red);font-weight:700' : cellStyle(d.months[m].U, d.months[m].T)">
                  {{ d.months[m].U || '' }}
                </td>
              </template>
            </tr>
          </template>
        </tbody>

        <!-- KP Total row -->
        <tfoot>
          <tr style="background:var(--navy);color:#fff;font-weight:700">
            <td style="color:#fff;padding:6px 6px;position:sticky;left:0;background:var(--navy);z-index:2"></td>
            <td style="color:#fff;padding:6px 8px;position:sticky;left:28px;background:var(--navy);z-index:2">KP TOTAL</td>
            <td style="color:#fff;position:sticky;left:140px;background:var(--navy);z-index:2"></td>
            <td class="mono" style="color:#fff;text-align:center;padding:4px 5px;border-left:1px solid rgba(255,255,255,.2);font-weight:800">{{ monthWiseData.kpTotals?.netTotal.T }}</td>
            <td class="mono" style="color:#4ade80;text-align:center;padding:4px 5px">{{ monthWiseData.kpTotals?.netTotal.F }}</td>
            <td class="mono" style="color:#f87171;text-align:center;padding:4px 5px">{{ monthWiseData.kpTotals?.netTotal.U }}</td>
            <template v-for="m in MONTHS" :key="m">
              <td class="mono" style="color:#fff;text-align:center;padding:4px 5px;border-left:1px solid rgba(255,255,255,.2)">{{ monthWiseData.kpTotals?.months[m].T || '' }}</td>
              <td class="mono" style="color:#4ade80;text-align:center;padding:4px 5px">{{ monthWiseData.kpTotals?.months[m].F || '' }}</td>
              <td class="mono" style="color:#f87171;text-align:center;padding:4px 5px">{{ monthWiseData.kpTotals?.months[m].U || '' }}</td>
            </template>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</template>

<style>
/* Crisp-text override scoped to GAR view: defeats the global td.mono rule
   (DM Mono 11.5px → fuzzy on Windows) without touching every cell inline. */
.gar-page td.mono {
  font-family: 'DM Sans', sans-serif;
  font-variant-numeric: tabular-nums;
  font-size: 12.5px;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

/* ── Skeleton loading (matches GAR table layout) ─────────────────── */
.gar-page .gar-sk .sk {
  background: linear-gradient(90deg, #e5e7eb 0%, #f3f4f6 50%, #e5e7eb 100%);
  background-size: 200% 100%;
  border-radius: 4px;
  animation: gar-sk-shimmer 1.4s infinite linear;
}
@keyframes gar-sk-shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
.gar-page .gar-sk .sk-tbl {
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  overflow: hidden;
}
.gar-page .gar-sk .sk-tbl-head,
.gar-page .gar-sk .sk-tbl-row {
  display: grid;
  grid-template-columns: 24px 1.6fr 1fr 1fr 1.4fr 0.6fr 0.6fr 0.6fr 0.6fr 1.2fr 0.7fr;
  gap: 8px;
  padding: 9px 12px;
}
.gar-page .gar-sk .sk-tbl-head { background: #f3f4f6; border-bottom: 1px solid #e5e7eb; }
.gar-page .gar-sk .sk-tbl-row + .sk-tbl-row { border-top: 1px solid #f3f4f6; }
.gar-page .gar-sk .sk-th,
.gar-page .gar-sk .sk-td { height: 12px; }

/* ── Print rules (A3 landscape; preserve background colors for RAG/headers) */
@page {
  size: A3 landscape;
  margin: 8mm;
}
@media print {
  .filters, .btn, nav, aside, .gar-sk { display: none !important; }
  body { font-size: 9px; background: #fff !important; }

  .tbl-wrap     { overflow: visible !important; border: 0 !important; }
  .tbl-wrap table { font-size: 8px !important; table-layout: auto; }
  .tbl-wrap td, .tbl-wrap th { padding: 2px 4px !important; }

  /* Keep RAG colors and header backgrounds visible on paper */
  .rag, .cards .card, thead tr, tfoot tr, .abar {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
}
</style>
