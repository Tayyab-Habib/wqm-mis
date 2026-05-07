<script setup>
import { ref, computed, onMounted } from 'vue'
import { reportService } from '../../../services/reportService.js'
import { dropdownService } from '../../../services/dropdownService.js'

const loading    = ref(false)
const errorMsg   = ref('')
const divisions  = ref([])
const laboratories = ref([])
const filters    = ref({ from_date:'', to_date:'', division_id:'', laboratory_id:'', parameter:'' })
const viewMode   = ref('all')

const paramOverview    = ref([])
const districtBreakdown = ref([])

function mapParamRow(item) {
  const tested    = item.tested    || 0
  const exceeding = item.exceeding || 0
  const pct       = tested > 0 ? ((exceeding / tested) * 100).toFixed(1) + '%' : '0%'
  const ratio     = tested > 0 ? exceeding / tested : 0
  return {
    param:    item.parameter || item.param || '—',
    limit:    item.limit || '—',
    tested,
    exceeding,
    pct,
    rag:      ratio > 0.1 ? 'r-amber' : ratio > 0 ? 'r-green' : 'r-grey',
    ragLabel: ratio > 0.1 ? 'Amber' : ratio > 0 ? 'Green' : 'Grey',
  }
}

function mapDistrictRow(item, idx) {
  const tested    = item.total    || 0
  const within    = item.fit      || 0
  const exceeding = item.unfit    || 0
  const pct       = tested > 0 ? ((exceeding / tested) * 100).toFixed(1) + '%' : '0%'
  const ratio     = tested > 0 ? exceeding / tested : 0
  return {
    district: item.district_name || item.district || '—',
    tested, within, exceeding, pct,
    rag:    ratio > 0.2 ? 'r-red' : ratio > 0.1 ? 'r-amber' : 'r-green',
    action: ratio > 0.2 ? 'Action Required' : ratio > 0.1 ? 'Monitor' : 'No Action',
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
  } catch (e) { console.error('Dropdown error:', e) }
}

async function generateReport() {
  loading.value = true
  errorMsg.value = ''
  try {
    const payload = {}
    if (filters.value.from_date)     payload.from_date     = filters.value.from_date
    if (filters.value.to_date)       payload.to_date       = filters.value.to_date
    if (filters.value.division_id)   payload.division_id   = filters.value.division_id
    if (filters.value.laboratory_id) payload.laboratory_id = filters.value.laboratory_id
    const res  = await reportService.searchResults(payload)
    const data = res.data || {}
    paramOverview.value     = (data.parameter_wise || data.parameters || []).map(mapParamRow)
    districtBreakdown.value = (data.district_wise  || data.districts  || []).map(mapDistrictRow)
  } catch (e) {
    errorMsg.value = 'Failed to generate report'
    console.error('PWR error:', e)
  } finally {
    loading.value = false
  }
}

onMounted(loadDropdowns)
</script>

<template>
  <div>
    <div class="mode-toggle" style="display:flex;border:1px solid var(--border);border-radius:5px;overflow:hidden;margin-bottom:14px;width:fit-content">
      <div @click="viewMode='all'"  style="padding:7px 16px;font-size:12.5px;font-weight:500;cursor:pointer;transition:all .12s" :style="viewMode==='all'  ? 'background:var(--blue);color:#fff' : 'color:var(--muted)'">All Parameters</div>
      <div @click="viewMode='cont'" style="padding:7px 16px;font-size:12.5px;font-weight:500;cursor:pointer;transition:all .12s" :style="viewMode==='cont' ? 'background:var(--blue);color:#fff' : 'color:var(--muted)'">Contamination Only</div>
    </div>

    <div class="filters" style="margin-bottom:14px">
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
      <div class="tsp"></div>
      <button class="btn btn-pri btn-sm" @click="generateReport" :disabled="loading">{{ loading ? '🔄…' : 'Generate' }}</button>
    </div>

    <div v-if="errorMsg" style="background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:10px 14px;margin-bottom:10px;color:#b91c1c;font-size:12px">⚠️ {{ errorMsg }}</div>

    <div class="sh"><h2>View 1 — Parameter Overview</h2></div>
    <div class="tbl-wrap" style="margin-bottom:14px">
      <table>
        <thead>
          <tr><th>Parameter</th><th>WHO/NEQS Limit</th><th>Total Tested</th><th>Exceeding</th><th>% Exceeding</th><th>Risk Level</th></tr>
        </thead>
        <tbody>
          <tr v-if="!paramOverview.length">
            <td colspan="6" style="text-align:center;padding:20px;color:var(--muted)">Click Generate to load data.</td>
          </tr>
          <tr v-for="(r, i) in paramOverview" :key="r.param" :class="i%2===1?'alt':''">
            <td><b>{{ r.param }}</b></td>
            <td class="mono">{{ r.limit }}</td>
            <td class="mono">{{ r.tested.toLocaleString() }}</td>
            <td class="mono">{{ r.exceeding }}</td>
            <td class="mono">{{ r.pct }}</td>
            <td><span class="rag" :class="r.rag">{{ r.ragLabel }}</span></td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="sh"><h2>View 2 — District-wise Breakdown</h2></div>
    <div class="tbl-wrap" style="margin-bottom:14px">
      <table>
        <thead>
          <tr><th>District</th><th>Tested</th><th>Within Limit</th><th>Exceeding</th><th>% Exceeding</th><th>Remarks</th></tr>
        </thead>
        <tbody>
          <tr v-if="!districtBreakdown.length">
            <td colspan="6" style="text-align:center;padding:20px;color:var(--muted)">Click Generate to load data.</td>
          </tr>
          <tr v-for="(r, i) in districtBreakdown" :key="r.district" :class="i%2===1?'alt':''">
            <td>{{ r.district }}</td>
            <td class="mono">{{ r.tested }}</td>
            <td class="mono">{{ r.within }}</td>
            <td class="mono" :style="r.rag==='r-red'?'color:var(--red);font-weight:700':''">{{ r.exceeding }}</td>
            <td class="mono">{{ r.pct }}</td>
            <td><span class="rag" :class="r.rag">{{ r.action }}</span></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
