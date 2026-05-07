<script setup>
import { ref, computed, onMounted } from 'vue'
import { reportService } from '../../../services/reportService.js'
import { dropdownService } from '../../../services/dropdownService.js'
import { exportToExcel } from '../../../utils/exportHelpers.js'

const loading    = ref(false)
const errorMsg   = ref('')
const divisions  = ref([])
const districts  = ref([])
const laboratories = ref([])

const filters = ref({ from_date:'', to_date:'', region:'', division_id:'', district_id:'', laboratory_id:'', result:'' })

const allRows = ref([])

function mapSampleRow(s, idx) {
  return {
    sn: idx + 1,
    id: s.slug || String(s.id),
    wss: s.water_scheme?.name || s.water_sample_address || '—',
    date: s.sampled_at ? s.sampled_at.split(' ')[0] : '—',
    point: s.sampling_point || '—',
    div: s.phed_division?.name || s.division?.name || '—',
    lat: s.latitude || '—',
    lng: s.longitude || '—',
    type: s.test_type || '—',
    result: s.result || '—',
    cause: s.analysis_result_cause || '—',
    ion: s.analysis_result_detail || '—',
  }
}

async function loadDropdowns() {
  try {
    const [divRes, labRes] = await Promise.all([
      dropdownService.getDivisions(),
      dropdownService.getLaboratories(),
    ])
    divisions.value    = divRes.data || []
    laboratories.value = labRes.data || []
  } catch (e) {
    console.error('Dropdown error:', e)
  }
}

async function generateReport() {
  loading.value = true
  errorMsg.value = ''
  try {
    const payload = {}
    if (filters.value.from_date)    payload.from_date    = filters.value.from_date
    if (filters.value.to_date)      payload.to_date      = filters.value.to_date
    if (filters.value.division_id)  payload.division_id  = filters.value.division_id
    if (filters.value.district_id)  payload.district_id  = filters.value.district_id
    if (filters.value.laboratory_id) payload.laboratory_id = filters.value.laboratory_id

    const res  = await reportService.getWaterQualityAnalysis(payload)
    const data = res.data?.water_samples || res.data?.samples || res.data?.data || res.data || []
    allRows.value = Array.isArray(data) ? data.map(mapSampleRow) : []
  } catch (e) {
    errorMsg.value = 'Failed to generate report'
    console.error('GSR error:', e)
  } finally {
    loading.value = false
  }
}

const filteredRows = computed(() => allRows.value.filter(r => {
  const matchResult = !filters.value.result || r.result === filters.value.result
  return matchResult
}))

const fitCount   = computed(() => filteredRows.value.filter(r => r.result === 'Fit').length)
const unfitCount = computed(() => filteredRows.value.filter(r => r.result === 'Unfit').length)
const pct        = computed(() => filteredRows.value.length > 0 ? ((unfitCount.value / filteredRows.value.length) * 100).toFixed(1) + '%' : '—')

function exportReport() {
  if (!filteredRows.value.length) {
    alert('No data to export. Please generate the report first.')
    return
  }
  
  const exportData = filteredRows.value.map(r => ({
    'S#': r.sn,
    'Sample ID': r.id,
    'WSS / Client Name': r.wss,
    'Sampling Date': r.date,
    'Sampling Point': r.point,
    'PHE Division': r.div,
    'Latitude': r.lat,
    'Longitude': r.lng,
    'Test Type': r.type,
    'Result': r.result,
    'Cause': r.cause,
    'Specific Ion / Component': r.ion
  }))
  
  exportToExcel(exportData, 'GSR_General_Summary_Report', { includeTimestamp: true })
}

onMounted(loadDropdowns)
</script>

