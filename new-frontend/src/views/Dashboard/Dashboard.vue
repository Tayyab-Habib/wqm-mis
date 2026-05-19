<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { Chart, registerables } from 'chart.js'
import { api } from '../../services/api.js'
import { useUserStore } from '../../stores/useUserStore.js'
Chart.register(...registerables)

const router    = useRouter()
const userStore = useUserStore()

// ── Filter state ──────────────────────────────────────────────────────
const filters = ref({
  client: 'PHE',
  from: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
  to:   new Date().toISOString().split('T')[0],
  allTime: true,
  // All location filters use IDs (matching DB rows). The auxiliary name
  // strings are kept for legacy backend compatibility / display.
  regionId: '',
  divisionId: '',
  circleId: '',
  districtId: '',
  phedDivisionId: '',
  labId: '',
})

// ── Cascading filter relationships (DB-truth) ─────────────────────────
// Region → Circles (direct)
// Circle → Districts (direct)
// Division → Districts (direct)
// District → PHE Divisions (direct)
// District / Division → Laboratories (direct)
//
// Note: divisions.region_id is NULL in this DB, so Division is derived
// indirectly from Region via Region → Circles → Districts → Divisions.
//
// Reset downstream selections when an upstream changes so the user can't
// hold a stale child selection that doesn't belong to the new parent.
watch(() => filters.value.regionId, () => {
  filters.value.circleId = ''
  filters.value.divisionId = ''
  filters.value.districtId = ''
  filters.value.phedDivisionId = ''
  filters.value.labId = ''
})
watch(() => filters.value.divisionId, () => {
  filters.value.districtId = ''
  filters.value.phedDivisionId = ''
  filters.value.labId = ''
})
watch(() => filters.value.circleId, () => {
  filters.value.districtId = ''
  filters.value.phedDivisionId = ''
  filters.value.labId = ''
})
watch(() => filters.value.districtId, () => {
  filters.value.phedDivisionId = ''
  filters.value.labId = ''
})

// ── Charts ────────────────────────────────────────────────────────────
const ch01Ref = ref(null)
const ch02Ref = ref(null)
const chartFont = { family: "'DM Sans', sans-serif", size: 11 }

// ── CH-03 Heatmap ─────────────────────────────────────────────────────
const hmParam  = ref('overall')
const hmSub    = ref('')

const hmSubs = {
  overall:  [],
  microbial:['All Microbial','E. coli','Total Coliform'],
  chemical: ['All Chemical','Arsenic','Fluoride','Nitrates','Hardness','Chlorides','Iron','Manganese'],
  physical: ['All Physical','pH','Turbidity','TDS','Colour'],
}

const hmSubOptions = computed(() => hmSubs[hmParam.value] || [])

// Polygon ID → DB district name(s). One polygon can aggregate multiple DB
// districts (e.g. "Chitral" polygon = Upper+Lower Chitral DB rows).
// Names must match districts.name in the DB exactly (case-insensitive).
const polygonToDbNames = {
  chitral:    ['Upper Chitral', 'Lower Chitral'],
  upperdir:   ['Upper Dir'],
  lowerdir:   ['Lower Dir'],
  swat:       ['Swat'],
  malakand:   ['Malakand'],
  shangla:    ['Shangla'],
  buner:      ['Buner'],
  bajaur:     ['Bajaur'],
  mohmand:    ['Mohmand'],
  charsadda:  ['Charsadda'],
  mardan:     ['Mardan'],
  swabi:      ['Swabi'],
  peshawar:   ['Peshawar'],
  nowshera:   ['Nowshera'],
  khyber:     ['Khyber'],
  kurram:     ['Kurram'],
  kohat:      ['Kohat'],
  orakzai:    ['Orakzai'],
  hangu:      ['Hangu'],
  attock:     [],                              // Punjab district, not in KP DB
  haripur:    ['Haripur'],
  abbottabad: ['Abbottabad'],
  mansehra:   ['Mansehra'],
  kohistanu:  ['Kohistan'],                    // Upper Kohistan in DB is just "Kohistan"
  kohistanl:  ['Kohistan Lower', 'Kolai Palas Kohistan'],
  torghar:    ['Torghar'],
  battagram:  ['Battagram'],
  karak:      ['Karak'],
  bannu:      ['Bannu'],
  nwaz:       ['North Waziristan'],
  swaz:       ['South Waziristan'],
  lakki:      ['Lakki Marwat'],
  tank:       ['Tank'],
  dik:        ['D.I. Khan'],
}

// Per-district % unfit data
const districtData = {
  chitral:    { name:'Chitral',         fit:82, unfit:18, division:'Malakand', lab:'Malakand', wss:24, tested:18, unfitWss:3, micro:1, chem:2, phys:0 },
  upperdir:   { name:'Dir Upper',       fit:91, unfit:9,  division:'Malakand', lab:'Malakand', wss:16, tested:12, unfitWss:1, micro:1, chem:0, phys:0 },
  lowerdir:   { name:'Dir Lower',       fit:88, unfit:12, division:'Malakand', lab:'Malakand', wss:14, tested:10, unfitWss:2, micro:1, chem:1, phys:0 },
  swat:       { name:'Swat',            fit:76, unfit:24, division:'Malakand', lab:'Lab Swat',  wss:38, tested:30, unfitWss:7, micro:4, chem:3, phys:2 },
  malakand:   { name:'Malakand',        fit:93, unfit:7,  division:'Malakand', lab:'Malakand', wss:20, tested:16, unfitWss:3, micro:3, chem:1, phys:1 },
  shangla:    { name:'Shangla',         fit:85, unfit:15, division:'Malakand', lab:'Lab Swat',  wss:11, tested:8,  unfitWss:3, micro:3, chem:1, phys:0 },
  buner:      { name:'Buner',           fit:62, unfit:38, division:'Mardan',   lab:'Mardan',   wss:12, tested:9,  unfitWss:5, micro:4, chem:2, phys:1 },
  bajaur:     { name:'Bajaur',          fit:55, unfit:45, division:'Malakand', lab:'Malakand', wss:14, tested:10, unfitWss:6, micro:5, chem:2, phys:1 },
  mohmand:    { name:'Mohmand',         fit:48, unfit:52, division:'Peshawar', lab:'Peshawar', wss:12, tested:9,  unfitWss:5, micro:5, chem:1, phys:1 },
  charsadda:  { name:'Charsadda',       fit:58, unfit:42, division:'Peshawar', lab:'Peshawar', wss:18, tested:14, unfitWss:5, micro:5, chem:2, phys:1 },
  mardan:     { name:'Mardan',          fit:89, unfit:11, division:'Mardan',   lab:'Mardan',   wss:25, tested:20, unfitWss:8, micro:8, chem:3, phys:2 },
  swabi:      { name:'Swabi',           fit:93, unfit:7,  division:'Mardan',   lab:'Mardan',   wss:17, tested:14, unfitWss:6, micro:6, chem:2, phys:1 },
  peshawar:   { name:'Peshawar',        fit:67, unfit:33, division:'Peshawar', lab:'Peshawar', wss:48, tested:41, unfitWss:9, micro:7, chem:3, phys:1 },
  nowshera:   { name:'Nowshera',        fit:88, unfit:12, division:'Peshawar', lab:'Peshawar', wss:14, tested:12, unfitWss:3, micro:3, chem:1, phys:1 },
  khyber:     { name:'Khyber',          fit:70, unfit:30, division:'Peshawar', lab:'Peshawar', wss:15, tested:11, unfitWss:6, micro:5, chem:2, phys:1 },
  kurram:     { name:'Kurram',          fit:72, unfit:28, division:'Kohat',    lab:'Kohat',    wss:10, tested:8,  unfitWss:4, micro:3, chem:1, phys:1 },
  kohat:      { name:'Kohat',           fit:85, unfit:15, division:'Kohat',    lab:'Kohat',    wss:20, tested:16, unfitWss:2, micro:2, chem:1, phys:0 },
  orakzai:    { name:'Orakzai',         fit:90, unfit:10, division:'Kohat',    lab:'Kohat',    wss:10, tested:8,  unfitWss:4, micro:3, chem:2, phys:0 },
  hangu:      { name:'Hangu',           fit:94, unfit:6,  division:'Kohat',    lab:'Kohat',    wss:12, tested:9,  unfitWss:4, micro:3, chem:2, phys:0 },
  haripur:    { name:'Haripur',         fit:95, unfit:5,  division:'Hazara',   lab:'Abbottabad',wss:20,tested:17, unfitWss:1, micro:1, chem:1, phys:0 },
  abbottabad: { name:'Abbottabad',      fit:97, unfit:3,  division:'Hazara',   lab:'Abbottabad',wss:22,tested:18, unfitWss:3, micro:2, chem:1, phys:1 },
  mansehra:   { name:'Mansehra',        fit:84, unfit:16, division:'Hazara',   lab:'Abbottabad',wss:25,tested:19, unfitWss:3, micro:2, chem:1, phys:1 },
  kohistanu:  { name:'Kohistan Upper',  fit:78, unfit:22, division:'Hazara',   lab:'Abbottabad',wss:8, tested:6,  unfitWss:2, micro:1, chem:1, phys:0 },
  kohistanl:  { name:'Kohistan Lower',  fit:80, unfit:20, division:'Hazara',   lab:'Abbottabad',wss:7, tested:5,  unfitWss:2, micro:1, chem:1, phys:0 },
  torghar:    { name:'Torghar',         fit:82, unfit:18, division:'Hazara',   lab:'Abbottabad',wss:7, tested:5,  unfitWss:2, micro:1, chem:1, phys:0 },
  battagram:  { name:'Battagram',       fit:43, unfit:57, division:'Hazara',   lab:'Abbottabad',wss:9, tested:7,  unfitWss:2, micro:2, chem:1, phys:0 },
  karak:      { name:'Karak',           fit:92, unfit:8,  division:'Bannu',    lab:'Bannu',    wss:44, tested:38, unfitWss:5, micro:4, chem:2, phys:1 },
  bannu:      { name:'Bannu',           fit:96, unfit:4,  division:'Bannu',    lab:'Bannu',    wss:40, tested:32, unfitWss:8, micro:7, chem:3, phys:1 },
  nwaz:       { name:'N. Waziristan',   fit:74, unfit:26, division:'Bannu',    lab:'Bannu',    wss:9,  tested:7,  unfitWss:3, micro:3, chem:1, phys:0 },
  swaz:       { name:'S. Waziristan',   fit:92, unfit:8,  division:'D.I.Khan', lab:'D.I. Khan',wss:8,  tested:6,  unfitWss:3, micro:3, chem:1, phys:0 },
  lakki:      { name:'Lakki Marwat',    fit:90, unfit:10, division:'Bannu',    lab:'Bannu',    wss:16, tested:12, unfitWss:3, micro:3, chem:1, phys:0 },
  tank:       { name:'Tank',            fit:88, unfit:12, division:'D.I.Khan', lab:'D.I. Khan',wss:14, tested:10, unfitWss:4, micro:3, chem:1, phys:1 },
  dik:        { name:'D.I. Khan',       fit:73, unfit:27, division:'D.I.Khan', lab:'D.I. Khan',wss:40, tested:34, unfitWss:8, micro:7, chem:3, phys:1 },
  attock:     { name:'Attock (Punjab)', fit:95, unfit:5,  division:'Peshawar', lab:'Peshawar', wss:5,  tested:4,  unfitWss:1, micro:1, chem:0, phys:0 },
}

