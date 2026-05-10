<script setup>
import { ref, computed, onMounted } from 'vue'
import { reportService }   from '../../../services/reportService.js'
import { dropdownService } from '../../../services/dropdownService.js'
import { exportToXLSX }    from '../../../utils/exportHelpers.js'
import { useUserStore }    from '../../../stores/useUserStore.js'

const userStore = useUserStore()
const loading   = ref(false)
const errorMsg  = ref('')
const generated = ref(false)

// ── Dropdowns ──────────────────────────────────────────────────────────
const divisions    = ref([])
const districts    = ref([])
const laboratories = ref([])
const regions      = ref([])
const circles      = ref([])
const phedDivs     = ref([])

// ── Filters ────────────────────────────────────────────────────────────
const filters = ref({
  from_date:        new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
  to_date:          new Date().toISOString().split('T')[0],
  region_id:        '',
  division_id:      '',
  circle_id:        '',
  district_id:      '',
  phed_division_id: '',
  laboratory_id:    '',
  sample_type:      '',   // matches desired_test DB values
  result:           '',
})

// ── Cascaded dropdowns ─────────────────────────────────────────────────
const filteredDistricts = computed(() =>
  filters.value.division_id
    ? districts.value.filter(d => String(d.division_id) === String(filters.value.division_id))
    : districts.value
)
const filteredCircles = computed(() =>
  filters.value.region_id
    ? circles.value.filter(c => String(c.region_id) === String(filters.value.region_id))
    : circles.value
)
const filteredPhedDivs = computed(() =>
  filters.value.district_id
    ? phedDivs.value.filter(p => String(p.district_id) === String(filters.value.district_id))
    : phedDivs.value
)

// ── Data ───────────────────────────────────────────────────────────────
const allRows     = ref([])
const generatedAt = ref('')

// desired_test comes as array (model accessor) or comma-separated string
function resolveDesiredTest(val) {
  if (!val) return '—'
  if (Array.isArray(val)) {
    return val.map(v => abbreviateTest(v)).join('+')
  }
  return String(val).split(',').map(v => abbreviateTest(v.trim())).join('+')
}

// Map full desired_test labels → short display codes
function abbreviateTest(v) {
  if (!v) return ''
  const s = v.toLowerCase()
  if (s.includes('microbiological') || s.includes('microbial')) return 'M'
  if (s.includes('physical') && s.includes('chemical')) return 'PC'
  if (s.includes('physical')) return 'P'
  if (s.includes('chemical')) return 'C'
  if (s.includes('on demand')) return 'OD'
  return v
}

function resolveResult(s) {
  if (!s) return '—'
  const v = String(s).toLowerCase()
  if (v === '1' || v === 'fit')   return 'Fit'
  if (v === '2' || v === 'unfit') return 'Unfit'
  return s
}

function formatDate(dt) {
  if (!dt) return '—'
  // sampled_at comes pre-formatted from model accessor e.g. "10 May, 2026 14:30"
  // try to parse it anyway
  const d = new Date(dt)
  if (isNaN(d)) return dt
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: '2-digit' }).replace(/ /g, '-')
}

function mapSampleRow(s, idx) {
  return {
    sn:       idx + 1,
    id:       s.slug || String(s.id),
    wss:      s.water_scheme?.name || s.waterScheme?.name || s.water_sample_address || s.sample_name || '—',
    district: s.district?.name || '—',
    division: s.division?.name || '—',
    region:   s.region?.name   || '—',
    circle:   s.circle?.name   || '—',
    date:     s.sampled_at ? formatDate(s.sampled_at) : '—',
    point:    s.sampling_point?.value || s.sampling_point || '—',
    phedDiv:  s.phed_division?.name || s.phedDivision?.name || '—',
    lat:      s.latitude  ? parseFloat(s.latitude).toFixed(4)  : '—',
    lng:      s.longitude ? parseFloat(s.longitude).toFixed(4) : '—',
    type:     resolveDesiredTest(s.desired_test),
    result:   resolveResult(s.result),
    cause:    s.remarks || '—',
    ion:      '—',
  }
}

