<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { waterSchemeService } from '../../services/waterSchemeService.js'
import { api } from '../../services/api.js'
import { useUserStore } from '../../stores/useUserStore.js'
import { exportToExcel, exportToXLSX } from '../../utils/exportHelpers.js'

const userStore = useUserStore()

const loading  = ref(false)
const errorMsg = ref('')
const wssData  = ref([])

// ── Toast notification ────────────────────────────────────────────────
const toast = ref({ show: false, message: '', type: 'success' })
let toastTimer = null

function showToast(message, type = 'success') {
  clearTimeout(toastTimer)
  toast.value = { show: true, message, type }
  toastTimer = setTimeout(() => { toast.value.show = false }, 4000)
}

// ── Filter dropdown data ──────────────────────────────────────────────
const allRegions   = ref([])
const allDivisions = ref([])
const allCircles   = ref([])
const allDistricts = ref([])
const allPhedDivs  = ref([])

const regionFilter   = ref('')
const divisionFilter = ref('')
const circleFilter   = ref('')
const districtFilter = ref('')
const phedDivFilter  = ref('')

async function loadFilterDropdowns() {
  try {
    const [regRes, divRes, cirRes, distRes, phdRes] = await Promise.all([
      api.get('/regions'),
      api.get('/all-divisions'),
      api.get('/circles'),
      api.get('/all-districts'),
      api.get('/phed-divisions'),
    ])
    allRegions.value   = regRes.data  || []
    allDivisions.value = divRes.data  || []
    allCircles.value   = cirRes.data  || []
    allDistricts.value = distRes.data || []
    allPhedDivs.value  = phdRes.data  || []
  } catch (e) {
    console.error('Filter dropdown load error:', e)
  }
}

// Cascade: when region changes, reset division/circle/district/phed
function onRegionChange() {
  divisionFilter.value = ''
  circleFilter.value   = ''
  districtFilter.value = ''
  phedDivFilter.value  = ''
}
function onDivisionChange() {
  districtFilter.value = ''
  phedDivFilter.value  = ''
}
function onDistrictChange() {
  phedDivFilter.value  = ''
}

// Filtered dropdown options (cascade)
const filteredDivisions = computed(() =>
  regionFilter.value
    ? allDivisions.value.filter(d => d.region_id == regionFilter.value)
    : allDivisions.value
)
const filteredCircles = computed(() =>
  regionFilter.value
    ? allCircles.value.filter(c => c.region_id == regionFilter.value)
    : allCircles.value
)
const filteredDistricts = computed(() =>
  divisionFilter.value
    ? allDistricts.value.filter(d => d.division_id == divisionFilter.value)
    : allDistricts.value
)
const filteredPhedDivs = computed(() =>
  districtFilter.value
    ? allPhedDivs.value.filter(p => p.district_id == districtFilter.value)
    : allPhedDivs.value
)

// Map backend fields to display format
function mapWss(w) {
  return {
    id: w.id,
    code: w.slug || w.code || `WSS-${w.id}`,
    name: w.name || '—',
    div: w.phedDivision?.name || w.division?.name || '—',
    district: w.district?.name || '—',
    source: w.source_type || '—',
    solar: w.power_input === 'Solar',
    opStatus: w.operation || 'Operational',
    tested: w.water_samples_count || 0,
    lastWQ: w.last_sample_result || 'Untested',
    lastSampled: w.last_sampled_at ? w.last_sampled_at.split(' ')[0] : '—',
    nextScheduled: w.next_scheduled_at ? w.next_scheduled_at.split(' ')[0] : '—',
    schedStatus: w.next_scheduled_at ? 'scheduled' : (w.last_sampled_at ? 'overdue' : 'none'),
    latitude: w.latitude,
    longitude: w.longitude,
    // IDs for cascade filtering — derive circle/region from the loaded
    // district→circle relationship since water_schemes has no direct
    // circle_id or region_id column.
    regionId:      w.division?.region_id || w.district?.circle?.region_id || null,
    divisionId:    w.division_id  || null,
    circleId:      w.district?.circle_id || w.district?.circle?.id || w.circle_id || null,
    districtId:    w.district_id  || null,
    phedDivisionId: w.phed_division_id || null,
  }
}

async function loadWss() {
  loading.value = true
  errorMsg.value = ''
  try {
    // Use GET /water-schemes (index) — returns full data with slug, division, district, source_type etc.
    const res = await api.get('/water-schemes')
    const data = res.data?.data || res.data || []
    wssData.value = Array.isArray(data) ? data.map(mapWss) : []
  } catch (e) {
    errorMsg.value = 'Failed to load water schemes'
    console.error('WSS load error:', e)
  } finally {
    loading.value = false
  }
}

const searchText  = ref('')
const wqFilter    = ref('')
const schedFilter = ref('')

function clearFilters() {
  searchText.value     = ''
  regionFilter.value   = ''
  divisionFilter.value = ''
  circleFilter.value   = ''
  districtFilter.value = ''
  phedDivFilter.value  = ''
  wqFilter.value       = ''
  schedFilter.value    = ''
}

const hasActiveFilters = computed(() =>
  !!(searchText.value || regionFilter.value || divisionFilter.value || circleFilter.value
     || districtFilter.value || phedDivFilter.value || wqFilter.value || schedFilter.value)
)

const filtered = computed(() => wssData.value.filter(w => {
  const matchSearch    = !searchText.value || w.name.toLowerCase().includes(searchText.value.toLowerCase()) || w.code.toLowerCase().includes(searchText.value.toLowerCase()) || w.district.toLowerCase().includes(searchText.value.toLowerCase())
  const matchWQ        = !wqFilter.value    || w.lastWQ === wqFilter.value
  const matchSched     = !schedFilter.value || w.schedStatus === schedFilter.value
  const matchRegion    = !regionFilter.value   || w.regionId == regionFilter.value
  const matchDivision  = !divisionFilter.value || w.divisionId == divisionFilter.value
  const matchCircle    = !circleFilter.value   || w.circleId == circleFilter.value
  const matchDistrict  = !districtFilter.value || w.districtId == districtFilter.value
  const matchPhedDiv   = !phedDivFilter.value  || w.phedDivisionId == phedDivFilter.value
  return matchSearch && matchWQ && matchSched && matchRegion && matchDivision && matchCircle && matchDistrict && matchPhedDiv
}))