function ragColor(u) {
  if (u > 35) return '#d32f2f'
  if (u > 20) return '#f4a236'
  if (u > 10) return '#7dc97a'
  return '#1a7a3f'
}

// Reactive fill colors per district
const districtColors = ref({})

// Live stats per polygon (aggregated across the polygon's mapped DB districts).
// Shape: { [polygonId]: { tested, fit, unfit, wss, unfitPct, hasData } }
const districtStats = ref({})

// Map sub-param dropdown values to the canonical water_quality_parameter
// strings stored on tests rows. Anything not in this map is sent as-is.
const subParamMap = {
  ecoli:         'E. coli',
  totalcoliform: 'Total Coliform',
  arsenic:       'Arsenic',
  fluoride:      'Fluoride',
  nitrates:      'Nitrates',
  hardness:      'Hardness',
  chlorides:     'Chlorides',
  iron:          'Iron',
  manganese:     'Manganese',
  ph:            'pH',
  turbidity:     'Turbidity',
  tds:           'TDS',
  colour:        'Colour',
}
function resolveSubParam(rawSub) {
  if (!rawSub) return null
  const key = rawSub.toLowerCase().replace(/[^a-z]/g, '')
  if (key.startsWith('all')) return null  // "All Microbial" etc. → no sub filter
  return subParamMap[key] || null
}

// Recolor the polygons from whatever's currently in districtStats.
function recolorHeatmap() {
  const colors = {}
  Object.keys(polygonToDbNames).forEach(id => {
    const s = districtStats.value[id]
    const u = s?.unfitPct
    colors[id] = {
      color: u != null ? ragColor(u) : '#cccccc',
      unfit: u ?? null,
    }
  })
  districtColors.value = colors
}

// Fold a backend district row (matched by name) into the polygon-keyed store.
// Multiple DB districts can map to one polygon (e.g. Kohistan Lower + Kolai Palas),
// so we sum the per-type breakdowns as well as the aggregate counts.
function aggregateBackendDistricts(rows) {
  const byName = {}
  rows.forEach(r => { byName[String(r.name).toLowerCase()] = r })
  const stats = {}
  Object.entries(polygonToDbNames).forEach(([polyId, dbNames]) => {
    let tested = 0, fit = 0, unfit = 0
    let wss = 0, testedWss = 0, unfitWss = 0
    let hadHit = false
    const byType = {
      microbial: { tested: 0, unfit: 0 },
      chemical:  { tested: 0, unfit: 0 },
      physical:  { tested: 0, unfit: 0 },
    }
    dbNames.forEach(name => {
      const row = byName[name.toLowerCase()]
      if (!row) return
      hadHit = true
      tested    += Number(row.tested     || 0)
      fit       += Number(row.fit        || 0)
      unfit     += Number(row.unfit      || 0)
      wss       += Number(row.wss        || 0)
      testedWss += Number(row.tested_wss || 0)
      unfitWss  += Number(row.unfit_wss  || 0)
      const bt = row.by_type || {}
      ;['microbial', 'chemical', 'physical'].forEach(k => {
        byType[k].tested += Number(bt[k]?.tested || 0)
        byType[k].unfit  += Number(bt[k]?.unfit  || 0)
      })
    })
    stats[polyId] = {
      tested, fit, unfit,
      wss, testedWss, unfitWss,
      unfitPct: tested > 0 ? Math.round((unfit / tested) * 100) : null,
      byType,
      hasData: hadHit,
    }
  })
  districtStats.value = stats
  recolorHeatmap()
}

// Fetch heatmap data from the dedicated endpoint, honoring the current
// parameter_type / sub-parameter selection plus the dashboard filter bar.
async function fetchHeatmap() {
  try {
    const payload = { ...buildPayload() }
    if (hmParam.value && hmParam.value !== 'overall') payload.parameter_type = hmParam.value
    const sub = resolveSubParam(hmSub.value)
    if (sub) payload.water_quality_parameter = sub
    const res = await api.post('/dashboard/district-heatmap', payload)
    const rows = res.data?.data?.districts || res.data?.districts || []
    aggregateBackendDistricts(rows)
  } catch (e) {
    console.error('Heatmap fetch failed:', e?.response?.data || e)
  }
}

watch(hmParam, () => { hmSub.value = ''; fetchHeatmap() })
watch(hmSub,   () => { fetchHeatmap() })

// Kept for the initial onMounted call before the API resolves — just paints
// every polygon grey via recolorHeatmap().
function updateHeatmap() { recolorHeatmap() }

// ── Selected district panel ───────────────────────────────────────────
const selectedDistrict = ref(null)
const hoveredDistrict  = ref(null)
const tooltipStyle     = ref({ display:'none', top:'0px', left:'0px' })

function onDistrictClick(id) {
  if (selectedDistrict.value === id) { selectedDistrict.value = null; return }
  selectedDistrict.value = id
}

function onDistrictHover(id, event) {
  hoveredDistrict.value = id
  const rect = event.currentTarget.closest('svg').getBoundingClientRect()
  tooltipStyle.value = {
    display: 'block',
    top:  (event.clientY - 10) + 'px',
    left: (event.clientX + 14) + 'px',
  }
}

function onDistrictLeave() {
  hoveredDistrict.value = null
  tooltipStyle.value = { display:'none', top:'0px', left:'0px' }
}

function clearSelection() { selectedDistrict.value = null }

// Build the display object for hover/click. Live numbers from districtStats
// win over the static fallback in districtData (which carries division/lab/etc).
function buildDistrictDisplay(polyId) {
  if (!polyId) return null
  const meta = districtData[polyId]
  const live = districtStats.value[polyId]
  if (!meta && !live) return null
  const hasLive = !!live?.hasData && live?.tested > 0

  const u      = hasLive ? live.unfitPct : null
  const tested = hasLive ? live.tested   : 0
  const fit    = hasLive ? live.fit      : 0
  const unfit  = hasLive ? live.unfit    : 0

  // Real WSS counts: total registered, those with ≥1 sample (= tested), and
  // those with ≥1 unfit sample. Coverage % is now schemes/schemes — same unit.
  const wss       = live?.wss       ?? 0
  const testedWss = live?.testedWss ?? 0
  const unfitWss  = live?.unfitWss  ?? 0
  const covPct    = wss > 0 ? Math.round((testedWss / wss) * 100) : null

  const fitPct = hasLive && tested > 0 ? Math.round((fit / tested) * 100) : null

  const rag = u == null ? '⚪ No Data'
            : u > 35  ? '🔴 High Risk'
            : u > 20  ? '🟠 Concern'
            : u > 10  ? '🟡 Moderate'
            : '🟢 Good'
  const ragClass = u == null ? 'r-grey'
                 : u > 35    ? 'r-red'
                 : u > 20    ? 'r-amber'
                 : 'r-green'

  // Per-parameter breakdown comes from the backend's by_type cut. The strip
  // shows the count of UNFIT samples per type — that's what stakeholders care
  // about ("where is contamination concentrated?"). When no live data exists
  // for the district, all three collapse to 0.
  const bt = live?.byType || {}
  return {
    id: polyId,
    name: meta?.name || polyId,
    division: meta?.division || '—',
    lab: meta?.lab || '—',
    wss, tested, fit, unfit,
    total: tested,                       // KPI strip labels "Total" = total tested samples
    u: u ?? 0,
    fitPct: fitPct ?? 0,
    rag, ragClass,
    covPct: covPct ?? 0,
    testedWss,                           // exposed if anyone wants the raw number
    unfitWss,                            // real count of WSS with ≥1 unfit sample
    micro: Number(bt.microbial?.unfit || 0),
    chem:  Number(bt.chemical?.unfit  || 0),
    phys:  Number(bt.physical?.unfit  || 0),
    hasData: hasLive,
  }
}

const selectedData = computed(() => buildDistrictDisplay(selectedDistrict.value))
const hoveredData  = computed(() => buildDistrictDisplay(hoveredDistrict.value))

// ── Real data from backend ────────────────────────────────────────────

// Start true so the skeleton shows immediately on mount — otherwise the
// v-else branch renders first with empty '—' placeholders, then flips to the
// skeleton when fetchDashboard() finally runs, which looks janky.
const dashLoading = ref(true)
const dashError   = ref('')
const dashData    = ref(null)   // raw response from POST /dashboard

// Dropdown data for filter selects
const dbRegions       = ref([])
const dbCircles       = ref([])
const dbDivisions     = ref([])
const dbDistricts     = ref([])
const dbPhedDivisions = ref([])
const dbLaboratories  = ref([])

// Reactive stat cards (updated from API)
const stats = ref({
  totalWss:        '—',
  testedWss:       '—',
  fitWss:          '—',
  unfitWss:        '—',
  totalSamples:    '—',
  fitSamples:      '—',
  unfitSamples:    '—',
  fitPct:          '—',
  unfitPct:        '—',
  microFitPct:     '—',
  microUnfitPct:   '—',
  chemFitPct:      '—',
  chemUnfitPct:    '—',
  physFitPct:      '—',
  physUnfitPct:    '—',
  totalLabs:       '—',
  totalComplaints: '—',
  pendingComplaints:'—',
  totalIssues:     '—',
  pendingInventory:'—',
  revenue:         '—',
})

// Build the filter payload for the API
function buildPayload() {
  const p = {}
  if (filters.value.client && filters.value.client !== 'ALL') p.type = filters.value.client
  if (filters.value.regionId)        p.region_id        = filters.value.regionId
  if (filters.value.circleId)        p.circle_id        = filters.value.circleId
  // division_id is required_with district_id — always send both together
  if (filters.value.divisionId)  p.division_id  = filters.value.divisionId
  if (filters.value.districtId && filters.value.divisionId) p.district_id = filters.value.districtId
  if (filters.value.phedDivisionId)  p.phed_division_id = filters.value.phedDivisionId
  if (filters.value.labId)       p.laboratory_id = filters.value.labId
  if (!filters.value.allTime && filters.value.from && filters.value.to) {
    // Ensure start strictly before end (backend requires before/after)
    if (filters.value.from < filters.value.to) {
      p.duration    = 'month'
      p.start_month = filters.value.from
      p.end_month   = filters.value.to
    }
  }
  return p
}

// Chart instances (kept so we can destroy & rebuild on filter change)
let ch01Instance = null
let ch02Instance = null