// ── Load dropdowns ─────────────────────────────────────────────────────
async function loadDropdowns() {
  try {
    const [divRes, labRes, regRes, cirRes, distRes, phdRes] = await Promise.all([
      dropdownService.getDivisions(),
      dropdownService.getLaboratories(),
      dropdownService.getRegions(),
      dropdownService.getCircles(),
      dropdownService.getDistricts(),
      dropdownService.getPhedDivisions(),
    ])
    divisions.value    = divRes.data?.data  || divRes.data  || []
    laboratories.value = labRes.data?.data  || labRes.data  || []
    regions.value      = regRes.data?.data  || regRes.data  || []
    circles.value      = cirRes.data?.data  || cirRes.data  || []
    districts.value    = distRes.data?.data || distRes.data || []
    phedDivs.value     = phdRes.data?.data  || phdRes.data  || []
  } catch (e) { console.error('Dropdown error:', e) }
}

// ── Generate report (called on mount + on Generate button) ─────────────
async function generateReport() {
  loading.value   = true
  errorMsg.value  = ''
  allRows.value   = []
  generated.value = false
  try {
    const payload = {}
    if (filters.value.from_date)        payload.from_date        = filters.value.from_date
    if (filters.value.to_date)          payload.to_date          = filters.value.to_date
    if (filters.value.region_id)        payload.region_id        = filters.value.region_id
    if (filters.value.division_id)      payload.division_id      = filters.value.division_id
    if (filters.value.circle_id)        payload.circle_id        = filters.value.circle_id
    if (filters.value.district_id)      payload.district_id      = filters.value.district_id
    if (filters.value.phed_division_id) payload.phed_division_id = filters.value.phed_division_id
    if (filters.value.laboratory_id)    payload.laboratory_id    = filters.value.laboratory_id
    if (filters.value.result)           payload.result           = filters.value.result
    // sample_type maps to actual desired_test DB values
    if (filters.value.sample_type)      payload.sample_type      = filters.value.sample_type

    const res  = await reportService.getWaterQualityAnalysis(payload)
    const data = res.data?.data || res.data || []
    allRows.value = Array.isArray(data) ? data.map(mapSampleRow) : []
    generatedAt.value = new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
    generated.value = true
  } catch (e) {
    errorMsg.value = 'Failed to generate report: ' + (e.response?.data?.message || e.message)
    console.error('GSR error:', e)
  } finally {
    loading.value = false
  }
}

// ── All rows are already server-filtered — just use allRows directly ───
const filteredRows = computed(() => allRows.value)

// ── Summary stats ──────────────────────────────────────────────────────
const totalCount       = computed(() => filteredRows.value.length)
const fitCount         = computed(() => filteredRows.value.filter(r => r.result === 'Fit').length)
const unfitCount       = computed(() => filteredRows.value.filter(r => r.result === 'Unfit').length)
const unfitPct         = computed(() => totalCount.value > 0
  ? ((unfitCount.value / totalCount.value) * 100).toFixed(1) + '%' : '—')
const districtsCovered = computed(() => new Set(filteredRows.value.map(r => r.district).filter(d => d !== '—')).size)
const phedDivCount     = computed(() => new Set(filteredRows.value.map(r => r.phedDiv).filter(d => d !== '—')).size)

// ── Group rows by district ─────────────────────────────────────────────
const groupedByDistrict = computed(() => {
  const groups = {}
  filteredRows.value.forEach(r => {
    const key = r.district || 'Unknown District'
    if (!groups[key]) groups[key] = []
    groups[key].push(r)
  })
  return groups
})

// ── Report header labels ───────────────────────────────────────────────
const reportPeriod = computed(() => `${filters.value.from_date || '—'} to ${filters.value.to_date || '—'}`)
const reportLab = computed(() => {
  if (!filters.value.laboratory_id) return 'All Labs'
  return laboratories.value.find(l => l.id == filters.value.laboratory_id)?.name || 'All Labs'
})
const reportDivision = computed(() => {
  if (!filters.value.division_id) return 'All Divisions'
  return divisions.value.find(d => d.id == filters.value.division_id)?.name || 'All Divisions'
})
const reportDistrict = computed(() => {
  if (!filters.value.district_id) return 'All Districts'
  return districts.value.find(d => d.id == filters.value.district_id)?.name || 'All Districts'
})
const generatedBy = computed(() => userStore.currentUser?.name || 'System')

