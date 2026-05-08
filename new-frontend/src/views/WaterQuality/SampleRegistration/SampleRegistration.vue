<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { api } from '../../../services/api.js'
import { useUserStore } from '../../../stores/useUserStore.js'

const userStore  = useUserStore()
const activeTab  = ref('phe')

// ── Status messages ───────────────────────────────────────────────────
const successMsg  = ref('')
const errorMsg    = ref('')
const saveLoading = ref(false)

// ── Dropdown data from backend ────────────────────────────────────────
const allDivisions    = ref([])   // { id, name, region_id, province_id }
const allDistricts    = ref([])   // { id, name, division_id, circle_id }
const allPhedDivs     = ref([])   // { id, name, district_id, circle_id }
const allHubLabs      = ref([])   // { id, name, division_id }
const allRegions      = ref([])   // { id, name }
const allCircles      = ref([])   // { id, name, region_id, hub_lab_id }
const allProvinces    = ref([])   // { id, name }
const wssOptions      = ref([])   // { id, name, latitude, longitude, district_id }
const clients         = ref([])
const collectedInOpts = ref([])
const reasonOpts      = ref([])

onMounted(async () => {
  try {
    const [divRes, distRes, phdRes, hlRes, regRes, cirRes, provRes, wssRes, ciRes, rfRes, clientRes] = await Promise.all([
      api.get('/all-divisions'),
      api.get('/all-districts'),
      api.get('/phed-divisions'),
      api.get('/hub-labs'),
      api.get('/regions'),
      api.get('/circles'),
      api.get('/provinces'),
      api.get('/all-water-schemes'),
      api.get('/collected-in-status'),
      api.get('/reason-for-testing-status'),
      api.get('/get-clients'),
    ])
    allDivisions.value = divRes.data?.data  || divRes.data  || []
    allDistricts.value = distRes.data?.data || distRes.data || []
    allPhedDivs.value  = phdRes.data?.data  || phdRes.data  || []
    allHubLabs.value   = hlRes.data?.data   || hlRes.data   || []
    allRegions.value   = regRes.data?.data  || regRes.data  || []
    allCircles.value   = cirRes.data?.data  || cirRes.data  || []
    allProvinces.value = provRes.data?.data || provRes.data || []
    wssOptions.value   = wssRes.data?.data  || wssRes.data  || []
    collectedInOpts.value = Object.keys(ciRes.data?.data  || ciRes.data  || {})
    reasonOpts.value      = Object.keys(rfRes.data?.data  || rfRes.data  || {})
    clients.value         = clientRes.data?.data || clientRes.data || []

    // Pre-fill location from logged-in user's district
    const user = userStore.currentUser
    if (user?.district_id) {
      const loc = resolveLocation(user.district_id)
      Object.assign(pheForm.value, loc)
    }
  } catch (e) {
    console.error('Dropdown load error:', e)
  }
})

// ── Resolve full location chain from a district_id ────────────────────
function resolveLocation(districtId) {
  const district = allDistricts.value.find(d => d.id == districtId)
  if (!district) return {}
  const division = allDivisions.value.find(d => d.id == district.division_id)
  const circle   = allCircles.value.find(c => c.id == district.circle_id)
  // region_id lives on circle, not division (division.region_id is null in DB)
  const region   = allRegions.value.find(r => r.id == circle?.region_id)
  const province = allProvinces.value.find(p => p.id == division?.province_id)
  const hubLab   = allHubLabs.value.find(h => h.id == circle?.hub_lab_id)
  const phedDiv  = allPhedDivs.value.find(p => p.district_id == districtId)
  return {
    division_id:      division?.id    || '',
    district_id:      district.id,
    circle_id:        circle?.id      || '',
    region_id:        region?.id      || '',
    province_id:      province?.id    || '',
    hub_lab_id:       hubLab?.id      || '',
    phed_division_id: phedDiv?.id     || '',
  }
}

// ── Filtered lists ────────────────────────────────────────────────────
const pheDistricts = computed(() =>
  pheForm.value.division_id
    ? allDistricts.value.filter(d => d.division_id == pheForm.value.division_id)
    : allDistricts.value
)
const phePhedDivs = computed(() =>
  pheForm.value.district_id
    ? allPhedDivs.value.filter(p => p.district_id == pheForm.value.district_id)
    : allPhedDivs.value
)
const pheHubLabs = computed(() =>
  pheForm.value.division_id
    ? allHubLabs.value.filter(h => h.division_id == pheForm.value.division_id)
    : allHubLabs.value
)

// Check if selected district is Peshawar
const isPeshawarDistrictPHE = computed(() => {
  if (!pheForm.value.district_id || !allDistricts.value.length) return false
  const district = allDistricts.value.find(d => d.id == pheForm.value.district_id)
  const name = district?.name?.trim().toLowerCase() || ''
  return name === 'peshawar' || name.startsWith('peshawar')
})

const pvtDistricts = computed(() =>
  pvtForm.value.division_id
    ? allDistricts.value.filter(d => d.division_id == pvtForm.value.division_id)
    : allDistricts.value
)

// ── Utility ───────────────────────────────────────────────────────────
function today() { return new Date().toISOString().split('T')[0] }
function addDays(d, n) {
  const dt = new Date(d); dt.setDate(dt.getDate() + n)
  return dt.toISOString().split('T')[0]
}
function toDateTime(date, time = '09:00:00') {
  if (!date) return ''
  // Ensure sampled_at is never in the future — cap to current time if needed
  const dt = new Date(`${date}T${time}`)
  const now = new Date()
  const final = dt > now ? now : dt
  const pad = n => String(n).padStart(2, '0')
  return `${final.getFullYear()}-${pad(final.getMonth()+1)}-${pad(final.getDate())} ${pad(final.getHours())}:${pad(final.getMinutes())}:${pad(final.getSeconds())}`
}