// ── Schedule modal ────────────────────────────────────────────────────
const showSchedModal = ref(false)
const schedTarget    = ref(null)
const schedDate      = ref('')
const schedNote      = ref('')
const schedSaving    = ref(false)

function openSchedule(wss) {
  schedTarget.value = wss
  schedDate.value   = ''
  schedNote.value   = ''
  showSchedModal.value = true
}

async function saveSchedule() {
  if (!schedDate.value) { showToast('⚠️ Please select a sampling date.', 'error'); return }
  schedSaving.value = true
  try {
    await waterSchemeService.createSchedule({
      water_scheme_id: schedTarget.value.id,
      scheduled_at: schedDate.value + ' 09:00:00',
      note: schedNote.value,
    })
    // Update local state
    const wss = wssData.value.find(w => w.id === schedTarget.value.id)
    if (wss) {
      wss.nextScheduled = schedDate.value
      wss.schedStatus   = 'scheduled'
    }
    showSchedModal.value = false
    showToast(`✅ Schedule saved for ${schedTarget.value?.name || 'WSS'} — ${schedDate.value}`, 'success')
  } catch (e) {
    showToast('❌ Failed to save schedule: ' + (e.response?.data?.message || e.message), 'error')
    console.error('Schedule save error:', e)
  } finally {
    schedSaving.value = false
  }
}

// ── Trail modal ───────────────────────────────────────────────────────
const showTrailModal  = ref(false)
const trailTarget     = ref(null)
const trailData       = ref([])
const trailLoading    = ref(false)

async function openTrail(wss) {
  trailTarget.value = wss
  trailData.value   = []
  showTrailModal.value = true
  trailLoading.value = true
  try {
    const res = await waterSchemeService.getSamples(wss.id)
    const samples = res.data?.data || res.data || []
    trailData.value = samples.map(s => ({
      date: s.sampled_at ? s.sampled_at.split(' ')[0] : '—',
      id: s.slug || s.id,
      result: s.analysis_result || s.current_status || '—',
      cause: s.analysis_result_cause || '—',
      ion: s.analysis_result_detail || '—',
    }))
  } catch (e) {
    console.error('Trail load error:', e)
  } finally {
    trailLoading.value = false
  }
}

function wqClass(wq) {
  return wq === 'Fit' ? 'r-green' : wq === 'Unfit' ? 'r-red' : 'r-grey'
}

function exportWss() {
  if (!filtered.value.length) {
    showToast('⚠️ No data to export.', 'error')
    return
  }
  
  const exportData = filtered.value.map(w => ({
    'WSS Code': w.code,
    'WSS Name': w.name,
    'PHE Division': w.div,
    'District': w.district,
    'Source Type': w.source,
    'Solar': w.solar ? 'Solar' : 'Non-Solar',
    'Operational Status': w.opStatus,
    'Total Tested': w.tested,
    'Last WQ Result': w.lastWQ,
    'Last Sampled': w.lastSampled,
    'Next Scheduled': w.nextScheduled,
    'Schedule Status': w.schedStatus,
    'Latitude': w.latitude || '—',
    'Longitude': w.longitude || '—'
  }))
  
  exportToXLSX(exportData, 'wss_water_scheme_detail')
}

onMounted(() => {
  loadWss()
  loadFilterDropdowns()
})

onUnmounted(() => {
  clearTimeout(toastTimer)
})

// ── Add WSS Modal ─────────────────────────────────────────────────────
const showAddModal   = ref(false)
const addLoading     = ref(false)
const addErrors      = ref({})

// Stepper config for the Add WSS wizard
const wzStep = ref(1)
const wzSteps = [
  { n: 1, icon: '📋', title: 'Basic Info',     subtitle: 'Name & address' },
  { n: 2, icon: '📍', title: 'Location',       subtitle: 'Administrative area' },
  { n: 3, icon: '🗺️', title: 'GPS',            subtitle: 'Map coordinates' },
  { n: 4, icon: '⚙️', title: 'Technical',      subtitle: 'Source, power & specs' },
]
const wzStepFields = {
  1: ['name','address'],
  2: ['division_id','district_id','tehsil_id','union_council_id'],
  3: ['latitude','longitude'],
  4: ['source_type','operation','power_input','chamber','years_of_installation','mode','type_of_machine','pipe_type','horse_power_motor','capacity','depth','storage','population','remarks'],
}
function wzStepHasErrors(n) {
  return wzStepFields[n].some(f => addErrors.value[f])
}
function wzGoTo(n) { if (n >= 1 && n <= wzSteps.length) wzStep.value = n }
function wzNext()  { if (wzStep.value < wzSteps.length) wzStep.value++ }
function wzPrev()  { if (wzStep.value > 1) wzStep.value-- }

// Locality data for the form
const allProvinces      = ref([])
const allTehsils        = ref([])
const allUnionCouncils  = ref([])
const formDistricts     = ref([])
const formTehsils       = ref([])
const formUnionCouncils = ref([])

// WSS-specific dropdowns (chambers, operations, source_types)
const wssDropdowns = ref({ chambers: {}, operations: {}, source_types: {} })

const powerInputs = [
  { name: 'Solar',  value: 'Solar'  },
  { name: 'WAPDA',  value: 'Wapda'  },
]

function enumToOptions(obj) {
  return Object.entries(obj).map(([key, value]) => ({ id: value, name: key }))
}

const chamberOptions    = computed(() => enumToOptions(wssDropdowns.value.chambers    || {}))
const operationOptions  = computed(() => enumToOptions(wssDropdowns.value.operations  || {}))
const sourceTypeOptions = computed(() => enumToOptions(wssDropdowns.value.source_types || {}))

// Map coordinates from district when district changes
const mapCoords = ref({ lat: 30.3753, lng: 69.3451 }) // default: Pakistan center

function emptyForm() {
  const user = userStore.currentUser
  return {
    name: '', address: '',
    province_id: 1,
    division_id: user?.division_id ? parseInt(user.division_id) : '',
    district_id: user?.district_id ? parseInt(user.district_id) : '',
    tehsil_id: '', union_council_id: '',
    latitude: '', longitude: '',
    source_type: '', years_of_installation: '',
    mode: '', operation: '', type_of_machine: '',
    horse_power_motor: '', storage: '', capacity: '',
    depth: '', chamber: '', pipe_type: '',
    power_input: '', population: '', remarks: '',
  }
}

