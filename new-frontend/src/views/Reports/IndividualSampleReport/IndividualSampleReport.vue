<script setup>
import { ref, computed } from 'vue'
import { api } from '../../../services/api.js'

const searchId   = ref('')
const searchWss  = ref('')
const searchLab  = ref('')
const searchFrom = ref('')
const searchTo   = ref('')
const allLabs    = ref([])
const loading    = ref(false)
const errorMsg   = ref('')
const rawData    = ref(null)

// Load labs on mount
import { onMounted } from 'vue'
onMounted(async () => {
  try {
    const res = await api.get('/all-laboratories')
    allLabs.value = res.data?.data || res.data || []
  } catch (e) { console.error('Labs load error:', e) }
})

// ── Fetch report ──────────────────────────────────────────────────────
async function searchSample() {
  const id = searchId.value.trim()
  if (!id && !searchWss.value && !searchLab.value && !searchFrom.value) {
    errorMsg.value = 'Please enter a Sample ID or at least one filter'
    return
  }
  loading.value = true
  errorMsg.value = ''
  rawData.value  = null
  try {
    let numericId = null

    if (id) {
      if (id.includes('/')) {
        // Slug — search to resolve numeric ID
        const searchRes = await api.post('/search-water-sample', { slug: id })
        const items = searchRes.data?.data?.data || searchRes.data?.data || []
        const found = Array.isArray(items) ? items.find(s => s.slug === id) || items[0] : null
        if (!found) throw new Error('Sample not found')
        numericId = found.id
      } else {
        numericId = parseInt(id)
        if (isNaN(numericId)) throw new Error('Invalid ID')
      }
    } else {
      // Search by WSS name / lab / date
      const params = {}
      if (searchLab.value)  params.laboratory_id = searchLab.value
      if (searchFrom.value) params.start_month   = searchFrom.value
      if (searchTo.value)   params.end_month     = searchTo.value
      const searchRes = await api.post('/search-water-sample', params)
      const items = searchRes.data?.data?.data || searchRes.data?.data || []
      // Filter by WSS name client-side if provided
      const filtered = searchWss.value
        ? items.filter(s => (s.water_scheme?.name || s.water_sample_address || '').toLowerCase().includes(searchWss.value.toLowerCase()))
        : items
      if (!filtered.length) throw new Error('No samples found')
      numericId = filtered[0].id
    }

    const res = await api.get(`/water-samples/${numericId}/report`)
    rawData.value = res.data?.data || res.data
  } catch (e) {
    errorMsg.value = 'Sample not found. Enter a numeric ID (e.g. 5) or full slug (e.g. 26/PWR/PHE/0005).'
    console.error('Report error:', e)
  } finally {
    loading.value = false
  }
}

// Walk grouped or flat details, collect distinct analyst names
function collectAnalystNames(details) {
  const flat = Array.isArray(details)
    ? details
    : Object.values(details || {}).flat()
  const names = new Set()
  flat.forEach(d => {
    const n = d?.analyst_name || d?.analyst?.name
    if (n) names.add(n)
  })
  return [...names]
}

// Compress desired_test (JSON array / comma list / plain string) into PCM / PC / P / C / M code
function formatTestType(raw) {
  if (!raw) return '—'
  let parts = []
  if (Array.isArray(raw)) {
    parts = raw
  } else {
    const str = String(raw).trim()
    if (str.startsWith('[')) {
      try { parts = JSON.parse(str) } catch { parts = [str] }
    } else {
      parts = str.split(/[,;]/)
    }
  }
  const blob = parts.join(' ').toLowerCase()
  let code = ''
  if (blob.includes('physical'))        code += 'P'
  if (blob.includes('chemical'))        code += 'C'
  if (blob.includes('microbiological') ||
      blob.includes('microbial') ||
      blob.includes('bacteriological')) code += 'M'
  return code || (Array.isArray(raw) ? raw.join(', ') : String(raw))
}