// ── Desired test mapping: UI testType → backend desired_test array ────
// Backend DesiredTestEnum: 'Physical', 'Physical & Chemical', 'Microbiological(MF)', 'Microbiological(Kit)', 'On Demand'
function desiredTestsFor(testType, testMethod) {
  const isMF = !testMethod || testMethod.toLowerCase().includes('mf') || testMethod.toLowerCase().includes('membrane')
  const micro = isMF ? 'Microbiological(MF)' : 'Microbiological(Kit)'
  const map = {
    PCM: ['Physical', 'Physical & Chemical', micro],
    PC:  ['Physical', 'Physical & Chemical'],
    P:   ['Physical'],
    C:   ['Physical & Chemical'],
    M:   [micro],
    SEL: ['Physical', 'Physical & Chemical', micro],
  }
  return map[testType] || ['Physical', 'Physical & Chemical', micro]
}

const activeTab_ref = activeTab  // alias for clarity

// ── PHE Form ──────────────────────────────────────────────────────────
const pheForm = ref({
  // Location IDs (sent to backend)
  division_id: '', district_id: '', circle_id: '', region_id: '',
  province_id: '', hub_lab_id: '', phed_division_id: '',
  water_scheme_id: '',
  // UI helpers
  sourceGps: '', consumerName: '', consumerGps: '',
  // Collection
  collectionDate: today(), collectionTime: '09:30',
  reportingDate: addDays(today(), 3),
  containerType: 'Plastic Bottle', collectedBy: 'Laboratory Staff',
  reasonForTesting: 'General Q.Analysis',
  // Testing
  testType: 'PCM', testMethod: 'MF Method — Membrane Filtration',
  collectionPoint: 'Source', sourceType: 'Pumping', sourceSubType: 'Tube Well',
  temperature: 20,
  sample_name: '', water_sample_address: '',
})

const generatedSampleId = ref('')

// Auto-fill GPS + location when WSS selected
watch(() => pheForm.value.water_scheme_id, (val) => {
  const wss = wssOptions.value.find(w => w.id == val)
  if (!wss) return
  pheForm.value.sourceGps = wss.latitude && wss.longitude
    ? `${wss.latitude}, ${wss.longitude}` : ''
  pheForm.value.sample_name = wss.name || ''
  pheForm.value.water_sample_address = wss.name || ''
  // Cascade location from WSS district
  const loc = resolveLocation(wss.district_id)
  Object.assign(pheForm.value, loc)
})

// When division changes, reset dependent fields
watch(() => pheForm.value.division_id, () => {
  pheForm.value.district_id      = ''
  pheForm.value.phed_division_id = ''
  pheForm.value.hub_lab_id       = ''
})

// When district changes, cascade circle/region/province/hublab/phed
watch(() => pheForm.value.district_id, (val) => {
  pheForm.value.phed_division_id = ''
  if (!val) return
  const loc = resolveLocation(val)
  pheForm.value.circle_id      = loc.circle_id
  pheForm.value.region_id      = loc.region_id
  pheForm.value.province_id    = loc.province_id
  pheForm.value.hub_lab_id     = loc.hub_lab_id
  pheForm.value.phed_division_id = loc.phed_division_id
})

// Auto-update reporting date
watch(() => pheForm.value.collectionDate, (val) => {
  if (val) pheForm.value.reportingDate = addDays(val, 3)
})

const showConsumerFields = computed(() => pheForm.value.collectionPoint === 'Consumer End')
const showTestMethod     = computed(() => ['PCM','M','PC','C'].includes(pheForm.value.testType))
const showParamChecklist = computed(() => pheForm.value.testType === 'SEL')

const pheParams   = ref({ physical:[], chemical:[], microbial:[] })
const heavyMetals = ref([])

const physicalParams  = ['pH','Turbidity (NTU)','TDS (mg/L)','Colour (TCU)','Taste','Odour','Temperature (°C)']
const chemicalParams  = ['Arsenic (µg/L)','Fluoride (mg/L)','Nitrates (mg/L)','Nitrites (mg/L)','Total Hardness (mg/L)','Calcium Hardness (mg/L)','Chlorides (mg/L)','Sulphates (mg/L)','Total Alkalinity (mg/L)','Iron (mg/L)','Manganese (mg/L)','Residual Chlorine (mg/L)','Ammonia (mg/L)']
const microbialParams = ['E. coli (CFU/100mL)','Total Coliform (CFU/100mL)']
const heavyMetalsList = ['Arsenic (As)','Lead (Pb)','Chromium (Cr)','Cadmium (Cd)','Mercury (Hg)','Nickel (Ni)','Zinc (Zn)','Copper (Cu)','Manganese (Mn)','Iron (Fe)','Barium (Ba)','Selenium (Se)']