const addForm = ref(emptyForm())

async function openAddModal() {
  addForm.value  = emptyForm()
  addErrors.value = {}
  wzStep.value   = 1
  showAddModal.value = true

  // Load locality + wss dropdowns if not yet loaded
  if (!allProvinces.value.length) {
    try {
      const [locRes, wssRes] = await Promise.all([
        api.get('/locality'),
        api.get('/water-schemes-dropdowns'),
      ])
      const loc = locRes.data?.data || locRes.data || {}
      allProvinces.value     = loc.provinces     || []
      // allDivisions already loaded by filter, reuse
      const distArr          = loc.districts      || []
      allTehsils.value       = loc.tehsils        || []
      allUnionCouncils.value = loc.union_councils || []

      // Seed allDistricts if not yet loaded
      if (!allDistricts.value.length) allDistricts.value = distArr

      wssDropdowns.value = wssRes.data?.data || wssRes.data || {}
    } catch (e) {
      console.error('Add WSS dropdown load error:', e)
    }
  }

  // Pre-cascade from user's division/district
  const user = userStore.currentUser
  if (user?.division_id) onFormDivisionChange(user.division_id)
  if (user?.district_id) onFormDistrictChange(user.district_id)
}

function onFormDivisionChange(divId) {
  addForm.value.district_id      = ''
  addForm.value.tehsil_id        = ''
  addForm.value.union_council_id = ''
  formDistricts.value = allDistricts.value.filter(d => d.division_id == divId)
}

function onFormDistrictChange(distId) {
  addForm.value.tehsil_id        = ''
  addForm.value.union_council_id = ''
  formTehsils.value = allTehsils.value.filter(t => t.district_id == distId)
  // Auto-fill coordinates from district
  const dist = allDistricts.value.find(d => d.id == distId)
  if (dist?.latitude && dist?.longitude) {
    addForm.value.latitude  = dist.latitude
    addForm.value.longitude = dist.longitude
    mapCoords.value = { lat: parseFloat(dist.latitude), lng: parseFloat(dist.longitude) }
  }
}

function onFormTehsilChange(tehsilId) {
  addForm.value.union_council_id = ''
  formUnionCouncils.value = allUnionCouncils.value.filter(u => u.tehsil_id == tehsilId)
}

function onLatLngInput() {
  const lat = parseFloat(addForm.value.latitude)
  const lng = parseFloat(addForm.value.longitude)
  if (!isNaN(lat) && !isNaN(lng)) {
    mapCoords.value = { lat, lng }
  }
}

async function submitAddWss() {
  addErrors.value  = {}
  addLoading.value = true
  try {
    // These fields are validated as 'string' on the backend — cast from number inputs
    const stringFields = ['horse_power_motor', 'storage', 'capacity', 'depth', 'population', 'chamber']
    const payload = { ...addForm.value }
    stringFields.forEach(f => {
      if (payload[f] !== '' && payload[f] !== null && payload[f] !== undefined) {
        payload[f] = String(payload[f])
      }
    })
    const res = await waterSchemeService.create(payload)
    const created = res.data?.data || res.data || {}
    const wssCode = created.slug || created.id || ''
    const wssName = created.name || addForm.value.name

    // Close modal first
    showAddModal.value = false
    addForm.value = emptyForm()
    formDistricts.value     = []
    formTehsils.value       = []
    formUnionCouncils.value = []

    // Show success toast
    showToast(`✅ Water Scheme "${wssName}" created successfully! Code: ${wssCode}`)

    // Reload the table
    await loadWss()
  } catch (e) {
    addErrors.value = e.response?.data?.errors || {}
    if (!Object.keys(addErrors.value).length) {
      addErrors.value._general = [e.response?.data?.message || e.message || 'Failed to create water scheme']
    }
    // Jump to first step that has a validation error
    for (const s of wzSteps) {
      if (wzStepHasErrors(s.n)) { wzStep.value = s.n; break }
    }
  } finally {
    addLoading.value = false
  }
}

function closeAddModal() {
  showAddModal.value = false
  addErrors.value    = {}
}
</script>