// Parse a Laravel timestamp that may arrive as:
//   - ISO:           "2026-05-13T10:30:00Z"  /  "2026-05-13T10:30:00.000000Z"
//   - SQL-ish:       "2026-05-13 10:30:00"
//   - Pretty (accessor-formatted): "07 May, 2026 09:30"
function splitDateTime(stamp) {
  if (!stamp) return { date: '—', time: '—' }
  const s = String(stamp).trim()

  // ISO / SQL: YYYY-MM-DD[ T]HH:MM
  const iso = s.match(/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})/)
  if (iso) return { date: iso[1], time: iso[2] }

  // Pretty format: extract HH:MM, treat the rest as the date string.
  const timeMatch = s.match(/(\d{1,2}:\d{2}(?::\d{2})?)/)
  if (timeMatch) {
    const time = timeMatch[1].substring(0, 5)
    const date = s.replace(timeMatch[0], '').trim()
    return { date: date || '—', time }
  }

  // Date-only string — return as-is
  return { date: s, time: '—' }
}

// ── Map report data ───────────────────────────────────────────────────
const report = computed(() => {
  if (!rawData.value) return null
  const s = rawData.value.water_sample || rawData.value

  const analystNames = collectAnalystNames(s.water_sample_details)
  const labIncharge      = s.lab_incharge?.name      || s.labIncharge?.name      || ''
  const researchOfficer  = s.research_officer?.name  || s.researchOfficer?.name  || ''
  const createdByName    = s.created_by_user?.name   || s.createdByUser?.name    || ''

  // Analysed By → users who actually entered test results (one row per analyst,
  // joined). Falls back to lab_incharge then created_by_user.
  const analysedBy = analystNames.length
    ? analystNames.join(', ')
    : (labIncharge || createdByName || '—')

  return {
    sampleId:         s.slug || String(s.id),
    reportNo:         `RPT-${new Date(s.created_at||Date.now()).getFullYear()}-${String(s.id).padStart(5,'0')}`,
    wss:              s.water_scheme?.name || s.waterScheme?.name || s.water_sample_address || '—',
    district:         s.district?.name || '—',
    division:         s.division?.name || '—',
    collectionPoint:  s.sampling_point || '—',
    sourceGps:        s.latitude && s.longitude ? `${s.latitude}, ${s.longitude}` : '—',
    collectionDate:   splitDateTime(s.sampled_at).date,
    collectionTime:   splitDateTime(s.sampled_at).time,
    dateReceived:     splitDateTime(s.reported_at).date !== '—'
                        ? splitDateTime(s.reported_at).date
                        : splitDateTime(s.created_at).date,
    collectedBy:      s.collected_by || createdByName || '—',
    analysedBy,
    checkedBy:        researchOfficer || '—',
    issuedBy:         labIncharge || '—',
    // desired_test is the array of parameter categories (Physical / Chemical / Microbial);
    // test_type is the sample's freshness category ("Fresh"), not what this column is asking for.
    testType:         formatTestType(s.desired_test || s.test_type),
    reasonForTesting: s.complaint_by_other?.trim()
                        || s.complaint
                        || '—',
    overallResult:    s.result || 'Pending',
    laboratory:       s.laboratory?.name || 'Central Water Quality Laboratory, Peshawar',
    labIncharge:      labIncharge || '—',
    researchOfficer:  researchOfficer || '—',
    createdBy:        createdByName || '—',
  }
})

// ── Map parameter details ─────────────────────────────────────────────
const isMissing = (v) => v === null || v === undefined || v === '' ||
                          (typeof v === 'string' && v.trim() === '')

function mapParam(d) {
  // Pick the first non-missing of input_result then analysis_result, else NT
  const raw = !isMissing(d.input_result)    ? d.input_result
            : !isMissing(d.analysis_result) ? d.analysis_result
            : null
  const result = raw === null ? 'NT' : String(raw).trim()

  const whoEnd   = parseFloat(d.who_guideline_end)
  const whoStart = parseFloat(d.who_guideline_start)
  const val      = parseFloat(result)
  const resLower = result.toLowerCase()

  let status = 'Info'
  if (d.criteria && result !== 'NT') {
    if (!isNaN(val) && !isNaN(whoEnd) && whoEnd > 0) {
      status = (val > whoEnd || (!isNaN(whoStart) && whoStart > 0 && val < whoStart)) ? 'Unfit' : 'Fit'
    } else if (['+ve', 'detected', 'positive', 'present'].includes(resLower)) {
      status = 'Unfit'
    } else if (['-ve', 'not detected', 'negative', 'absent', 'nil', 'none'].includes(resLower)) {
      status = 'Fit'
    }
  }

  const limitStr = d.who_guideline_end != null && d.who_guideline_end !== '0.00'
    ? (d.who_guideline_start && d.who_guideline_start !== '0.00'
        ? `${d.who_guideline_start} – ${d.who_guideline_end}`
        : String(d.who_guideline_end))
    : '—'

  return {
    param:   d.water_quality_parameter || '—',
    unit:    d.unit || '—',
    result:  result === 'NT' ? 'NT' : String(result),
    limit:   limitStr,
    status,
    remarks: '—',
  }
}

