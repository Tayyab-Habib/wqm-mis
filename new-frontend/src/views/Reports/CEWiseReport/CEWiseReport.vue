<script setup>
import { ref, computed, onMounted } from 'vue'
import { reportService } from '../../../services/reportService.js'
import { dropdownService } from '../../../services/dropdownService.js'

const loading    = ref(false)
const errorMsg   = ref('')
const divisions  = ref([])
const laboratories = ref([])
const filters    = ref({ from_date:'', to_date:'', division_id:'' })

const ceRows       = ref([])
const districtRows = ref([])

function mapCeRow(item) {
  const total = item.total || 0
  const fit   = item.fit   || 0
  const unfit = item.unfit || 0
  const pct   = total > 0 ? ((fit / total) * 100).toFixed(1) + '%' : '0%'
  const ratio = total > 0 ? unfit / total : 0
  return {
    ce: item.region_name || item.ce || '—',
    districts: item.districts_count || item.districts || 0,
    divisions: item.divisions_count || item.divisions || 0,
    total, fit, unfit,
    pctFit: pct,
    rag: ratio > 0.2 ? 'r-red' : ratio > 0.1 ? 'r-amber' : 'r-green',
    ragLabel: ratio > 0.2 ? 'Critical' : ratio > 0.1 ? 'Caution' : 'Satisfactory',
  }
}

function mapDistrictRow(item, idx) {
  const total = item.total || 0
  const fit   = item.fit   || 0
  const unfit = item.unfit || 0
  const pct   = total > 0 ? ((fit / total) * 100).toFixed(1) + '%' : '0%'
  const ratio = total > 0 ? unfit / total : 0
  return {
    sn: idx + 1,
    district: item.district_name || item.district || '—',
    division: item.division_name || item.division || '—',
    ce: item.region_name || item.ce || '—',
    total, fit, unfit,
    pctFit: pct,
    rag: ratio > 0.2 ? 'r-red' : ratio > 0.1 ? 'r-amber' : 'r-green',
    remark: ratio > 0.2 ? 'Immediate attention required' : ratio > 0.1 ? 'Monitor closely' : 'Satisfactory performance',
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
    if (filters.value.from_date)   payload.from_date   = filters.value.from_date
    if (filters.value.to_date)     payload.to_date     = filters.value.to_date
    if (filters.value.division_id) payload.division_id = filters.value.division_id
    const res  = await reportService.getWaterQualityAnalysis(payload)
    const data = res.data || {}
    ceRows.value       = (data.ce_wise || data.regions || []).map(mapCeRow)
    districtRows.value = (data.district_wise || data.districts || []).map(mapDistrictRow)
  } catch (e) {
    errorMsg.value = 'Failed to generate report'
    console.error('CE-wise error:', e)
  } finally {
    loading.value = false
  }
}

const grandTotal = computed(() => ({
  total:  ceRows.value.reduce((s, r) => s + r.total, 0),
  fit:    ceRows.value.reduce((s, r) => s + r.fit, 0),
  unfit:  ceRows.value.reduce((s, r) => s + r.unfit, 0),
}))

const uniqueCEs = computed(() => [...new Set(districtRows.value.map(r => r.ce))])

onMounted(loadDropdowns)
</script>