async function savePhe() {
  errorMsg.value   = ''
  successMsg.value = ''

  if (!pheForm.value.water_scheme_id)  { errorMsg.value = 'Please select a Water Supply Scheme'; return }
  if (!pheForm.value.division_id)      { errorMsg.value = 'Please select a Division'; return }
  if (!pheForm.value.district_id)      { errorMsg.value = 'Please select a District'; return }
  if (!pheForm.value.province_id)      { errorMsg.value = 'Province could not be resolved — check location data'; return }
  if (isPeshawarDistrictPHE.value && !pheForm.value.phed_division_id) { errorMsg.value = 'Please select a PHED Division'; return }

  const [lat, lng] = pheForm.value.sourceGps.split(',').map(s => s.trim())
  if (!lat || !lng) { errorMsg.value = 'GPS coordinates are required (select a WSS)'; return }

  saveLoading.value = true
  try {
    const desired = desiredTestsFor(pheForm.value.testType, pheForm.value.testMethod)
    const payload = {
      collectable_type:      'PHE',
      water_scheme_id:       pheForm.value.water_scheme_id,
      sample_name:           pheForm.value.sample_name || pheForm.value.water_sample_address,
      water_sample_address:  pheForm.value.water_sample_address || pheForm.value.sample_name,
      sampling_point:        pheForm.value.collectionPoint,
      source_type:           pheForm.value.sourceType || 'Pumping',
      source_sub_type:       pheForm.value.sourceSubType || 'Tube Well',
      latitude:              lat,
      longitude:             lng,
      sampled_at:            toDateTime(pheForm.value.collectionDate, pheForm.value.collectionTime + ':00'),
      reported_at:           toDateTime(pheForm.value.reportingDate, '09:00:00'),
      collected_in:          pheForm.value.containerType,
      collected_by:          pheForm.value.collectedBy,
      complaint:             pheForm.value.reasonForTesting,
      desired_test:          desired,
      test_type:             'Fresh',
      temperature_in_celsius: pheForm.value.temperature,
      division_id:           pheForm.value.division_id,
      district_id:           pheForm.value.district_id,
      circle_id:             pheForm.value.circle_id   || undefined,
      region_id:             pheForm.value.region_id   || undefined,
      province_id:           pheForm.value.province_id,
      hub_lab_id:            pheForm.value.hub_lab_id  || undefined,
      phed_division_id:      pheForm.value.phed_division_id || undefined,
    }

    const res = await api.post('/water-samples', payload)
    generatedSampleId.value = res.data?.slug || res.data?.id || 'Saved'
    successMsg.value = `✅ Sample saved! ID: ${generatedSampleId.value}`
    // Reset sample-specific fields, keep location
    pheForm.value.water_scheme_id = ''
    pheForm.value.sourceGps       = ''
    pheForm.value.sample_name     = ''
    pheForm.value.water_sample_address = ''
    pheForm.value.collectionDate  = today()
    heavyMetals.value = []
    pheParams.value   = { physical:[], chemical:[], microbial:[] }
  } catch (e) {
    const errs = e.response?.data?.errors
    const msg = errs
      ? Object.entries(errs).map(([field, msgs]) => `${field}: ${Array.isArray(msgs) ? msgs[0] : msgs}`).join(' | ')
      : (e.response?.data?.message || e.message || 'Error saving sample')
    errorMsg.value = msg
    console.error('PHE save error full:', JSON.stringify(e.response?.data, null, 2))
  } finally {
    saveLoading.value = false
  }
}

// ── Private Client Form ───────────────────────────────────────────────
const pvtClientType  = ref('existing')
const selectedClient = ref(null)
const clientSearch   = ref('')

const filteredClients = computed(() => {
  if (!clientSearch.value) return clients.value
  const q = clientSearch.value.toLowerCase()
  return clients.value.filter(c =>
    c.name?.toLowerCase().includes(q) || c.phone?.includes(q)
  )
})

const pvtForm = ref({
  division_id: '', district_id: '', circle_id: '', region_id: '',
  province_id: '', hub_lab_id: '', phed_division_id: '',
  sampleName: '', gps: '',
  collectionDate: today(), collectionTime: '10:15',
  reportingDate: addDays(today(), 3),
  collectionPoint: 'Source', sourceType: 'Pumping', sourceSubType: 'Tube Well',
  containerType: 'Plastic Bottle', collectedBy: 'Laboratory Staff',
  reasonForTesting: 'General Q.Analysis',
  testType: 'PCM', testMethod: 'MF Method — Membrane Filtration',
  temperature: 20,
  // New client fields
  name: '', phone: '', email: '', address: '', type: 'individual', organization_name: '',
})

watch(() => pvtForm.value.division_id, () => {
  pvtForm.value.district_id      = ''
  pvtForm.value.phed_division_id = ''
  pvtForm.value.hub_lab_id       = ''
})

watch(() => pvtForm.value.district_id, (val) => {
  pvtForm.value.phed_division_id = ''
  if (!val) return
  const loc = resolveLocation(val)
  pvtForm.value.circle_id       = loc.circle_id
  pvtForm.value.region_id       = loc.region_id
  pvtForm.value.province_id     = loc.province_id
  pvtForm.value.hub_lab_id      = loc.hub_lab_id
  pvtForm.value.phed_division_id = loc.phed_division_id
})

watch(() => pvtForm.value.collectionDate, (val) => {
  if (val) pvtForm.value.reportingDate = addDays(val, 3)
})

const pvtPhedDivs = computed(() =>
  pvtForm.value.district_id
    ? allPhedDivs.value.filter(p => p.district_id == pvtForm.value.district_id)
    : allPhedDivs.value
)
const pvtHubLabs = computed(() =>
  pvtForm.value.division_id
    ? allHubLabs.value.filter(h => h.division_id == pvtForm.value.division_id)
    : allHubLabs.value
)

// Check if selected district is Peshawar (Private form)
const isPeshawarDistrictPVT = computed(() => {
  if (!pvtForm.value.district_id || !allDistricts.value.length) return false
  const district = allDistricts.value.find(d => d.id == pvtForm.value.district_id)
  const name = district?.name?.trim().toLowerCase() || ''
  return name === 'peshawar' || name.startsWith('peshawar')
})

