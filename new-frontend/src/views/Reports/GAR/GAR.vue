<script setup>
import { ref, onMounted } from 'vue'
import { reportService } from '../../../services/reportService.js'
import { dropdownService } from '../../../services/dropdownService.js'
import { exportToExcel } from '../../../utils/exportHelpers.js'

const loading    = ref(false)
const errorMsg   = ref('')
const divisions  = ref([])
const laboratories = ref([])

const filters = ref({ from_date:'', to_date:'', division_id:'', laboratory_id:'' })

const labRows = ref([])
const totals  = ref({ tested:0, fit:0, unfit:0 })

function mapLabRow(lab, idx) {
  return {
    id: `lab-${lab.laboratory_id || idx}`,
    lab: lab.laboratory_name || lab.name || '—',
    ce: lab.region_name || lab.ce || '—',
    div: lab.division_name || lab.div || '—',
    districts: lab.districts || '—',
    tested: lab.total || lab.tested || 0,
    fit: lab.fit || 0,
    unfit: lab.unfit || 0,
    pct: lab.total > 0 ? ((lab.unfit / lab.total) * 100).toFixed(1) + '%' : '0%',
    rag: lab.unfit / (lab.total || 1) > 0.2 ? 'r-red' : lab.unfit / (lab.total || 1) > 0.1 ? 'r-amber' : 'r-green',
    ragLabel: lab.unfit / (lab.total || 1) > 0.2 ? 'High' : lab.unfit / (lab.total || 1) > 0.1 ? 'Moderate' : 'Good',
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
    if (filters.value.laboratory_id) payload.laboratory_id = filters.value.laboratory_id

    const res  = await reportService.getWaterQualityAnalysis(payload)
    const data = res.data || {}

    // Map response to labRows format
    const rows = data.laboratories || data.lab_wise || data.data || []
    labRows.value = Array.isArray(rows) ? rows.map(mapLabRow) : []

    totals.value = {
      tested: labRows.value.reduce((s, r) => s + r.tested, 0),
      fit:    labRows.value.reduce((s, r) => s + r.fit, 0),
      unfit:  labRows.value.reduce((s, r) => s + r.unfit, 0),
    }
  } catch (e) {
    errorMsg.value = 'Failed to generate report'
    console.error('GAR error:', e)
  } finally {
    loading.value = false
  }
}

const expanded = ref({})
function toggle(id) { expanded.value[id] = !expanded.value[id] }

function exportReport() {
  if (!labRows.value.length) {
    alert('No data to export. Please generate the report first.')
    return
  }
  
  const exportData = labRows.value.map(r => ({
    'Laboratory': r.lab,
    'CE Region': r.ce,
    'Division': r.div,
    'Districts Covered': r.districts,
    'Total Tested': r.tested,
    'Fit': r.fit,
    'Unfit': r.unfit,
    '% Unfit': r.pct,
    'RAG Status': r.ragLabel
  }))
  
  exportToExcel(exportData, 'GAR_General_Abstract_Report', { includeTimestamp: true })
}

onMounted(async () => {
  await loadDropdowns()
})
</script>

