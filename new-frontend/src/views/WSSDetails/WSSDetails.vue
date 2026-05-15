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
  if (!schedDate.value) { alert('Please select a sampling date.'); return }
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
  } catch (e) {
    alert('Failed to save schedule: ' + (e.response?.data?.message || e.message))
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
    alert('No data to export.')
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
      <button v-write class="btn btn-pri btn-sm" @click="openAddModal">+ Add WSS</button>
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
              <button v-write class="btn btn-pri" @click="saveSchedule">💾 Save Schedule</button>
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
            <div v-if="!trailData.length" style="text-align:center;color:var(--muted);padding:20px">No samples recorded yet.</div>
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
    <!-- ── ADD WSS MODAL ── -->
    <Teleport to="body">
      <div v-if="showAddModal" @click.self="closeAddModal"
           style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:5000;align-items:flex-start;justify-content:center;overflow-y:auto;padding:24px 12px">
        <div style="background:#fff;border-radius:10px;width:100%;max-width:860px;box-shadow:0 8px 48px rgba(0,0,0,.3);overflow:hidden;margin:auto">

          <!-- Header -->
          <div style="background:var(--navy);color:#fff;padding:14px 22px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:1">
            <div>
              <div style="font-size:14px;font-weight:700">💧 Create Water Scheme</div>
              <div style="font-size:11px;opacity:.65;margin-top:2px">Fill in the details below — all starred fields are required</div>
            </div>
            <button @click="closeAddModal" style="background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:5px;padding:5px 14px;cursor:pointer;font-size:14px">✕</button>
          </div>

          <div style="padding:22px 26px">

            <!-- General error -->
            <div v-if="addErrors._general" style="background:#fee2e2;border:1px solid #fca5a5;border-radius:5px;padding:10px 14px;margin-bottom:14px;font-size:12.5px;color:#991b1b">
              {{ addErrors._general[0] }}
            </div>

            <!-- ── Section: Basic Info ── -->
            <div style="font-size:11px;font-weight:700;color:var(--navy2);background:var(--sky2);border:1px solid var(--sky);border-radius:4px;padding:5px 12px;margin-bottom:12px">
              📋 Basic Information
            </div>
            <div class="form-grid" style="margin-bottom:14px">
              <!-- Name -->
              <div class="fg2 span2">
                <label>Name <span style="color:var(--red)">*</span></label>
                <input type="text" v-model="addForm.name" placeholder="e.g. Peshawar City WSS" :style="addErrors.name ? 'border-color:var(--red)' : ''">
                <span v-if="addErrors.name" style="font-size:11px;color:var(--red)">{{ addErrors.name[0] }}</span>
              </div>
              <!-- Address -->
              <div class="fg2 span2">
                <label>Address <span style="color:var(--red)">*</span></label>
                <input type="text" v-model="addForm.address" placeholder="Full address" :style="addErrors.address ? 'border-color:var(--red)' : ''">
                <span v-if="addErrors.address" style="font-size:11px;color:var(--red)">{{ addErrors.address[0] }}</span>
              </div>
            </div>

            <!-- ── Section: Location ── -->
            <div style="font-size:11px;font-weight:700;color:var(--navy2);background:var(--sky2);border:1px solid var(--sky);border-radius:4px;padding:5px 12px;margin-bottom:12px">
              📍 Location
            </div>
            <div class="form-grid" style="margin-bottom:14px">
              <!-- Division -->
              <div class="fg2">
                <label>Division <span style="color:var(--red)">*</span></label>
                <select v-model="addForm.division_id" @change="onFormDivisionChange(addForm.division_id)" :style="addErrors.division_id ? 'border-color:var(--red)' : ''">
                  <option value="">— Select Division —</option>
                  <option v-for="d in allDivisions" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
                <span v-if="addErrors.division_id" style="font-size:11px;color:var(--red)">{{ addErrors.division_id[0] }}</span>
              </div>
              <!-- District -->
              <div class="fg2">
                <label>District <span style="color:var(--red)">*</span></label>
                <select v-model="addForm.district_id" @change="onFormDistrictChange(addForm.district_id)" :disabled="!addForm.division_id" :style="addErrors.district_id ? 'border-color:var(--red)' : ''">
                  <option value="">— Select District —</option>
                  <option v-for="d in formDistricts" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
                <span v-if="addErrors.district_id" style="font-size:11px;color:var(--red)">{{ addErrors.district_id[0] }}</span>
              </div>
              <!-- Tehsil -->
              <div class="fg2">
                <label>Tehsil <span style="color:var(--red)">*</span></label>
                <select v-model="addForm.tehsil_id" @change="onFormTehsilChange(addForm.tehsil_id)" :disabled="!addForm.district_id" :style="addErrors.tehsil_id ? 'border-color:var(--red)' : ''">
                  <option value="">— Select Tehsil —</option>
                  <option v-for="t in formTehsils" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
                <span v-if="addErrors.tehsil_id" style="font-size:11px;color:var(--red)">{{ addErrors.tehsil_id[0] }}</span>
              </div>
              <!-- Union Council -->
              <div class="fg2">
                <label>Union Council</label>
                <select v-model="addForm.union_council_id" :disabled="!addForm.tehsil_id">
                  <option value="">— Select Union Council —</option>
                  <option v-for="u in formUnionCouncils" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
              </div>
            </div>

            <!-- ── Section: GPS Coordinates ── -->
            <div style="font-size:11px;font-weight:700;color:var(--navy2);background:var(--sky2);border:1px solid var(--sky);border-radius:4px;padding:5px 12px;margin-bottom:12px">
              🗺 GPS Coordinates <span style="font-weight:400;opacity:.7">(auto-filled from district, or enter manually)</span>
            </div>
            <div class="form-grid" style="margin-bottom:14px">
              <div class="fg2">
                <label>Latitude <span style="color:var(--red)">*</span></label>
                <input type="number" step="any" v-model="addForm.latitude" @input="onLatLngInput" placeholder="e.g. 34.0151" :style="addErrors.latitude ? 'border-color:var(--red)' : ''">
                <span v-if="addErrors.latitude" style="font-size:11px;color:var(--red)">{{ addErrors.latitude[0] }}</span>
              </div>
              <div class="fg2">
                <label>Longitude <span style="color:var(--red)">*</span></label>
                <input type="number" step="any" v-model="addForm.longitude" @input="onLatLngInput" placeholder="e.g. 71.5249" :style="addErrors.longitude ? 'border-color:var(--red)' : ''">
                <span v-if="addErrors.longitude" style="font-size:11px;color:var(--red)">{{ addErrors.longitude[0] }}</span>
              </div>
              <!-- Map placeholder — shows current pin coordinates -->
              <div class="fg2 span2" style="background:#f0f7ff;border:1px solid var(--sky);border-radius:6px;padding:12px 14px;font-size:12px;color:var(--navy2)">
                📌 <b>Pin Location:</b>
                <span v-if="addForm.latitude && addForm.longitude"> {{ addForm.latitude }}, {{ addForm.longitude }}</span>
                <span v-else style="color:var(--muted)"> Select a district or enter coordinates above</span>
                <div style="font-size:11px;color:var(--muted);margin-top:4px">
                  Tip: Select a district to auto-fill coordinates, then adjust manually if needed.
                </div>
              </div>
            </div>

            <!-- ── Section: Technical Details ── -->
            <div style="font-size:11px;font-weight:700;color:var(--navy2);background:var(--sky2);border:1px solid var(--sky);border-radius:4px;padding:5px 12px;margin-bottom:12px">
              ⚙ Technical Details
            </div>
            <div class="form-grid" style="margin-bottom:14px">
              <!-- Source Type -->
              <div class="fg2">
                <label>Source Type</label>
                <select v-model="addForm.source_type">
                  <option value="">— Select —</option>
                  <option v-for="s in sourceTypeOptions" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
              </div>
              <!-- Operation -->
              <div class="fg2">
                <label>Operation</label>
                <select v-model="addForm.operation">
                  <option value="">— Select —</option>
                  <option v-for="o in operationOptions" :key="o.id" :value="o.id">{{ o.name }}</option>
                </select>
              </div>
              <!-- Power Input -->
              <div class="fg2">
                <label>Power Input</label>
                <select v-model="addForm.power_input">
                  <option value="">— Select —</option>
                  <option v-for="p in powerInputs" :key="p.value" :value="p.value">{{ p.name }}</option>
                </select>
              </div>
              <!-- Chamber -->
              <div class="fg2">
                <label>Chamber</label>
                <select v-model="addForm.chamber">
                  <option value="">— Select —</option>
                  <option v-for="c in chamberOptions" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
              </div>
              <!-- Year of Installation -->
              <div class="fg2">
                <label>Year of Installation</label>
                <input type="number" v-model="addForm.years_of_installation" :min="1947" :max="new Date().getFullYear()" placeholder="e.g. 2005">
              </div>
              <!-- Mode -->
              <div class="fg2">
                <label>Mode</label>
                <input type="text" v-model="addForm.mode" placeholder="e.g. Continuous">
              </div>
              <!-- Type of Machine -->
              <div class="fg2">
                <label>Type of Machine</label>
                <input type="text" v-model="addForm.type_of_machine" placeholder="e.g. Submersible">
              </div>
              <!-- Pipe Type -->
              <div class="fg2">
                <label>Type of Pipe</label>
                <input type="text" v-model="addForm.pipe_type" placeholder="e.g. GI, PVC">
              </div>
              <!-- Horse Power Motor -->
              <div class="fg2">
                <label>Horse Power Motor</label>
                <input type="number" v-model="addForm.horse_power_motor" placeholder="e.g. 10">
              </div>
              <!-- Capacity -->
              <div class="fg2">
                <label>Capacity</label>
                <input type="number" v-model="addForm.capacity" placeholder="Gallons/hour">
              </div>
              <!-- Depth -->
              <div class="fg2">
                <label>Depth</label>
                <input type="number" v-model="addForm.depth" placeholder="Feet">
              </div>
              <!-- Storage -->
              <div class="fg2">
                <label>Storage</label>
                <input type="number" v-model="addForm.storage" placeholder="Gallons">
              </div>
              <!-- Population -->
              <div class="fg2">
                <label>Population Served</label>
                <input type="number" v-model="addForm.population" placeholder="e.g. 5000">
              </div>
              <!-- Remarks -->
              <div class="fg2 span2">
                <label>Remarks</label>
                <textarea v-model="addForm.remarks" rows="2" placeholder="Any additional notes…" style="width:100%;border:1px solid var(--border);border-radius:5px;padding:7px 10px;font-size:12.5px;font-family:inherit;resize:vertical;box-sizing:border-box"></textarea>
              </div>
            </div>

            <!-- Footer actions -->
            <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:6px;border-top:1px solid var(--border)">
              <button v-write class="btn btn-sec" @click="closeAddModal" :disabled="addLoading">Cancel</button>
              <button v-write class="btn btn-pri" @click="submitAddWss" :disabled="addLoading">
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
</style>