<template>
  <div>
    <div class="abar blue">💧 WSS Register — Testing Trail &amp; Sampling Schedule</div>

    <!-- ── Toast notification ── -->
    <Teleport to="body">
      <Transition name="toast-slide">
        <div v-if="toast.show"
             :style="`position:fixed;top:22px;right:24px;z-index:9999;min-width:300px;max-width:460px;
                      background:${toast.type === 'success' ? '#065f46' : '#991b1b'};
                      color:#fff;border-radius:8px;padding:14px 18px;
                      box-shadow:0 6px 32px rgba(0,0,0,.28);font-size:13px;display:flex;align-items:flex-start;gap:10px`">
          <span style="font-size:18px;line-height:1">{{ toast.type === 'success' ? '✅' : '❌' }}</span>
          <span style="flex:1;line-height:1.5">{{ toast.message }}</span>
          <button @click="toast.show = false"
                  style="background:rgba(255,255,255,.2);border:none;color:#fff;border-radius:4px;
                         padding:2px 8px;cursor:pointer;font-size:13px;margin-left:4px">✕</button>
        </div>
      </Transition>
    </Teleport>

    <!-- Toolbar -->
    <div class="toolbar" style="flex-wrap:wrap;gap:6px">
      <input type="text" v-model="searchText" placeholder="🔍 WSS name, code, district…" style="min-width:160px">

      <select v-model="regionFilter" @change="onRegionChange" style="border:1px solid var(--input-border);border-radius:4px;padding:6px 8px;font-size:12px;font-family:inherit">
        <option value="">All CE Regions</option>
        <option v-for="r in allRegions" :key="r.id" :value="r.id">{{ r.name }}</option>
      </select>

      <select v-model="divisionFilter" @change="onDivisionChange" style="border:1px solid var(--input-border);border-radius:4px;padding:6px 8px;font-size:12px;font-family:inherit">
        <option value="">All Divisions</option>
        <option v-for="d in filteredDivisions" :key="d.id" :value="d.id">{{ d.name }}</option>
      </select>

      <select v-model="circleFilter" style="border:1px solid var(--input-border);border-radius:4px;padding:6px 8px;font-size:12px;font-family:inherit">
        <option value="">All Circles</option>
        <option v-for="c in filteredCircles" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>

      <select v-model="districtFilter" @change="onDistrictChange" style="border:1px solid var(--input-border);border-radius:4px;padding:6px 8px;font-size:12px;font-family:inherit">
        <option value="">All Districts</option>
        <option v-for="d in filteredDistricts" :key="d.id" :value="d.id">{{ d.name }}</option>
      </select>

      <select v-model="phedDivFilter" style="border:1px solid var(--input-border);border-radius:4px;padding:6px 8px;font-size:12px;font-family:inherit">
        <option value="">All PHE Divisions</option>
        <option v-for="p in filteredPhedDivs" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>

      <select v-model="wqFilter" style="border:1px solid var(--input-border);border-radius:4px;padding:6px 8px;font-size:12px;font-family:inherit">
        <option value="">All WQ Results</option>
        <option value="Fit">Fit</option>
        <option value="Unfit">Unfit</option>
        <option value="Untested">Untested</option>
      </select>

      <select v-model="schedFilter" style="border:1px solid var(--input-border);border-radius:4px;padding:6px 8px;font-size:12px;font-family:inherit">
        <option value="">All Schedule Status</option>
        <option value="overdue">Overdue / Unscheduled</option>
        <option value="scheduled">Scheduled</option>
        <option value="none">Not Yet Scheduled</option>
      </select>

      <button class="btn btn-sec btn-sm" :disabled="!hasActiveFilters" @click="clearFilters"
              :title="hasActiveFilters ? 'Reset all filters' : 'No filters active'">
        ✕ Clear Filters
      </button>

      <div class="tsp"></div>
      <button class="btn btn-sec btn-sm" @click="exportWss">⬇ Export</button>
      <button v-write="'add_water_schemes'" class="btn btn-pri btn-sm" @click="openAddModal">+ Add WSS</button>
    </div>

    <!-- Row 2 removed — Add WSS is now in the toolbar -->

    <!-- Table -->
    <div class="tbl-wrap">
      <!-- Skeleton loading state — 6 placeholder rows -->
      <table v-if="loading" style="font-size:11.5px" class="wss-skel">
        <thead>
          <tr>
            <th>WSS Code</th><th>WSS Name</th><th>PHE Div.</th><th>Source Type</th><th>Solar?</th>
            <th>Op. Status</th><th style="text-align:center">Tested</th><th style="text-align:center">Last WQ</th>
            <th>Last Sampled</th><th>Next Scheduled</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="i in 6" :key="`skel-${i}`" :class="i%2===0?'alt':''">
            <td v-for="j in 11" :key="`skel-${i}-${j}`"><span class="skel-bar" :style="`width:${50+((i*j*7)%50)}%`"></span></td>
          </tr>
        </tbody>
      </table>
      <!-- Error state -->
      <div v-else-if="errorMsg" style="text-align:center;padding:24px;color:var(--red);font-size:13px">
        ⚠ {{ errorMsg }}
        <button class="btn btn-sec btn-sm" style="margin-left:10px" @click="loadWss">Retry</button>
      </div>
      <table v-else style="font-size:11.5px">
        <thead>
          <tr>
            <th>WSS Code</th><th>WSS Name</th><th>PHE Div.</th><th>Source Type</th><th>Solar?</th>
            <th>Op. Status</th><th style="text-align:center">Tested</th><th style="text-align:center">Last WQ</th>
            <th>Last Sampled</th><th>Next Scheduled</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!filtered.length">
            <td colspan="11" style="text-align:center;padding:28px;color:var(--muted)">
              No water schemes found. {{ wssData.length ? 'Try adjusting your filters.' : 'Click "+ Add WSS" to create the first one.' }}
            </td>
          </tr>
          <tr v-for="(w, i) in filtered" :key="w.code" :class="i%2===1?'alt':''">
            <td class="mono" style="font-size:10.5px">{{ w.code }}</td>
            <td><b>{{ w.name }}</b></td>
            <td>{{ w.div }}</td>
            <td>{{ w.source }}</td>
            <td>{{ w.solar ? '☀️ Solar' : '⚡ Non-Solar' }}</td>
            <td><span class="rag r-green">{{ w.opStatus }}</span></td>
            <td style="text-align:center;font-weight:700" :style="w.tested === 0 ? 'color:var(--muted)' : ''">{{ w.tested }}</td>
            <td style="text-align:center">
              <span class="rag" :class="wqClass(w.lastWQ)">{{ w.lastWQ }}</span>
            </td>
            <td :style="w.lastSampled === '—' ? 'color:var(--muted)' : ''">{{ w.lastSampled }}</td>
            <td :style="w.schedStatus === 'overdue' ? 'color:var(--red);font-weight:600' : w.schedStatus === 'none' ? 'color:var(--muted)' : ''">
              {{ w.nextScheduled }} <span v-if="w.schedStatus === 'overdue'">⚠</span>
            </td>
            <td style="white-space:nowrap">
              <button class="btn btn-sec btn-xs" @click="openTrail(w)" :disabled="w.tested === 0">📊 Trail</button>
              <button class="btn btn-xs" style="margin-left:4px"
                      :style="w.schedStatus === 'scheduled' ? 'background:#16a34a;color:#fff;border:none' : 'background:#dc2626;color:#fff;border:none'"
                      @click="openSchedule(w)">
                {{ w.schedStatus === 'scheduled' ? '✅ Sched.' : '📅 Sched.' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
      <div class="tbl-footer">
        <span>Showing {{ filtered.length }} schemes</span>
      </div>
    </div>

    <!-- ── SCHEDULE MODAL ── -->
    <Teleport to="body">
      <div v-if="showSchedModal" @click.self="showSchedModal = false"
           style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:4000;align-items:center;justify-content:center">
        <div style="background:#fff;border-radius:10px;width:420px;box-shadow:0 8px 40px rgba(0,0,0,.28);overflow:hidden">
          <div style="background:var(--navy);color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between">
            <div>
              <div style="font-size:14px;font-weight:700">📅 Schedule Next Sampling</div>
              <div style="font-size:11px;opacity:.7;margin-top:2px">{{ schedTarget?.code }}</div>
            </div>
            <button @click="showSchedModal = false" style="background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:5px;padding:5px 12px;cursor:pointer">✕</button>
          </div>
          <div style="padding:20px 24px">
            <div style="margin-bottom:14px">
              <label style="font-size:11px;font-weight:600;color:var(--muted);display:block;margin-bottom:4px">WSS Name</label>
              <div style="font-weight:700;font-size:13px;color:var(--navy)">{{ schedTarget?.name }}</div>
            </div>
            <div class="fg2" style="margin-bottom:14px">
              <label>Scheduled Sampling Date *</label>
              <input type="date" v-model="schedDate" style="width:100%;border:1px solid var(--border);border-radius:5px;padding:8px 10px;font-size:13px;font-family:inherit">
            </div>
            <div class="fg2" style="margin-bottom:20px">
              <label>Note (optional)</label>
              <input type="text" v-model="schedNote" placeholder="e.g. Joint sampling with DHO" style="width:100%;border:1px solid var(--border);border-radius:5px;padding:8px 10px;font-size:12.5px;font-family:inherit;box-sizing:border-box">
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
              <button class="btn btn-sec" @click="showSchedModal = false">Cancel</button>
              <button v-write="'edit_water_schemes'" class="btn btn-pri" @click="saveSchedule">💾 Save Schedule</button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── TRAIL MODAL ── -->
    <Teleport to="body">
      <div v-if="showTrailModal" @click.self="showTrailModal = false"
           style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:4000;align-items:center;justify-content:center">
        <div style="background:#fff;border-radius:10px;width:700px;max-height:80vh;overflow-y:auto;box-shadow:0 8px 40px rgba(0,0,0,.28)">
          <div style="background:var(--navy);color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:1">
            <div>
              <div style="font-size:14px;font-weight:700">📊 Analysis Trail</div>
              <div style="font-size:11px;opacity:.7;margin-top:2px">{{ trailTarget?.code }} — {{ trailTarget?.name }}, {{ trailTarget?.district }}</div>
            </div>
            <button @click="showTrailModal = false" style="background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:5px;padding:5px 12px;cursor:pointer">✕</button>
          </div>
          <div style="padding:20px 24px">
            <!-- Skeleton shimmer rows while the trail fetches.
                 Reuses the .skel-bar style already defined in this file. -->
            <div class="tbl-wrap" v-if="trailLoading">
              <table style="font-size:11.5px">
                <thead>
                  <tr><th>Date</th><th>Sample ID</th><th style="text-align:center">Result</th><th>Cause</th><th>Detail</th></tr>
                </thead>
                <tbody>
                  <tr v-for="n in 4" :key="'wd-trail-sk-' + n">
                    <td><span class="skel-bar" style="width:70%"></span></td>
                    <td><span class="skel-bar" style="width:60%"></span></td>
                    <td style="text-align:center"><span class="skel-bar" style="width:40px;display:inline-block"></span></td>
                    <td><span class="skel-bar" style="width:80%"></span></td>
                    <td><span class="skel-bar" style="width:75%"></span></td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-else-if="!trailData.length" style="text-align:center;color:var(--muted);padding:20px">No samples recorded yet.</div>
            <div class="tbl-wrap" v-else>
              <table style="font-size:11.5px">
                <thead>
                  <tr><th>Date</th><th>Sample ID</th><th style="text-align:center">Result</th><th>Cause</th><th>Detail</th></tr>
                </thead>
                <tbody>
                  <tr v-for="(r, i) in trailData" :key="r.id" :class="i%2===1?'alt':''">
                    <td class="mono">{{ r.date }}</td>
                    <td class="mono">{{ r.id }}</td>
                    <td style="text-align:center"><span class="rag" :class="r.result==='Fit'?'r-green':'r-red'">{{ r.result }}</span></td>
                    <td>{{ r.cause }}</td>
                    <td>{{ r.ion }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
    <!-- ── ADD WSS MODAL (step-by-step wizard) ── -->
    <Teleport to="body">
      <div v-if="showAddModal" @click.self="closeAddModal" class="wz-overlay">
        <div class="wz-modal">

          <!-- Header -->
          <div class="wz-header">
            <div>
              <div class="wz-title">💧 Create Water Scheme</div>
              <div class="wz-subtitle">Complete each step to register a new water supply scheme. Fields marked * are required.</div>
            </div>
            <button class="wz-close" @click="closeAddModal" aria-label="Close">✕</button>
          </div>

          <!-- Stepper -->
          <div class="wz-stepper">
            <div
              v-for="s in wzSteps" :key="s.n"
              class="wz-step"
              :class="{
                'is-active': wzStep === s.n,
                'is-done':   wzStep > s.n,
                'has-error': wzStepHasErrors(s.n) && wzStep !== s.n,
              }"
              @click="wzGoTo(s.n)"
            >
              <div class="wz-step-circle">
                <span v-if="wzStep > s.n && !wzStepHasErrors(s.n)">✓</span>
                <span v-else-if="wzStepHasErrors(s.n) && wzStep !== s.n">!</span>
                <span v-else>{{ s.n }}</span>
              </div>
              <div class="wz-step-label">
                <div class="wz-step-title">{{ s.icon }} {{ s.title }}</div>
                <div class="wz-step-sub">{{ s.subtitle }}</div>
              </div>
              <div v-if="s.n < wzSteps.length" class="wz-step-bar" :class="{ 'is-filled': wzStep > s.n }"></div>
            </div>
          </div>

          <!-- Body -->
          <div class="wz-body">

            <div v-if="addErrors._general" class="wz-alert wz-alert-err">{{ addErrors._general[0] }}</div>

            <!-- Step 1 — Basic Info -->
            <div v-show="wzStep === 1" class="wz-step-content">
              <div class="wz-section-head">
                <div class="wz-section-title">📋 Basic Information</div>
                <div class="wz-section-sub">Identify the scheme with a clear name and physical address.</div>
              </div>
              <div class="wz-grid wz-grid-1">
                <div class="wz-field">
                  <label>WSS Name <span class="req">*</span></label>
                  <input type="text" v-model="addForm.name" placeholder="e.g. Peshawar City WSS" :class="{ 'has-err': addErrors.name }">
                  <span v-if="addErrors.name" class="wz-err">{{ addErrors.name[0] }}</span>
                </div>
                <div class="wz-field">
                  <label>Address <span class="req">*</span></label>
                  <input type="text" v-model="addForm.address" placeholder="Full physical address of the scheme" :class="{ 'has-err': addErrors.address }">
                  <span v-if="addErrors.address" class="wz-err">{{ addErrors.address[0] }}</span>
                </div>
              </div>
            </div>

            <!-- Step 2 — Location -->
            <div v-show="wzStep === 2" class="wz-step-content">
              <div class="wz-section-head">
                <div class="wz-section-title">📍 Administrative Location</div>
                <div class="wz-section-sub">Cascading selection — pick Division first, then District, Tehsil, and Union Council.</div>
              </div>
              <div class="wz-grid wz-grid-2">
                <div class="wz-field">
                  <label>Division <span class="req">*</span></label>
                  <select v-model="addForm.division_id" @change="onFormDivisionChange(addForm.division_id)" :class="{ 'has-err': addErrors.division_id }">
                    <option value="">— Select Division —</option>
                    <option v-for="d in allDivisions" :key="d.id" :value="d.id">{{ d.name }}</option>
                  </select>
                  <span v-if="addErrors.division_id" class="wz-err">{{ addErrors.division_id[0] }}</span>
                </div>
                <div class="wz-field">
                  <label>District <span class="req">*</span></label>
                  <select v-model="addForm.district_id" @change="onFormDistrictChange(addForm.district_id)" :disabled="!addForm.division_id" :class="{ 'has-err': addErrors.district_id }">
                    <option value="">{{ addForm.division_id ? '— Select District —' : 'Pick a division first' }}</option>
                    <option v-for="d in formDistricts" :key="d.id" :value="d.id">{{ d.name }}</option>
                  </select>
                  <span v-if="addErrors.district_id" class="wz-err">{{ addErrors.district_id[0] }}</span>
                </div>
                <div class="wz-field">
                  <label>Tehsil <span class="req">*</span></label>
                  <select v-model="addForm.tehsil_id" @change="onFormTehsilChange(addForm.tehsil_id)" :disabled="!addForm.district_id" :class="{ 'has-err': addErrors.tehsil_id }">
                    <option value="">{{ addForm.district_id ? '— Select Tehsil —' : 'Pick a district first' }}</option>
                    <option v-for="t in formTehsils" :key="t.id" :value="t.id">{{ t.name }}</option>
                  </select>
                  <span v-if="addErrors.tehsil_id" class="wz-err">{{ addErrors.tehsil_id[0] }}</span>
                </div>
                <div class="wz-field">
                  <label>Union Council</label>
                  <select v-model="addForm.union_council_id" :disabled="!addForm.tehsil_id">
                    <option value="">{{ addForm.tehsil_id ? '— Select Union Council —' : 'Pick a tehsil first' }}</option>
                    <option v-for="u in formUnionCouncils" :key="u.id" :value="u.id">{{ u.name }}</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Step 3 — GPS Coordinates -->
            <div v-show="wzStep === 3" class="wz-step-content">
              <div class="wz-section-head">
                <div class="wz-section-title">🗺️ GPS Coordinates</div>
                <div class="wz-section-sub">Auto-filled from the selected district — adjust manually if you have a more precise pin.</div>
              </div>
              <div class="wz-grid wz-grid-2">
                <div class="wz-field">
                  <label>Latitude <span class="req">*</span></label>
                  <input type="number" step="any" v-model="addForm.latitude" @input="onLatLngInput" placeholder="e.g. 34.0151" :class="{ 'has-err': addErrors.latitude }">
                  <span v-if="addErrors.latitude" class="wz-err">{{ addErrors.latitude[0] }}</span>
                </div>
                <div class="wz-field">
                  <label>Longitude <span class="req">*</span></label>
                  <input type="number" step="any" v-model="addForm.longitude" @input="onLatLngInput" placeholder="e.g. 71.5249" :class="{ 'has-err': addErrors.longitude }">
                  <span v-if="addErrors.longitude" class="wz-err">{{ addErrors.longitude[0] }}</span>
                </div>
              </div>
              <div class="wz-pin">
                <div class="wz-pin-icon">📌</div>
                <div class="wz-pin-body">
                  <div class="wz-pin-title">Current Pin Location</div>
                  <div v-if="addForm.latitude && addForm.longitude" class="wz-pin-value">{{ addForm.latitude }}, {{ addForm.longitude }}</div>
                  <div v-else class="wz-pin-empty">No coordinates set — select a district in Step 2 to auto-fill, or enter them above.</div>
                </div>
              </div>
            </div>

            <!-- Step 4 — Technical Details -->
            <div v-show="wzStep === 4" class="wz-step-content">
              <div class="wz-section-head">
                <div class="wz-section-title">⚙️ Technical Details</div>
                <div class="wz-section-sub">Source, power, machinery and capacity. All optional — fill what you have.</div>
              </div>
              <div class="wz-subhead">Source & Operation</div>
              <div class="wz-grid wz-grid-3">
                <div class="wz-field">
                  <label>Source Type</label>
                  <select v-model="addForm.source_type">
                    <option value="">— Select —</option>
                    <option v-for="s in sourceTypeOptions" :key="s.id" :value="s.id">{{ s.name }}</option>
                  </select>
                </div>
                <div class="wz-field">
                  <label>Operation</label>
                  <select v-model="addForm.operation">
                    <option value="">— Select —</option>
                    <option v-for="o in operationOptions" :key="o.id" :value="o.id">{{ o.name }}</option>
                  </select>
                </div>
                <div class="wz-field">
                  <label>Power Input</label>
                  <select v-model="addForm.power_input">
                    <option value="">— Select —</option>
                    <option v-for="p in powerInputs" :key="p.value" :value="p.value">{{ p.name }}</option>
                  </select>
                </div>
              </div>

              <div class="wz-subhead">Machinery</div>
              <div class="wz-grid wz-grid-3">
                <div class="wz-field">
                  <label>Type of Machine</label>
                  <input type="text" v-model="addForm.type_of_machine" placeholder="e.g. Submersible">
                </div>
                <div class="wz-field">
                  <label>Horse Power Motor</label>
                  <input type="number" v-model="addForm.horse_power_motor" placeholder="e.g. 10">
                </div>
                <div class="wz-field">
                  <label>Chamber</label>
                  <select v-model="addForm.chamber">
                    <option value="">— Select —</option>
                    <option v-for="c in chamberOptions" :key="c.id" :value="c.id">{{ c.name }}</option>
                  </select>
                </div>
                <div class="wz-field">
                  <label>Pipe Type</label>
                  <input type="text" v-model="addForm.pipe_type" placeholder="e.g. GI, PVC">
                </div>
                <div class="wz-field">
                  <label>Mode</label>
                  <input type="text" v-model="addForm.mode" placeholder="e.g. Continuous">
                </div>
                <div class="wz-field">
                  <label>Year of Installation</label>
                  <input type="number" v-model="addForm.years_of_installation" :min="1947" :max="new Date().getFullYear()" placeholder="e.g. 2005">
                </div>
              </div>

              <div class="wz-subhead">Capacity & Storage</div>
              <div class="wz-grid wz-grid-4">
                <div class="wz-field">
                  <label>Capacity</label>
                  <input type="number" v-model="addForm.capacity" placeholder="Gallons / hr">
                </div>
                <div class="wz-field">
                  <label>Storage</label>
                  <input type="number" v-model="addForm.storage" placeholder="Gallons">
                </div>
                <div class="wz-field">
                  <label>Depth</label>
                  <input type="number" v-model="addForm.depth" placeholder="Feet">
                </div>
                <div class="wz-field">
                  <label>Population Served</label>
                  <input type="number" v-model="addForm.population" placeholder="e.g. 5000">
                </div>
              </div>

              <div class="wz-grid wz-grid-1">
                <div class="wz-field">
                  <label>Remarks</label>
                  <textarea v-model="addForm.remarks" rows="3" placeholder="Any additional notes about this scheme…"></textarea>
                </div>
              </div>

              <!-- Review summary -->
              <div class="wz-review">
                <div class="wz-review-title">📋 Review Before Submitting</div>
                <div class="wz-review-grid">
                  <div><span>WSS Name</span><b>{{ addForm.name || '—' }}</b></div>
                  <div><span>Address</span><b>{{ addForm.address || '—' }}</b></div>
                  <div><span>Division</span><b>{{ allDivisions.find(d => d.id == addForm.division_id)?.name || '—' }}</b></div>
                  <div><span>District</span><b>{{ allDistricts.find(d => d.id == addForm.district_id)?.name || '—' }}</b></div>
                  <div><span>Tehsil</span><b>{{ formTehsils.find(t => t.id == addForm.tehsil_id)?.name || '—' }}</b></div>
                  <div><span>Union Council</span><b>{{ formUnionCouncils.find(u => u.id == addForm.union_council_id)?.name || '—' }}</b></div>
                  <div><span>Coordinates</span><b>{{ addForm.latitude && addForm.longitude ? `${addForm.latitude}, ${addForm.longitude}` : '—' }}</b></div>
                  <div><span>Power Input</span><b>{{ addForm.power_input || '—' }}</b></div>
                </div>
              </div>
            </div>

          </div>

          <!-- Footer -->
          <div class="wz-footer">
            <div class="wz-footer-left">
              <span class="wz-step-counter">Step {{ wzStep }} of {{ wzSteps.length }}</span>
            </div>
            <div class="wz-footer-right">
              <button class="btn btn-sec" @click="closeAddModal" :disabled="addLoading">Cancel</button>
              <button v-if="wzStep > 1" class="btn btn-sec" @click="wzPrev" :disabled="addLoading">← Back</button>
              <button v-if="wzStep < wzSteps.length" class="btn btn-pri" @click="wzNext">Next →</button>
              <button v-else v-write="'add_water_schemes'" class="btn btn-pri" @click="submitAddWss" :disabled="addLoading">
                <span v-if="addLoading">⏳ Saving…</span>
                <span v-else>💾 Create Water Scheme</span>
              </button>
            </div>
          </div>

        </div>
      </div>
    </Teleport>

  </div>
</template>

<style scoped>
.toast-slide-enter-active,
.toast-slide-leave-active {
  transition: all 0.3s ease;
}
.toast-slide-enter-from,
.toast-slide-leave-to {
  opacity: 0;
  transform: translateX(60px);
}

/* Skeleton loading rows for the WSS table */
.wss-skel td { padding: 9px 8px; }
.skel-bar {
  display: inline-block;
  height: 11px;
  border-radius: 4px;
  background: linear-gradient(90deg, #e9eef5 25%, #f4f7fb 50%, #e9eef5 75%);
  background-size: 200% 100%;
  animation: skel-shimmer 1.2s ease-in-out infinite;
}
@keyframes skel-shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ── Add WSS wizard ─────────────────────────────────────────────── */
.wz-overlay {
  position: fixed; inset: 0;
  background: rgba(15, 23, 42, .55);
  z-index: 5000;
  display: flex; align-items: flex-start; justify-content: center;
  overflow-y: auto;
  padding: 24px 12px;
  backdrop-filter: blur(2px);
}
.wz-modal {
  background: #fff;
  border-radius: 12px;
  width: 100%;
  max-width: 960px;
  margin: auto;
  box-shadow: 0 20px 60px rgba(0, 0, 0, .35);
  overflow: hidden;
  display: flex; flex-direction: column;
  max-height: calc(100vh - 48px);
}

.wz-header {
  background: linear-gradient(135deg, var(--navy, #0f2945) 0%, #1e3a5f 100%);
  color: #fff;
  padding: 16px 24px;
  display: flex; align-items: center; justify-content: space-between;
  flex-shrink: 0;
}
.wz-title    { font-size: 15px; font-weight: 700; letter-spacing: .2px; }
.wz-subtitle { font-size: 11.5px; opacity: .72; margin-top: 3px; }
.wz-close {
  background: rgba(255, 255, 255, .15);
  border: none; color: #fff; border-radius: 6px;
  padding: 6px 12px; cursor: pointer; font-size: 14px;
  transition: background .15s;
}
.wz-close:hover { background: rgba(255, 255, 255, .28); }

.wz-stepper {
  display: flex; align-items: stretch;
  padding: 18px 24px 16px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  flex-shrink: 0;
}
.wz-step {
  flex: 1; display: flex; align-items: center; gap: 10px;
  cursor: pointer; position: relative; min-width: 0; user-select: none;
}
.wz-step-circle {
  width: 32px; height: 32px; border-radius: 50%;
  background: #e2e8f0; color: #64748b;
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 700; flex-shrink: 0;
  border: 2px solid transparent;
  transition: all .2s;
}
.wz-step.is-active .wz-step-circle {
  background: var(--navy, #0f2945); color: #fff;
  border-color: var(--navy, #0f2945);
  box-shadow: 0 0 0 4px rgba(15, 41, 69, .15);
}
.wz-step.is-done .wz-step-circle   { background: #10b981; color: #fff; }
.wz-step.has-error .wz-step-circle { background: #ef4444; color: #fff; }
.wz-step-label  { min-width: 0; flex: 1; }
.wz-step-title  { font-size: 12px; font-weight: 700; color: #334155; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wz-step.is-active .wz-step-title { color: var(--navy, #0f2945); }
.wz-step-sub    { font-size: 10.5px; color: #64748b; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wz-step-bar {
  position: absolute; top: 16px; right: -50%;
  width: 100%; height: 2px;
  background: #e2e8f0; z-index: 0;
}
.wz-step-bar.is-filled { background: #10b981; }

.wz-body {
  padding: 24px 28px;
  overflow-y: auto;
  flex: 1;
}
.wz-step-content { animation: wzFadeIn .22s ease; }
@keyframes wzFadeIn {
  from { opacity: 0; transform: translateY(4px); }
  to   { opacity: 1; transform: translateY(0); }
}

.wz-section-head  { margin-bottom: 18px; padding-bottom: 12px; border-bottom: 1px dashed #e2e8f0; }
.wz-section-title { font-size: 14px; font-weight: 700; color: var(--navy, #0f2945); }
.wz-section-sub   { font-size: 12px; color: #64748b; margin-top: 4px; line-height: 1.5; }
.wz-subhead {
  font-size: 11px; font-weight: 700; color: #475569;
  text-transform: uppercase; letter-spacing: .5px;
  margin: 6px 0 10px; padding-left: 6px;
  border-left: 3px solid var(--navy, #0f2945);
}

.wz-grid { display: grid; gap: 14px 18px; margin-bottom: 14px; }
.wz-grid-1 { grid-template-columns: 1fr; }
.wz-grid-2 { grid-template-columns: 1fr 1fr; }
.wz-grid-3 { grid-template-columns: 1fr 1fr 1fr; }
.wz-grid-4 { grid-template-columns: 1fr 1fr 1fr 1fr; }

.wz-field { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
.wz-field label {
  font-size: 11.5px; font-weight: 600; color: #334155; letter-spacing: .15px;
}
.wz-field .req { color: #ef4444; font-weight: 700; }
.wz-field input[type="text"],
.wz-field input[type="email"],
.wz-field input[type="number"],
.wz-field input[type="date"],
.wz-field select,
.wz-field textarea {
  width: 100%;
  padding: 8px 11px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 12.5px;
  font-family: inherit;
  background: #fff;
  color: #0f172a;
  box-sizing: border-box;
  transition: border-color .15s, box-shadow .15s, background .15s;
}
.wz-field textarea { resize: vertical; min-height: 80px; line-height: 1.5; }
.wz-field input:focus,
.wz-field select:focus,
.wz-field textarea:focus {
  outline: none;
  border-color: var(--navy, #0f2945);
  box-shadow: 0 0 0 3px rgba(15, 41, 69, .12);
}
.wz-field input:disabled,
.wz-field select:disabled {
  background: #f1f5f9; color: #94a3b8; cursor: not-allowed;
}
.wz-field .has-err { border-color: #ef4444 !important; background: #fef2f2; }
.wz-field .has-err:focus { box-shadow: 0 0 0 3px rgba(239, 68, 68, .12); }

.wz-err { font-size: 11px; color: #dc2626; font-weight: 500; }

.wz-alert { border-radius: 6px; padding: 10px 14px; margin-bottom: 16px; font-size: 12.5px; line-height: 1.5; }
.wz-alert-err { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }

.wz-pin {
  display: flex; gap: 14px; align-items: flex-start;
  background: #f0f7ff;
  border: 1px solid #bae6fd;
  border-radius: 8px;
  padding: 14px 16px;
  margin-top: 4px;
}
.wz-pin-icon { font-size: 22px; line-height: 1; }
.wz-pin-body { flex: 1; }
.wz-pin-title { font-size: 11px; font-weight: 700; color: #0c4a6e; text-transform: uppercase; letter-spacing: .4px; }
.wz-pin-value { font-size: 13px; color: #0f172a; font-weight: 600; margin-top: 4px; font-family: 'JetBrains Mono', ui-monospace, monospace; }
.wz-pin-empty { font-size: 12px; color: #64748b; margin-top: 4px; line-height: 1.5; }

.wz-review {
  margin-top: 22px; padding: 16px 18px;
  background: #f8fafc; border: 1px solid #e2e8f0;
  border-radius: 8px;
}
.wz-review-title { font-size: 12px; font-weight: 700; color: var(--navy, #0f2945); margin-bottom: 10px; }
.wz-review-grid {
  display: grid; grid-template-columns: repeat(2, 1fr);
  gap: 10px 16px;
}
.wz-review-grid > div { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.wz-review-grid span  { font-size: 10.5px; color: #64748b; text-transform: uppercase; letter-spacing: .3px; }
.wz-review-grid b     { font-size: 12px; color: #0f172a; font-weight: 600; word-break: break-word; }

.wz-footer {
  padding: 14px 24px;
  border-top: 1px solid #e2e8f0;
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  background: #fafbfc; flex-shrink: 0;
}
.wz-footer-left  { font-size: 11.5px; color: #64748b; font-weight: 600; }
.wz-footer-right { display: flex; gap: 8px; }
.wz-step-counter {
  background: #e2e8f0; padding: 5px 11px; border-radius: 99px;
  color: #475569; font-size: 11px; letter-spacing: .3px;
}

@media (max-width: 720px) {
  .wz-modal    { max-height: calc(100vh - 24px); }
  .wz-stepper  { padding: 14px 14px 12px; gap: 4px; overflow-x: auto; }
  .wz-step     { flex: 0 0 auto; }
  .wz-step-label { display: none; }
  .wz-step-bar   { display: none; }
  .wz-body     { padding: 18px 16px; }
  .wz-grid-2,
  .wz-grid-3,
  .wz-grid-4   { grid-template-columns: 1fr; }
  .wz-review-grid { grid-template-columns: 1fr; }
  .wz-footer   { flex-direction: column-reverse; align-items: stretch; }
  .wz-footer-right { justify-content: flex-end; }
}
</style>
