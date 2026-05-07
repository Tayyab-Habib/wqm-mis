<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { Chart, registerables } from 'chart.js'
import { api } from '../../services/api.js'
Chart.register(...registerables)

const router = useRouter()

// ── Filter state ──────────────────────────────────────────────────────
const filters = ref({
  client: 'PHE',
  from: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
  to:   new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).toISOString().split('T')[0],
  allTime: false,
  region: '',
  division: '',
  divisionId: '',
  circle: '',
  lab: '',
  labId: '',
  district: '',
  districtId: '',
  phediv: '',
})

// ── Cascading location data ───────────────────────────────────────────
const KP_GEO = {
  Peshawar:  { Peshawar:['PWR-I','PWR-II'], Charsadda:['Charsadda'], Nowshera:['Nowshera'] },
  Mardan:    { Mardan:['Mardan'], Swabi:['Swabi'], Buner:['Buner'] },
  Malakand:  { Malakand:['Malakand'], Swat:['Swat-I','Swat-II'], 'Dir Upper':['Dir Upper'], 'Dir Lower':['Dir Lower'], Chitral:['Chitral'], Bajaur:['Bajaur'], Mohmand:['Mohmand'], Shangla:['Shangla'] },
  Hazara:    { Abbottabad:['Abbottabad'], Haripur:['Haripur'], Mansehra:['Mansehra'], Battagram:['Battagram'], Torghar:['Torghar'] },
  Kohat:     { Kohat:['Kohat'], Hangu:['Hangu'], Orakzai:['Orakzai'], Kurram:['Kurram'] },
  Bannu:     { Bannu:['Bannu'], 'Lakki Marwat':['Lakki Marwat'], Karak:['Karak'], 'N. Waziristan':['N. Waziristan'] },
  'D.I.Khan':{ 'D.I.Khan':['D.I.Khan'], Tank:['Tank'], 'S. Waziristan':['S. Waziristan'] },
}
const allDivisions = Object.keys(KP_GEO)
const availableDistricts = computed(() => filters.value.division ? Object.keys(KP_GEO[filters.value.division] || {}) : [])
const availablePhedivs   = computed(() => {
  if (!filters.value.division || !filters.value.district) return []
  return KP_GEO[filters.value.division]?.[filters.value.district] || []
})
// All districts flat list (used when no division selected)
const allDistrictOptions = computed(() => {
  if (filters.value.division) return Object.keys(KP_GEO[filters.value.division] || {})
  const all = []
  Object.values(KP_GEO).forEach(divObj => all.push(...Object.keys(divObj)))
  return [...new Set(all)].sort()
})
watch(() => filters.value.division, () => { filters.value.district = ''; filters.value.phediv = '' })
watch(() => filters.value.district, () => {
  filters.value.phediv = ''
  const divs = availablePhedivs.value
  if (divs.length === 1) filters.value.phediv = divs[0]
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
watch(hmParam, () => { hmSub.value = ''; updateHeatmap() })
watch(hmSub,   () => { updateHeatmap() })

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

// Sub-param override data (key → districtId → %unfit)
const hmSubData = {
  ecoli:     { chitral:16, upperdir:9, lowerdir:11, swat:22, malakand:7, shangla:14, buner:36, bajaur:42, mohmand:50, charsadda:40, mardan:10, swabi:7, peshawar:30, nowshera:12, khyber:28, kurram:26, kohat:14, orakzai:10, hangu:6, haripur:5, abbottabad:3, mansehra:14, kohistanu:20, kohistanl:18, torghar:16, battagram:55, karak:8, bannu:8, nwaz:20, swaz:8, lakki:10, tank:12, dik:25, attock:4 },
  coliform:  { chitral:18, upperdir:10, lowerdir:12, swat:24, malakand:8, shangla:16, buner:38, bajaur:46, mohmand:54, charsadda:44, mardan:12, swabi:8, peshawar:35, nowshera:13, khyber:30, kurram:28, kohat:16, orakzai:11, hangu:7, haripur:6, abbottabad:4, mansehra:16, kohistanu:22, kohistanl:20, torghar:18, battagram:58, karak:9, bannu:10, nwaz:22, swaz:9, lakki:12, tank:14, dik:28, attock:5 },
  arsenic:   { chitral:4, upperdir:6, lowerdir:5, swat:8, malakand:7, shangla:10, buner:15, bajaur:20, mohmand:28, charsadda:45, mardan:12, swabi:5, peshawar:38, nowshera:9, khyber:25, kurram:22, kohat:30, orakzai:7, hangu:6, haripur:4, abbottabad:3, mansehra:5, kohistanu:7, kohistanl:9, torghar:6, battagram:8, karak:8, bannu:14, nwaz:18, swaz:10, lakki:16, tank:12, dik:35, attock:3 },
  fluoride:  { chitral:2, upperdir:4, lowerdir:3, swat:5, malakand:5, shangla:8, buner:10, bajaur:6, mohmand:8, charsadda:12, mardan:10, swabi:6, peshawar:15, nowshera:4, khyber:12, kurram:16, kohat:18, orakzai:8, hangu:10, haripur:3, abbottabad:2, mansehra:4, kohistanu:5, kohistanl:6, torghar:4, battagram:6, karak:12, bannu:22, nwaz:14, swaz:8, lakki:20, tank:18, dik:28, attock:2 },
  turbidity: { chitral:8, upperdir:7, lowerdir:6, swat:12, malakand:8, shangla:12, buner:16, bajaur:10, mohmand:14, charsadda:22, mardan:9, swabi:6, peshawar:18, nowshera:8, khyber:14, kurram:14, kohat:16, orakzai:7, hangu:8, haripur:5, abbottabad:4, mansehra:7, kohistanu:10, kohistanl:12, torghar:9, battagram:10, karak:10, bannu:14, nwaz:10, swaz:8, lakki:12, tank:16, dik:20, attock:3 },
}

const catScale = { overall:1, microbial:0.6, chemical:0.7, physical:0.3 }

function ragColor(u) {
  if (u > 35) return '#d32f2f'
  if (u > 20) return '#f4a236'
  if (u > 10) return '#7dc97a'
  return '#1a7a3f'
}

// Reactive fill colors per district
const districtColors = ref({})

function updateHeatmap() {
  const cat = hmParam.value
  const sub = hmSub.value.toLowerCase().replace(/[^a-z]/g, '')
  const keyMap = { ecoli:'ecoli', totalcoliform:'coliform', arsenic:'arsenic', fluoride:'fluoride', turbidity:'turbidity' }
  const dataKey = keyMap[sub] || null
  const colors = {}
  Object.entries(districtData).forEach(([id, d]) => {
    let u
    if (dataKey && hmSubData[dataKey]) {
      u = hmSubData[dataKey][id] ?? Math.round(d.unfit * (catScale[cat] || 1))
    } else {
      u = Math.round(d.unfit * (catScale[cat] || 1))
    }
    colors[id] = { color: ragColor(u), unfit: u }
  })
  districtColors.value = colors
}

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

const selectedData = computed(() => {
  if (!selectedDistrict.value) return null
  const d = districtData[selectedDistrict.value]
  if (!d) return null
  const u = districtColors.value[selectedDistrict.value]?.unfit ?? d.unfit
  const rag = u > 35 ? '🔴 High Risk' : u > 20 ? '🟠 Concern' : u > 10 ? '🟡 Moderate' : '🟢 Good'
  const ragClass = u > 35 ? 'r-red' : u > 20 ? 'r-amber' : 'r-green'
  const total = d.fit + d.unfit  // approximate
  const covPct = d.wss > 0 ? Math.round(d.tested / d.wss * 100) : 0
  return { ...d, id: selectedDistrict.value, u, rag, ragClass, total: d.wss * 3, fit: Math.round(d.wss * 3 * d.fit / 100), unfit: Math.round(d.wss * 3 * d.unfit / 100), covPct }
})

const hoveredData = computed(() => {
  if (!hoveredDistrict.value) return null
  const d = districtData[hoveredDistrict.value]
  if (!d) return null
  const u = districtColors.value[hoveredDistrict.value]?.unfit ?? d.unfit
  const rag = u > 35 ? '🔴 High Risk' : u > 20 ? '🟠 Concern' : u > 10 ? '🟡 Moderate' : '🟢 Good'
  return { ...d, u, rag }
})

// ── Real data from backend ────────────────────────────────────────────

const dashLoading = ref(false)
const dashError   = ref('')
const dashData    = ref(null)   // raw response from POST /dashboard

// Dropdown data for filter selects
const dbDivisions    = ref([])
const dbDistricts    = ref([])
const dbLaboratories = ref([])

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
  // Only send type filter if explicitly set (not the default 'PHE' which shows all)
  if (filters.value.client && filters.value.client !== 'ALL') p.type = filters.value.client
  if (filters.value.divisionId)  p.division_id   = filters.value.divisionId
  if (filters.value.districtId)  p.district_id   = filters.value.districtId
  if (filters.value.labId)       p.laboratory_id  = filters.value.labId
  if (!filters.value.allTime && filters.value.from && filters.value.to) {
    // Ensure start < end (backend requires before/after)
    if (filters.value.from < filters.value.to) {
      p.duration    = 'month'   // DurationEnum::MONTH = 'month' (lowercase)
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
  try {
    // Fire all available endpoints in parallel
    const [dashRes, diaryRes, dispatchRes, assetRes, maintRes] = await Promise.allSettled([
      api.post('/dashboard', buildPayload()),
      api.get('/diary-dispatch/diary/registers'),
      api.get('/diary-dispatch/dispatch/registers'),
      api.get('/laboratory/assets/all'),          // no role restriction
      api.get('/asset-maintenance-schedules'),
    ])

    // ── Main dashboard data ─────────────────────────────────────────
    if (dashRes.status === 'fulfilled') {
      dashData.value = dashRes.value.data || dashRes.value
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
        // WSS
        totalWss:          d.total_water_schemes ?? '—',
        operationalWss:    opMap['Operational']     ?? '—',
        nonOperationalWss: opMap['Non-Operational'] ?? '—',
        abandonedWss:      opMap['Abandoned']       ?? '—',
        wipWss:            opMap['Work in progress'] ?? '—',
        // Labs & compliance
        totalLabs:         d.total_laboratories ?? '—',
        totalComplaints:   d.total_complaints?.datasets?.[0]?.data?.[0] ?? '—',
        pendingComplaints: d.total_complaints?.datasets?.[0]?.data?.[1] ?? '—',
        totalIssues:       d.total_issues?.datasets?.[0]?.data?.[0] ?? '—',
        pendingInventory:  d.total_pending_inventory_requests ?? '—',
        revenue:           rev.series?.[0]?.data?.reduce((a, b) => a + b, 0)?.toLocaleString() ?? '—',
        // Row 4 — filled below from separate calls
        diaryCount:    '—',
        dispatchCount: '—',
        assetCount:    '—',
        calibDue:      '—',
      }

      await nextTick()
      rebuildCH01(d.laboratories_water_sample_results)
      rebuildCH02(d.districts_water_sample_results)
      updateHeatmapFromApi(d.districts_water_sample_results)
    } else {
      dashError.value = dashRes.reason?.response?.data?.message || dashRes.reason?.message || 'Failed to load dashboard'
      console.error('Dashboard error:', dashRes.reason?.response?.data || dashRes.reason)
    }

    // ── Row 4 — Diary count ─────────────────────────────────────────
    if (diaryRes.status === 'fulfilled') {
      const d = diaryRes.value.data
      stats.value.diaryCount = Array.isArray(d) ? d.length : (d?.length ?? 0)
    }

    // ── Row 4 — Dispatch count ──────────────────────────────────────
    if (dispatchRes.status === 'fulfilled') {
      const d = dispatchRes.value.data
      stats.value.dispatchCount = Array.isArray(d) ? d.length : (d?.length ?? 0)
    }

    // ── Row 4 — Equipment (lab assets) count ───────────────────────
    if (assetRes.status === 'fulfilled') {
      const d = assetRes.value?.data
      stats.value.assetCount = Array.isArray(d) ? d.length : 0
    }

    // ── Row 4 — Calibration due (maintenance schedules ≤30 days) ───
    if (maintRes.status === 'fulfilled') {
      const d = maintRes.value?.data
      const schedules = Array.isArray(d) ? d : []
      const today = new Date()
      const in30  = new Date(); in30.setDate(today.getDate() + 30)
      // field is 'scheduled_at' from AssetMaintenanceScheduleLog
      stats.value.calibDue = schedules.filter(s => {
        const dt = new Date(s.scheduled_at || s.next_maintenance_date || s.date)
        return !isNaN(dt) && dt >= today && dt <= in30
      }).length
    }

  } catch (e) {
    dashError.value = e.response?.data?.message || e.message || 'Failed to load dashboard'
    console.error('Dashboard error:', e.response?.data || e)
  } finally {
    dashLoading.value = false
  }
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
  const gap = tested.map((t, i) => Math.max(0, t - (fit[i]||0) - (unfit[i]||0)))

  ch01Instance = new Chart(ch01Ref.value, {
    type: 'bar',
    data: {
      labels: labs,
      datasets: [
        { label:'Fit',           data:fit,   backgroundColor:'rgba(76,175,80,0.90)',   borderColor:'#388e3c', borderWidth:1, stack:'s' },
        { label:'Unfit',         data:unfit, backgroundColor:'rgba(229,57,53,0.88)',   borderColor:'#c62828', borderWidth:1, stack:'s' },
        { label:'Gap to Target', data:gap,   backgroundColor:'rgba(200,220,255,0.45)', borderColor:'rgba(21,101,192,0.25)', borderWidth:1, stack:'s' },
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

  ch02Instance = new Chart(ch02Ref.value, {
    type: 'bar',
    data: {
      labels: districts,
      datasets: [
        { label:'Fit',   data:fit,   backgroundColor:'rgba(76,175,80,0.90)',  borderColor:'#388e3c', borderWidth:1, stack:'s' },
        { label:'Unfit', data:unfit, backgroundColor:'rgba(229,57,53,0.88)',  borderColor:'#c62828', borderWidth:1, stack:'s' },
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

// Update heatmap colors from district API data
function updateHeatmapFromApi(districtResults) {
  if (!districtResults?.labels) return
  const labels  = Object.values(districtResults.labels)   // district names
  const ds      = districtResults.datasets || []
  const fitArr  = ds.find(d => d.label?.toLowerCase().includes('fit') && !d.label?.toLowerCase().includes('unfit'))?.data || []
  const unfitArr= ds.find(d => d.label?.toLowerCase().includes('unfit'))?.data || []

  // Build a name→unfit% map
  const nameMap = {}
  labels.forEach((name, i) => {
    const total = (fitArr[i] || 0) + (unfitArr[i] || 0)
    nameMap[name.toLowerCase()] = total > 0 ? Math.round((unfitArr[i] / total) * 100) : null
  })

  // Match to districtData keys by name
  const colors = {}
  Object.entries(districtData).forEach(([id, d]) => {
    const key = d.name.toLowerCase()
    const u   = nameMap[key] ?? Math.round(d.unfit)
    colors[id] = { color: ragColor(u), unfit: u }
  })
  districtColors.value = colors
}

// Load filter dropdowns from backend
async function loadFilterDropdowns() {
  try {
    const [divRes, distRes, labRes] = await Promise.all([
      api.get('/all-divisions'),
      api.get('/all-districts'),
      api.get('/all-laboratories'),
    ])
    dbDivisions.value    = divRes.data  || []
    dbDistricts.value    = distRes.data || []
    dbLaboratories.value = labRes.data  || []
  } catch (e) {
    console.warn('Filter dropdown load failed:', e.message)
  }
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
const kpiLabs = ['Peshawar', 'Kohat', 'Lakki/Bannu', 'D.I.Khan', 'Mardan', 'Malakand', 'Swat', 'Abbottabad']

const kpiRows = [
  { id:'KPI-001', name:'Inter-lab Comparison Success Rate',
    values:{ Peshawar:97, Kohat:98, 'Lakki/Bannu':93, 'D.I.Khan':95, Mardan:95, Malakand:94, Swat:92, Abbottabad:100 } },
  { id:'KPI-002', name:'Equipment Calibration Compliance',
    values:{ Peshawar:94, Kohat:96, 'Lakki/Bannu':88, 'D.I.Khan':91, Mardan:100, Malakand:90, Swat:87, Abbottabad:100 } },
  { id:'KPI-003', name:'Retesting of Unfit Samples',
    values:{ Peshawar:88, Kohat:85, 'Lakki/Bannu':78, 'D.I.Khan':82, Mardan:91, Malakand:83, Swat:80, Abbottabad:90 } },
  { id:'KPI-004', name:'Monthly Sampling Coverage',
    values:{ Peshawar:87, Kohat:89, 'Lakki/Bannu':82, 'D.I.Khan':85, Mardan:93, Malakand:88, Swat:84, Abbottabad:96 } },
  { id:'KPI-005', name:'TAT — Analysis',
    values:{ Peshawar:96, Kohat:95, 'Lakki/Bannu':94, 'D.I.Khan':93, Mardan:98, Malakand:92, Swat:91, Abbottabad:99 } },
  { id:'KPI-006', name:'Data Entry Timeliness',
    values:{ Peshawar:99, Kohat:100, 'Lakki/Bannu':97, 'D.I.Khan':98, Mardan:98, Malakand:97, Swat:96, Abbottabad:98 } },
  { id:'KPI-007', name:'Staff Training Compliance',
    values:{ Peshawar:82, Kohat:88, 'Lakki/Bannu':80, 'D.I.Khan':79, Mardan:90, Malakand:81, Swat:78, Abbottabad:95 } },
  { id:'KPI-008', name:'SOP Standard Compliance',
    values:{ Peshawar:95, Kohat:91, 'Lakki/Bannu':89, 'D.I.Khan':87, Mardan:97, Malakand:90, Swat:88, Abbottabad:100 } },
  { id:'KPI-009', name:'Data Verification Compliance',
    values:{ Peshawar:92, Kohat:90, 'Lakki/Bannu':87, 'D.I.Khan':85, Mardan:95, Malakand:88, Swat:86, Abbottabad:98 } },
]

function kpiCellStyle(val) {
  if (val >= 90) return 'background:#d1fae5;color:#065f46;border:1px solid #6ee7b7'
  if (val >= 75) return 'background:#fef9c3;color:#713f12;border:1px solid #fde047'
  return 'background:#fee2e2;color:#991b1b;border:1px solid #fca5a5'
}

function exportKpiCsv() {
  const header = ['KPI ID', 'KPI Name', ...kpiLabs].join(',')
  const rows = kpiRows.map(r =>
    [r.id, `"${r.name}"`, ...kpiLabs.map(l => r.values[l] !== undefined ? r.values[l] + '%' : '—')].join(',')
  )
  const csv = [header, ...rows].join('\n')
  const a = document.createElement('a')
  a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv)
  a.download = 'kpi-performance-labs.csv'
  a.click()
}
</script>

<template>
  <div>
    <!-- Loading / Error bar -->
    <div v-if="dashLoading" style="background:var(--sky2);border:1px solid var(--sky);border-radius:5px;padding:8px 14px;margin-bottom:10px;font-size:12px;color:var(--navy2)">
      ⏳ Loading dashboard data…
    </div>
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
      <div class="sep"></div>
      <div class="fg">
        <label>From</label>
        <input type="date" v-model="filters.from">
      </div>
      <div class="fg">
        <label>To</label>
        <input type="date" v-model="filters.to">
      </div>
      <div class="fg" style="flex-direction:row;align-items:center;gap:4px;margin-top:14px">
        <input type="checkbox" id="alltime" v-model="filters.allTime">
        <label for="alltime" style="font-size:11.5px;margin-top:0">All Time</label>
      </div>
      <div class="sep"></div>
      <div class="fg">
        <label>Region (CE)</label>
        <select v-model="filters.region">
          <option value="">All CE Zones</option>
          <option value="CE Center">CE Center</option>
          <option value="CE North">CE North</option>
          <option value="CE South">CE South</option>
          <option value="CE East">CE East</option>
        </select>
      </div>
      <div class="fg">
        <label>Division</label>
        <select v-model="filters.divisionId" @change="d => { const found = dbDivisions.find(x=>x.id==filters.divisionId); filters.division=found?.name||''; filters.districtId=''; filters.district='' }">
          <option value="">All Divisions</option>
          <option v-for="d in dbDivisions" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
      </div>
      <div class="fg">
        <label>PHE Circle</label>
        <select v-model="filters.circle">
          <option value="">All</option>
          <option value="Swat-I">Swat-I</option>
          <option value="Swat-II">Swat-II</option>
          <option value="Peshawar">Peshawar</option>
          <option value="Mardan">Mardan</option>
          <option value="Hazara">Hazara</option>
          <option value="Kohat">Kohat</option>
          <option value="Bannu">Bannu</option>
          <option value="D.I.Khan">D.I.Khan</option>
        </select>
      </div>
      <div class="fg">
        <label>Lab</label>
        <select v-model="filters.labId" @change="() => { const found = dbLaboratories.find(x=>x.id==filters.labId); filters.lab=found?.name||'' }">
          <option value="">All Labs</option>
          <option v-for="l in dbLaboratories" :key="l.id" :value="l.id">{{ l.name }}</option>
        </select>
      </div>
      <!-- Second row: District + PHE Division -->
      <div style="flex-basis:100%;display:flex;gap:6px;flex-wrap:wrap;padding-top:4px">
        <div class="fg">
          <label>District</label>
          <select v-model="filters.districtId" @change="() => { const found = dbDistricts.find(x=>x.id==filters.districtId); filters.district=found?.name||'' }">
            <option value="">All Districts</option>
            <option v-for="d in (filters.divisionId ? dbDistricts.filter(x=>x.division_id==filters.divisionId) : dbDistricts)" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>
        </div>
        <div class="fg">
          <label>PHE Division</label>
          <select v-model="filters.phediv">
            <option value="">All PHE Divisions</option>
            <option v-for="p in availablePhedivs" :key="p" :value="p">{{ p }}</option>
          </select>
        </div>
      </div>
    </div>

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
        <div class="c-nav">→ Complaints</div>
      </div>
      <div class="card c-red clickable">
        <div class="c-lbl">Issues</div>
        <div class="c-val">{{ stats.totalIssues }}</div>
        <div class="c-sub">Total issues logged</div>
        <div class="c-nav">→ Issues</div>
      </div>
      <div class="card c-amber clickable" @click="router.push('/admin/kpi-framework')">
        <div class="c-lbl">Pending Inventory</div>
        <div class="c-val">{{ stats.pendingInventory }}</div>
        <div class="c-sub">Pending requests</div>
        <div class="c-nav">→ Inventory</div>
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
          <button class="btn btn-sec btn-xs">⬇ PNG</button>
        </div>
        <div style="position:relative;height:210px">
          <canvas ref="ch01Ref"></canvas>
        </div>
      </div>
      <div class="chart-box">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
          <h3 style="margin-bottom:0">District-wise WQ Analysis — March 2026 <span style="font-size:11px;font-weight:400;color:var(--muted)">(CH-02)</span></h3>
          <button class="btn btn-sec btn-xs">⬇ PNG</button>
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
          <button class="btn btn-sec btn-xs">⬇ PNG</button>
        </div>
      </div>

      <!-- Floating tooltip -->
      <div v-if="hoveredData" style="position:fixed;background:rgba(13,33,55,.93);color:#fff;border-radius:6px;padding:8px 12px;font-size:11.5px;pointer-events:none;z-index:9999;line-height:1.6;max-width:200px"
           :style="tooltipStyle">
        <b>{{ hoveredData.name }}</b><br>
        ✅ Fit: {{ hoveredData.fit }}% &nbsp; ❌ Unfit: {{ hoveredData.u }}%<br>
        <span style="opacity:.8">{{ hoveredData.rag }}</span>
      </div>

      <!-- SVG Map -->
      <div style="background:#d6eaf8;border-radius:6px;border:1px solid var(--border);overflow:hidden;position:relative">
        <svg viewBox="0 0 620 375" style="width:100%;display:block;cursor:default">
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
          KP Province · District Heatmap · Dummy Data
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
            {l:'WSS Tested', v: selectedData.tested + ' (' + selectedData.covPct + '%)'},
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
              <th v-for="lab in kpiLabs" :key="lab" style="padding:7px 8px;text-align:center;font-weight:600;white-space:nowrap;min-width:72px">
                <span v-if="lab === 'Lakki/Bannu'">Lakki/<br>Bannu</span>
                <span v-else>{{ lab }}</span>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(kpi, i) in kpiRows" :key="kpi.id"
                :style="i % 2 === 0 ? 'background:#fff' : 'background:#f8fafc'">
              <td style="padding:6px 10px;font-family:'DM Mono',monospace;font-size:11px;color:var(--muted);white-space:nowrap">{{ kpi.id }}</td>
              <td style="padding:6px 10px;font-weight:500">{{ kpi.name }}</td>
              <td v-for="lab in kpiLabs" :key="lab" style="padding:6px 8px;text-align:center">
                <span v-if="kpi.values[lab] !== undefined"
                  style="display:inline-block;padding:2px 8px;border-radius:4px;font-weight:700;font-size:11.5px;min-width:44px"
                  :style="kpiCellStyle(kpi.values[lab])">
                  {{ kpi.values[lab] }}%
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
  </div>
</template>

<style>
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