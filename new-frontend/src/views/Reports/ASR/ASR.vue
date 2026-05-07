<script setup>
import { ref, computed, onMounted } from 'vue'
import { reportService } from '../../../services/reportService.js'
import { dropdownService } from '../../../services/dropdownService.js'
import { exportToExcel } from '../../../utils/exportHelpers.js'

const loading      = ref(false)
const errorMsg     = ref('')
const divisions    = ref([])
const laboratories = ref([])
const filters      = ref({ from_date:'', to_date:'', division_id:'', laboratory_id:'' })
const rows         = ref([])

function mapRow(s, idx) {
  const details = s.waterSampleDetails || []
  const getVal  = (keyword) => {
    const d = details.find(d => (d.test?.name || d.test?.water_quality_parameter || '').toLowerCase().includes(keyword))
    return d?.input_result || ''
  }
  return {
    sn: idx + 1,
    id: s.slug || String(s.id),
    wss: s.waterScheme?.name || s.water_sample_address || '—',
    date: s.sampled_at ? s.sampled_at.split(' ')[0] : '—',
    ce: s.region?.name || '—',
    lab: s.laboratory?.name || '—',
    district: s.district?.name || '—',
    div: s.phedDivision?.name || '—',
    lat: s.latitude || '—',
    lng: s.longitude || '—',
    type: s.test_type || '—',
    aesthetic: getVal('aesthetic'),
    turbidity: getVal('turbidity'),
    ec: getVal('ec') || getVal('conductivity'),
    ph: getVal('ph'),
    tds: getVal('tds') || getVal('dissolved solids'),
    hardness: getVal('hardness'),
    ca: getVal('calcium'),
    mg: getVal('magnesium'),
    alk: getVal('alkalinity'),
    bic: getVal('bicarbonate'),
    cl: getVal('chloride'),
    so4: getVal('sulphate'),
    no3: getVal('nitrate'),
    fl: getVal('fluoride'),
    as: getVal('arsenic'),
    fe: getVal('iron'),
    coliform: getVal('coliform'),
    ecoli: getVal('e. coli') || getVal('ecoli'),
    result: s.result || '—',
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
    const res  = await reportService.getWaterQualityAnalysis(payload)
    const data = res.data?.water_samples || res.data?.data || res.data || []
    rows.value = Array.isArray(data) ? data.map(mapRow) : []
  } catch (e) {
    errorMsg.value = 'Failed to generate report'
    console.error('ASR error:', e)
  } finally {
    loading.value = false
  }
}

const fitCount   = computed(() => rows.value.filter(r => r.result === 'Fit').length)
const unfitCount = computed(() => rows.value.filter(r => r.result === 'Unfit').length)