// ── Test type badge style ──────────────────────────────────────────────
function typeStyle(type) {
  if (!type || type === '—') return 'background:#6b7280;color:#fff;border-color:#6b7280'
  const t = type.toUpperCase()
  if (t.includes('M'))  return 'background:#0891b2;color:#fff;border-color:#0891b2'
  if (t.includes('PC')) return 'background:#0d9488;color:#fff;border-color:#0d9488'
  if (t.includes('P'))  return 'background:#7c3aed;color:#fff;border-color:#7c3aed'
  if (t.includes('C'))  return 'background:#b45309;color:#fff;border-color:#b45309'
  return 'background:#6b7280;color:#fff;border-color:#6b7280'
}

// ── Export ─────────────────────────────────────────────────────────────
function exportReport() {
  if (!filteredRows.value.length) { alert('No data to export.'); return }
  exportToXLSX(filteredRows.value.map(r => ({
    'S#':                       r.sn,
    'Sample ID':                r.id,
    'WSS / Client Name':        r.wss,
    'District':                 r.district,
    'Division':                 r.division,
    'Sampling Date':            r.date,
    'Sampling Point':           r.point,
    'PHE Division':             r.phedDiv,
    'Latitude':                 r.lat,
    'Longitude':                r.lng,
    'Test Type':                r.type,
    'Result':                   r.result,
    'Cause':                    r.cause,
    'Specific Ion / Component': r.ion,
  })), 'GSR_General_Summary_Report')
}

function printReport() { window.print() }

onMounted(async () => {
  await loadDropdowns()
  await generateReport()
})
</script>

