<script setup>
import { ref, computed, onMounted } from 'vue'
import { api } from '../../../services/api.js'
import { sampleService } from '../../../services/sampleService.js'
import { exportToXLSX } from '../../../utils/exportHelpers.js'

// ── State ─────────────────────────────────────────────────────────────
const loading      = ref(false)
const errorMsg     = ref('')
const unfitSamples = ref([])
const searchText   = ref('')
const labFilter    = ref('')
const statusFilter = ref('')
const allLabs      = ref([])

// ── Status map (current_status integer â†’ label) ───────────────────────
const STATUS = { 1:'Pending', 2:'Fit', 3:'Unfit', 4:'In Progress', 5:'Closed' }

// ── Map backend row ───────────────────────────────────────────────────
function mapRow(s) {
  const tests   = s.tests || []
  const round   = s.current_round || 0
  const lastTest = tests[tests.length - 1]

  // Determine action status from current_status + round
  const cs = s.current_status  // integer
  let actionStatus = 'No Action Yet'
  if (cs === 3 && round === 0)  actionStatus = 'No Action Yet'
  if (cs === 3 && round === 1)  actionStatus = 'Action Taken'
  if (cs === 3 && round === 2)  actionStatus = 'XEN Re-notified'
  if (cs === 3 && round >= 3)   actionStatus = 'Fate Decision Req.'
  if (cs === 2)                 actionStatus = 'Resolved'
  if (cs === 5)                 actionStatus = 'Resolved'

  // Retest result from last test
  const retestResult = lastTest?.result
    ? (String(lastTest.result).toLowerCase().includes('fit') ? 'Fit' : 'Unfit')
    : '—'

  return {
    id:          s.slug || String(s.id),
    backendId:   s.id,
    wss:         s.water_scheme?.name || s.waterScheme?.name || s.water_sample_address || '—',
    phedDiv:     s.phed_division?.name || s.phedDivision?.name || '—',
    district:    s.district?.name || '—',
    date:        s.sampled_at ? formatDate(s.sampled_at) : '—',
    cause:       s.analysis_result_cause || '—',
    value:       s.analysis_result_value || '—',
    actionStatus,
    round,
    retestResult,
    currentStatus: cs,
    isClosed:    s.is_closed || false,
    tests,
  }
}

function formatDate(dt) {
  if (!dt) return '—'
  const d = new Date(dt)
  if (isNaN(d)) return dt
  return d.toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'2-digit' }).replace(/ /g,'-')
}

// ── Load data ─────────────────────────────────────────────────────────
async function loadData() {
  loading.value  = true
  errorMsg.value = ''
  try {
    const [samplesRes, labsRes] = await Promise.all([
      sampleService.getAll({ result: 'Unfit' }),
      api.get('/all-laboratories'),
    ])
    // Try result='Unfit' first, fallback to current_status=3 (UNFIT enum)
    let raw = samplesRes.data?.data?.data
           || samplesRes.data?.data
           || samplesRes.data
           || []
    // If no results with result='Unfit', try fetching by current_status=3
    if (!Array.isArray(raw) || raw.length === 0) {
      const res2 = await sampleService.getAll({ current_status: 3 })
      raw = res2.data?.data?.data || res2.data?.data || res2.data || []
    }
    unfitSamples.value = Array.isArray(raw) ? raw.map(mapRow) : []
    allLabs.value = labsRes.data?.data || labsRes.data || []
  } catch (e) {
    errorMsg.value = 'Failed to load unfit samples: ' + (e.response?.data?.message || e.message)
    console.error(e)
  } finally {
    loading.value = false
  }
}

// ── Summary stats ─────────────────────────────────────────────────────
const summary = computed(() => {
  const s = unfitSamples.value
  return {
    total:       s.length,
    noAction:    s.filter(r => r.actionStatus === 'No Action Yet').length,
    actionTaken: s.filter(r => r.actionStatus === 'Action Taken').length,
    renotified:  s.filter(r => r.actionStatus === 'XEN Re-notified').length,
    fatePending: s.filter(r => r.actionStatus === 'Fate Decision Req.').length,
    resolved:    s.filter(r => r.actionStatus === 'Resolved').length,
  }
})

// ── Filtered rows ─────────────────────────────────────────────────────
const filteredRows = computed(() => unfitSamples.value.filter(r => {
  const q = searchText.value.toLowerCase()
  const matchSearch = !q || r.id.toLowerCase().includes(q) || r.wss.toLowerCase().includes(q) || r.district.toLowerCase().includes(q)
  const matchStatus = !statusFilter.value || r.actionStatus === statusFilter.value
  return matchSearch && matchStatus
}))