<template>
  <div>
    <!-- Filters -->
    <div class="filters">
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
      <button class="btn btn-pri btn-sm" @click="generateReport" :disabled="loading">{{ loading ? '🔄 Generating…' : '⚙ Generate' }}</button>
      <button class="btn btn-sec btn-sm" @click="exportReport">⬇ Export .xlsx</button>
      <button class="btn btn-sec btn-sm">🖨 Print / PDF</button>
    </div>

    <div v-if="errorMsg" style="background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:10px 14px;margin-bottom:10px;color:#b91c1c;font-size:12px">⚠️ {{ errorMsg }}</div>

    <div class="abar green">📋 General Abstract Report (GAR) &nbsp;|&nbsp; All Regions · All Labs &nbsp;|&nbsp; Annexure-1</div>

    <!-- KP-Level Abstract -->
    <div class="sh"><h2>KP-Level Abstract</h2></div>
    <div class="cards" style="grid-template-columns:repeat(6,1fr);margin-bottom:14px">
      <div class="card"><div class="c-lbl">Total Tested</div><div class="c-val">{{ totals.tested.toLocaleString() }}</div></div>
      <div class="card c-green"><div class="c-lbl">Fit</div><div class="c-val">{{ totals.fit.toLocaleString() }}</div></div>
      <div class="card c-red"><div class="c-lbl">Unfit</div><div class="c-val">{{ totals.unfit.toLocaleString() }}</div></div>
      <div class="card c-amber"><div class="c-lbl">% Unfit</div><div class="c-val">{{ totals.tested > 0 ? ((totals.unfit/totals.tested)*100).toFixed(1) + '%' : '—' }}</div></div>
      <div class="card"><div class="c-lbl">Labs Reporting</div><div class="c-val">{{ labRows.length }}</div></div>
      <div class="card"><div class="c-lbl">Divisions</div><div class="c-val">{{ [...new Set(labRows.map(r=>r.div))].length }}</div></div>
    </div>

    <!-- Lab-wise Abstract -->
    <div class="sh" style="display:flex;align-items:center;justify-content:space-between">
      <h2>Lab-wise Abstract</h2>
      <div style="display:flex;gap:6px">
        <button class="btn btn-sec btn-xs" @click="labRows.forEach(r => expanded[r.id] = true)">⊞ Expand All</button>
        <button class="btn btn-sec btn-xs" @click="labRows.forEach(r => expanded[r.id] = false)">⊟ Collapse All</button>
      </div>
    </div>

    <div class="tbl-wrap" style="margin-bottom:14px">
      <table>
        <thead>
          <tr>
            <th style="width:24px"></th>
            <th>Laboratory</th>
            <th>CE Region</th>
            <th>Division</th>
            <th>Districts Covered</th>
            <th>Tested</th>
            <th>Fit</th>
            <th>Unfit</th>
            <th>% Unfit</th>
            <th>Fit vs Unfit</th>
            <th>RAG</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="row in labRows" :key="row.id">
            <tr style="cursor:pointer;background:#f0f7ff" @click="toggle(row.id)">
              <td style="font-size:11px;color:var(--blue);text-align:center">{{ expanded[row.id] ? '▼' : '▶' }}</td>
              <td><b>{{ row.lab }}</b></td>
              <td>{{ row.ce }}</td>
              <td>{{ row.div }}</td>
              <td>{{ row.districts }}</td>
              <td class="mono">{{ row.tested }}</td>
              <td class="mono">{{ row.fit }}</td>
              <td class="mono" :style="row.rag === 'r-red' ? 'color:var(--red);font-weight:600' : ''">{{ row.unfit }}</td>
              <td class="mono">{{ row.pct }}</td>
              <td>
                <div class="fit-bar">
                  <div class="pb"><div class="pf" :style="{ width: (row.fit/row.tested*100).toFixed(1) + '%' }"></div></div>
                  <span style="font-size:10px">{{ row.fit }} / {{ row.unfit }}</span>
                </div>
              </td>
              <td><span class="rag" :class="row.rag">{{ row.ragLabel }}</span></td>
            </tr>
            <tr v-if="expanded[row.id]">
              <td colspan="11" style="padding:0;background:#fafcff;font-size:11.5px;padding:8px 24px;color:var(--muted)">
                ↳ District breakdown available in full implementation
              </td>
            </tr>
          </template>
        </tbody>
        <tfoot>
          <tr style="background:var(--navy);color:#fff">
            <td></td>
            <td style="font-weight:700">KP TOTAL — All Labs</td>
            <td></td><td></td><td></td>
            <td class="mono" style="font-weight:700">{{ totals.tested.toLocaleString() }}</td>
            <td class="mono">{{ totals.fit.toLocaleString() }}</td>
            <td class="mono">{{ totals.unfit.toLocaleString() }}</td>
            <td class="mono">{{ totals.tested > 0 ? ((totals.unfit/totals.tested)*100).toFixed(1) + '%' : '—' }}</td>
            <td>
              <div class="fit-bar" style="background:rgba(255,255,255,.15)">
                <div class="pb" style="background:rgba(255,255,255,.2)">
                  <div class="pf" :style="{ width: totals.tested > 0 ? ((totals.fit/totals.tested)*100).toFixed(1)+'%' : '0%', background:'#4ade80' }"></div>
                </div>
                <span style="font-size:10px;color:#fff">{{ totals.fit }} / {{ totals.unfit }}</span>
              </div>
            </td>
            <td><span class="rag r-amber">—</span></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</template>