async function fetchDashboard() {
  dashLoading.value = true
  dashError.value   = ''

  // Fire the 4 secondary endpoints in the BACKGROUND — they only feed Row 4
  // cards (Diary / Dispatch / Equipment / Calib Due). Awaiting them blocks
  // the skeleton from clearing even though they're not on the critical path.
  // Each one updates its card independently when it returns.
  loadRow4Async()

  // The main /dashboard endpoint is the only blocking call — as soon as it
  // returns, the skeleton clears and the user sees real cards.
  try {
    const dashRes = await api.post('/dashboard', buildPayload())
    dashData.value = dashRes.data || dashRes
    const d = dashData.value

    const ws  = d.water_samples || {}
    const tws = d.tested_water_samples || {}
    const mws = d.microbial_water_samples || {}
    const cws = d.chemical_parameter_water_samples || {}
    const pws = d.physical_parameter_water_samples || {}
    const rev = d.laboratory_wise_revenue || {}

    // WSS operation breakdown from operation_wise_graph
    const opGraph = d.operation_wise_graph || {}
    const opLabels = opGraph.labels || []
    const opData   = opGraph.datasets?.[0]?.data || []
    const opMap    = {}
    opLabels.forEach((l, i) => { opMap[l] = opData[i] || 0 })

    stats.value = {
      // Water samples
      totalSamples:      ws.total_water_samples    ?? '—',
      fitSamples:        ws.total_water_samples_fit ?? '—',
      unfitSamples:      ws.total_water_samples_unfit ?? '—',
      testedSamples:     tws.total_tested_water_samples ?? '—',
      fitPct:            tws.total_water_samples_fit   ?? '—',
      unfitPct:          tws.total_water_samples_unfit ?? '—',
      microFitPct:       mws.total_microbial_samples_fit   ?? '—',
      microUnfitPct:     mws.total_microbial_samples_unfit ?? '—',
      chemFitPct:        cws.total_chemical_samples_fit    ?? '—',
      chemUnfitPct:      cws.total_chemical_samples_unfit  ?? '—',
      physFitPct:        pws.total_physical_samples_fit    ?? '—',
      physUnfitPct:      pws.total_physical_samples_unfit  ?? '—',
      // WSS — operation buckets fall back to 0 (not '—') because absence from
      // the GROUP BY response means zero schemes in that bucket, not missing data.
      totalWss:          d.total_water_schemes ?? 0,
      operationalWss:    opMap['Operational']     ?? 0,
      nonOperationalWss: opMap['Non-Operational'] ?? 0,
      abandonedWss:      opMap['Abandoned']       ?? 0,
      wipWss:            opMap['Work in progress'] ?? 0,
      // Labs & compliance
      totalLabs:         d.total_laboratories ?? '—',
      totalComplaints:   d.total_complaints?.datasets?.[0]?.data?.[0] ?? '—',
      pendingComplaints: d.total_complaints?.datasets?.[0]?.data?.[1] ?? '—',
      // SRS §2.9 — auto-escalated count: complaints unresolved > 72h.
      // Backend now returns this on `total_complaints.escalated_count`;
      // fall back to position 5 of the dataset for safety.
      escalatedComplaints: d.total_complaints?.escalated_count
                           ?? d.total_complaints?.datasets?.[0]?.data?.[5]
                           ?? 0,
      totalIssues:       d.total_issues?.datasets?.[0]?.data?.[0] ?? '—',
      pendingInventory:  d.total_pending_inventory_requests ?? '—',
      revenue:           rev.series?.[0]?.data?.reduce((a, b) => a + b, 0)?.toLocaleString() ?? '—',
      // Row 4 — populated asynchronously by loadRow4Async()
      diaryCount:    stats.value?.diaryCount    ?? '—',
      dispatchCount: stats.value?.dispatchCount ?? '—',
      assetCount:    stats.value?.assetCount    ?? '—',
      calibDue:      stats.value?.calibDue      ?? '—',
    }

    // Clear loading BEFORE rebuilds so the canvas elements get mounted
    // (they live inside a v-else template paired with the skeleton).
    // Without this, rebuildCH01/CH02 run while ch01Ref.value/ch02Ref.value
    // are still null and silently return.
    dashLoading.value = false
    await nextTick()
    rebuildCH01(d.laboratories_water_sample_results)
    rebuildCH02(d.districts_water_sample_results)
    // Heatmap pulls from its own filtered endpoint instead of reusing the
    // bar-chart numbers, so it can honor parameter_type / sub-parameter.
    fetchHeatmap()
    // Lab KPI matrix is also a dedicated endpoint — fired in parallel so a
    // slow KPI query doesn't block heatmap reactivity.
    fetchLabKpis()
  } catch (e) {
    dashError.value = e.response?.data?.message || e.message || 'Failed to load dashboard'
    console.error('Dashboard error:', e.response?.data || e)
  } finally {
    dashLoading.value = false
  }
}

// Row 4 cards (Diary / Dispatch / Equipment / Calib Due) load in the
// background so they don't block the initial render. Each one writes into
// stats.value as soon as its endpoint responds.
function loadRow4Async() {
  api.get('/diary-dispatch/diary/registers').then(res => {
    const d = res.data
    stats.value.diaryCount = Array.isArray(d) ? d.length : (d?.length ?? 0)
  }).catch(() => {})

  api.get('/diary-dispatch/dispatch/registers').then(res => {
    const d = res.data
    stats.value.dispatchCount = Array.isArray(d) ? d.length : (d?.length ?? 0)
  }).catch(() => {})

  // One fetch feeds two cards: total equipment + count of assets whose
  // next_calibration_date falls within the next 30 days. The dedicated
  // /asset-maintenance-schedules endpoint isn't useful here because that table
  // stores `day_of_month` / `frequency`, not a concrete next-due date.
  api.get('/laboratory/assets/all').then(res => {
    const assets = Array.isArray(res.data) ? res.data : []
    stats.value.assetCount = assets.length

    const today = new Date()
    const in30  = new Date(); in30.setDate(today.getDate() + 30)
    stats.value.calibDue = assets.filter(a => {
      if (!a.next_calibration_date) return false
      const dt = new Date(a.next_calibration_date)
      return !isNaN(dt) && dt >= today && dt <= in30
    }).length
  }).catch(() => {})
}

// Rebuild CH-01 (Lab-wise) from API data
function rebuildCH01(labResults) {
  if (!ch01Ref.value) return
  if (ch01Instance) { ch01Instance.destroy(); ch01Instance = null }

  let labs, fit, unfit, tested
  if (labResults?.categories?.length) {
    labs   = labResults.categories
    tested = labResults.series?.find(s => s.name === 'Tested')?.data || []
    fit    = labResults.series?.find(s => s.name === 'Fit')?.data    || []
    unfit  = labResults.series?.find(s => s.name === 'Unfit')?.data  || []
  } else {
    // fallback to dummy
    labs   = ['Central Lab Psh.','Mardan','Kohat','Abbottabad','Bannu','D.I. Khan','Malakand','Lab Swat','Lab Chitral']
    tested = [186,129,93,147,108,62,87,78,27]
    fit    = [152,118,82,149,95,52,76,61,22]
    unfit  = [22,13,6,3,4,6,15,11,2]
  }

  // Lab-incharge sees only their own lab on the X-axis. The backend returns
  // the full lab list as categories so unscoped roles get the cross-lab
  // comparison; for a lab-incharge that's noise — they only manage one lab,
  // so we narrow the categories + corresponding data series in place.
  const myLabName = userStore.currentUser?.laboratory?.name
  if (userStore.hasRole('lab-incharge') && myLabName) {
    const idx = labs.findIndex(l => String(l).trim().toLowerCase() === String(myLabName).trim().toLowerCase())
    if (idx >= 0) {
      labs   = [labs[idx]]
      tested = [tested[idx] ?? 0]
      fit    = [fit[idx] ?? 0]
      unfit  = [unfit[idx] ?? 0]
    }
  }

  const gap = tested.map((t, i) => Math.max(0, t - (fit[i]||0) - (unfit[i]||0)))

  // maxBarThickness caps how fat a single column can get — without it,
  // Chart.js spreads one bar across the whole canvas when there's only one
  // category (which is exactly what happens for lab-incharge after the
  // filter above, or whenever the data set has 1 lab / 1 district).
  const BAR_CAP = 64

  ch01Instance = new Chart(ch01Ref.value, {
    type: 'bar',
    data: {
      labels: labs,
      datasets: [
        { label:'Fit',           data:fit,   backgroundColor:'rgba(76,175,80,0.90)',   borderColor:'#388e3c', borderWidth:1, stack:'s', maxBarThickness: BAR_CAP },
        { label:'Unfit',         data:unfit, backgroundColor:'rgba(229,57,53,0.88)',   borderColor:'#c62828', borderWidth:1, stack:'s', maxBarThickness: BAR_CAP },
        { label:'Gap to Target', data:gap,   backgroundColor:'rgba(200,220,255,0.45)', borderColor:'rgba(21,101,192,0.25)', borderWidth:1, stack:'s', maxBarThickness: BAR_CAP },
      ],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position:'bottom', labels:{ boxWidth:12, padding:14, font:chartFont } } },
      scales: {
        x: { stacked:true, grid:{display:false}, ticks:{font:{...chartFont,size:10},maxRotation:30} },
        y: { stacked:true, beginAtZero:true, grid:{color:'rgba(0,0,0,0.04)'}, ticks:{font:chartFont,stepSize:50} },
      },
    },
  })
}

// Rebuild CH-02 (District-wise) from API data
function rebuildCH02(districtResults) {
  if (!ch02Ref.value) return
  if (ch02Instance) { ch02Instance.destroy(); ch02Instance = null }

  let districts, fit, unfit
  if (districtResults?.labels?.length) {
    districts = Object.values(districtResults.labels)
    const ds  = districtResults.datasets || []
    fit   = ds.find(d => d.label?.toLowerCase().includes('fit') && !d.label?.toLowerCase().includes('unfit'))?.data || []
    unfit = ds.find(d => d.label?.toLowerCase().includes('unfit'))?.data || []
  } else {
    districts = ['Peshawar','Mardan','Swat','Abbottabad','Kohat','Charsadda','D.I.Khan','Bannu','Nowshera','Swabi','Mansehra','Haripur','Karak','Malakand']
    fit    = [41,15,33,20,19,12,34,35,10,11,24,21,41,17]
    unfit  = [7,7,5,1,1,5,6,7,2,6,1,0,3,3]
  }

  // Same bar-thickness cap as CH-01 — a lab-incharge often sees only one
  // district (their lab's catchment), and without this the single bar
  // stretches edge-to-edge across the canvas and looks like a solid block.
  const BAR_CAP = 64

  ch02Instance = new Chart(ch02Ref.value, {
    type: 'bar',
    data: {
      labels: districts,
      datasets: [
        { label:'Fit',   data:fit,   backgroundColor:'rgba(76,175,80,0.90)',  borderColor:'#388e3c', borderWidth:1, stack:'s', maxBarThickness: BAR_CAP },
        { label:'Unfit', data:unfit, backgroundColor:'rgba(229,57,53,0.88)',  borderColor:'#c62828', borderWidth:1, stack:'s', maxBarThickness: BAR_CAP },
      ],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position:'bottom', labels:{ boxWidth:12, padding:14, font:chartFont } } },
      scales: {
        x: { stacked:true, grid:{display:false}, ticks:{font:{...chartFont,size:10},maxRotation:35} },
        y: { stacked:true, beginAtZero:true, grid:{color:'rgba(0,0,0,0.04)'}, ticks:{font:chartFont,stepSize:10} },
      },
    },
  })
}

// Title suffix reflects the current heatmap selection.
const hmTitleSuffix = computed(() => {
  const cat = hmParam.value
  const sub = hmSub.value
  const catLabels = { overall:'Overall', microbial:'Microbial', chemical:'Chemical', physical:'Physical' }
  const label = catLabels[cat] || 'Overall'
  if (sub) {
    const subDisplay = (hmSubs[cat] || []).find(s => s.toLowerCase().replace(/[^a-z]/g,'') === sub.toLowerCase().replace(/[^a-z]/g,''))
    if (subDisplay && !subDisplay.toLowerCase().startsWith('all')) return `${label} · ${subDisplay}`
  }
  return label
})