<template>
  <div>
    <!-- ── Filters ── -->
    <div class="filters" style="flex-wrap:wrap;gap:6px;margin-bottom:10px">
      <div class="fg"><label>From</label><input type="date" v-model="filters.from_date"></div>
      <div class="fg"><label>To</label><input type="date" v-model="filters.to_date"></div>

      <div class="fg">
        <label>Region (CE)</label>
        <select v-model="filters.region_id"
                @change="filters.circle_id='';filters.division_id='';filters.district_id='';filters.phed_division_id=''">
          <option value="">All Regions</option>
          <option v-for="r in regions" :key="r.id" :value="r.id">{{ r.name }}</option>
        </select>
      </div>

      <div class="fg">
        <label>Division</label>
        <select v-model="filters.division_id" @change="filters.district_id='';filters.phed_division_id=''">
          <option value="">All Divisions</option>
          <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
      </div>

      <div class="fg">
        <label>PHE Circle</label>
        <select v-model="filters.circle_id">
          <option value="">All Circles</option>
          <option v-for="c in filteredCircles" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>

      <div class="fg">
        <label>District</label>
        <select v-model="filters.district_id" @change="filters.phed_division_id=''">
          <option value="">All Districts</option>
          <option v-for="d in filteredDistricts" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
      </div>

      <div class="fg">
        <label>PHE Division</label>
        <select v-model="filters.phed_division_id">
          <option value="">All PHE Divisions</option>
          <option v-for="p in filteredPhedDivs" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
      </div>

      <div class="fg">
        <label>Lab</label>
        <select v-model="filters.laboratory_id">
          <option value="">All Labs</option>
          <option v-for="l in laboratories" :key="l.id" :value="l.id">{{ l.name }}</option>
        </select>
      </div>

      <!-- sample_type values match actual desired_test DB values -->
      <div class="fg">
        <label>Sample Type</label>
        <select v-model="filters.sample_type">
          <option value="">All Types</option>
          <option value="Physical">Physical (P)</option>
          <option value="Physical &amp; Chemical">Physical &amp; Chemical (PC)</option>
          <option value="Microbiological">Microbiological (M)</option>
          <option value="On Demand">On Demand</option>
        </select>
      </div>

      <div class="fg">
        <label>Result</label>
        <select v-model="filters.result">
          <option value="">All</option>
          <option value="Fit">Fit</option>
          <option value="Unfit">Unfit</option>
        </select>
      </div>

      <div class="tsp"></div>
      <button class="btn btn-pri btn-sm" @click="generateReport" :disabled="loading">
        {{ loading ? 'Generating...' : 'Generate' }}
      </button>
      <button class="btn btn-sec btn-sm" @click="exportReport">Export .xlsx</button>
      <button class="btn btn-sec btn-sm" @click="printReport">Print PDF</button>
    </div>

    <!-- Error -->
    <div v-if="errorMsg"
         style="background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:10px 14px;margin-bottom:10px;color:#b91c1c;font-size:12px">
      {{ errorMsg }}
    </div>

    <!-- Loading -->
    <div v-if="loading" style="text-align:center;padding:48px;color:var(--muted);font-size:13px">
      Loading report data...
    </div>

    <template v-if="!loading && generated">
      <!-- ── Report header banner ── -->
      <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:10px 16px;margin-bottom:8px;font-size:12px">
        <div style="font-weight:700;color:#166534;margin-bottom:3px">
          General Summary Report (GSR) &nbsp;|&nbsp; Period: {{ reportPeriod }} &nbsp;|&nbsp; {{ reportLab }} &nbsp;|&nbsp; Annexure-2
        </div>
        <div style="color:#6b7280;font-size:11px">
          Generated By: <b>{{ generatedBy }}</b> &nbsp;·&nbsp;
          Generated On: <b>{{ generatedAt }}</b> &nbsp;·&nbsp;
          Lab: <b>{{ reportLab }}</b> &nbsp;·&nbsp;
          Division: <b>{{ reportDivision }}</b> &nbsp;·&nbsp;
          District: <b>{{ reportDistrict }}</b>
        </div>
      </div>

      <!-- ── Summary stat cards ── -->
      <div class="cards" style="grid-template-columns:repeat(5,1fr);margin-bottom:14px">
        <div class="card">
          <div class="c-lbl">Total Samples</div>
          <div class="c-val">{{ totalCount.toLocaleString() }}</div>
          <div class="c-sub">Tested</div>
        </div>
        <div class="card c-green">
          <div class="c-lbl">Fit</div>
          <div class="c-val">{{ fitCount.toLocaleString() }}</div>
          <div class="c-sub">Potable</div>
        </div>
        <div class="card c-red">
          <div class="c-lbl">% Unfit</div>
          <div class="c-val">{{ unfitPct }}</div>
          <div class="c-sub">{{ unfitCount }} Contaminated</div>
        </div>
        <div class="card">
          <div class="c-lbl">Districts Covered</div>
          <div class="c-val">{{ districtsCovered }}</div>
        </div>
        <div class="card">
          <div class="c-lbl">PHE Divisions</div>
          <div class="c-val">{{ phedDivCount }}</div>
        </div>
      </div>

      <!-- ── Sample-wise Results table ── -->
      <div class="sh" style="margin-bottom:8px"><h2>Sample-wise Results</h2></div>

      <div class="tbl-wrap" style="overflow-x:auto">
        <table style="font-size:11.5px;min-width:1200px">
          <thead>
            <tr style="background:var(--navy);color:#fff">
              <th style="color:#fff;width:36px">S#</th>
              <th style="color:#fff">Sample ID</th>
              <th style="color:#fff">WSS / Client Name</th>
              <th style="color:#fff">Sampling Date</th>
              <th style="color:#fff">Sampling Point</th>
              <th style="color:#fff">PHE Division</th>
              <th style="color:#fff">Latitude</th>
              <th style="color:#fff">Longitude</th>
              <th style="color:#fff;text-align:center">Test Type</th>
              <th style="color:#fff;text-align:center">Result</th>
              <th style="color:#fff">Cause</th>
              <th style="color:#fff">Specific Ion / Component</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!filteredRows.length">
              <td colspan="12" style="text-align:center;padding:28px;color:var(--muted)">
                No samples found for the selected filters.
              </td>
            </tr>

            <template v-for="(rows, district) in groupedByDistrict" :key="district">
              <!-- District header row -->
              <tr style="background:#f0f4ff;border-top:2px solid var(--sky)">
                <td colspan="12"
                    style="font-size:11px;font-weight:700;color:var(--navy2);padding:5px 12px;text-transform:uppercase;letter-spacing:.05em">
                  ▸ {{ district }} District
                  <span style="font-weight:400;color:var(--muted);margin-left:8px">
                    ({{ rows.length }} sample{{ rows.length !== 1 ? 's' : '' }} —
                    Fit: {{ rows.filter(r => r.result === 'Fit').length }},
                    Unfit: {{ rows.filter(r => r.result === 'Unfit').length }})
                  </span>
                </td>
              </tr>

              <!-- Sample rows -->
              <tr v-for="(r, i) in rows" :key="r.id"
                  :class="i % 2 === 1 ? 'alt' : ''"
                  :style="r.result === 'Unfit' ? 'background:#fff3f3' : ''">
                <td class="mono" style="color:var(--muted);font-size:11px;text-align:center">{{ r.sn }}</td>
                <td class="mono" style="font-size:11px;font-weight:600"
                    :style="r.result === 'Unfit' ? 'color:var(--red)' : ''">{{ r.id }}</td>
                <td style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ r.wss }}</td>
                <td style="white-space:nowrap">{{ r.date }}</td>
                <td style="font-size:11px">{{ r.point }}</td>
                <td style="font-size:11px">{{ r.phedDiv }}</td>
                <td class="mono" style="font-size:11px">{{ r.lat }}</td>
                <td class="mono" style="font-size:11px">{{ r.lng }}</td>
                <td style="text-align:center">
                  <span class="rag" :style="typeStyle(r.type)">{{ r.type }}</span>
                </td>
                <td style="text-align:center">
                  <span class="rag"
                        :class="r.result === 'Fit' ? 'r-green' : r.result === 'Unfit' ? 'r-red' : 'r-grey'">
                    {{ r.result }}
                  </span>
                </td>
                <td style="font-size:11px;max-width:130px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"
                    :title="r.cause">{{ r.cause }}</td>
                <td style="font-size:11px">{{ r.ion }}</td>
              </tr>
            </template>
          </tbody>

          <tfoot>
            <tr style="background:var(--navy);color:#fff;font-weight:700">
              <td colspan="9" style="color:#fff;text-align:right;padding-right:12px">
                TOTALS ({{ totalCount.toLocaleString() }} samples)
              </td>
              <td style="text-align:center">
                <span style="background:#16a34a;color:#fff;border-radius:4px;padding:2px 8px;font-size:11px;margin-right:4px">
                  Fit: {{ fitCount }}
                </span>
                <span style="background:#dc2626;color:#fff;border-radius:4px;padding:2px 8px;font-size:11px">
                  Unfit: {{ unfitCount }}
                </span>
              </td>
              <td colspan="2"></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Legend -->
      <div style="font-size:10.5px;color:var(--muted);margin-top:8px;line-height:1.9">
        <b>Test Type:</b>
        P = Physical &nbsp;·&nbsp;
        PC = Physical &amp; Chemical &nbsp;·&nbsp;
        M = Microbiological &nbsp;·&nbsp;
        OD = On Demand<br>
        <b>Cause:</b>
        Biological = microbial contamination &nbsp;·&nbsp;
        Chemical = ionic/dissolved &nbsp;·&nbsp;
        Physical = turbidity/colour/odour &nbsp;·&nbsp;
        — = sample is Fit
      </div>
    </template>
  </div>
</template>

<style>
@media print {
  .filters, .btn, nav, aside { display: none !important; }
  .tbl-wrap, .tbl-wrap *, .cards, .cards *, .sh, .sh * { visibility: visible; }
  body { font-size: 10px; }
}
</style>