async function savePvt() {
  errorMsg.value   = ''
  successMsg.value = ''
  if (!pvtForm.value.district_id)  { errorMsg.value = 'Please select a District'; return }
  if (!pvtForm.value.province_id)  { errorMsg.value = 'Province could not be resolved'; return }
  if (isPeshawarDistrictPVT.value && !pvtForm.value.phed_division_id) { errorMsg.value = 'Please select a PHED Division'; return }
  if (pvtClientType.value === 'new') {
    if (!pvtForm.value.name)    { errorMsg.value = 'Client name is required'; return }
    if (!pvtForm.value.phone)   { errorMsg.value = 'Client phone is required'; return }
    if (!pvtForm.value.address) { errorMsg.value = 'Client address is required'; return }
  }
  if (pvtClientType.value === 'existing' && !selectedClient.value) {
    errorMsg.value = 'Please select an existing client'; return
  }

  saveLoading.value = true
  try {
    const [lat, lng] = (pvtForm.value.gps || '0,0').split(',').map(s => s.trim())
    const desired = desiredTestsFor(pvtForm.value.testType, pvtForm.value.testMethod)
    const clientData = pvtClientType.value === 'existing'
      ? { name: selectedClient.value.name, phone: selectedClient.value.phone,
          email: selectedClient.value.email || '', address: selectedClient.value.address || '',
          type: selectedClient.value.type || 'individual' }
      : { name: pvtForm.value.name, phone: pvtForm.value.phone,
          email: pvtForm.value.email, address: pvtForm.value.address,
          type: pvtForm.value.type, organization_name: pvtForm.value.organization_name }

    const payload = {
      collectable_type:      'Private',
      sample_name:           pvtForm.value.sampleName || clientData.name,
      water_sample_address:  pvtForm.value.sampleName || clientData.address,
      sampling_point:        pvtForm.value.collectionPoint,
      source_type:           pvtForm.value.sourceType || 'Pumping',
      source_sub_type:       pvtForm.value.sourceSubType || 'Tube Well',
      latitude:              lat || '0',
      longitude:             lng || '0',
      sampled_at:            toDateTime(pvtForm.value.collectionDate, pvtForm.value.collectionTime + ':00'),
      reported_at:           toDateTime(pvtForm.value.reportingDate, '09:00:00'),
      collected_in:          pvtForm.value.containerType,
      collected_by:          pvtForm.value.collectedBy,
      complaint:             pvtForm.value.reasonForTesting,
      desired_test:          desired,
      test_type:             'Fresh',
      temperature_in_celsius: pvtForm.value.temperature,
      division_id:           pvtForm.value.division_id,
      district_id:           pvtForm.value.district_id,
      circle_id:             pvtForm.value.circle_id,
      region_id:             pvtForm.value.region_id,
      province_id:           pvtForm.value.province_id,
      hub_lab_id:            pvtForm.value.hub_lab_id,
      phed_division_id:      pvtForm.value.phed_division_id,
      ...clientData,
    }

    const res = await api.post('/water-samples', payload)
    successMsg.value = `✅ Sample saved! ID: ${res.data?.slug || res.data?.id}`
    pvtForm.value.sampleName = ''
    pvtForm.value.gps        = ''
    pvtForm.value.collectionDate = today()
    selectedClient.value = null
    clientSearch.value   = ''
  } catch (e) {
    const errs = e.response?.data?.errors
    errorMsg.value = errs
      ? Object.values(errs).flat().join(' | ')
      : (e.response?.data?.message || e.message || 'Error saving private sample')
    console.error('PVT save error:', e.response?.data || e)
  } finally {
    saveLoading.value = false
  }
}

// ── PT Form ───────────────────────────────────────────────────────────
const ptForm = ref({
  round: '', provider: '', programme: '', year: new Date().getFullYear().toString(),
  receiptDate: today(), deadline: '',
  sampleCount: 1, matrix: 'drinking', scope: 'PCM',
  condition: 'ok', receivedBy: '', courier: 'post',
  tracking: '', coc: '', remarks: '',
})

async function savePT() {
  errorMsg.value   = ''
  successMsg.value = ''
  if (!ptForm.value.round)    { errorMsg.value = 'PT Round is required'; return }
  if (!ptForm.value.provider) { errorMsg.value = 'PT Provider is required'; return }

  saveLoading.value = true
  try {
    const user = userStore.currentUser
    const loc  = user?.district_id ? resolveLocation(user.district_id) : {}
    if (!loc.province_id) { errorMsg.value = 'User location not set — cannot save PT sample'; saveLoading.value = false; return }

    const payload = {
      collectable_type:      'PHE',
      test_type:             'Fresh',
      desired_test:          ['Physical', 'Physical & Chemical', 'Microbiological(MF)'],
      sampling_point:        'Source',
      source_type:           'Pumping',
      source_sub_type:       'Tube Well',
      collected_by:          'Laboratory Staff',
      collected_in:          'Plastic Bottle',
      complaint:             'General Q.Analysis',
      temperature_in_celsius: 20,
      sample_name:           `PT-${ptForm.value.round}`,
      water_sample_address:  `PT Round: ${ptForm.value.round} — Provider: ${ptForm.value.provider}`,
      latitude:              '0',
      longitude:             '0',
      sampled_at:            toDateTime(ptForm.value.receiptDate, '09:00:00'),
      reported_at:           toDateTime(ptForm.value.deadline || addDays(ptForm.value.receiptDate, 5), '09:00:00'),
      ...loc,
    }

    const res = await api.post('/water-samples', payload)
    successMsg.value = `✅ PT Sample saved! ID: ${res.data?.slug || res.data?.id}`
    ptForm.value.round = ''
  } catch (e) {
    const errs = e.response?.data?.errors
    errorMsg.value = errs
      ? Object.values(errs).flat().join(' | ')
      : (e.response?.data?.message || e.message || 'Error saving PT sample')
    console.error('PT save error:', e.response?.data || e)
  } finally {
    saveLoading.value = false
  }
}
</script>