// Classify a detail's `type` into one of: physical / chemical / microbial / other.
// "On Demand" tests are chemistry-style assays (Arsenic, Fluoride, Iron, ...) — group as chemical.
function classifyType(typeStr) {
  const t = String(typeStr || '').toLowerCase()
  if (t.includes('physical') && !t.includes('chemical')) return 'physical'
  if (t.includes('microbial') || t.includes('biological')) return 'microbial'
  if (t.includes('chemical') || t.includes('on demand') || t.includes('on-demand')) return 'chemical'
  return 'other'
}

// Pull a flat array of detail rows out of either { type: [...] } groups or [ ... ] flat
function flattenDetails(details) {
  if (!details) return []
  if (Array.isArray(details)) return details
  return Object.entries(details).flatMap(([type, arr]) =>
    Array.isArray(arr) ? arr.map(d => ({ ...d, type: d.type || type })) : []
  )
}

const physicalParams = computed(() => {
  if (!rawData.value) return []
  const s = rawData.value.water_sample || rawData.value
  return flattenDetails(s.water_sample_details)
    .filter(d => classifyType(d.type) === 'physical')
    .map(mapParam)
})

const chemicalParams = computed(() => {
  if (!rawData.value) return []
  const s = rawData.value.water_sample || rawData.value
  return flattenDetails(s.water_sample_details)
    .filter(d => classifyType(d.type) === 'chemical')
    .map(mapParam)
})

const microbialParams = computed(() => {
  if (!rawData.value) return []
  const s = rawData.value.water_sample || rawData.value
  return flattenDetails(s.water_sample_details)
    .filter(d => classifyType(d.type) === 'microbial')
    .map(mapParam)
})

const isUnfit = computed(() => report.value?.overallResult?.toLowerCase() === 'unfit' || report.value?.overallResult === '2')

function statusBadge(status) {
  if (status === 'Fit')   return { label: 'Fit',   cls: 'background:#16a34a;color:#fff;border-radius:4px;padding:2px 10px;font-size:11px;font-weight:700' }
  if (status === 'Unfit') return { label: 'Unfit', cls: 'background:#dc2626;color:#fff;border-radius:4px;padding:2px 10px;font-size:11px;font-weight:700' }
  return { label: 'Info', cls: 'background:#6b7280;color:#fff;border-radius:4px;padding:2px 10px;font-size:11px;font-weight:700' }
}

function printReport() { window.print() }
</script>