// Load filter dropdowns from backend (full geography + labs).
async function loadFilterDropdowns() {
  try {
    const [regRes, cirRes, divRes, distRes, phedRes, labRes] = await Promise.all([
      api.get('/regions'),
      api.get('/circles'),
      api.get('/all-divisions'),
      api.get('/all-districts'),
      api.get('/phed-divisions'),
      api.get('/all-laboratories'),
    ])
    dbRegions.value       = regRes.data?.data  || regRes.data  || []
    dbCircles.value       = cirRes.data?.data  || cirRes.data  || []
    dbDivisions.value     = divRes.data        || []
    dbDistricts.value     = distRes.data       || []
    dbPhedDivisions.value = phedRes.data?.data || phedRes.data || []
    dbLaboratories.value  = labRes.data        || []
  } catch (e) {
    console.warn('Filter dropdown load failed:', e.message)
  }
}

// Reset every filter back to its default — useful when the user has narrowed
// the view too far and wants to start over without reloading the page.
function clearFilters() {
  filters.value = {
    client:        'PHE',
    from:          new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
    to:            new Date().toISOString().split('T')[0],
    allTime:       true,
    regionId:      '',
    divisionId:    '',
    circleId:      '',
    districtId:    '',
    phedDivisionId:'',
    labId:         '',
  }
}

// ── Cascade helpers ───────────────────────────────────────────────────
// Division is derived indirectly: Region → Circles → Districts → Divisions
// because divisions.region_id is NULL in this DB.
const filteredCircles = computed(() => {
  if (!filters.value.regionId) return dbCircles.value
  return dbCircles.value.filter(c => String(c.region_id) === String(filters.value.regionId))
})
const filteredDivisions = computed(() => {
  if (!filters.value.regionId) return dbDivisions.value
  const circleIds   = new Set(filteredCircles.value.map(c => String(c.id)))
  const divisionIds = new Set(
    dbDistricts.value
      .filter(d => circleIds.has(String(d.circle_id)))
      .map(d => String(d.division_id))
  )
  return dbDivisions.value.filter(d => divisionIds.has(String(d.id)))
})
const filteredDistricts = computed(() => {
  let list = dbDistricts.value
  if (filters.value.divisionId) list = list.filter(d => String(d.division_id) === String(filters.value.divisionId))
  if (filters.value.circleId)   list = list.filter(d => String(d.circle_id)   === String(filters.value.circleId))
  return list
})
const filteredPhedDivisions = computed(() => {
  if (!filters.value.districtId) return dbPhedDivisions.value
  return dbPhedDivisions.value.filter(p => String(p.district_id) === String(filters.value.districtId))
})
const filteredLaboratories = computed(() => {
  let list = dbLaboratories.value
  if (filters.value.districtId) list = list.filter(l => String(l.district_id) === String(filters.value.districtId))
  if (filters.value.divisionId) list = list.filter(l => String(l.division_id) === String(filters.value.divisionId))
  return list
})

// ── PNG export helpers ───────────────────────────────────────────────
// Chart.js canvases render with a transparent background; we composite
// them onto white before exporting so downloaded PNGs look like the UI.
function downloadChartPng(chart, filename) {
  if (!chart || !chart.canvas) return
  const src = chart.canvas
  const out = document.createElement('canvas')
  out.width  = src.width
  out.height = src.height
  const ctx = out.getContext('2d')
  ctx.fillStyle = '#ffffff'
  ctx.fillRect(0, 0, out.width, out.height)
  ctx.drawImage(src, 0, 0)
  const link = document.createElement('a')
  link.href = out.toDataURL('image/png')
  link.download = filename
  link.click()
}

// SVG → PNG via offscreen canvas. Used for the heatmap (CH-03).
function downloadSvgPng(svgEl, filename) {
  if (!svgEl) return
  const rect = svgEl.getBoundingClientRect()
  const w = Math.max(rect.width  || 0, 1200)
  const h = Math.max(rect.height || 0, 750)
  const clone = svgEl.cloneNode(true)
  if (!clone.getAttribute('xmlns')) clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg')
  const xml = new XMLSerializer().serializeToString(clone)
  const url = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(xml)
  const img = new Image()
  img.onload = () => {
    const out = document.createElement('canvas')
    out.width  = w
    out.height = h
    const ctx = out.getContext('2d')
    ctx.fillStyle = '#ffffff'
    ctx.fillRect(0, 0, w, h)
    ctx.drawImage(img, 0, 0, w, h)
    const link = document.createElement('a')
    link.href = out.toDataURL('image/png')
    link.download = filename
    link.click()
  }
  img.src = url
}

const ch03SvgRef = ref(null)

function pngStamp() {
  const d = new Date()
  return `${d.getFullYear()}${String(d.getMonth()+1).padStart(2,'0')}${String(d.getDate()).padStart(2,'0')}-${String(d.getHours()).padStart(2,'0')}${String(d.getMinutes()).padStart(2,'0')}`
}

function exportCh01() { downloadChartPng(ch01Instance, `CH01-lab-wise-${pngStamp()}.png`) }
function exportCh02() { downloadChartPng(ch02Instance, `CH02-district-wise-${pngStamp()}.png`) }
function exportCh03() { downloadSvgPng(ch03SvgRef.value, `CH03-heatmap-${pngStamp()}.png`) }

// ── Expand-chart modal ────────────────────────────────────────────────
// Clicking ⛶ on a chart header opens it full-size in an in-page modal.
// We re-mount Chart.js on a fresh canvas inside the modal using a deep
// clone of the source chart's config — interactive, not just a screenshot.
const expandedChart      = ref(null)            // 'ch01' | 'ch02' | null
const expandedTitle      = ref('')
const expandedCanvasRef  = ref(null)
let   expandedInstance   = null

function openExpanded(which) {
  const src = which === 'ch01' ? ch01Instance : ch02Instance
  if (!src) return
  expandedTitle.value   = which === 'ch01'
    ? 'Lab-wise WQ Analysis (CH-01)'
    : 'District-wise WQ Analysis (CH-02)'
  expandedChart.value   = which
  nextTick(() => {
    if (!expandedCanvasRef.value) return
    if (expandedInstance) { expandedInstance.destroy(); expandedInstance = null }
    expandedInstance = new Chart(expandedCanvasRef.value, {
      type:    src.config.type,
      data:    JSON.parse(JSON.stringify(src.config.data)),
      options: { ...src.config.options, responsive: true, maintainAspectRatio: false },
    })
  })
}

function closeExpanded() {
  if (expandedInstance) { expandedInstance.destroy(); expandedInstance = null }
  expandedChart.value = null
}

// Re-fetch when filters change (debounced)
let fetchTimer = null
watch(filters, () => {
  clearTimeout(fetchTimer)
  fetchTimer = setTimeout(fetchDashboard, 600)
}, { deep: true })

onMounted(async () => {
  await loadFilterDropdowns()
  await fetchDashboard()
  updateHeatmap()   // also run static heatmap as fallback
})

// ── CH-05: KPI Performance — Labs ────────────────────────────────────
// Reactive matrix populated by POST /dashboard/lab-kpis. Each row is a KPI,
// each column is a lab. Cells are integer percentages or null (data source
// not implemented — rendered as "—" with a tooltip).
const kpiLabs = ref([])         // [{id, name, displayName}]
const kpiRows = ref([])         // [{id, name, target_pct, missing_reason, values: {labId: pct|null}}]
const kpiMeta = ref({})         // {tat_target_hours, entry_target_hours, period_start, period_end}
const kpiLoading = ref(false)

// Lab name → short header used in the table. Keeps display compact without
// changing the actual data.
function shortLabName(fullName) {
  const map = {
    'Central Laboratory Peshawar':           'Peshawar',
    'Swat Laboratory':                       'Swat',
    'Timergara (at Batkhela) Laboratory':    'Malakand',
    'Kohat Laboratory':                      'Kohat',
    'Mardan Laboratory':                     'Mardan',
    'Di Khan Laboratory':                    'D.I. Khan',
    'Bannu/lakki Laboratory':                'Lakki/Bannu',
    'Abbottabad Laboratory':                 'Abbottabad',
  }
  return map[fullName] || fullName
}

async function fetchLabKpis() {
  kpiLoading.value = true
  try {
    const res = await api.post('/dashboard/lab-kpis', buildPayload())
    const d = res.data?.data || res.data || {}
    let labs = d.labs || []

    // Frontend safety net — even though labKpis() backend now scopes labs
    // by the user's pivot, defend against stale tokens or future regressions
    // by filtering the column set to the lab-incharge's own lab on render.
    const myLabName = userStore.currentUser?.laboratory?.name
    const myLabId   = userStore.currentUser?.laboratory?.id
    if (userStore.hasRole('lab-incharge') && (myLabId || myLabName)) {
      labs = labs.filter(l =>
        (myLabId && Number(l.id) === Number(myLabId)) ||
        (myLabName && String(l.name).trim().toLowerCase() === String(myLabName).trim().toLowerCase())
      )
    }

    kpiLabs.value = labs.map(l => ({ id: l.id, name: l.name, displayName: shortLabName(l.name) }))
    const catalog = d.kpis || []
    const rowsByLab = d.rows || []
    kpiRows.value = catalog.map(k => {
      const values = {}
      rowsByLab.forEach(r => { values[r.lab_id] = r.kpis?.[k.id] ?? null })
      return {
        id: k.id,
        name: k.name,
        target_pct: k.target_pct,
        rag_green: k.rag_green,
        rag_amber: k.rag_amber,
        manual: k.manual,
        missing_reason: k.missing_reason,
        values,
      }
    })
    kpiMeta.value = d.meta || {}
  } catch (e) {
    console.error('Lab KPIs fetch failed:', e?.response?.data || e)
    kpiLabs.value = []
    kpiRows.value = []
  } finally {
    kpiLoading.value = false
  }
}

// Per-KPI RAG band lookup. Falls back to a generic 90/75 split when the
// catalog row doesn't supply rag_green/rag_amber (older clients, missing
// catalog entries).
function kpiCellStyle(val, kpi) {
  if (val == null) return 'background:#f1f5f9;color:#94a3b8;border:1px solid #e2e8f0'
  const green = kpi?.rag_green ?? 90
  const amber = kpi?.rag_amber ?? 75
  if (val >= green) return 'background:#d1fae5;color:#065f46;border:1px solid #6ee7b7'
  if (val >= amber) return 'background:#fef9c3;color:#713f12;border:1px solid #fde047'
  return 'background:#fee2e2;color:#991b1b;border:1px solid #fca5a5'
}

function exportKpiCsv() {
  const labs = kpiLabs.value
  const header = ['KPI ID', 'KPI Name', 'Target', ...labs.map(l => `"${l.name}"`)].join(',')
  const rows = kpiRows.value.map(r =>
    [
      r.id,
      `"${r.name}"`,
      r.target_pct != null ? r.target_pct + '%' : '—',
      ...labs.map(l => r.values[l.id] != null ? r.values[l.id] + '%' : '—'),
    ].join(',')
  )
  const csv = [header, ...rows].join('\n')
  const a = document.createElement('a')
  a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv)
  a.download = 'kpi-performance-labs.csv'
  a.click()
}
</script>