<template>
  <div>
    <!-- Tabs -->
    <div class="tabs">
      <div class="tab" :class="{ active: activeTab === 'phe' }"   @click="activeTab = 'phe'">🏗 PHE / WSS Sample</div>
      <div class="tab" :class="{ active: activeTab === 'pvt' }"   @click="activeTab = 'pvt'">👤 Private Client Sample</div>
      <div class="tab" :class="{ active: activeTab === 'pt' }"    @click="activeTab = 'pt'">
        🔬 PT Sample
        <span style="font-size:9px;background:#7c3aed;color:#fff;border-radius:3px;padding:1px 5px;margin-left:4px;vertical-align:middle">Proficiency</span>
      </div>
    </div>

    <!-- ── PHE FORM ── -->
    <div v-if="activeTab === 'phe'">
      <!-- Messages -->
      <div v-if="successMsg" style="background:#d1fae5;border:1px solid #6ee7b7;border-radius:5px;padding:9px 14px;margin-bottom:10px;font-size:12.5px;color:#065f46">
        {{ successMsg }} <button @click="successMsg=''" style="float:right;background:none;border:none;cursor:pointer">✕</button>
      </div>
      <div v-if="errorMsg" style="background:#fee2e2;border:1px solid #fca5a5;border-radius:5px;padding:9px 14px;margin-bottom:10px;font-size:12.5px;color:#991b1b">
        {{ errorMsg }} <button @click="errorMsg=''" style="float:right;background:none;border:none;cursor:pointer">✕</button>
      </div>

      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
        <h3 style="margin-bottom:0">New PHE Sample Registration</h3>
        <div style="background:var(--sky2);border:1px solid var(--sky);border-radius:5px;padding:5px 14px;font-size:12px;color:var(--navy2)">
          🔖 Sample ID: <b>{{ generatedSampleId || 'Auto-generated on Save' }}</b>
        </div>
      </div>

      <!-- A — Location -->
      <div style="margin-bottom:14px">
        <div class="section-group-header">📍 A — Location</div>
        <div class="form-grid">
          <div class="fg2">
            <label>Division *</label>
            <select v-model="pheForm.division_id">
              <option value="">— Select Division —</option>
              <option v-for="d in allDivisions" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
          </div>
          <div class="fg2">
            <label>District *</label>
            <select v-model="pheForm.district_id" :disabled="!pheForm.division_id">
              <option value="">— Select District —</option>
              <option v-for="d in pheDistricts" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
          </div>
          <div class="fg2" v-if="isPeshawarDistrictPHE">
            <label>PHE Division *</label>
            <select v-model="pheForm.phed_division_id">
              <option value="">— Select PHE Division —</option>
              <option v-for="d in phePhedDivs" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
          </div>
          <!-- DEBUG: remove after confirming it works -->
          <div v-if="pheForm.district_id && !isPeshawarDistrictPHE" style="font-size:10px;color:var(--muted);padding:4px 8px;background:#fff3cd;border-radius:4px">
            Selected district: "{{ allDistricts.find(d => d.id == pheForm.district_id)?.name }}" — PHE Division only shown for Peshawar
          </div>
          <div class="fg2 span2">
            <label>Water Supply Scheme (WSS) *</label>
            <select v-model="pheForm.water_scheme_id">
              <option value="">— Select WSS —</option>
              <option v-for="w in wssOptions" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
          </div>
          <div class="fg2">
            <label>Collection Point *</label>
            <select v-model="pheForm.collectionPoint">
              <option value="Source">Source (WSS Outlet)</option>
              <option value="Consumer End">C/End</option>
              <option value="Mid">Mid</option>
            </select>
          </div>
          <div class="fg2">
            <label>Source Sub-Type *</label>
            <select v-model="pheForm.sourceSubType">
              <option value="Tube Well">Tube Well</option>
              <option value="Hand Pump">Hand Pump</option>
              <option value="Dam">Dam</option>
              <option value="Reservoir">Reservoir</option>
              <option value="Press: Pump">Press: Pump</option>
            </select>
          </div>
          <div class="fg2" v-if="!showConsumerFields">
            <label>Source GPS <span style="font-size:10px;color:var(--muted)">— auto-filled</span></label>
            <input type="text" :value="pheForm.sourceGps" readonly style="background:#f0f7ff;color:var(--muted)" placeholder="Select WSS above…">
          </div>
          <div class="fg2" v-if="showConsumerFields">
            <label>Consumer Point Name <span style="font-size:10px;color:var(--red)">★ required</span></label>
            <input type="text" v-model="pheForm.consumerName" placeholder="e.g. Ali Home, School No. 2…">
          </div>
          <div class="fg2" v-if="showConsumerFields">
            <label>Sample GPS <span style="font-size:10px;color:var(--muted)">(optional)</span></label>
            <input type="text" v-model="pheForm.consumerGps" placeholder="34.0151, 71.5249">
          </div>
        </div>
      </div>

      <!-- B — Collection Details -->
      <div style="margin-bottom:14px">
        <div class="section-group-header">🧪 B — Collection Details</div>
        <div class="form-grid">
          <div class="fg2">
            <label>Collection Date *</label>
            <input type="date" v-model="pheForm.collectionDate">
          </div>
          <div class="fg2">
            <label>Collection Time *</label>
            <input type="time" v-model="pheForm.collectionTime">
          </div>
          <div class="fg2">
            <label>Container Type *</label>
            <select v-model="pheForm.containerType">
              <option v-for="ci in collectedInOpts" :key="ci" :value="ci">{{ ci }}</option>
            </select>
          </div>
          <div class="fg2">
            <label>Collected By *</label>
            <select v-model="pheForm.collectedBy">
              <option value="Laboratory Staff">Lab Staff</option>
              <option value="Client">Client</option>
            </select>
          </div>
          <div class="fg2">
            <label>Reporting Date <span style="font-size:10px;color:var(--muted)">(auto: +3 days)</span></label>
            <input type="date" v-model="pheForm.reportingDate">
          </div>
          <div class="fg2">
            <label>Temperature (°C)</label>
            <input type="number" v-model="pheForm.temperature" min="-5" max="50">
          </div>
        </div>
      </div>

      <!-- C — Testing Requirements -->
      <div style="margin-bottom:14px">
        <div class="section-group-header">🔬 C — Testing Requirements</div>
        <div class="form-grid">
          <div class="fg2">
            <label>Reason for Testing *</label>
            <select v-model="pheForm.reasonForTesting">
              <option v-for="r in reasonOpts" :key="r" :value="r">{{ r }}</option>
            </select>
          </div>
          <div class="fg2">
            <label>Required Test Type *</label>
            <select v-model="pheForm.testType">
              <option value="PCM">PCM — Physical + Chemical + Microbial</option>
              <option value="PC">PC — Physical + Chemical</option>
              <option value="P">P — Physical only</option>
              <option value="C">C — Chemical only</option>
              <option value="M">M — Microbial only</option>
              <option value="SEL">Selected Parameter(s)</option>
            </select>
          </div>
          <div class="fg2" v-if="showTestMethod">
            <label>Test Method *</label>
            <select v-model="pheForm.testMethod">
              <option value="MF Method — Membrane Filtration">MF Method — Membrane Filtration</option>
              <option value="Kit Method — H₂S / Colilert">Kit Method — H₂S / Colilert</option>
            </select>
          </div>
        </div>

        <!-- Parameter checklist -->
        <div v-if="showParamChecklist" style="background:#f8f9ff;border:1px solid #c5d0e8;border-radius:5px;padding:12px 16px;margin-top:8px">
          <div style="font-size:11px;font-weight:700;color:var(--navy2);margin-bottom:10px">📋 Select Parameter(s) to Test</div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0">
            <div>
              <div class="param-group-header blue">🔵 Physical</div>
              <div class="param-group-body blue">
                <label v-for="p in physicalParams" :key="p" class="chk-lbl">
                  <input type="checkbox" v-model="pheParams.physical" :value="p"> {{ p }}
                </label>
              </div>
            </div>
            <div>
              <div class="param-group-header amber">🟠 Chemical</div>
              <div class="param-group-body amber">
                <label v-for="p in chemicalParams" :key="p" class="chk-lbl">
                  <input type="checkbox" v-model="pheParams.chemical" :value="p"> {{ p }}
                </label>
              </div>
            </div>
            <div>
              <div class="param-group-header red">🔴 Microbial</div>
              <div class="param-group-body red">
                <label v-for="p in microbialParams" :key="p" class="chk-lbl">
                  <input type="checkbox" v-model="pheParams.microbial" :value="p"> {{ p }}
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- Heavy Metals -->
        <div style="background:var(--sky2);border:1px solid var(--sky);border-radius:5px;padding:10px 14px;margin-top:6px">
          <div style="font-size:11px;font-weight:700;color:var(--navy2);margin-bottom:8px">⚗ Special Tests — Heavy Metals</div>
          <div style="display:flex;flex-wrap:wrap;gap:8px">
            <label v-for="m in heavyMetalsList" :key="m" style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;background:var(--white);border:1px solid var(--border);border-radius:4px;padding:4px 10px">
              <input type="checkbox" v-model="heavyMetals" :value="m"> {{ m }}
            </label>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:8px;align-items:center">
        <button class="btn btn-pri" @click="savePhe" :disabled="saveLoading">
          {{ saveLoading ? '⏳ Saving…' : '💾 Save & Print Label' }}
        </button>
        <button class="btn btn-sec">🖨 Print Barcode Label</button>
        <button class="btn btn-sec" @click="pheForm.water_scheme_id=''; pheForm.sourceGps=''; successMsg=''; errorMsg=''">+ New</button>
        <span style="font-size:11px;color:var(--muted);margin-left:6px">📅 Reporting date: <b>{{ pheForm.reportingDate }}</b> (auto: receipt + 3 days)</span>
      </div>
    </div>

    <!-- ── PRIVATE CLIENT FORM ── -->
    <div v-if="activeTab === 'pvt'" class="panel">
      <!-- Messages -->
      <div v-if="successMsg" style="background:#d1fae5;border:1px solid #6ee7b7;border-radius:5px;padding:9px 14px;margin-bottom:10px;font-size:12.5px;color:#065f46">
        {{ successMsg }} <button @click="successMsg=''" style="float:right;background:none;border:none;cursor:pointer">✕</button>
      </div>
      <div v-if="errorMsg" style="background:#fee2e2;border:1px solid #fca5a5;border-radius:5px;padding:9px 14px;margin-bottom:10px;font-size:12.5px;color:#991b1b">
        {{ errorMsg }} <button @click="errorMsg=''" style="float:right;background:none;border:none;cursor:pointer">✕</button>
      </div>

      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
        <h3 style="margin-bottom:0">New Private Client Sample Registration</h3>
        <div style="background:var(--sky2);border:1px solid var(--sky);border-radius:5px;padding:5px 14px;font-size:12px;color:var(--navy2)">
          🔖 Sample ID: Auto-generated on Save
        </div>
      </div>

      <!-- Client type toggle -->
      <div style="display:flex;gap:0;margin-bottom:12px;border:1px solid var(--border);border-radius:5px;overflow:hidden;width:fit-content">
        <div @click="pvtClientType = 'existing'" :style="pvtClientType==='existing' ? 'background:var(--navy);color:#fff' : 'background:#fff;color:var(--muted)'" style="padding:6px 20px;font-size:12px;font-weight:600;cursor:pointer">🔍 Existing Client</div>
        <div @click="pvtClientType = 'new'"      :style="pvtClientType==='new'      ? 'background:var(--navy);color:#fff' : 'background:#fff;color:var(--muted)'" style="padding:6px 20px;font-size:12px;font-weight:600;cursor:pointer">➕ New Client</div>
      </div>

      <!-- Existing client search -->
      <div v-if="pvtClientType === 'existing'" style="margin-bottom:12px">
        <input type="text" v-model="clientSearch" placeholder="🔍 Search by name or phone…"
          style="width:100%;margin-bottom:8px;border:1px solid var(--input-border);border-radius:4px;padding:6px 10px;font-size:12px;font-family:inherit">
        <div style="max-height:150px;overflow-y:auto;border:1px solid var(--border);border-radius:5px">
          <div v-if="!filteredClients.length" style="padding:10px 14px;font-size:12px;color:var(--muted)">No clients found</div>
          <div v-for="c in filteredClients" :key="c.id"
            @click="selectedClient = c"
            :style="selectedClient?.id === c.id ? 'background:var(--sky2);border-left:3px solid var(--blue)' : 'background:#fff'"
            style="padding:8px 14px;cursor:pointer;font-size:12px;border-bottom:1px solid var(--border)">
            <b>{{ c.name }}</b> &nbsp;·&nbsp; {{ c.phone }} &nbsp;·&nbsp; <span style="color:var(--muted)">{{ c.type }}</span>
          </div>
        </div>
        <div v-if="selectedClient" style="background:var(--sky2);border:1px solid var(--sky);border-radius:5px;padding:8px 14px;margin-top:8px">
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;font-size:12px">
            <div><div style="font-size:10px;color:var(--muted);font-weight:600">CLIENT NAME</div><div style="font-weight:600">{{ selectedClient.name }}</div></div>
            <div><div style="font-size:10px;color:var(--muted);font-weight:600">TYPE</div><div>{{ selectedClient.type }}</div></div>
            <div><div style="font-size:10px;color:var(--muted);font-weight:600">PHONE</div><div>{{ selectedClient.phone }}</div></div>
          </div>
        </div>
      </div>

      <!-- New client fields -->
      <div v-if="pvtClientType === 'new'" class="form-grid" style="margin-bottom:12px">
        <div class="fg2"><label>Client Type *</label>
          <select v-model="pvtForm.type"><option value="individual">Individual</option><option value="organization">Organization</option></select>
        </div>
        <div class="fg2 span2"><label>Full Name / Organization Name *</label>
          <input type="text" v-model="pvtForm.name" placeholder="e.g. Ahmad Khan, Al-Noor Hospital…">
        </div>
        <div class="fg2"><label>Phone Number *</label>
          <input type="text" v-model="pvtForm.phone" placeholder="03xx-xxxxxxx">
        </div>
        <div class="fg2"><label>Email</label>
          <input type="text" v-model="pvtForm.email" placeholder="email@example.com">
        </div>
        <div class="fg2 span2"><label>Address *</label>
          <input type="text" v-model="pvtForm.address" placeholder="Street, Area, District…">
        </div>
      </div>

      <!-- Location -->
      <div style="margin-bottom:12px">
        <div class="section-group-header">📍 Location</div>
        <div class="form-grid">
          <div class="fg2">
            <label>Division *</label>
            <select v-model="pvtForm.division_id">
              <option value="">— Select Division —</option>
              <option v-for="d in allDivisions" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
          </div>
          <div class="fg2">
            <label>District *</label>
            <select v-model="pvtForm.district_id" :disabled="!pvtForm.division_id">
              <option value="">— Select District —</option>
              <option v-for="d in pvtDistricts" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
          </div>
          <div class="fg2" v-if="isPeshawarDistrictPVT">
            <label>PHED Division *</label>
            <select v-model="pvtForm.phed_division_id">
              <option value="">— Select PHED Division —</option>
              <option v-for="p in pvtPhedDivs" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>
          <div class="fg2">
            <label>GPS <span style="font-size:10px;color:var(--muted)">(optional)</span></label>
            <input type="text" v-model="pvtForm.gps" placeholder="34.0151, 71.5249">
          </div>
        </div>
      </div>

      <!-- Collection & Testing -->
      <div class="form-grid" style="margin-bottom:12px">
        <div class="fg2"><label>Collection Date *</label><input type="date" v-model="pvtForm.collectionDate"></div>
        <div class="fg2"><label>Collection Time</label><input type="time" v-model="pvtForm.collectionTime"></div>
        <div class="fg2">
          <label>Container Type *</label>
          <select v-model="pvtForm.containerType">
            <option v-for="ci in collectedInOpts" :key="ci" :value="ci">{{ ci }}</option>
          </select>
        </div>
        <div class="fg2">
          <label>Collected By *</label>
          <select v-model="pvtForm.collectedBy">
            <option value="Laboratory Staff">Lab Staff</option>
            <option value="Client">Client</option>
          </select>
        </div>
        <div class="fg2">
          <label>Reason for Testing *</label>
          <select v-model="pvtForm.reasonForTesting">
            <option v-for="r in reasonOpts" :key="r" :value="r">{{ r }}</option>
          </select>
        </div>
        <div class="fg2">
          <label>Test Type *</label>
          <select v-model="pvtForm.testType">
            <option value="PCM">PCM — Physical + Chemical + Microbial</option>
            <option value="PC">PC — Physical + Chemical</option>
            <option value="P">P — Physical only</option>
            <option value="C">C — Chemical only</option>
            <option value="M">M — Microbial only</option>
          </select>
        </div>
        <div class="fg2">
          <label>Test Method</label>
          <select v-model="pvtForm.testMethod">
            <option value="MF Method — Membrane Filtration">MF Method — Membrane Filtration</option>
            <option value="Kit Method — H₂S / Colilert">Kit Method — H₂S / Colilert</option>
          </select>
        </div>
        <div class="fg2">
          <label>Sample Name</label>
          <input type="text" v-model="pvtForm.sampleName" placeholder="e.g. Tap Water — Kitchen">
        </div>
      </div>

      <div style="display:flex;gap:8px;align-items:center;margin-top:14px">
        <button class="btn btn-pri" @click="savePvt" :disabled="saveLoading">
          {{ saveLoading ? '⏳ Saving…' : '💾 Save & Generate Invoice' }}
        </button>
        <button class="btn btn-sec">🖨 Print Barcode Label</button>
        <button class="btn btn-sec" @click="selectedClient=null; clientSearch=''; successMsg=''; errorMsg=''">+ New</button>
      </div>
    </div>

    <!-- ── PT FORM ── -->
    <div v-if="activeTab === 'pt'" class="panel">
      <!-- Messages -->
      <div v-if="successMsg" style="background:#d1fae5;border:1px solid #6ee7b7;border-radius:5px;padding:9px 14px;margin-bottom:10px;font-size:12.5px;color:#065f46">
        {{ successMsg }} <button @click="successMsg=''" style="float:right;background:none;border:none;cursor:pointer">✕</button>
      </div>
      <div v-if="errorMsg" style="background:#fee2e2;border:1px solid #fca5a5;border-radius:5px;padding:9px 14px;margin-bottom:10px;font-size:12.5px;color:#991b1b">
        {{ errorMsg }} <button @click="errorMsg=''" style="float:right;background:none;border:none;cursor:pointer">✕</button>
      </div>

      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
        <div>
          <h3 style="margin-bottom:2px">🔬 PT Sample Registration</h3>
          <div style="font-size:11.5px;color:var(--muted)">Proficiency Testing — Inter-lab / PCRWR / PCSIR / Third-Party Blind Samples</div>
        </div>
        <div style="background:#f3e8ff;border:1px solid #c4b5fd;border-radius:5px;padding:5px 14px;font-size:12px;color:#5b21b6">
          🔖 PT Sample ID: <b>PT/26/CLB/001</b>
        </div>
      </div>

      <div style="background:#f3e8ff;border-left:4px solid #7c3aed;border-radius:0 6px 6px 0;padding:9px 14px;margin-bottom:16px;font-size:11.5px;color:#3b0764">
        <b>ℹ PT samples</b> are blind water quality samples sent by an external provider to assess lab accuracy.
      </div>

      <div class="form-grid">
        <div class="fg2"><label>PT Round / Batch No. *</label><input type="text" v-model="ptForm.round" placeholder="e.g. PT-2026-R1, PCRWR-Q1-2026" style="font-family:monospace"></div>
        <div class="fg2">
          <label>PT Provider *</label>
          <select v-model="ptForm.provider">
            <option value="">— Select Provider —</option>
            <option value="PCRWR">PCRWR</option>
            <option value="PCSIR">PCSIR</option>
            <option value="HO">PHED Head Office (Internal PT)</option>
            <option value="WHO">WHO / UNICEF Supported</option>
            <option value="Other">Other External Provider</option>
          </select>
        </div>
        <div class="fg2">
          <label>PT Programme Type *</label>
          <select v-model="ptForm.programme">
            <option value="">— Select —</option>
            <option value="ILC">Inter-Lab Comparison (ILC)</option>
            <option value="EQA">External Quality Assurance (EQA)</option>
            <option value="blind">Blind Sample Audit</option>
            <option value="retest">Retest / Verification Round</option>
          </select>
        </div>
        <div class="fg2"><label>PT Dispatch / Receipt Date *</label><input type="date" v-model="ptForm.receiptDate"></div>
        <div class="fg2"><label>Analysis Deadline *</label><input type="date" v-model="ptForm.deadline"></div>
        <div class="fg2">
          <label>No. of PT Samples *</label>
          <input type="number" v-model="ptForm.sampleCount" min="1" max="20">
        </div>
        <div class="fg2">
          <label>Sample Matrix *</label>
          <select v-model="ptForm.matrix">
            <option value="drinking">Drinking Water</option>
            <option value="groundwater">Groundwater</option>
            <option value="surface">Surface Water</option>
            <option value="treated">Treated Water</option>
          </select>
        </div>
        <div class="fg2">
          <label>Test Scope Required *</label>
          <select v-model="ptForm.scope">
            <option value="PCM">PCM — Physical + Chemical + Microbial</option>
            <option value="PC">PC — Physical + Chemical</option>
            <option value="M">M — Microbial only</option>
            <option value="C">C — Chemical only</option>
          </select>
        </div>
        <div class="fg2">
          <label>Sample Condition on Receipt</label>
          <select v-model="ptForm.condition">
            <option value="ok">✅ Acceptable — intact, within temp</option>
            <option value="warn">⚠ Minor concern — noted but proceeding</option>
            <option value="reject">❌ Rejected — compromised / expired</option>
          </select>
        </div>
        <div class="fg2">
          <label>Received By (Lab Staff) *</label>
          <select v-model="ptForm.receivedBy">
            <option value="">— Select —</option>
            <option>Dr. Sana Ullah — Chemist</option>
            <option>Mr. Imran Rauf — Lab Technician</option>
            <option>Ms. Nadia Bibi — Microbiologist</option>
          </select>
        </div>
        <div class="fg2 span2"><label>Receipt Remarks</label><textarea v-model="ptForm.remarks" rows="2" placeholder="e.g. Sample arrived cold, seals intact…"></textarea></div>
      </div>

      <div style="display:flex;gap:8px;align-items:center;padding-top:10px;border-top:1px solid var(--border)">
        <button class="btn btn-pri" @click="savePT" :disabled="saveLoading">
          {{ saveLoading ? '⏳ Saving…' : '💾 Save & Assign to Queue' }}
        </button>
        <button class="btn btn-sec">🖨 Save &amp; Print Label</button>
        <button class="btn btn-sec" style="border-color:#7c3aed;color:#7c3aed" @click="ptForm.round=''; ptForm.provider=''; successMsg=''; errorMsg=''">↺ Reset</button>
        <span style="font-size:11px;color:var(--muted);margin-left:6px">📅 Reporting deadline auto-set: Receipt + 5 days</span>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.section-group-header {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .07em;
  color: var(--blue);
  margin-bottom: 7px;
  padding-bottom: 4px;
  border-bottom: 2px solid var(--sky);
}
.param-group-header {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  padding: 4px 10px;
  border-radius: 4px 4px 0 0;
  &.blue  { color: var(--blue);  background: var(--sky2); border: 1px solid var(--sky); }
  &.amber { color: #b45000; background: #fff3e0; border: 1px solid #f4a236; }
  &.red   { color: #b71c1c; background: #fff3f3; border: 1px solid #f5c6c6; }
}
.param-group-body {
  padding: 6px 10px;
  display: flex;
  flex-direction: column;
  gap: 5px;
  border-radius: 0 0 4px 4px;
  &.blue  { border: 1px solid var(--sky);  border-top: none; }
  &.amber { border: 1px solid #f4a236; border-top: none; }
  &.red   { border: 1px solid #f5c6c6; border-top: none; }
}
</style>