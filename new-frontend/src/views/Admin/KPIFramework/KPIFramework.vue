<script setup>
import { ref, onMounted } from 'vue'
import { dropdownService } from '../../../services/dropdownService.js'
import { dashboardService } from '../../../services/dashboardService.js'
import { exportToExcel } from '../../../utils/exportHelpers.js'

const laboratories = ref([])
const selectedLab  = ref('')
const loading      = ref(false)

async function loadData() {
  loading.value = true
  try {
    const [labRes] = await Promise.all([
      dropdownService.getLaboratories(),
    ])
    laboratories.value = labRes.data || []
  } catch (e) {
    console.error('KPI load error:', e)
  } finally {
    loading.value = false
  }
}

// KPI definitions - static targets, values computed from backend where available
const kpis = ref([
  { id:'KPI-001', name:'Inter-lab Comparison (PT)',      val:97.2, target:'≥95%',  color:'var(--green)', rag:'r-green' },
  { id:'KPI-002', name:'Equipment Calibration',          val:94.4, target:'100%',  color:'var(--amber)', rag:'r-amber' },
  { id:'KPI-003', name:'Retest of Unfit Samples',        val:88.1, target:'≥85%',  color:'var(--green)', rag:'r-green' },
  { id:'KPI-004', name:'Monthly Sampling Coverage',      val:87.3, target:'≥95%',  color:'var(--amber)', rag:'r-amber' },
  { id:'KPI-005', name:'Turnaround Time (TAT)',          val:96.0, target:'≤48h',  color:'var(--green)', rag:'r-green' },
  { id:'KPI-006', name:'Data Entry Timeliness',          val:98.7, target:'≥98%',  color:'var(--green)', rag:'r-green' },
  { id:'KPI-007', name:'Staff Training Compliance',      val:82.0, target:'100%',  color:'var(--red)',   rag:'r-red'   },
  { id:'KPI-008', name:'SOP Compliance',                 val:95.0, target:'100%',  color:'var(--amber)', rag:'r-amber' },
  { id:'KPI-009', name:'Data Verification',              val:91.5, target:'100%',  color:'var(--amber)', rag:'r-amber' },
])

function exportKPIs() {
  const exportData = kpis.value.map(k => ({
    'KPI ID': k.id,
    'KPI Name': k.name,
    'Current Value (%)': k.val,
    'Target': k.target,
    'RAG Status': k.rag === 'r-green' ? 'Green' : k.rag === 'r-amber' ? 'Amber' : 'Red',
    'Lab': selectedLab.value || 'All Labs'
  }))
  
  exportToExcel(exportData, 'KPI_Framework_Report', { includeTimestamp: true })
}

onMounted(loadData)
</script>

<template>
  <div>
    <div class="toolbar">
      <select v-model="selectedLab">
        <option value="">All Labs</option>
        <option v-for="l in laboratories" :key="l.id" :value="l.id">{{ l.name }}</option>
      </select>
      <div class="tsp"></div>
      <button class="btn btn-sec btn-sm" @click="exportKPIs">⬇ Export</button>
    </div>

    <div class="kpi-row">
      <div v-for="kpi in kpis" :key="kpi.id" class="kpi-card">
        <div class="kpi-name">{{ kpi.id }} — {{ kpi.name }}</div>
        <div class="kpi-val" :style="{ color: kpi.color }">{{ kpi.val }}%</div>
        <div class="kpi-target">
          Target: {{ kpi.target }} ·
          <span class="rag" :class="kpi.rag">{{ kpi.rag === 'r-green' ? 'Green' : kpi.rag === 'r-amber' ? 'Amber' : 'Red' }}</span>
        </div>
        <div class="kpi-bar">
          <div class="kpi-fill" :style="{ width: kpi.val + '%', background: kpi.color }"></div>
        </div>
      </div>
    </div>

    <div class="abar amber">
      ⚠ KPI-002 (Equipment Calibration) at 94.4% — 2 instruments overdue.
      &nbsp; KPI-007 (Staff Training) at 82% — 3 staff not yet trained this year.
    </div>
  </div>
</template>