<template>
  <div>
    <!-- Filters -->
    <div class="filters" style="margin-bottom:10px">
      <div class="fg"><label>From</label><input type="date" v-model="filters.from_date"></div>
      <div class="fg"><label>To</label><input type="date" v-model="filters.to_date"></div>
      <div class="fg">
        <label>Division</label>
        <select v-model="filters.division_id">
          <option value="">All Divisions</option>
          <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.name }}</option>
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
        <label>Result</label>
        <select v-model="filters.result">
          <option value="">All</option>
          <option>Fit</option>
          <option>Unfit</option>
        </select>
      </div>
      <div class="tsp"></div>
      <button class="btn btn-pri btn-sm" @click="generateReport" :disabled="loading">{{ loading ? '🔄…' : '⚙ Generate' }}</button>
      <button class="btn btn-sec btn-sm" @click="exportReport">⬇ Export .xlsx</button>
      <button class="btn btn-sec btn-sm">🖨 Print PDF</button>
    </div>

    <div v-if="errorMsg" style="background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:10px 14px;margin-bottom:10px;color:#b91c1c;font-size:12px">⚠️ {{ errorMsg }}</div>

    <div class="abar green">📋 General Summary Report (GSR) &nbsp;|&nbsp; Period: {{ filters.from }} to {{ filters.to }} &nbsp;|&nbsp; All Regions · All Labs &nbsp;|&nbsp; Annexure-2</div>

    <!-- Summary cards -->
    <div class="cards" style="grid-template-columns:repeat(6,1fr);margin-bottom:14px">
      <div class="card"><div class="c-lbl">Total Samples</div><div class="c-val">{{ filteredRows.length }}</div></div>
      <div class="card c-green"><div class="c-lbl">Fit</div><div class="c-val">{{ fitCount }}</div></div>
      <div class="card c-red"><div class="c-lbl">Unfit</div><div class="c-val">{{ unfitCount }}</div></div>
      <div class="card c-amber"><div class="c-lbl">% Unfit</div><div class="c-val">{{ pct }}</div></div>
      <div class="card"><div class="c-lbl">Districts Covered</div><div class="c-val">18</div></div>
      <div class="card"><div class="c-lbl">Active Labs</div><div class="c-val">7</div></div>
    </div>

    <!-- Sample-wise table -->
    <div class="sh"><h2>Sample-wise Results</h2></div>
    <div class="tbl-wrap" style="overflow-x:auto">
      <table style="font-size:11.5px;min-width:1100px">
        <thead>
          <tr>
            <th>S#</th><th>Sample ID</th><th>WSS / Client Name</th><th>Sampling Date</th>
            <th>Sampling Point</th><th>PHE Division</th><th>Latitude</th><th>Longitude</th>
            <th>Test Type</th><th>Result</th><th>Cause</th><th>Specific Ion / Component</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(r, i) in filteredRows" :key="r.id"
              :class="i%2===1?'alt':''"
              :style="r.result==='Unfit'?'background:#fff3f3':''">
            <td class="mono">{{ r.sn }}</td>
            <td class="mono">{{ r.id }}</td>
            <td>{{ r.wss }}</td>
            <td>{{ r.date }}</td>
            <td>{{ r.point }}</td>
            <td>{{ r.div }}</td>
            <td class="mono">{{ r.lat }}</td>
            <td class="mono">{{ r.lng }}</td>
            <td><span class="rag r-blue">{{ r.type }}</span></td>
            <td><span class="rag" :class="r.result==='Fit'?'r-green':'r-red'">{{ r.result }}</span></td>
            <td>{{ r.cause }}</td>
            <td>{{ r.ion }}</td>
          </tr>
        </tbody>
        <tfoot>
          <tr style="background:var(--navy);color:#fff;font-weight:700">
            <td colspan="10" style="text-align:right;padding-right:12px">TOTALS ({{ filteredRows.length }} samples)</td>
            <td colspan="2">
              <span class="rag r-green" style="margin-right:6px">✅ Fit: {{ fitCount }}</span>
              <span class="rag r-red">❌ Unfit: {{ unfitCount }}</span>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
    <div style="font-size:10.5px;color:var(--muted);margin-top:8px;line-height:1.8">
      <b>Test Type:</b> PCM = Physical + Chemical + Microbial &nbsp;·&nbsp; M = Microbial only &nbsp;·&nbsp; P = Physical only &nbsp;·&nbsp; C = Chemical only &nbsp;·&nbsp; PC = Physical + Chemical<br>
      <b>Cause:</b> Biological = microbial contamination &nbsp;·&nbsp; Chemical = ionic/dissolved &nbsp;·&nbsp; Physical = turbidity/colour/odour &nbsp;·&nbsp; — = sample is Fit
    </div>
  </div>
</template>