// Group by district for display
const groupedRows = computed(() => {
  const groups = {}
  filteredRows.value.forEach(r => {
    const key = r.district || 'Unknown District'
    if (!groups[key]) groups[key] = []
    groups[key].push(r)
  })
  return groups
})

// ── RAG helpers ───────────────────────────────────────────────────────
function ragClass(row) {
  if (row.actionStatus === 'Resolved')           return 'r-green'
  if (row.actionStatus === 'Fate Decision Req.')  return 'r-red'
  if (row.actionStatus === 'XEN Re-notified')     return 'r-amber'
  if (row.actionStatus === 'Action Taken')        return 'r-amber'
  return 'r-red'
}

function statusStyle(row) {
  if (row.actionStatus === 'Resolved')           return 'background:#16a34a;color:#fff;border:none'
  if (row.actionStatus === 'Fate Decision Req.')  return 'background:#9d174d;color:#fff;border:none'
  if (row.actionStatus === 'XEN Re-notified')     return 'background:#d97706;color:#fff;border:none'
  if (row.actionStatus === 'Action Taken')        return 'background:#d97706;color:#fff;border:none'
  return 'background:#dc2626;color:#fff;border:none'
}

// ── Retest modal ──────────────────────────────────────────────────────
const showRetestModal = ref(false)
const retestTarget    = ref(null)
const retestLoading   = ref(false)
const retestForm      = ref({
  date: new Date().toISOString().split('T')[0],
  time: '09:00',
  collected_in: 'Plastic Bottle',
  collected_by: 'Laboratory Staff',
  desired_test: ['Physical', 'Physical & Chemical', 'Microbiological(MF)'],
  source_type: 'Pumping',
  source_sub_type: 'Tube Well',
  complaint: 'General Q.Analysis',
  temperature_in_celsius: 20,
  sampling_point: 'Source',
  reported_at: '',
})

function openRetest(row) {
  retestTarget.value = row
  const d = new Date(); d.setDate(d.getDate() + 3)
  retestForm.value = {
    date: new Date().toISOString().split('T')[0],
    time: '09:00',
    collected_in: 'Plastic Bottle',
    collected_by: 'Laboratory Staff',
    desired_test: ['Physical', 'Physical & Chemical', 'Microbiological(MF)'],
    source_type: 'Pumping',
    source_sub_type: 'Tube Well',
    complaint: 'General Q.Analysis',
    temperature_in_celsius: 20,
    sampling_point: 'Source',
    reported_at: d.toISOString().split('T')[0] + ' 09:00:00',
  }
  showRetestModal.value = true
}