<template>
  <div class="dashboard-page">
    <!-- Error bar (separate from loading — error can persist while a refetch runs) -->
    <div v-if="dashError" style="background:#fee2e2;border:1px solid #fca5a5;border-radius:5px;padding:8px 14px;margin-bottom:10px;font-size:12px;color:#991b1b">
      ⚠ {{ dashError }} — showing cached/demo data
    </div>

    <!-- Filter bar -->
    <div class="filters">
      <div class="fg">
        <label>Client</label>
        <select v-model="filters.client">
          <option value="PHE">PHE</option>
          <option value="Private">Private</option>
        </select>
      </div>
      <div class="fg">
        <label>From</label>
        <input type="date" v-model="filters.from" :disabled="filters.allTime">
      </div>
      <div class="fg">
        <label>To</label>
        <input type="date" v-model="filters.to" :disabled="filters.allTime">
      </div>
      <div class="fg fg-check">
        <label for="alltime">All Time</label>
        <div class="alltime-box">
          <input type="checkbox" id="alltime" v-model="filters.allTime">
          <span class="alltime-hint">Ignore date range</span>
        </div>
      </div>
      <div class="fg">
        <label>Region (CE)</label>
        <select v-model="filters.regionId">
          <option value="">All CE Zones</option>
          <option v-for="r in dbRegions" :key="r.id" :value="r.id">{{ r.name }}</option>
        </select>
      </div>
      <div class="fg">
        <label>Division</label>
        <select v-model="filters.divisionId">
          <option value="">All Divisions</option>
          <option v-for="d in filteredDivisions" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
      </div>
      <div class="fg">
        <label>PHE Circle</label>
        <select v-model="filters.circleId">
          <option value="">All Circles</option>
          <option v-for="c in filteredCircles" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>
      <div class="fg">
        <label>District</label>
        <select v-model="filters.districtId">
          <option value="">All Districts</option>
          <option v-for="d in filteredDistricts" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
      </div>
      <div class="fg">
        <label>PHE Division</label>
        <select v-model="filters.phedDivisionId">
          <option value="">All PHE Divisions</option>
          <option v-for="p in filteredPhedDivisions" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
      </div>
      <div class="fg">
        <label>Lab</label>
        <select v-model="filters.labId">
          <option value="">All Labs</option>
          <option v-for="l in filteredLaboratories" :key="l.id" :value="l.id">{{ l.name }}</option>
        </select>
      </div>
      <div class="fg fg-action">
        <label>&nbsp;</label>
        <button type="button" class="btn-clear" @click="clearFilters">✕ Clear Filters</button>
      </div>
    </div>

    <!-- Skeleton loading state — mirrors the real layout (4 card rows + chart area) -->
    <div v-if="dashLoading" class="dash-skeleton">
      <template v-for="(row, ri) in [5, 5, 5, 5]" :key="'sk-row-' + ri">
        <div class="sk-row-label"></div>
        <div class="cards">
          <div v-for="i in row" :key="'sk-c-' + ri + '-' + i" class="sk-card">
            <div class="sk-bar sk-bar-sm"></div>
            <div class="sk-bar sk-bar-xl"></div>
            <div class="sk-bar sk-bar-md"></div>
            <div class="sk-bar sk-bar-sm"></div>
          </div>
        </div>
        <!-- Chart placeholder row between Row 2 and Row 3 -->
        <div v-if="ri === 1" class="sk-charts">
          <div class="sk-chart"></div>
          <div class="sk-chart"></div>
        </div>
        <div v-if="ri === 1" class="sk-map"></div>
      </template>
    </div>

    <!-- Real content (rendered only when not loading) -->
    <template v-else>

    <!-- Row 1 — Water Supply Schemes -->
    <div style="font-size:10.5px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">
      Row 1 — Water Supply Schemes
    </div>
    <div class="cards">
      <div class="card clickable" @click="router.push('/wss-details')">
        <div class="c-lbl">Functional WSS</div>
        <div class="c-val">{{ stats.totalWss }}</div>
        <div class="c-row"><span>☀️ Solar: <b>—</b></span><span>⚡ Non-Solar: <b>—</b></span></div>
        <div class="c-sub">Live from database</div>
        <div class="c-nav">→ WSS Register</div>
      </div>
      <div class="card c-amber clickable" @click="router.push('/wss-details')">
        <div class="c-lbl">Tested Samples</div>
        <div class="c-val">{{ stats.testedWss }}</div>
        <div class="c-row"><span>✅ Fit: <b>{{ stats.fitSamples }}</b></span><span>❌ Unfit: <b>{{ stats.unfitSamples }}</b></span></div>
        <div class="c-sub">{{ stats.fitPct }} fit · {{ stats.unfitPct }} unfit</div>
        <div class="c-nav">→ WSS Register</div>
      </div>
      <div class="card clickable" @click="router.push('/reports/gsr')">
        <div class="c-lbl">Total Samples</div>
        <div class="c-val">{{ stats.totalSamples }}</div>
        <div class="c-row"><span>🔬 Micro: {{ stats.microFitPct }} fit</span><span>⚗ Chem: {{ stats.chemFitPct }} fit</span></div>
        <div class="c-nav">→ GSR Report</div>
      </div>
      <div class="card c-red clickable" @click="router.push('/water-quality/unfit-sample-trail')">
        <div class="c-lbl">Unfit Samples</div>
        <div class="c-val">{{ stats.unfitSamples }}</div>
        <div class="c-row"><span>Micro: {{ stats.microUnfitPct }}</span><span>Chem: {{ stats.chemUnfitPct }}</span><span>Phys: {{ stats.physUnfitPct }}</span></div>
        <div class="c-sub">{{ stats.unfitPct }} of tested</div>
        <div class="c-nav">→ Unfit Trail</div>
      </div>
      <div class="card c-amber clickable" @click="router.push('/water-quality/unfit-sample-trail')">
        <div class="c-lbl">Pending Inventory</div>
        <div class="c-val">{{ stats.pendingInventory }}</div>
        <div class="c-row"><span>Labs: <b>{{ stats.totalLabs }}</b></span></div>
        <div class="c-sub">Pending requests</div>
        <div class="c-nav">→ Inventory</div>
      </div>
    </div>

    <!-- Row 2 — Performance & Compliance -->
    <div style="font-size:10.5px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">
      Row 2 — Performance &amp; Compliance
    </div>
    <div class="cards">
      <div class="card c-amber clickable" @click="router.push('/admin/kpi-framework')">
        <div class="c-lbl">15% Monthly Target</div>
        <div class="c-val">{{ stats.totalLabs }}</div>
        <div class="c-row"><span>Labs total</span></div>
        <div class="c-sub">Active laboratories</div>
        <div class="c-nav">→ KPI Dashboard</div>
      </div>
      <div class="card c-red clickable">
        <div class="c-lbl">Alerts / Complaints</div>
        <div class="c-val">{{ stats.totalComplaints }}</div>
        <div class="c-row"><span>Pending: <b>{{ stats.pendingComplaints }}</b></span></div>
        <div class="c-sub">Active complaints</div>
        <!-- SRS §2.9 Feature 1: button label uses "Report Issue", not legacy "Create Issue". -->
        <div class="c-nav">→ Report Issue</div>
      </div>
      <div class="card c-red clickable">
        <div class="c-lbl">Issues</div>
        <div class="c-val">{{ stats.totalIssues }}</div>
        <div class="c-sub">Total issues logged</div>
        <div class="c-nav">→ Report Issue</div>
      </div>
      <!-- SRS §2.9 Feature 2: Escalated Issues — complaints unresolved > 72h.
           Computed in backend DashboardController::getTotalComplaints. -->
      <div class="card c-amber clickable" :class="{ 'c-red': Number(stats.escalatedComplaints) > 0 }">
        <div class="c-lbl">Escalated Issues</div>
        <div class="c-val">{{ stats.escalatedComplaints }}</div>
        <div class="c-row"><span>Unresolved &gt; 72h</span></div>
        <div class="c-sub">Auto-escalated complaints</div>
        <div class="c-nav">→ Review &amp; Resolve</div>
      </div>
      <div class="card c-gold clickable" @click="router.push('/finance/invoices')">
        <div class="c-lbl">Revenue (Total)</div>
        <div class="c-val">₨ {{ stats.revenue }}</div>
        <div class="c-row"><span>All labs combined</span></div>
        <div class="c-nav">→ Finance</div>
      </div>
    </div>

    <!-- Charts Row -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
      <div class="chart-box">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
          <h3 style="margin-bottom:0">Lab-wise WQ Analysis — March 2026 <span style="font-size:11px;font-weight:400;color:var(--muted)">(CH-01)</span></h3>
          <div style="display:flex;gap:6px">
            <button class="btn btn-sec btn-xs" @click="openExpanded('ch01')" title="Open chart full-size">⛶ Expand</button>
            <button class="btn btn-sec btn-xs" @click="exportCh01">⬇ PNG</button>
          </div>
        </div>
        <div style="position:relative;height:210px">
          <canvas ref="ch01Ref"></canvas>
        </div>
      </div>
      <div class="chart-box">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
          <h3 style="margin-bottom:0">District-wise WQ Analysis — March 2026 <span style="font-size:11px;font-weight:400;color:var(--muted)">(CH-02)</span></h3>
          <div style="display:flex;gap:6px">
            <button class="btn btn-sec btn-xs" @click="openExpanded('ch02')" title="Open chart full-size">⛶ Expand</button>
            <button class="btn btn-sec btn-xs" @click="exportCh02">⬇ PNG</button>
          </div>
        </div>
        <div style="position:relative;height:210px">
          <canvas ref="ch02Ref"></canvas>
        </div>
      </div>
    </div>

    <!-- CH-03: District Water Quality Heatmap -->
    <div class="chart-box" style="margin-bottom:12px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
        <h3 style="margin-bottom:0">
          District Water Quality Heatmap — KP
          <span style="font-size:11px;font-weight:400;color:var(--muted)">(CH-03)</span>
        </h3>
        <div style="display:flex;gap:6px;align-items:center">
          <select v-model="hmParam" style="border:1px solid var(--border);border-radius:4px;padding:4px 7px;font-size:11.5px;font-family:inherit">
            <option value="overall">Overall (Fit / Unfit)</option>
            <option value="microbial">Microbial</option>
            <option value="chemical">Chemical</option>
            <option value="physical">Physical</option>
          </select>
          <select v-if="hmSubOptions.length" v-model="hmSub" style="border:1px solid var(--border);border-radius:4px;padding:4px 7px;font-size:11.5px;font-family:inherit">
            <option v-for="s in hmSubOptions" :key="s" :value="s.toLowerCase().replace(/[^a-z]/g,'')">{{ s }}</option>
          </select>
          <button class="btn btn-sec btn-xs" @click="exportCh03">⬇ PNG</button>
        </div>
      </div>

      <!-- Floating tooltip -->
      <div v-if="hoveredData" style="position:fixed;background:rgba(13,33,55,.93);color:#fff;border-radius:6px;padding:8px 12px;font-size:11.5px;pointer-events:none;z-index:9999;line-height:1.6;max-width:200px"
           :style="tooltipStyle">
        <b>{{ hoveredData.name }}</b><br>
        <template v-if="hoveredData.hasData">
          ✅ Fit: {{ hoveredData.fitPct }}% &nbsp; ❌ Unfit: {{ hoveredData.u }}%<br>
          <span style="opacity:.8">{{ hoveredData.rag }}</span>
        </template>
        <template v-else>
          <span style="opacity:.7">No samples in current filter</span>
        </template>
      </div>

      <!-- SVG Map -->
      <div style="background:#d6eaf8;border-radius:6px;border:1px solid var(--border);overflow:hidden;position:relative">
        <svg ref="ch03SvgRef" viewBox="0 0 620 375" style="width:100%;display:block;cursor:default">
          <rect width="620" height="375" fill="#cce5f5"/>

          <!-- Districts -->
          <polygon id="d-chitral"    :style="{fill: districtColors['chitral']?.color    || '#ccc', cursor:'pointer'}" points="120,10 185,8 200,35 195,70 175,90 145,85 120,60 110,35"   class="hm-d" @click="onDistrictClick('chitral')"    @mousemove="onDistrictHover('chitral',$event)"    @mouseleave="onDistrictLeave"/>
          <polygon id="d-upperdir"   :style="{fill: districtColors['upperdir']?.color   || '#ccc', cursor:'pointer'}" points="185,8 245,12 255,40 245,65 220,75 200,65 195,70 185,35"   class="hm-d" @click="onDistrictClick('upperdir')"   @mousemove="onDistrictHover('upperdir',$event)"   @mouseleave="onDistrictLeave"/>
          <polygon id="d-lowerdir"   :style="{fill: districtColors['lowerdir']?.color   || '#ccc', cursor:'pointer'}" points="245,40 270,38 278,60 268,80 248,82 238,68 245,65"          class="hm-d" @click="onDistrictClick('lowerdir')"   @mousemove="onDistrictHover('lowerdir',$event)"   @mouseleave="onDistrictLeave"/>
          <polygon id="d-swat"       :style="{fill: districtColors['swat']?.color       || '#ccc', cursor:'pointer'}" points="255,40 315,36 325,60 318,90 295,100 268,95 268,80 278,60"  class="hm-d" @click="onDistrictClick('swat')"       @mousemove="onDistrictHover('swat',$event)"       @mouseleave="onDistrictLeave"/>
          <polygon id="d-malakand"   :style="{fill: districtColors['malakand']?.color   || '#ccc', cursor:'pointer'}" points="268,80 295,100 285,115 265,118 252,105 258,90"             class="hm-d" @click="onDistrictClick('malakand')"   @mousemove="onDistrictHover('malakand',$event)"   @mouseleave="onDistrictLeave"/>
          <polygon id="d-shangla"    :style="{fill: districtColors['shangla']?.color    || '#ccc', cursor:'pointer'}" points="318,90 345,85 352,108 338,122 318,118 310,105"             class="hm-d" @click="onDistrictClick('shangla')"    @mousemove="onDistrictHover('shangla',$event)"    @mouseleave="onDistrictLeave"/>
          <polygon id="d-buner"      :style="{fill: districtColors['buner']?.color      || '#ccc', cursor:'pointer'}" points="285,115 318,118 322,140 302,150 278,142 272,128"           class="hm-d" @click="onDistrictClick('buner')"      @mousemove="onDistrictHover('buner',$event)"      @mouseleave="onDistrictLeave"/>
          <polygon id="d-bajaur"     :style="{fill: districtColors['bajaur']?.color     || '#ccc', cursor:'pointer'}" points="145,85 175,90 185,110 172,130 148,128 132,108"             class="hm-d" @click="onDistrictClick('bajaur')"     @mousemove="onDistrictHover('bajaur',$event)"     @mouseleave="onDistrictLeave"/>
          <polygon id="d-mohmand"    :style="{fill: districtColors['mohmand']?.color    || '#ccc', cursor:'pointer'}" points="175,90 200,65 220,75 232,92 225,115 205,122 185,110"       class="hm-d" @click="onDistrictClick('mohmand')"    @mousemove="onDistrictHover('mohmand',$event)"    @mouseleave="onDistrictLeave"/>
          <polygon id="d-charsadda"  :style="{fill: districtColors['charsadda']?.color  || '#ccc', cursor:'pointer'}" points="225,115 248,108 258,125 248,140 230,142 218,130"           class="hm-d" @click="onDistrictClick('charsadda')"  @mousemove="onDistrictHover('charsadda',$event)"  @mouseleave="onDistrictLeave"/>
          <polygon id="d-mardan"     :style="{fill: districtColors['mardan']?.color     || '#ccc', cursor:'pointer'}" points="258,125 278,142 272,158 252,162 240,148 248,140"           class="hm-d" @click="onDistrictClick('mardan')"     @mousemove="onDistrictHover('mardan',$event)"     @mouseleave="onDistrictLeave"/>
          <polygon id="d-swabi"      :style="{fill: districtColors['swabi']?.color      || '#ccc', cursor:'pointer'}" points="272,128 302,150 298,168 278,170 262,158 265,145"           class="hm-d" @click="onDistrictClick('swabi')"      @mousemove="onDistrictHover('swabi',$event)"      @mouseleave="onDistrictLeave"/>
          <polygon id="d-peshawar"   :style="{fill: districtColors['peshawar']?.color   || '#ccc', cursor:'pointer'}" points="205,122 232,115 248,140 240,148 220,155 200,148 192,132"   class="hm-d" @click="onDistrictClick('peshawar')"   @mousemove="onDistrictHover('peshawar',$event)"   @mouseleave="onDistrictLeave"/>
          <polygon id="d-nowshera"   :style="{fill: districtColors['nowshera']?.color   || '#ccc', cursor:'pointer'}" points="240,148 252,162 248,178 230,182 218,170 228,158"           class="hm-d" @click="onDistrictClick('nowshera')"   @mousemove="onDistrictHover('nowshera',$event)"   @mouseleave="onDistrictLeave"/>
          <polygon id="d-khyber"     :style="{fill: districtColors['khyber']?.color     || '#ccc', cursor:'pointer'}" points="148,128 172,130 185,150 175,168 152,165 138,148"           class="hm-d" @click="onDistrictClick('khyber')"     @mousemove="onDistrictHover('khyber',$event)"     @mouseleave="onDistrictLeave"/>
          <polygon id="d-kurram"     :style="{fill: districtColors['kurram']?.color     || '#ccc', cursor:'pointer'}" points="175,168 200,165 210,185 202,208 180,215 162,200 158,182"   class="hm-d" @click="onDistrictClick('kurram')"     @mousemove="onDistrictHover('kurram',$event)"     @mouseleave="onDistrictLeave"/>
          <polygon id="d-kohat"      :style="{fill: districtColors['kohat']?.color      || '#ccc', cursor:'pointer'}" points="248,178 270,172 282,192 275,215 252,220 235,205 230,188"   class="hm-d" @click="onDistrictClick('kohat')"      @mousemove="onDistrictHover('kohat',$event)"      @mouseleave="onDistrictLeave"/>
          <polygon id="d-orakzai"    :style="{fill: districtColors['orakzai']?.color    || '#ccc', cursor:'pointer'}" points="210,185 235,178 248,195 240,212 218,215 205,200"           class="hm-d" @click="onDistrictClick('orakzai')"    @mousemove="onDistrictHover('orakzai',$event)"    @mouseleave="onDistrictLeave"/>
          <polygon id="d-hangu"      :style="{fill: districtColors['hangu']?.color      || '#ccc', cursor:'pointer'}" points="202,208 225,205 235,222 225,238 205,240 192,225"           class="hm-d" @click="onDistrictClick('hangu')"      @mousemove="onDistrictHover('hangu',$event)"      @mouseleave="onDistrictLeave"/>
          <polygon id="d-attock"     :style="{fill: districtColors['attock']?.color     || '#ccc', cursor:'pointer'}" points="298,168 322,165 325,185 308,192 292,182"                   class="hm-d" @click="onDistrictClick('attock')"     @mousemove="onDistrictHover('attock',$event)"     @mouseleave="onDistrictLeave"/>
          <polygon id="d-haripur"    :style="{fill: districtColors['haripur']?.color    || '#ccc', cursor:'pointer'}" points="322,165 352,160 360,182 348,200 325,200 308,188"           class="hm-d" @click="onDistrictClick('haripur')"    @mousemove="onDistrictHover('haripur',$event)"    @mouseleave="onDistrictLeave"/>
          <polygon id="d-abbottabad" :style="{fill: districtColors['abbottabad']?.color || '#ccc', cursor:'pointer'}" points="352,108 390,105 398,130 388,158 360,162 348,142 345,120"   class="hm-d" @click="onDistrictClick('abbottabad')" @mousemove="onDistrictHover('abbottabad',$event)" @mouseleave="onDistrictLeave"/>
          <polygon id="d-mansehra"   :style="{fill: districtColors['mansehra']?.color   || '#ccc', cursor:'pointer'}" points="325,60 380,55 395,80 398,108 390,105 352,108 338,90 325,75" class="hm-d" @click="onDistrictClick('mansehra')"   @mousemove="onDistrictHover('mansehra',$event)"   @mouseleave="onDistrictLeave"/>
          <polygon id="d-kohistanu"  :style="{fill: districtColors['kohistanu']?.color  || '#ccc', cursor:'pointer'}" points="380,10 440,8 450,35 438,60 408,65 395,45 380,30"           class="hm-d" @click="onDistrictClick('kohistanu')"  @mousemove="onDistrictHover('kohistanu',$event)"  @mouseleave="onDistrictLeave"/>
          <polygon id="d-kohistanl"  :style="{fill: districtColors['kohistanl']?.color  || '#ccc', cursor:'pointer'}" points="408,65 438,60 448,85 435,105 410,108 398,90"               class="hm-d" @click="onDistrictClick('kohistanl')"  @mousemove="onDistrictHover('kohistanl',$event)"  @mouseleave="onDistrictLeave"/>
          <polygon id="d-torghar"    :style="{fill: districtColors['torghar']?.color    || '#ccc', cursor:'pointer'}" points="352,60 380,55 380,80 365,90 348,82"                        class="hm-d" @click="onDistrictClick('torghar')"    @mousemove="onDistrictHover('torghar',$event)"    @mouseleave="onDistrictLeave"/>
          <polygon id="d-battagram"  :style="{fill: districtColors['battagram']?.color  || '#ccc', cursor:'pointer'}" points="388,108 410,108 415,130 402,148 382,148 375,130 388,118"   class="hm-d" @click="onDistrictClick('battagram')"  @mousemove="onDistrictHover('battagram',$event)"  @mouseleave="onDistrictLeave"/>
          <polygon id="d-karak"      :style="{fill: districtColors['karak']?.color      || '#ccc', cursor:'pointer'}" points="275,215 305,210 318,232 308,255 285,258 268,242 268,225"   class="hm-d" @click="onDistrictClick('karak')"      @mousemove="onDistrictHover('karak',$event)"      @mouseleave="onDistrictLeave"/>
          <polygon id="d-bannu"      :style="{fill: districtColors['bannu']?.color      || '#ccc', cursor:'pointer'}" points="252,240 278,235 290,255 282,275 258,278 242,262 245,248"   class="hm-d" @click="onDistrictClick('bannu')"      @mousemove="onDistrictHover('bannu',$event)"      @mouseleave="onDistrictLeave"/>
          <polygon id="d-nwaz"       :style="{fill: districtColors['nwaz']?.color       || '#ccc', cursor:'pointer'}" points="180,248 210,240 228,258 222,282 198,288 175,272 170,255"   class="hm-d" @click="onDistrictClick('nwaz')"       @mousemove="onDistrictHover('nwaz',$event)"       @mouseleave="onDistrictLeave"/>
          <polygon id="d-swaz"       :style="{fill: districtColors['swaz']?.color       || '#ccc', cursor:'pointer'}" points="155,278 182,268 202,285 198,315 172,322 148,308 145,290"   class="hm-d" @click="onDistrictClick('swaz')"       @mousemove="onDistrictHover('swaz',$event)"       @mouseleave="onDistrictLeave"/>
          <polygon id="d-lakki"      :style="{fill: districtColors['lakki']?.color      || '#ccc', cursor:'pointer'}" points="258,278 290,272 305,292 298,315 272,320 252,305 250,288"   class="hm-d" @click="onDistrictClick('lakki')"      @mousemove="onDistrictHover('lakki',$event)"      @mouseleave="onDistrictLeave"/>
          <polygon id="d-tank"       :style="{fill: districtColors['tank']?.color       || '#ccc', cursor:'pointer'}" points="195,318 225,308 242,325 235,348 210,352 192,338"           class="hm-d" @click="onDistrictClick('tank')"       @mousemove="onDistrictHover('tank',$event)"       @mouseleave="onDistrictLeave"/>
          <polygon id="d-dik"        :style="{fill: districtColors['dik']?.color        || '#ccc', cursor:'pointer'}" points="235,295 268,288 282,310 278,345 252,358 225,348 220,325"   class="hm-d" @click="onDistrictClick('dik')"        @mousemove="onDistrictHover('dik',$event)"        @mouseleave="onDistrictLeave"/>

          <!-- District labels -->
          <text x="148" y="52"  class="hm-label">Chitral</text>
          <text x="212" y="38"  class="hm-label">Dir U.</text>
          <text x="252" y="62"  class="hm-label">Dir L.</text>
          <text x="288" y="68"  class="hm-label">Swat</text>
          <text x="268" y="110" class="hm-label">Mlknd</text>
          <text x="326" y="108" class="hm-label">Shangla</text>
          <text x="292" y="136" class="hm-label">Buner</text>
          <text x="150" y="110" class="hm-label">Bajaur</text>
          <text x="198" y="110" class="hm-label">Mohmand</text>
          <text x="232" y="130" class="hm-label">Charsadda</text>
          <text x="258" y="152" class="hm-label">Mardan</text>
          <text x="278" y="158" class="hm-label">Swabi</text>
          <text x="212" y="140" class="hm-label">Peshawar</text>
          <text x="232" y="170" class="hm-label">Nowshera</text>
          <text x="152" y="150" class="hm-label">Khyber</text>
          <text x="178" y="195" class="hm-label">Kurram</text>
          <text x="255" y="200" class="hm-label">Kohat</text>
          <text x="218" y="202" class="hm-label">Orakzai</text>
          <text x="208" y="228" class="hm-label">Hangu</text>
          <text x="330" y="182" class="hm-label">Haripur</text>
          <text x="362" y="138" class="hm-label">Abbottabad</text>
          <text x="356" y="82"  class="hm-label">Mansehra</text>
          <text x="400" y="35"  class="hm-label">Kohistan U.</text>
          <text x="412" y="90"  class="hm-label">Kohistan L.</text>
          <text x="356" y="72"  class="hm-label">Torghar</text>
          <text x="390" y="132" class="hm-label">Battagram</text>
          <text x="285" y="238" class="hm-label">Karak</text>
          <text x="258" y="260" class="hm-label">Bannu</text>
          <text x="192" y="268" class="hm-label">N.Waz.</text>
          <text x="162" y="302" class="hm-label">S.Waz.</text>
          <text x="268" y="302" class="hm-label">Lakki M.</text>
          <text x="202" y="338" class="hm-label">Tank</text>
          <text x="248" y="328" class="hm-label">D.I. Khan</text>
        </svg>

        <!-- Legend -->
        <div style="position:absolute;bottom:10px;right:10px;background:rgba(255,255,255,.95);border-radius:6px;padding:8px 12px;font-size:11px;box-shadow:0 1px 6px rgba(0,0,0,.12)">
          <div style="font-weight:700;color:var(--navy2);margin-bottom:5px;font-size:10.5px;text-transform:uppercase;letter-spacing:.05em">% Unfit</div>
          <div style="display:flex;flex-direction:column;gap:3px">
            <div style="display:flex;align-items:center;gap:6px"><span style="width:16px;height:12px;background:#1a7a3f;border-radius:2px;display:inline-block"></span> &lt; 10% — Good</div>
            <div style="display:flex;align-items:center;gap:6px"><span style="width:16px;height:12px;background:#7dc97a;border-radius:2px;display:inline-block"></span> 10–20% — Moderate</div>
            <div style="display:flex;align-items:center;gap:6px"><span style="width:16px;height:12px;background:#f4a236;border-radius:2px;display:inline-block"></span> 20–35% — Concern</div>
            <div style="display:flex;align-items:center;gap:6px"><span style="width:16px;height:12px;background:#d32f2f;border-radius:2px;display:inline-block"></span> &gt; 35% — High Risk</div>
          </div>
        </div>

        <!-- Title overlay -->
        <div style="position:absolute;top:8px;left:10px;background:rgba(13,33,55,.82);color:#fff;border-radius:4px;padding:3px 10px;font-size:11px;font-weight:600">
          KP Province · District Heatmap · {{ hmTitleSuffix }}
        </div>
      </div>

      <!-- Selected District Panel -->
      <div v-if="selectedData" style="margin-top:12px;border:2px solid var(--navy);border-radius:8px;overflow:hidden;animation:fadeIn .18s ease">
        <!-- Header -->
        <div style="background:var(--navy);color:#fff;padding:10px 16px;display:flex;align-items:center;justify-content:space-between">
          <div style="display:flex;align-items:center;gap:10px">
            <span style="font-size:15px;font-weight:700">{{ selectedData.name }} District</span>
            <span style="font-size:11.5px;font-weight:700;padding:2px 10px;border-radius:12px;"
                  :style="{ background: selectedData.u > 35 ? '#dc2626' : selectedData.u > 20 ? '#d97706' : '#16a34a', color:'#fff' }">
              {{ selectedData.u }}% Unfit · {{ selectedData.rag }}
            </span>
            <span style="font-size:11px;opacity:.65">{{ selectedData.division }} Div. · {{ selectedData.lab }}</span>
          </div>
          <button @click="clearSelection()" style="background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:5px;padding:4px 12px;cursor:pointer;font-size:12px;font-weight:600">✕ Clear</button>
        </div>

        <!-- KPI strip -->
        <div style="background:#f8fafc;padding:12px 16px;display:grid;grid-template-columns:repeat(7,1fr);gap:8px">
          <div v-for="kpi in [
            {l:'Total',      v: selectedData.total},
            {l:'✅ Fit',     v: selectedData.fit},
            {l:'❌ Unfit',   v: selectedData.unfit},
            {l:'% Unfit',    v: selectedData.u + '%'},
            {l:'WSS Count',  v: selectedData.wss},
            {l:'WSS Tested', v: selectedData.testedWss + ' (' + selectedData.covPct + '%)'},
            {l:'Unfit WSS',  v: selectedData.unfitWss},
          ]" :key="kpi.l" style="background:#fff;border:1px solid #e2e8f0;border-radius:6px;padding:8px 6px;text-align:center">
            <div style="font-size:9.5px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em">{{ kpi.l }}</div>
            <div style="font-size:17px;font-weight:800;color:var(--navy);margin-top:3px;font-family:'DM Mono',monospace">{{ kpi.v }}</div>
          </div>
        </div>

        <!-- Breakdown + actions -->
        <div style="background:#fff;padding:10px 16px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;border-top:1px solid var(--border)">
          <div style="flex:1;min-width:200px">
            <div style="font-size:10.5px;font-weight:600;color:var(--muted);margin-bottom:5px">Contamination Breakdown</div>
            <div style="height:14px;border-radius:3px;overflow:hidden;display:flex">
              <div :style="{ flex: selectedData.micro, background:'#7c3aed' }"></div>
              <div :style="{ flex: selectedData.chem,  background:'#d97706' }"></div>
              <div :style="{ flex: selectedData.phys,  background:'#2563eb' }"></div>
            </div>
            <div style="display:flex;gap:10px;margin-top:4px;font-size:10px;color:var(--muted)">
              <span style="display:flex;align-items:center;gap:3px"><span style="width:9px;height:9px;border-radius:2px;background:#7c3aed;display:inline-block"></span>Microbial: {{ selectedData.micro }}</span>
              <span style="display:flex;align-items:center;gap:3px"><span style="width:9px;height:9px;border-radius:2px;background:#d97706;display:inline-block"></span>Chemical: {{ selectedData.chem }}</span>
              <span style="display:flex;align-items:center;gap:3px"><span style="width:9px;height:9px;border-radius:2px;background:#2563eb;display:inline-block"></span>Physical: {{ selectedData.phys }}</span>
            </div>
          </div>
          <div style="display:flex;gap:8px;flex-shrink:0">
            <button class="btn btn-pri btn-sm" @click="router.push('/reports/gsr')">📋 GSR Report →</button>
            <button class="btn btn-sec btn-sm" @click="router.push('/wss-details')">💧 WSS Register →</button>
          </div>
        </div>
      </div>
    </div>

    <!-- CH-05: KPI Performance — Labs -->
    <div class="chart-box" style="margin-bottom:14px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
        <h3 style="margin-bottom:0">
          KPI Performance — Labs
          <span style="font-size:11px;font-weight:400;color:var(--muted)">(CH-05)</span>
        </h3>
        <button class="btn btn-sec btn-xs" @click="exportKpiCsv">↑ Export</button>
      </div>

      <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:12px">
          <thead>
            <tr style="background:var(--navy);color:#fff">
              <th style="padding:7px 10px;text-align:left;font-weight:600;white-space:nowrap;min-width:72px">KPI ID</th>
              <th style="padding:7px 10px;text-align:left;font-weight:600;min-width:220px">KPI Name</th>
              <th style="padding:7px 8px;text-align:center;font-weight:600;white-space:nowrap;min-width:60px">Target</th>
              <th v-for="lab in kpiLabs" :key="lab.id" :title="lab.name" style="padding:7px 8px;text-align:center;font-weight:600;white-space:nowrap;min-width:72px">
                <span v-if="lab.displayName === 'Lakki/Bannu'">Lakki/<br>Bannu</span>
                <span v-else>{{ lab.displayName }}</span>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!kpiLoading && kpiRows.length === 0">
              <td :colspan="3 + kpiLabs.length" style="padding:18px;text-align:center;color:var(--muted);font-size:12px">No KPI data available</td>
            </tr>
            <tr v-for="(kpi, i) in kpiRows" :key="kpi.id"
                :style="i % 2 === 0 ? 'background:#fff' : 'background:#f8fafc'"
                :title="kpi.missing_reason || ''">
              <td style="padding:6px 10px;font-family:'DM Mono',monospace;font-size:11px;color:var(--muted);white-space:nowrap">{{ kpi.id }}</td>
              <td style="padding:6px 10px;font-weight:500">
                {{ kpi.name }}
                <span v-if="kpi.missing_reason" style="margin-left:6px;font-size:10px;color:#94a3b8;font-weight:400" :title="kpi.missing_reason">ⓘ</span>
              </td>
              <td style="padding:6px 8px;text-align:center;color:var(--muted);font-size:11px;white-space:nowrap">
                {{ kpi.target_pct != null ? '≥ ' + kpi.target_pct + '%' : '—' }}
              </td>
              <td v-for="lab in kpiLabs" :key="lab.id" style="padding:6px 8px;text-align:center"
                  :title="kpi.values[lab.id] == null ? (kpi.missing_reason || 'No data for this lab in current filter') : ''">
                <span v-if="kpi.values[lab.id] != null"
                  style="display:inline-block;padding:2px 8px;border-radius:4px;font-weight:700;font-size:11.5px;min-width:44px"
                  :style="kpiCellStyle(kpi.values[lab.id], kpi)">
                  {{ kpi.values[lab.id] }}%
                </span>
                <span v-else style="color:#ccc;font-size:11px">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Legend -->
      <div style="display:flex;gap:16px;margin-top:10px;font-size:11px;color:var(--muted);flex-wrap:wrap">
        <span style="display:flex;align-items:center;gap:5px">
          <span style="width:14px;height:14px;background:#d1fae5;border:1px solid #6ee7b7;border-radius:3px;display:inline-block"></span> Satisfactory (≥ 90%)
        </span>
        <span style="display:flex;align-items:center;gap:5px">
          <span style="width:14px;height:14px;background:#fef9c3;border:1px solid #fde047;border-radius:3px;display:inline-block"></span> Caution (75–89%)
        </span>
        <span style="display:flex;align-items:center;gap:5px">
          <span style="width:14px;height:14px;background:#fee2e2;border:1px solid #fca5a5;border-radius:3px;display:inline-block"></span> Critical (&lt; 75%)
        </span>
        <span style="display:flex;align-items:center;gap:5px">
          <span style="width:14px;height:14px;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:3px;display:inline-block"></span> — No Data
        </span>
      </div>
    </div>

    <!-- Row 3 — WSS Status -->
    <div style="font-size:10.5px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">
      Row 3 — WSS Status
    </div>
    <div class="cards">
      <div class="card clickable" @click="router.push('/wss-details')">
        <div class="c-lbl">Operational WSS</div>
        <div class="c-val">{{ stats.operationalWss }}</div>
        <div class="c-sub">Currently operational</div>
        <div class="c-nav">→ WSS Register</div>
      </div>
      <div class="card c-amber clickable" @click="router.push('/wss-details')">
        <div class="c-lbl">Non-Operational WSS</div>
        <div class="c-val">{{ stats.nonOperationalWss }}</div>
        <div class="c-sub">Marked non-operational</div>
        <div class="c-nav">→ WSS Register</div>
      </div>
      <div class="card c-red clickable" @click="router.push('/reports/wss-map')">
        <div class="c-lbl">Abandoned WSS</div>
        <div class="c-val">{{ stats.abandonedWss }}</div>
        <div class="c-sub">Decommissioned</div>
        <div class="c-nav">→ WSS Map</div>
      </div>
      <div class="card c-amber clickable" @click="router.push('/wss-details')">
        <div class="c-lbl">Work in Progress</div>
        <div class="c-val">{{ stats.wipWss }}</div>
        <div class="c-sub">Under construction</div>
        <div class="c-nav">→ WSS Register</div>
      </div>
      <div class="card clickable" @click="router.push('/wss-details')">
        <div class="c-lbl">Total WSS</div>
        <div class="c-val">{{ stats.totalWss }}</div>
        <div class="c-sub">All registered schemes</div>
        <div class="c-nav">→ WSS Register</div>
      </div>
    </div>

    <!-- Row 4 — Operations & Assets -->
    <div style="font-size:10.5px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">
      Row 4 — Operations &amp; Assets
    </div>
    <div class="cards">
      <div class="card clickable" @click="router.push('/admin/diaries-dispatches')">
        <div class="c-lbl">Diary Entries</div>
        <div class="c-val">{{ stats.diaryCount }}</div>
        <div class="c-sub">Total diary records</div>
        <div class="c-nav">→ Diary Register</div>
      </div>
      <div class="card clickable" @click="router.push('/admin/diaries-dispatches')">
        <div class="c-lbl">Dispatches</div>
        <div class="c-val">{{ stats.dispatchCount }}</div>
        <div class="c-sub">Total dispatch records</div>
        <div class="c-nav">→ Dispatch Register</div>
      </div>
      <div class="card c-amber clickable" @click="router.push('/assets/stock-inventory')">
        <div class="c-lbl">Stock / Inventory</div>
        <div class="c-val">{{ stats.pendingInventory }}</div>
        <div class="c-sub">Pending requests</div>
        <div class="c-nav">→ Stock Register</div>
      </div>
      <div class="card clickable" @click="router.push('/assets/equipment-register')">
        <div class="c-lbl">Equipment</div>
        <div class="c-val">{{ stats.assetCount }}</div>
        <div class="c-sub">Total registered assets</div>
        <div class="c-nav">→ Equipment</div>
      </div>
      <div class="card c-amber clickable" @click="router.push('/assets/equipment-register')">
        <div class="c-lbl">Calib. Due (30 days)</div>
        <div class="c-val">{{ stats.calibDue }}</div>
        <div class="c-sub">Maintenance scheduled</div>
        <div class="c-nav">→ Equipment</div>
      </div>
    </div>

    </template>

    <!-- Expand-chart modal — opens full-size view of CH-01 / CH-02 -->
    <Teleport to="body">
      <div v-if="expandedChart"
           @click.self="closeExpanded"
           style="position:fixed;inset:0;background:rgba(15,23,42,.62);z-index:9998;display:flex;align-items:center;justify-content:center;padding:24px">
        <div style="background:#fff;border-radius:8px;width:100%;max-width:1200px;max-height:92vh;display:flex;flex-direction:column;box-shadow:0 12px 48px rgba(0,0,0,.32)">
          <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid #e5e7eb">
            <h3 style="margin:0;font-size:15px;font-weight:700">{{ expandedTitle }}</h3>
            <button @click="closeExpanded"
                    style="background:rgba(0,0,0,.06);border:none;border-radius:5px;padding:4px 12px;cursor:pointer;font-size:14px">✕ Close</button>
          </div>
          <div style="flex:1;padding:18px;position:relative;min-height:480px">
            <canvas ref="expandedCanvasRef"></canvas>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style>