<template>
  <div>
    <!-- Search bar -->
    <div class="filters" style="margin-bottom:14px">
      <div class="fg" style="flex:2">
        <label>Sample ID</label>
        <input type="text" v-model="searchId" placeholder="e.g. 26/CLB/5042 or numeric ID" @keyup.enter="searchSample">
      </div>
      <div class="fg" style="flex:2">
        <label>Client / WSS Name</label>
        <input type="text" v-model="searchWss" placeholder="e.g. Hayatabad WSS, Al-Noor Hospital...">
      </div>
      <div class="tsp"></div>
      <button class="btn btn-pri btn-sm" @click="searchSample" :disabled="loading">
        {{ loading ? 'Searching...' : 'Search' }}
      </button>
      <button v-if="report" class="btn btn-sec btn-sm" @click="printReport">Print PDF</button>
    </div>

    <div v-if="errorMsg" style="background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:10px 14px;margin-bottom:10px;color:#b91c1c;font-size:12px">
      {{ errorMsg }}
    </div>
    <div v-if="!report && !loading" style="text-align:center;padding:40px;color:var(--muted);font-size:13px">
      Enter a Sample ID and click Search to view the report.
    </div>

    <!-- ── Skeleton loading (matches the report layout) ── -->
    <div v-if="loading" class="isr-sk">
      <!-- Govt header -->
      <div class="sk sk-header"></div>

      <!-- Meta grid (12 cells, 2 columns) -->
      <div class="sk-meta">
        <div class="sk-meta-cell" v-for="i in 12" :key="'sm'+i">
          <div class="sk sk-lbl"></div>
          <div class="sk sk-val"></div>
        </div>
      </div>

      <!-- 3 parameter tables (Physical / Chemical / Microbial) -->
      <div v-for="g in 3" :key="'st'+g" style="margin-top:18px">
        <div class="sk sk-section-head"></div>
        <div class="sk-tbl">
          <div class="sk-tbl-head">
            <div class="sk sk-th" v-for="i in 6" :key="'th'+g+'-'+i"></div>
          </div>
          <div class="sk-tbl-row" v-for="r in 4" :key="'tr'+g+'-'+r">
            <div class="sk sk-td" v-for="i in 6" :key="'tc'+g+'-'+r+'-'+i"></div>
          </div>
        </div>
      </div>

      <!-- Verdict banner -->
      <div class="sk sk-verdict"></div>

      <!-- 3 signature blocks -->
      <div class="sk-sigs">
        <div class="sk-sig" v-for="i in 3" :key="'ss'+i">
          <div class="sk sk-sig-line"></div>
          <div class="sk sk-sig-name"></div>
          <div class="sk sk-sig-role"></div>
        </div>
      </div>
    </div>

    <!-- ── REPORT DOCUMENT ── -->
    <div v-if="report" id="report-print"
         style="background:#fff;border:1px solid #d0d7e0;border-radius:6px;padding:28px 32px;max-width:860px;margin:0 auto;box-shadow:0 2px 12px rgba(0,0,0,.08);font-family:'DM Sans',sans-serif">

      <!-- Header -->
      <div style="text-align:center;border-bottom:2px solid #1a2e4a;padding-bottom:14px;margin-bottom:18px">
        <div style="font-size:12px;font-weight:700;color:#1a2e4a;letter-spacing:.05em;text-transform:uppercase">Government of Khyber Pakhtunkhwa</div>
        <div style="font-size:11.5px;color:#2a3f5f;margin:2px 0">Public Health Engineering Department</div>
        <div style="font-size:14px;font-weight:800;color:#1a2e4a;margin:5px 0">Water Quality Analysis Report</div>
        <div style="font-size:11px;color:#6b7280">{{ report.laboratory }}</div>
      </div>

      <!-- Sample meta grid -->
      <div style="border:1px solid #d0d7e0;border-radius:5px;overflow:hidden;margin-bottom:18px;font-size:12px">
        <div style="display:grid;grid-template-columns:1fr 1fr">
          <div style="background:#f0f7ff;padding:8px 14px;border-bottom:1px solid #d0d7e0;border-right:1px solid #d0d7e0">
            <div style="color:#6b7280;font-size:10.5px">Sample ID</div>
            <div style="font-weight:700;font-family:monospace;font-size:13px;color:#1a2e4a">{{ report.sampleId }}</div>
          </div>
          <div style="padding:8px 14px;border-bottom:1px solid #d0d7e0">
            <div style="color:#6b7280;font-size:10.5px">Report No.</div>
            <div style="font-weight:600">{{ report.reportNo }}</div>
          </div>
          <div style="background:#f0f7ff;padding:8px 14px;border-bottom:1px solid #d0d7e0;border-right:1px solid #d0d7e0">
            <div style="color:#6b7280;font-size:10.5px">Water Supply Scheme</div>
            <div style="font-weight:600">{{ report.wss }}</div>
          </div>
          <div style="padding:8px 14px;border-bottom:1px solid #d0d7e0">
            <div style="color:#6b7280;font-size:10.5px">District / Division</div>
            <div style="font-weight:600">{{ report.district }} / {{ report.division }}</div>
          </div>
          <div style="background:#f0f7ff;padding:8px 14px;border-bottom:1px solid #d0d7e0;border-right:1px solid #d0d7e0">
            <div style="color:#6b7280;font-size:10.5px">Collection Point</div>
            <div style="font-weight:600">{{ report.collectionPoint }}</div>
          </div>
          <div style="padding:8px 14px;border-bottom:1px solid #d0d7e0">
            <div style="color:#6b7280;font-size:10.5px">Source GPS</div>
            <div style="font-weight:600;font-family:monospace">{{ report.sourceGps }}</div>
          </div>
          <div style="background:#f0f7ff;padding:8px 14px;border-bottom:1px solid #d0d7e0;border-right:1px solid #d0d7e0">
            <div style="color:#6b7280;font-size:10.5px">Collection Date &amp; Time</div>
            <div style="font-weight:600">{{ report.collectionDate }} &nbsp; {{ report.collectionTime }}</div>
          </div>
          <div style="padding:8px 14px;border-bottom:1px solid #d0d7e0">
            <div style="color:#6b7280;font-size:10.5px">Date Received at Lab</div>
            <div style="font-weight:600">{{ report.dateReceived }}</div>
          </div>
          <div style="background:#f0f7ff;padding:8px 14px;border-bottom:1px solid #d0d7e0;border-right:1px solid #d0d7e0">
            <div style="color:#6b7280;font-size:10.5px">Collected By</div>
            <div style="font-weight:600">{{ report.collectedBy }}</div>
          </div>
          <div style="padding:8px 14px;border-bottom:1px solid #d0d7e0">
            <div style="color:#6b7280;font-size:10.5px">Analysed By</div>
            <div style="font-weight:600">{{ report.analysedBy }}</div>
          </div>
          <div style="background:#f0f7ff;padding:8px 14px;border-bottom:1px solid #d0d7e0;border-right:1px solid #d0d7e0">
            <div style="color:#6b7280;font-size:10.5px">Checked By</div>
            <div style="font-weight:600">{{ report.checkedBy }}</div>
          </div>
          <div style="padding:8px 14px;border-bottom:1px solid #d0d7e0">
            <div style="color:#6b7280;font-size:10.5px">Issued By</div>
            <div style="font-weight:600">{{ report.issuedBy }}</div>
          </div>
          <div style="background:#f0f7ff;padding:8px 14px;border-right:1px solid #d0d7e0">
            <div style="color:#6b7280;font-size:10.5px">Test Type</div>
            <div style="font-weight:600">{{ report.testType }}</div>
          </div>
          <div style="padding:8px 14px">
            <div style="color:#6b7280;font-size:10.5px">Reason for Testing</div>
            <div style="font-weight:600">{{ report.reasonForTesting }}</div>
          </div>
        </div>
      </div>

      <!-- Physical Parameters -->
      <div v-if="physicalParams.length">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;padding-bottom:4px;border-bottom:2px solid #3b82f6">
          <div style="width:10px;height:10px;border-radius:50%;background:#3b82f6"></div>
          <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#1d4ed8">Physical Parameters</span>
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:12px;margin-bottom:16px">
          <thead>
            <tr style="background:#1a2e4a;color:#fff">
              <th style="padding:7px 10px;text-align:left;color:#fff">Parameter</th>
              <th style="padding:7px 10px;text-align:left;color:#fff">Unit</th>
              <th style="padding:7px 10px;text-align:center;color:#fff">Result</th>
              <th style="padding:7px 10px;text-align:center;color:#fff">WHO / NEQS Limit</th>
              <th style="padding:7px 10px;text-align:center;color:#fff">Status</th>
              <th style="padding:7px 10px;text-align:left;color:#fff">Remarks</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(p, i) in physicalParams" :key="p.param" :style="i%2===1?'background:#f8fafc':''">
              <td style="padding:6px 10px;border-bottom:1px solid #e2e8f0">{{ p.param }}</td>
              <td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;color:#6b7280">{{ p.unit }}</td>
              <td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;text-align:center;font-family:monospace;font-weight:600">{{ p.result }}</td>
              <td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;text-align:center;font-family:monospace">{{ p.limit }}</td>
              <td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;text-align:center">
                <span :style="statusBadge(p.status).cls">{{ statusBadge(p.status).label }}</span>
              </td>
              <td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;color:#6b7280">{{ p.remarks }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Chemical Parameters -->
      <div v-if="chemicalParams.length">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;padding-bottom:4px;border-bottom:2px solid #f97316">
          <div style="width:10px;height:10px;border-radius:50%;background:#f97316"></div>
          <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#c2410c">Chemical Parameters</span>
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:12px;margin-bottom:16px">
          <thead>
            <tr style="background:#1a2e4a;color:#fff">
              <th style="padding:7px 10px;text-align:left;color:#fff">Parameter</th>
              <th style="padding:7px 10px;text-align:left;color:#fff">Unit</th>
              <th style="padding:7px 10px;text-align:center;color:#fff">Result</th>
              <th style="padding:7px 10px;text-align:center;color:#fff">WHO / NEQS Limit</th>
              <th style="padding:7px 10px;text-align:center;color:#fff">Status</th>
              <th style="padding:7px 10px;text-align:left;color:#fff">Remarks</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(p, i) in chemicalParams" :key="p.param" :style="i%2===1?'background:#f8fafc':''">
              <td style="padding:6px 10px;border-bottom:1px solid #e2e8f0">{{ p.param }}</td>
              <td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;color:#6b7280">{{ p.unit }}</td>
              <td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;text-align:center;font-family:monospace;font-weight:600">{{ p.result }}</td>
              <td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;text-align:center;font-family:monospace">{{ p.limit }}</td>
              <td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;text-align:center">
                <span :style="statusBadge(p.status).cls">{{ statusBadge(p.status).label }}</span>
              </td>
              <td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;color:#6b7280">{{ p.remarks }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Microbial Parameters -->
      <div v-if="microbialParams.length">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;padding-bottom:4px;border-bottom:2px solid #dc2626">
          <div style="width:10px;height:10px;border-radius:50%;background:#dc2626"></div>
          <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#991b1b">Microbial Parameters</span>
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:12px;margin-bottom:16px">
          <thead>
            <tr style="background:#1a2e4a;color:#fff">
              <th style="padding:7px 10px;text-align:left;color:#fff">Parameter</th>
              <th style="padding:7px 10px;text-align:left;color:#fff">Unit</th>
              <th style="padding:7px 10px;text-align:center;color:#fff">Result</th>
              <th style="padding:7px 10px;text-align:center;color:#fff">WHO / NEQS Limit</th>
              <th style="padding:7px 10px;text-align:center;color:#fff">Status</th>
              <th style="padding:7px 10px;text-align:left;color:#fff">Remarks</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(p, i) in microbialParams" :key="p.param" :style="i%2===1?'background:#f8fafc':''">
              <td style="padding:6px 10px;border-bottom:1px solid #e2e8f0">{{ p.param }}</td>
              <td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;color:#6b7280">{{ p.unit }}</td>
              <td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;text-align:center;font-family:monospace;font-weight:700"
                  :style="p.status === 'Unfit' ? 'color:#dc2626' : ''">{{ p.result }}</td>
              <td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;text-align:center;font-family:monospace">{{ p.limit }}</td>
              <td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;text-align:center">
                <span :style="statusBadge(p.status).cls">{{ statusBadge(p.status).label }}</span>
              </td>
              <td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;color:#6b7280">{{ p.remarks }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Overall Verdict -->
      <div style="border-radius:7px;padding:14px 20px;margin-bottom:22px;display:flex;align-items:center;gap:16px"
           :style="isUnfit ? 'background:#fff3f3;border:2px solid #dc2626' : 'background:#f0fdf4;border:2px solid #16a34a'">
        <div style="font-size:36px">{{ isUnfit ? 'X' : 'V' }}</div>
        <div>
          <div style="font-size:15px;font-weight:800;letter-spacing:.02em"
               :style="isUnfit ? 'color:#dc2626' : 'color:#16a34a'">
            {{ isUnfit ? 'UNFIT FOR HUMAN CONSUMPTION' : 'FIT FOR HUMAN CONSUMPTION' }}
          </div>
          <div style="font-size:11.5px;color:#6b7280;margin-top:3px">
            <template v-if="isUnfit">
              Contamination detected — parameters exceed WHO/NEQS limits. Immediate corrective action recommended.
            </template>
            <template v-else>
              All tested parameters are within WHO/NEQS permissible limits.
            </template>
          </div>
        </div>
      </div>

      <!-- Signatures -->
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-top:8px;font-size:11.5px">
        <div style="border-top:1px solid #9ca3af;padding-top:8px;text-align:center">
          <div style="color:#6b7280;font-size:10.5px">Analysed By</div>
          <div style="font-weight:600;margin-top:4px">{{ report.analysedBy }}</div>
          <div style="color:#6b7280">Lab Officer</div>
        </div>
        <div style="border-top:1px solid #9ca3af;padding-top:8px;text-align:center">
          <div style="color:#6b7280;font-size:10.5px">Checked By</div>
          <div style="font-weight:600;margin-top:4px">{{ report.researchOfficer }}</div>
          <div style="color:#6b7280">Senior Research Officer</div>
        </div>
        <div style="border-top:1px solid #9ca3af;padding-top:8px;text-align:center">
          <div style="color:#6b7280;font-size:10.5px">Issued By / Lab In-charge</div>
          <div style="font-weight:600;margin-top:4px">{{ report.labIncharge }}</div>
          <div style="color:#6b7280">SRO / Lab In-charge</div>
        </div>
      </div>

      <!-- Footer -->
      <div style="margin-top:18px;padding-top:10px;border-top:1px solid #e2e8f0;font-size:10.5px;color:#9ca3af;display:flex;justify-content:space-between">
        <span>Generated: {{ report.collectionDate }} | PHED KP Lab MIS | Confidential — Internal Use Only</span>
        <span>Report valid for 30 days from date of collection</span>
      </div>
    </div>
  </div>