async function submitRetest() {
  if (!retestForm.value.date) { alert('Please enter a collection date.'); return }
  retestLoading.value = true
  try {
    const now = new Date()
    const sampled = new Date(`${retestForm.value.date}T${retestForm.value.time}:00`)
    const finalSampled = sampled > now ? now : sampled
    const pad = n => String(n).padStart(2,'0')
    const fmt = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`

    await sampleService.requestRetest(retestTarget.value.backendId, {
      source_type:           retestForm.value.source_type,
      source_sub_type:       retestForm.value.source_sub_type,
      complaint:             retestForm.value.complaint,
      desired_test:          retestForm.value.desired_test,
      sampling_point:        retestForm.value.sampling_point,
      collected_by:          retestForm.value.collected_by,
      collected_in:          retestForm.value.collected_in,
      temperature_in_celsius: retestForm.value.temperature_in_celsius,
      sampled_at:            fmt(finalSampled),
      reported_at:           retestForm.value.reported_at || fmt(new Date(finalSampled.getTime() + 3*24*60*60*1000)),
    })
    showRetestModal.value = false
    await loadData()
  } catch (e) {
    alert('Failed to register retest: ' + (e.response?.data?.message || e.message))
    console.error(e)
  } finally {
    retestLoading.value = false
  }
}

// ── Trail modal ───────────────────────────────────────────────────────
const showTrailModal = ref(false)
const trailTarget    = ref(null)
const trailDetail    = ref(null)
const trailLoading   = ref(false)

// Decoders for water_sample_tests enum columns —
// Laravel may serialize these as raw integers depending on cast configuration.
// Mirrors backend enums: WaterSampleTestStatusEnum (0=Pending,1=Completed,2=In Progress)
// and WaterSampleTestResultEnum (1=Fit, 2=Unfit).
const TEST_STATUS_MAP = { 0: 'Pending', 1: 'Completed', 2: 'In Progress' }
const TEST_RESULT_MAP = { 1: 'Fit', 2: 'Unfit' }

function labelStatus(v) {
  if (v === null || v === undefined || v === '') return null
  if (typeof v === 'string' && isNaN(Number(v))) return v   // already a label
  return TEST_STATUS_MAP[Number(v)] ?? String(v)
}
function labelResult(v) {
  if (v === null || v === undefined || v === '') return null
  if (typeof v === 'string' && isNaN(Number(v))) return v   // already 'Fit'/'Unfit'
  return TEST_RESULT_MAP[Number(v)] ?? String(v)
}

// Test rounds shown in the Trail modal. When the backend has no
// `water_sample_tests` rows for this sample (legacy records that store
// the result directly on `water_samples`), synthesize an R0 entry so the
// user still sees the original sampling/analysis dates and the Fit/Unfit
// result. Real test rounds — when present — take precedence.
const trailRounds = computed(() => {
  const d = trailDetail.value
  if (!d) return []
  const explicit = d.water_sample_tests || d.tests || []
  if (explicit.length) {
    return explicit.map(t => ({
      ...t,
      status: labelStatus(t.status),
      result: labelResult(t.result),
    }))
  }
  if (d.sampled_at || d.analyzed_at || d.result) {
    return [{
      id: 'r0-' + (d.id || 'sample'),
      round: 0,
      sampled_at:  d.sampled_at  || null,
      analyzed_at: d.analyzed_at || null,
      status: d.analyzed_at ? 'Completed' : (STATUS[d.current_status] || 'Pending'),
      result: labelResult(d.result),
    }]
  }
  return []
})

async function openTrail(row) {
  trailTarget.value = row
  trailDetail.value = null
  showTrailModal.value = true
  trailLoading.value = true
  try {
    const res = await sampleService.getById(row.backendId)
    trailDetail.value = res.data?.data || res.data
  } catch (e) {
    console.error('Trail load error:', e)
  } finally {
    trailLoading.value = false
  }
}

// ── Fate modal ────────────────────────────────────────────────────────
const showFateModal = ref(false)
const fateTarget    = ref(null)
const fateDecision  = ref('')
const fateForm      = ref({ authorisedBy:'', date: new Date().toISOString().split('T')[0], remarks:'', docRef:'' })
const fateSuccess   = ref(false)
const fateLoading   = ref(false)

function openFate(row) {
  fateTarget.value   = row
  fateDecision.value = ''
  fateSuccess.value  = false
  fateForm.value     = { authorisedBy:'', date: new Date().toISOString().split('T')[0], remarks:'', docRef:'' }
  showFateModal.value = true
}

async function submitFate() {
  if (!fateDecision.value) { alert('Please select a decision.'); return }
  if (!fateForm.value.remarks) { alert('Remarks are required.'); return }
  fateLoading.value = true
  try {
    await api.patch(`/water-samples/${fateTarget.value.backendId}/fate`, {
      decision:       fateDecision.value,
      authorised_by:  fateForm.value.authorisedBy || null,
      decision_date:  fateForm.value.date || null,
      remarks:        fateForm.value.remarks,
      doc_ref:        fateForm.value.docRef || null,
    })
    fateSuccess.value = true
    await loadData()
  } catch (e) {
    alert('Failed to record decision: ' + (e.response?.data?.message || e.message))
  } finally {
    fateLoading.value = false
  }
}

// ── Export ────────────────────────────────────────────────────────────
function exportData() {
  if (!filteredRows.value.length) { alert('No data to export.'); return }
  exportToXLSX(filteredRows.value.map(r => ({
    'Sample ID':      r.id,
    'WSS Name':       r.wss,
    'PHE Division':   r.phedDiv,
    'District':       r.district,
    'Result Date':    r.date,
    'Cause':          r.cause,
    'Value / Limit':  r.value,
    'Status':         r.actionStatus,
    'Retest Round':   r.round,
    'Retest Result':  r.retestResult,
  })), 'Unfit_Sample_Trail')
}

onMounted(loadData)
</script>
<template>
  <div>
    <!-- Auto-notification banner -->
    <div class="abar blue" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:10px;font-size:12px">
      <span>🔔 <b>Auto-Notification:</b> XEN of the relevant PHE Division is automatically notified via MIS Dashboard alert &amp; official email within 15 minutes of an unfit result being recorded. No manual action is required.</span>
      <button class="btn btn-sec btn-xs">View Notification Log</button>
    </div>

    <!-- Workflow steps -->
    <div style="background:#fff;border:1px solid var(--border);border-radius:8px;padding:14px 18px;margin-bottom:12px;overflow-x:auto">
      <div style="font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:12px">Unfit Sample Resolution Workflow</div>
      <div style="display:flex;align-items:center;gap:0;min-width:900px">

        <!-- Step 1 -->
        <div style="text-align:center;min-width:72px">
          <div style="width:32px;height:32px;border-radius:50%;background:#1d4ed8;color:#fff;display:flex;align-items:center;justify-content:center;margin:0 auto 4px;font-size:13px;font-weight:700">1</div>
          <div style="font-size:11px;font-weight:700;color:var(--navy)">Unfit Result</div>
          <div style="font-size:10px;color:var(--muted)">Recorded by lab</div>
        </div>
        <div style="flex:1;height:2px;background:#cbd5e1;min-width:16px"></div>

        <!-- Step 2 -->
        <div style="text-align:center;min-width:72px">
          <div style="width:32px;height:32px;border-radius:50%;background:#0891b2;color:#fff;display:flex;align-items:center;justify-content:center;margin:0 auto 4px;font-size:13px;font-weight:700">2</div>
          <div style="font-size:11px;font-weight:700;color:var(--navy)">XEN Notified</div>
          <div style="font-size:10px;color:var(--muted)">Auto — ≤15 min</div>
        </div>
        <div style="flex:1;height:2px;background:#cbd5e1;min-width:16px"></div>

        <!-- Step 3 -->
        <div style="text-align:center;min-width:72px">
          <div style="width:32px;height:32px;border-radius:50%;background:#d97706;color:#fff;display:flex;align-items:center;justify-content:center;margin:0 auto 4px;font-size:13px;font-weight:700">3</div>
          <div style="font-size:11px;font-weight:700;color:var(--navy)">XEN Action</div>
          <div style="font-size:10px;color:var(--muted)">Remedial work logged</div>
        </div>
        <div style="flex:1;height:2px;background:#cbd5e1;min-width:16px"></div>

        <!-- Step 4 -->
        <div style="text-align:center;min-width:72px">
          <div style="width:32px;height:32px;border-radius:50%;background:#7c3aed;color:#fff;display:flex;align-items:center;justify-content:center;margin:0 auto 4px;font-size:13px;font-weight:700">4</div>
          <div style="font-size:11px;font-weight:700;color:var(--navy)">Retest</div>
          <div style="font-size:10px;color:var(--muted)">New sample collected</div>
        </div>
        <div style="flex:1;height:2px;background:#cbd5e1;min-width:16px"></div>

        <!-- Retest Outcome box -->
        <div style="border:1.5px solid #16a34a;border-radius:7px;padding:7px 12px;min-width:130px;background:#f0fdf4">
          <div style="font-size:10px;font-weight:700;color:#166534;margin-bottom:5px;text-align:center">Retest Outcome</div>
          <div style="display:flex;flex-direction:column;gap:4px">
            <div style="display:flex;align-items:center;gap:5px;font-size:10.5px">
              <span style="background:#16a34a;color:#fff;border-radius:3px;padding:1px 6px;font-size:10px;font-weight:700">✔ Fit</span>
              <span style="color:#166534;font-size:10px">→ Resolved</span>
            </div>
            <div style="display:flex;align-items:center;gap:5px;font-size:10.5px">
              <span style="background:#dc2626;color:#fff;border-radius:3px;padding:1px 6px;font-size:10px;font-weight:700">✕ Still Unfit</span>
              <span style="color:#991b1b;font-size:10px">↓ Re-notify XEN</span>
            </div>
          </div>
        </div>
        <div style="flex:1;height:2px;background:#cbd5e1;min-width:16px"></div>

        <!-- Step 5 -->
        <div style="text-align:center;min-width:80px">
          <div style="width:32px;height:32px;border-radius:50%;background:#d97706;color:#fff;display:flex;align-items:center;justify-content:center;margin:0 auto 4px;font-size:13px;font-weight:700">5</div>
          <div style="font-size:11px;font-weight:700;color:var(--navy)">XEN Re-notified</div>
          <div style="font-size:10px;color:var(--muted)">Escalation flag set</div>
        </div>
        <div style="flex:1;height:2px;background:#cbd5e1;min-width:16px"></div>

        <!-- Step 6 -->
        <div style="text-align:center;min-width:72px">
          <div style="width:32px;height:32px;border-radius:50%;background:#7c3aed;color:#fff;display:flex;align-items:center;justify-content:center;margin:0 auto 4px;font-size:13px;font-weight:700">6</div>
          <div style="font-size:11px;font-weight:700;color:var(--navy)">Retest #2</div>
          <div style="font-size:10px;color:var(--muted)">Repeat cycle</div>
        </div>
        <div style="flex:1;height:2px;background:#cbd5e1;min-width:16px"></div>

        <!-- Persistent Unfit box -->
        <div style="border:1.5px solid #dc2626;border-radius:7px;padding:7px 12px;min-width:130px;background:#fff5f5">
          <div style="font-size:10px;font-weight:700;color:#991b1b;margin-bottom:5px;text-align:center">Persistent Unfit</div>
          <div style="display:flex;flex-direction:column;gap:4px">
            <div style="display:flex;align-items:center;gap:5px;font-size:10.5px">
              <span style="background:#16a34a;color:#fff;border-radius:3px;padding:1px 6px;font-size:10px;font-weight:700">✔ Fit</span>
              <span style="color:#166534;font-size:10px">→ Resolved</span>
            </div>
            <div style="display:flex;align-items:center;gap:5px;font-size:10.5px">
              <span style="background:#dc2626;color:#fff;border-radius:3px;padding:1px 6px;font-size:10px;font-weight:700">✕ Still Unfit</span>
              <span style="color:#991b1b;font-size:10px">↓ WSS Fate Decision</span>
            </div>
          </div>
        </div>

      </div>
      <!-- Footer note -->
      <div style="font-size:10.5px;color:var(--muted);margin-top:10px;padding-top:8px;border-top:1px solid var(--border)">
        ℹ After each failed retest, XEN is re-notified with an escalation flag. If a WSS remains persistently unfit (especially chemical), the Lab In-charge is prompted to record a formal WSS Fate Decision — options include continued monitoring, public advisory, or decommissioning.
      </div>
    </div>

    <!-- Summary KPI cards -->
    <div class="cards" style="grid-template-columns:repeat(6,1fr);margin-bottom:12px">
      <div class="card c-red" style="cursor:pointer" @click="statusFilter=''">
        <div class="c-lbl">Total Unfit</div><div class="c-val">{{ summary.total }}</div>
        <div class="c-sub">All unfit samples</div>
      </div>
      <div class="card c-red" style="cursor:pointer" @click="statusFilter='No Action Yet'">
        <div class="c-lbl">No Action Yet</div><div class="c-val">{{ summary.noAction }}</div>
        <div class="c-sub">XEN yet to respond</div>
      </div>
      <div class="card c-amber" style="cursor:pointer" @click="statusFilter='Action Taken'">
        <div class="c-lbl">Action Taken</div><div class="c-val">{{ summary.actionTaken }}</div>
        <div class="c-sub">Retest due</div>
      </div>
      <div class="card" style="cursor:pointer" @click="statusFilter='XEN Re-notified'">
        <div class="c-lbl">Re-Notified</div><div class="c-val">{{ summary.renotified }}</div>
        <div class="c-sub">Still unfit after R1</div>
      </div>
      <div class="card" style="cursor:pointer;background:#fff0f5" @click="statusFilter='Fate Decision Req.'">
        <div class="c-lbl">Fate Decision Req.</div><div class="c-val" style="color:#9d174d">{{ summary.fatePending }}</div>
        <div class="c-sub">Persistently unfit</div>
      </div>
      <div class="card c-green" style="cursor:pointer" @click="statusFilter='Resolved'">
        <div class="c-lbl">Resolved</div><div class="c-val">{{ summary.resolved }}</div>
        <div class="c-sub">Status confirmed fit</div>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar" style="margin-bottom:10px">
      <input type="text" v-model="searchText" placeholder="🔍 Sample ID, WSS, District…" style="min-width:200px">
      <select v-model="labFilter" style="border:1px solid var(--input-border);border-radius:4px;padding:6px 8px;font-size:12px;font-family:inherit">
        <option value="">All Labs</option>
        <option v-for="l in allLabs" :key="l.id" :value="l.id">{{ l.name }}</option>
      </select>
      <select v-model="statusFilter" style="border:1px solid var(--input-border);border-radius:4px;padding:6px 8px;font-size:12px;font-family:inherit">
        <option value="">All Status</option>
        <option value="No Action Yet">No Action Yet</option>
        <option value="Action Taken">Action Taken</option>
        <option value="XEN Re-notified">XEN Re-notified</option>
        <option value="Fate Decision Req.">Fate Decision Req.</option>
        <option value="Resolved">Resolved</option>
      </select>
      <div class="tsp"></div>
      <button class="btn btn-sec btn-sm" @click="loadData" :disabled="loading">{{ loading ? '⏳' : '↺ Refresh' }}</button>
      <button class="btn btn-sec btn-sm" @click="exportData">⬇ Export</button>
    </div>

    <!-- Error -->
    <div v-if="errorMsg" style="background:#fee2e2;border:1px solid #fca5a5;border-radius:5px;padding:9px 14px;margin-bottom:10px;font-size:12.5px;color:#991b1b">
      ⚠ {{ errorMsg }}
    </div>

    <!-- Main table grouped by district -->
    <div class="tbl-wrap">
      <div v-if="loading" style="text-align:center;padding:32px;color:var(--muted)">⏳ Loading unfit samples…</div>
      <table v-else style="font-size:12px">
        <thead>
          <tr style="background:var(--navy);color:#fff">
            <th style="color:#fff">Sample ID</th>
            <th style="color:#fff">WSS Name</th>
            <th style="color:#fff">PHE Div.</th>
            <th style="color:#fff">Result Date</th>
            <th style="color:#fff">Cause</th>
            <th style="color:#fff">Value / Limit</th>
            <th style="color:#fff">Status</th>
            <th style="color:#fff;text-align:center">Re-Stage</th>
            <th style="color:#fff;text-align:center">Re-Result</th>
            <th style="color:#fff;text-align:center">RAG</th>
            <th style="color:#fff">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!filteredRows.length">
            <td colspan="11" style="text-align:center;padding:32px;color:var(--muted)">
              {{ loading ? '' : 'No unfit samples found.' }}
            </td>
          </tr>
          <template v-for="(rows, district) in groupedRows" :key="district">
            <tr style="background:#f0f4ff;border-top:2px solid var(--sky)">
              <td colspan="11" style="font-size:11px;font-weight:700;color:var(--navy2);padding:6px 12px;text-transform:uppercase;letter-spacing:.05em">
                📍 {{ district }} District
              </td>
            </tr>
            <tr v-for="(row, i) in rows" :key="row.id"
                :class="i%2===1?'alt':''"
                :style="row.actionStatus === 'Fate Decision Req.' ? 'background:#fff0f5' : ''">
              <td class="mono" style="font-size:11.5px;font-weight:400"
                  :style="row.actionStatus === 'Fate Decision Req.' ? 'color:#9d174d' : ''">{{ row.id }}</td>
              <td>{{ row.wss }}</td>
              <td style="font-size:11px">{{ row.phedDiv }}</td>
              <td style="white-space:nowrap;font-size:11px">{{ row.date }}</td>
              <td style="font-size:11px">{{ row.cause }}</td>
              <td class="mono" style="font-size:11px">{{ row.value }}</td>
              <td>
                <span class="rag" style="font-size:10.5px;white-space:nowrap" :style="statusStyle(row)">{{ row.actionStatus }}</span>
              </td>
              <td style="text-align:center;font-size:11px;color:var(--muted)">{{ row.round > 0 ? 'R' + row.round : '—' }}</td>
              <td style="text-align:center">
                <span v-if="row.retestResult !== '—'" class="rag" style="font-size:10.5px"
                      :class="row.retestResult === 'Fit' ? 'r-green' : 'r-red'">{{ row.retestResult }}</span>
                <span v-else style="color:var(--muted);font-size:11px">—</span>
              </td>
              <td style="text-align:center">
                <span style="font-size:16px" :class="ragClass(row)">●</span>
              </td>
              <td style="white-space:nowrap">
                <template v-if="row.actionStatus === 'Fate Decision Req.'">
                  <button class="btn btn-xs" style="background:#9d174d;color:#fff;border:none;font-size:11px;margin-right:4px" @click="openFate(row)">📋 Decide WSS Fate</button>
                  <button class="btn btn-sec btn-xs" style="font-size:11px" @click="openTrail(row)">Trail</button>
                </template>
                <template v-else-if="row.actionStatus !== 'Resolved'">
                  <button class="btn btn-pri btn-xs" style="font-size:11px;margin-right:4px" @click="openRetest(row)">▶ Register Retest</button>
                  <button class="btn btn-sec btn-xs" style="font-size:11px" @click="openTrail(row)">Trail</button>
                </template>
                <template v-else>
                  <button class="btn btn-sec btn-xs" style="font-size:11px" @click="openTrail(row)">👁 View Trail</button>
                </template>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
      <div class="tbl-footer">
        <span>Showing {{ filteredRows.length }} of {{ unfitSamples.length }} unfit samples</span>
      </div>
    </div>

    <!-- RETEST MODAL -->
    <Teleport to="body">
      <div v-if="showRetestModal" @click.self="showRetestModal = false"
           style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:3200;align-items:center;justify-content:center;padding:16px">
        <div style="background:#fff;border-radius:10px;width:100%;max-width:620px;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,.28)">
          <div style="background:var(--navy);color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between">
            <div>
              <div style="font-size:14px;font-weight:700">▶ Register Retest Sample</div>
              <div style="font-size:11px;opacity:.7;margin-top:2px">{{ retestTarget?.id }} — {{ retestTarget?.wss }}</div>
            </div>
            <button @click="showRetestModal = false" style="background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:5px;padding:5px 12px;cursor:pointer">✕</button>
          </div>
          <div style="padding:20px 24px">
            <div style="background:#fff3f3;border:1px solid #fca5a5;border-radius:6px;padding:10px 14px;margin-bottom:14px;font-size:11.5px;display:grid;grid-template-columns:repeat(4,1fr);gap:8px">
              <div><div style="font-size:10px;color:var(--muted)">Original Result</div><b style="color:var(--red)">❌ UNFIT</b></div>
              <div><div style="font-size:10px;color:var(--muted)">Cause</div><b>{{ retestTarget?.cause }}</b></div>
              <div><div style="font-size:10px;color:var(--muted)">Value / Limit</div><b class="mono">{{ retestTarget?.value }}</b></div>
              <div><div style="font-size:10px;color:var(--muted)">Date</div><b>{{ retestTarget?.date }}</b></div>
            </div>
            <div class="form-grid" style="grid-template-columns:1fr 1fr;gap:12px">
              <div class="fg2"><label>Collection Date *</label><input type="date" v-model="retestForm.date"></div>
              <div class="fg2"><label>Collection Time</label><input type="time" v-model="retestForm.time"></div>
              <div class="fg2">
                <label>Container Type *</label>
                <select v-model="retestForm.collected_in">
                  <option value="Plastic Bottle">Plastic Bottle</option>
                  <option value="Glass Bottle">Glass Bottle</option>
                  <option value="Kit">Kit</option>
                </select>
              </div>
              <div class="fg2">
                <label>Collected By *</label>
                <select v-model="retestForm.collected_by">
                  <option value="Laboratory Staff">Laboratory Staff</option>
                  <option value="Client">Client</option>
                </select>
              </div>
              <div class="fg2">
                <label>Sampling Point *</label>
                <select v-model="retestForm.sampling_point">
                  <option value="Source">Source</option>
                  <option value="Consumer End">Consumer End</option>
                  <option value="Mid">Mid</option>
                </select>
              </div>
              <div class="fg2"><label>Temperature (°C)</label><input type="number" v-model="retestForm.temperature_in_celsius" min="-5" max="50"></div>
            </div>
          </div>
          <div style="padding:12px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px;background:#fafbfc">
            <button class="btn btn-sec" @click="showRetestModal = false">Cancel</button>
            <button class="btn btn-pri" @click="submitRetest" :disabled="retestLoading">{{ retestLoading ? '⏳ Registering…' : '▶ Register Retest' }}</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- TRAIL MODAL -->
    <Teleport to="body">
      <div v-if="showTrailModal" @click.self="showTrailModal = false"
           style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:3200;align-items:flex-start;justify-content:center;overflow-y:auto;padding:24px">
        <div style="background:#fff;border-radius:10px;width:100%;max-width:760px;margin:auto;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,.28)">
          <div style="background:var(--navy);color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:1">
            <div>
              <div style="font-size:14px;font-weight:700">📊 Analysis Trail — {{ trailTarget?.id }}</div>
              <div style="font-size:11px;opacity:.7;margin-top:2px">{{ trailTarget?.wss }} · {{ trailTarget?.district }}</div>
            </div>
            <button @click="showTrailModal = false" style="background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:5px;padding:5px 12px;cursor:pointer">✕</button>
          </div>
          <div style="padding:20px 24px">
            <div v-if="trailLoading" style="text-align:center;padding:24px;color:var(--muted)">⏳ Loading trail…</div>
            <template v-else-if="trailDetail">
              <div class="tbl-wrap">
                <table style="font-size:11.5px">
                  <thead>
                    <tr style="background:var(--navy);color:#fff">
                      <th style="color:#fff">Round</th>
                      <th style="color:#fff">Sampled At</th>
                      <th style="color:#fff">Analyzed At</th>
                      <th style="color:#fff;text-align:center">Status</th>
                      <th style="color:#fff;text-align:center">Result</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="!trailRounds.length">
                      <td colspan="5" style="text-align:center;padding:20px;color:var(--muted)">No test rounds recorded yet.</td>
                    </tr>
                    <tr v-for="(t, i) in trailRounds" :key="t.id" :class="i%2===1?'alt':''">
                      <td class="mono">R{{ t.round }}</td>
                      <td>{{ t.sampled_at ? formatDate(t.sampled_at) : '—' }}</td>
                      <td>{{ t.analyzed_at ? formatDate(t.analyzed_at) : 'Pending' }}</td>
                      <td style="text-align:center"><span class="rag r-blue" style="font-size:10.5px">{{ t.status }}</span></td>
                      <td style="text-align:center">
                        <span class="rag" style="font-size:10.5px"
                              :class="String(t.result||'').toLowerCase().includes('fit') && !String(t.result||'').toLowerCase().includes('unfit') ? 'r-green' : t.result ? 'r-red' : 'r-grey'">
                          {{ t.result || 'Pending' }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </template>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- FATE MODAL -->
    <Teleport to="body">
      <div v-if="showFateModal" @click.self="showFateModal = false"
           style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:3400;align-items:flex-start;justify-content:center;overflow-y:auto;padding:30px">
        <div style="background:#fff;border-radius:10px;width:100%;max-width:580px;margin:auto;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,.28)">
          <div style="background:#9d174d;color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between">
            <div>
              <div style="font-size:14px;font-weight:700">📋 WSS Fate Decision</div>
              <div style="font-size:11px;opacity:.7;margin-top:2px">{{ fateTarget?.wss }} · {{ fateTarget?.district }}</div>
            </div>
            <button @click="showFateModal = false" style="background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:5px;padding:5px 12px;cursor:pointer">✕</button>
          </div>
          <div style="padding:20px 24px">
            <div v-if="!fateSuccess">
              <div style="background:#fff5f5;border:1px solid #fca5a5;border-radius:6px;padding:10px 14px;margin-bottom:14px;font-size:11.5px">
                <b style="color:#991b1b">⚠ Persistently Unfit:</b> {{ fateTarget?.round }} consecutive retest failure(s). Immediate decision required.
              </div>
              <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:8px">Select Decision</div>
              <label v-for="opt in [
                { val:'monitor',      title:'🔄 Continue Monitoring',       desc:'Keep WSS operational. Schedule additional retests.', color:'var(--blue)' },
                { val:'advisory',     title:'⚠ Issue Public Advisory',      desc:'WSS remains operational but public advised against drinking.', color:'#b45309' },
                { val:'decommission', title:'🚫 Decommission / Abandon WSS', desc:'WSS taken out of service permanently. Requires formal approval.', color:'#9d174d' },
              ]" :key="opt.val"
                style="display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border:2px solid var(--border);border-radius:6px;cursor:pointer;margin-bottom:8px"
                :style="fateDecision === opt.val ? `border-color:${opt.color}` : ''"
                @click="fateDecision = opt.val">
                <input type="radio" :value="opt.val" v-model="fateDecision" style="margin-top:2px">
                <div>
                  <div style="font-size:12px;font-weight:600" :style="{ color: opt.color }">{{ opt.title }}</div>
                  <div style="font-size:11px;color:var(--muted)">{{ opt.desc }}</div>
                </div>
              </label>
              <div class="form-grid" style="grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
                <div class="fg2"><label>Authorising Officer</label><input type="text" v-model="fateForm.authorisedBy" placeholder="Name / Designation"></div>
                <div class="fg2"><label>Decision Date</label><input type="date" v-model="fateForm.date"></div>
                <div class="fg2 span2"><label>Remarks / Justification *</label><textarea v-model="fateForm.remarks" rows="3" placeholder="State the basis for this decision…"></textarea></div>
                <div class="fg2 span2"><label>Document Reference (optional)</label><input type="text" v-model="fateForm.docRef" placeholder="e.g. Field inspection report no."></div>
              </div>
              <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:14px">
                <button class="btn btn-sec" @click="showFateModal = false">Cancel</button>
                <button class="btn" style="background:#9d174d;color:#fff;border:none" @click="submitFate" :disabled="fateLoading">{{ fateLoading ? '⏳ Saving…' : '✔ Record Decision' }}</button>
              </div>
            </div>
            <div v-else style="text-align:center;padding:20px 0">
              <div style="font-size:32px;margin-bottom:8px">✅</div>
              <div style="font-size:14px;font-weight:700;color:#166534;margin-bottom:4px">Decision Recorded</div>
              <div style="font-size:12px;color:var(--muted)">WSS status updated. XEN has been notified.</div>
              <button class="btn btn-sec btn-sm" style="margin-top:14px" @click="showFateModal = false">Close</button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