/* Dashboard filter bar — uniform grid so every filter has the same width
   and wraps predictably on smaller screens. Scoped via .dashboard-page so it
   doesn't affect other pages that share the global .filters style. */
.dashboard-page .filters {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 10px 12px;
  align-items: end;
}
.dashboard-page .filters .fg { width: 100%; }
.dashboard-page .filters .fg label {
  display: block;
  margin-bottom: 3px;
}
.dashboard-page .filters .fg select,
.dashboard-page .filters .fg input[type="date"] {
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
}
.dashboard-page .filters .sep { display: none; }
.dashboard-page .filters .fg-check .alltime-box {
  display: flex;
  align-items: center;
  gap: 6px;
  height: 28px; /* match select height so checkbox lines up with row */
  padding: 0 7px;
  border: 1px solid var(--input-border);
  border-radius: 4px;
  background: var(--white);
}
.dashboard-page .filters .fg-check .alltime-box input { margin: 0; }
.dashboard-page .filters .fg-check .alltime-hint {
  font-size: 11.5px;
  color: var(--muted);
}
.dashboard-page .filters .fg-action .btn-clear {
  height: 28px;
  padding: 0 10px;
  font-size: 12px;
  font-weight: 600;
  color: #b91c1c;
  background: #fff;
  border: 1px solid #fca5a5;
  border-radius: 4px;
  cursor: pointer;
  white-space: nowrap;
}
.dashboard-page .filters .fg-action .btn-clear:hover {
  background: #fef2f2;
  border-color: #f87171;
}