function exportReport() {
  if (!rows.value.length) {
    alert('No data to export. Please generate the report first.')
    return
  }
  
  const exportData = rows.value.map(r => ({
    'S#': r.sn,
    'Sample ID': r.id,
    'WSS / Client': r.wss,
    'Sampling Date': r.date,
    'PHE Region': r.ce,
    'Laboratory': r.lab,
    'District': r.district,
    'PHE Division': r.div,
    'Latitude': r.lat,
    'Longitude': r.lng,
    'Test Type': r.type,
    'Aesthetic': r.aesthetic,
    'Turbidity': r.turbidity,
    'EC': r.ec,
    'pH': r.ph,
    'TDS': r.tds,
    'Hardness': r.hardness,
    'Calcium': r.ca,
    'Magnesium': r.mg,
    'Alkalinity': r.alk,
    'Bicarbonate': r.bic,
    'Chloride': r.cl,
    'Sulphate': r.so4,
    'Nitrate': r.no3,
    'Fluoride': r.fl,
    'Arsenic': r.as,
    'Iron': r.fe,
    'Total Coliform': r.coliform,
    'E. Coli': r.ecoli,
    'Result': r.result
  }))
  
  exportToExcel(exportData, 'ASR_Analysis_Summary_Report', { includeTimestamp: true })
}

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
      <div class="fg">
        <label>Lab</label>
        <select v-model="filters.laboratory_id">
          <option value="">All Labs</option>
          <option v-for="l in laboratories" :key="l.id" :value="l.id">{{ l.name }}</option>
        </select>
      </div>
      <div class="tsp"></div>
      <button class="btn btn-pri btn-sm" @click="generateReport" :disabled="loading">{{ loading ? '🔄…' : 'Generate' }}</button>
      <button class="btn btn-sec btn-sm" @click="exportReport">⬇ Export .xlsx</button>
      <button class="btn btn-sec btn-sm">🖨 Print PDF</button>
    </div>

    <div v-if="errorMsg" style="background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:10px 14px;margin-bottom:10px;color:#b91c1c;font-size:12px">⚠️ {{ errorMsg }}</div>

    <div style="background:#fff;border:1px solid var(--border);border-radius:6px;padding:20px 24px;box-shadow:0 2px 10px rgba(0,0,0,.06)">
      <div style="text-align:center;border-bottom:2px solid var(--navy);padding-bottom:12px;margin-bottom:14px">
        <div style="font-size:11.5px;font-weight:700;color:var(--navy);letter-spacing:.04em;text-transform:uppercase">Government of Khyber Pakhtunkhwa</div>
        <div style="font-size:11px;color:var(--navy2);margin:2px 0">Public Health Engineering Department</div>
        <div style="font-size:13px;font-weight:700;color:var(--navy);margin:4px 0">Analysis Summary Report (ASR)</div>
        <div style="font-size:11px;color:var(--muted)">Central Water Quality Laboratory, Peshawar</div>
      </div>

      <div class="abar blue" style="margin-bottom:12px;font-size:11px">
        📋 Period: <b>{{ filters.from_date || '—' }} to {{ filters.to_date || '—' }}</b>
      </div>

      <div style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--navy2);margin-bottom:6px">
        Parameter-wise Analysis Results ({{ rows.length }} samples)
      </div>

      <div style="overflow-x:auto;border:1px solid #d0d7e0;border-radius:5px">
        <table style="font-size:10.5px;min-width:1800px;border-collapse:collapse;width:100%">
          <thead>
            <tr style="background:#1a2e4a;color:#fff">
              <th colspan="11" style="color:#fff;text-align:left;padding:5px 8px;border-right:2px solid #fff">Sample Identification &amp; Location</th>
              <th colspan="16" style="color:#fff;text-align:center;padding:5px 8px;border-right:2px solid #fff;background:#1a3a5c">🔵 Physical &amp; 🟠 Chemical Parameters</th>
              <th colspan="2" style="color:#fff;text-align:center;padding:5px 8px;background:#4a1a1a">🔴 Microbial</th>
              <th style="color:#fff;text-align:center;padding:5px 8px">Result</th>
            </tr>
            <tr style="background:#2a3f5f;color:#fff;font-size:9.5px">
              <th style="color:#fff;white-space:nowrap;padding:4px 6px">S#</th>
              <th style="color:#fff;white-space:nowrap;padding:4px 6px">Sample ID</th>
              <th style="color:#fff;white-space:nowrap;padding:4px 6px">WSS / Client</th>
              <th style="color:#fff;white-space:nowrap;padding:4px 6px">Sampling Date</th>
              <th style="color:#fff;white-space:nowrap;padding:4px 6px">PHE Region</th>
              <th style="color:#fff;white-space:nowrap;padding:4px 6px">Laboratory</th>
              <th style="color:#fff;white-space:nowrap;padding:4px 6px">District</th>
              <th style="color:#fff;white-space:nowrap;padding:4px 6px">PHE Div.</th>
              <th style="color:#fff;white-space:nowrap;padding:4px 6px">Lat.</th>
              <th style="color:#fff;white-space:nowrap;padding:4px 6px">Long.</th>
              <th style="color:#fff;white-space:nowrap;padding:4px 6px;border-right:2px solid #6a8fc0">Test Type</th>
              <th style="color:#add8ff;white-space:nowrap;padding:4px 6px">Aesthetic</th>
              <th style="color:#add8ff;white-space:nowrap;padding:4px 6px">Turbidity</th>
              <th style="color:#add8ff;white-space:nowrap;padding:4px 6px">EC</th>
              <th style="color:#add8ff;white-space:nowrap;padding:4px 6px">pH</th>
              <th style="color:#add8ff;white-space:nowrap;padding:4px 6px">TDS</th>
              <th style="color:#ffd699;white-space:nowrap;padding:4px 6px">Hardness</th>
              <th style="color:#ffd699;white-space:nowrap;padding:4px 6px">Calcium</th>
              <th style="color:#ffd699;white-space:nowrap;padding:4px 6px">Magnesium</th>
              <th style="color:#ffd699;white-space:nowrap;padding:4px 6px">Alkalinity</th>
              <th style="color:#ffd699;white-space:nowrap;padding:4px 6px">Bicarbonate</th>
              <th style="color:#ffd699;white-space:nowrap;padding:4px 6px">Chloride</th>
              <th style="color:#ffd699;white-space:nowrap;padding:4px 6px">Sulphate</th>
              <th style="color:#ffd699;white-space:nowrap;padding:4px 6px">Nitrate</th>
              <th style="color:#ffd699;white-space:nowrap;padding:4px 6px">Fluoride</th>
              <th style="color:#ffd699;white-space:nowrap;padding:4px 6px">Arsenic</th>
              <th style="color:#ffd699;white-space:nowrap;padding:4px 6px;border-right:2px solid #6a8fc0">Iron</th>
              <th style="color:#ffaaaa;white-space:nowrap;padding:4px 6px">T. Coliform</th>
              <th style="color:#ffaaaa;white-space:nowrap;padding:4px 6px;border-right:2px solid #6a8fc0">E. Coli</th>
              <th style="color:#fff;white-space:nowrap;padding:4px 6px">Result</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!rows.length">
              <td colspan="30" style="text-align:center;padding:20px;color:var(--muted)">Click Generate to load report data.</td>
            </tr>
            <tr v-for="(r, i) in rows" :key="r.id"
                :style="r.result === 'Unfit' ? 'background:#fff3f3' : i%2===1 ? 'background:#f5f5f5' : ''">
              <td style="padding:3px 6px">{{ r.sn }}</td>
              <td class="mono" style="padding:3px 6px;white-space:nowrap">{{ r.id }}</td>
              <td style="padding:3px 6px;white-space:nowrap">{{ r.wss }}</td>
              <td style="padding:3px 6px;white-space:nowrap">{{ r.date }}</td>
              <td style="padding:3px 6px">{{ r.ce }}</td>
              <td style="padding:3px 6px">{{ r.lab }}</td>
              <td style="padding:3px 6px">{{ r.district }}</td>
              <td style="padding:3px 6px">{{ r.div }}</td>
              <td class="mono" style="padding:3px 6px;font-size:9.5px">{{ r.lat }}</td>
              <td class="mono" style="padding:3px 6px;font-size:9.5px">{{ r.lng }}</td>
              <td style="padding:3px 6px"><span class="rag r-blue" style="font-size:9.5px">{{ r.type }}</span></td>
              <td style="padding:3px 6px">{{ r.aesthetic }}</td>
              <td style="padding:3px 6px">{{ r.turbidity }}</td>
              <td style="padding:3px 6px">{{ r.ec }}</td>
              <td style="padding:3px 6px">{{ r.ph }}</td>
              <td style="padding:3px 6px">{{ r.tds }}</td>
              <td style="padding:3px 6px">{{ r.hardness }}</td>
              <td style="padding:3px 6px">{{ r.ca }}</td>
              <td style="padding:3px 6px">{{ r.mg }}</td>
              <td style="padding:3px 6px">{{ r.alk }}</td>
              <td style="padding:3px 6px">{{ r.bic }}</td>
              <td style="padding:3px 6px">{{ r.cl }}</td>
              <td style="padding:3px 6px">{{ r.so4 }}</td>
              <td style="padding:3px 6px">{{ r.no3 }}</td>
              <td style="padding:3px 6px">{{ r.fl }}</td>
              <td style="padding:3px 6px">{{ r.as }}</td>
              <td style="padding:3px 6px">{{ r.fe }}</td>
              <td style="padding:3px 6px">{{ r.coliform }}</td>
              <td style="padding:3px 6px">{{ r.ecoli }}</td>
              <td style="padding:3px 6px"><span class="rag" :class="r.result==='Fit'?'r-green':'r-red'" style="font-size:9.5px">{{ r.result }}</span></td>
            </tr>
          </tbody>
          <tfoot>
            <tr style="background:#1a2e4a;color:#fff;font-weight:700;font-size:9.5px">
              <td colspan="11" style="padding:4px 8px;color:#fff">TOTALS ({{ rows.length }} samples)</td>
              <td colspan="18" style="padding:4px 8px;color:#fff">
                <span class="rag r-green" style="margin-right:8px">✅ Fit: {{ fitCount }}</span>
                <span class="rag r-red">❌ Unfit: {{ unfitCount }}</span>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div style="margin-top:10px;font-size:10.5px;color:var(--muted);line-height:1.8">
        <div>
          <span style="display:inline-block;width:14px;height:14px;background:#ffcccc;border:1px solid #f5c6c6;border-radius:2px;vertical-align:middle;margin-right:4px"></span>
          Red-highlighted cell = parameter value exceeds WHO / NEQS guideline limit. &nbsp;&nbsp; Blank cell = parameter not tested for this sample.
        </div>
        <div style="margin-top:4px">
          <b>Test Type:</b> PCM = Physical + Chemical + Microbial &nbsp;·&nbsp; PC = Physical + Chemical &nbsp;·&nbsp; M = Microbial only &nbsp;·&nbsp; P = Physical only &nbsp;·&nbsp; C = Chemical only
        </div>
      </div>
    </div>
  </div>
</template>