<template>
  <div>
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
      <div class="tsp"></div>
      <button class="btn btn-pri btn-sm" @click="generateReport" :disabled="loading">{{ loading ? '🔄…' : 'Generate' }}</button>
      <button class="btn btn-sec btn-sm">⬇ Export Annexure-7</button>
    </div>

    <div v-if="errorMsg" style="background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:10px 14px;margin-bottom:10px;color:#b91c1c;font-size:12px">⚠️ {{ errorMsg }}</div>

    <div class="abar blue">📊 Chief Engineer-wise Water Quality Report &nbsp;|&nbsp; Annexure-7</div>

    <div class="cards" style="grid-template-columns:repeat(4,1fr);margin-bottom:14px">
      <div class="card"><div class="c-lbl">Total Tested</div><div class="c-val">{{ grandTotal.total.toLocaleString() }}</div></div>
      <div class="card c-green"><div class="c-lbl">Fit Samples</div><div class="c-val">{{ grandTotal.fit.toLocaleString() }}</div><div class="c-sub">{{ grandTotal.total > 0 ? ((grandTotal.fit/grandTotal.total)*100).toFixed(1) + '%' : '—' }}</div></div>
      <div class="card c-red"><div class="c-lbl">Unfit Samples</div><div class="c-val">{{ grandTotal.unfit.toLocaleString() }}</div></div>
      <div class="card"><div class="c-lbl">CE Zones</div><div class="c-val">{{ ceRows.length }}</div></div>
    </div>

    <div class="tbl-wrap" style="margin-bottom:14px">
      <table>
        <thead>
          <tr><th>Chief Engineer</th><th>Districts</th><th>Divisions</th><th>Total</th><th>Fit</th><th>Unfit</th><th>% Fit</th><th>RAG Status</th></tr>
        </thead>
        <tbody>
          <tr v-if="!ceRows.length">
            <td colspan="8" style="text-align:center;padding:20px;color:var(--muted)">Click Generate to load data.</td>
          </tr>
          <tr v-for="(r, i) in ceRows" :key="r.ce" :class="i%2===1?'alt':''">
            <td><b>{{ r.ce }}</b></td>
            <td>{{ r.districts }}</td>
            <td>{{ r.divisions }}</td>
            <td class="mono">{{ r.total.toLocaleString() }}</td>
            <td class="mono">{{ r.fit.toLocaleString() }}</td>
            <td class="mono">{{ r.unfit }}</td>
            <td class="mono">{{ r.pctFit }}</td>
            <td><span class="rag" :class="r.rag">{{ r.ragLabel }}</span></td>
          </tr>
        </tbody>
        <tfoot>
          <tr style="background:var(--navy2)">
            <td style="color:#fff;font-weight:600">PROVINCE TOTAL</td>
            <td style="color:#fff">—</td><td style="color:#fff">—</td>
            <td class="mono" style="color:#fff;font-weight:600">{{ grandTotal.total.toLocaleString() }}</td>
            <td class="mono" style="color:#fff">{{ grandTotal.fit.toLocaleString() }}</td>
            <td class="mono" style="color:#fff">{{ grandTotal.unfit }}</td>
            <td class="mono" style="color:#fff">{{ grandTotal.total > 0 ? ((grandTotal.fit/grandTotal.total)*100).toFixed(1) + '%' : '—' }}</td>
            <td><span class="rag r-amber">—</span></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="sh"><h2>CE-wise District Detail</h2><div class="cnt">Annexure-7 · Sheet 2</div></div>
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr><th>S#</th><th>District</th><th>Division</th><th>Total</th><th>Fit</th><th>Unfit</th><th>% Fit</th><th>RAG</th><th>Remarks</th></tr>
        </thead>
        <tbody>
          <tr v-if="!districtRows.length">
            <td colspan="9" style="text-align:center;padding:20px;color:var(--muted)">Click Generate to load data.</td>
          </tr>
          <template v-for="ce in uniqueCEs" :key="ce">
            <tr class="ce-hdr">
              <td colspan="9">{{ ce }}</td>
            </tr>
            <tr v-for="(d, i) in districtRows.filter(r=>r.ce===ce)" :key="d.sn" :class="i%2===1?'alt':''">
              <td>{{ d.sn }}</td>
              <td>{{ d.district }}</td>
              <td>{{ d.division }}</td>
              <td class="mono">{{ d.total }}</td>
              <td class="mono">{{ d.fit }}</td>
              <td class="mono">{{ d.unfit }}</td>
              <td class="mono">{{ d.pctFit }}</td>
              <td><span class="rag" :class="d.rag">{{ d.rag === 'r-green' ? 'Satisfactory' : d.rag === 'r-amber' ? 'Caution' : 'Critical' }}</span></td>
              <td>{{ d.remark }}</td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>