/* Dashboard skeleton loader — mirrors the real layout so the page doesn't
   jump when data arrives. Pure CSS shimmer, no JS. */
.dashboard-page .dash-skeleton .sk-row-label {
  width: 240px;
  height: 11px;
  background: linear-gradient(90deg, #eef2f7 0%, #f7fafc 50%, #eef2f7 100%);
  background-size: 200% 100%;
  animation: skShimmer 1.2s ease-in-out infinite;
  border-radius: 3px;
  margin: 4px 0 8px;
}
.dashboard-page .dash-skeleton .sk-card {
  background: #fff;
  border: 1px solid var(--border);
  border-top: 3px solid #e5e7eb;
  border-radius: 6px;
  padding: 12px 14px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-height: 96px;
}
.dashboard-page .dash-skeleton .sk-bar {
  display: block;
  height: 10px;
  width: 100%;
  background: linear-gradient(90deg, #eef2f7 0%, #f7fafc 50%, #eef2f7 100%);
  background-size: 200% 100%;
  animation: skShimmer 1.2s ease-in-out infinite;
  border-radius: 3px;
}
.dashboard-page .dash-skeleton .sk-bar.sk-bar-sm { width: 50%; }
.dashboard-page .dash-skeleton .sk-bar.sk-bar-md { width: 75%; }
.dashboard-page .dash-skeleton .sk-bar.sk-bar-xl { height: 22px; width: 60%; }
.dashboard-page .dash-skeleton .sk-charts {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 14px;
}
.dashboard-page .dash-skeleton .sk-chart {
  height: 220px;
  background: linear-gradient(90deg, #eef2f7 0%, #f7fafc 50%, #eef2f7 100%);
  background-size: 200% 100%;
  animation: skShimmer 1.2s ease-in-out infinite;
  border-radius: 6px;
  border: 1px solid var(--border);
}
.dashboard-page .dash-skeleton .sk-map {
  height: 340px;
  background: linear-gradient(90deg, #eef2f7 0%, #f7fafc 50%, #eef2f7 100%);
  background-size: 200% 100%;
  animation: skShimmer 1.2s ease-in-out infinite;
  border-radius: 6px;
  border: 1px solid var(--border);
  margin-bottom: 14px;
}
@keyframes skShimmer {
  0%   { background-position: 100% 0; }
  100% { background-position: -100% 0; }
}

.hm-d {
  stroke: #fff;
  stroke-width: 1;
  transition: opacity .2s, stroke .15s;
}
.hm-d:hover {
  opacity: .75;
  stroke: #333;
  stroke-width: 1.5;
}
.hm-label {
  font-size: 7.5px;
  fill: #fff;
  text-anchor: middle;
  pointer-events: none;
  font-weight: 600;
  paint-order: stroke;
  stroke: #0d2137;
  stroke-width: 2.5px;
  stroke-linejoin: round;
}
</style>