</template>

<style>
@media print {
  body * { visibility: hidden; }
  #report-print, #report-print * { visibility: visible; }
  #report-print { position: absolute; left: 0; top: 0; width: 100%; }
}

/* ── Skeleton loading ─────────────────────────────────────────────── */
.isr-sk {
  max-width: 860px;
  margin: 0 auto;
  background: #fff;
  border: 1px solid #d0d7e0;
  border-radius: 6px;
  padding: 28px 32px;
  box-shadow: 0 2px 12px rgba(0,0,0,.08);
}
.isr-sk .sk {
  background: linear-gradient(90deg, #e5e7eb 0%, #f3f4f6 50%, #e5e7eb 100%);
  background-size: 200% 100%;
  border-radius: 4px;
  animation: isr-shimmer 1.4s infinite linear;
}
@keyframes isr-shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
.isr-sk .sk-header { height: 72px; margin-bottom: 18px; }

.isr-sk .sk-meta {
  display: grid;
  grid-template-columns: 1fr 1fr;
  border: 1px solid #d0d7e0;
  border-radius: 5px;
  overflow: hidden;
  margin-bottom: 18px;
}
.isr-sk .sk-meta-cell {
  padding: 8px 14px;
  border-bottom: 1px solid #e5e7eb;
}
.isr-sk .sk-meta-cell:nth-child(odd) { border-right: 1px solid #e5e7eb; background: #f8fafc; }
.isr-sk .sk-lbl { height: 8px; width: 45%; margin-bottom: 6px; }
.isr-sk .sk-val { height: 12px; width: 70%; }

.isr-sk .sk-section-head { height: 14px; width: 200px; margin-bottom: 6px; }

.isr-sk .sk-tbl {
  border: 1px solid #e5e7eb;
  border-radius: 4px;
  overflow: hidden;
}
.isr-sk .sk-tbl-head, .isr-sk .sk-tbl-row {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1.5fr 1fr 1.5fr;
  gap: 8px;
  padding: 8px 10px;
}
.isr-sk .sk-tbl-head { background: #f3f4f6; }
.isr-sk .sk-tbl-row + .sk-tbl-row { border-top: 1px solid #f3f4f6; }
.isr-sk .sk-th, .isr-sk .sk-td { height: 11px; }

.isr-sk .sk-verdict {
  height: 60px;
  border-radius: 7px;
  margin: 18px 0;
}

.isr-sk .sk-sigs {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 20px;
  margin-top: 8px;
}
.isr-sk .sk-sig { text-align: center; }
.isr-sk .sk-sig-line { height: 1px; width: 100%; margin-bottom: 8px; }
.isr-sk .sk-sig-name { height: 12px; width: 70%; margin: 0 auto 4px; }
.isr-sk .sk-sig-role { height: 9px; width: 55%; margin: 0 auto; }
</style>
