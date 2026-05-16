<script setup>
import { ref, computed, onMounted } from 'vue'
import { xenService } from '../../services/xenService.js'
import { sampleService } from '../../services/sampleService.js'
import { api } from '../../services/api.js'
import SkelRow from './SkelRow.vue'
import XenTrailModal from './XenTrailModal.vue'

const loading = ref(true)
const errorMsg = ref('')
const samples = ref([])
const stats   = ref({ total: 0, no_action: 0, action_taken: 0, resolved: 0 })
const q       = ref('')
const statusFilter = ref('')

// ── Toast (matches the pattern in Topbar.vue / UsersHR.vue) ──────
const toast = ref({ show: false, message: '', type: 'success' })
let toastTimer = null
function showToast(message, type = 'success') {
  clearTimeout(toastTimer)
  toast.value = { show: true, message, type }
  toastTimer = setTimeout(() => { toast.value.show = false }, 4000)
}

// ── Load ─────────────────────────────────────────────────────────
async function load() {
  loading.value = true
  errorMsg.value = ''
  try {
    const res = await xenService.trail('unfit')
    samples.value = (res.samples || []).map(mapRow)
    stats.value = res.stats || stats.value
  } catch (e) {
    samples.value = []
    errorMsg.value = e?.response?.data?.message || e?.message || 'Failed to load unfit samples'
  } finally {
    loading.value = false
  }
}
onMounted(load)

// ── Status mapping — same as admin page ───────────────────────────
function mapRow(s) {
  const tests = s.tests || []
  const round = s.current_round || 0
  const lastTest = tests[tests.length - 1]
  const cs = s.current_status   // 1=Pending 2=Fit 3=Unfit 4=InProgress 5=Closed

  let actionStatus = 'No Action Yet'
  if (cs === 3 && round === 0) actionStatus = 'No Action Yet'
  if (cs === 3 && round === 1) actionStatus = 'Action Taken'
  if (cs === 3 && round === 2) actionStatus = 'XEN Re-notified'
  if (cs === 3 && round >= 3)  actionStatus = 'Fate Decision Req.'
  if (cs === 2)                actionStatus = 'Resolved'
  if (cs === 5)                actionStatus = 'Resolved'

  const retestResult = lastTest?.result
    ? (String(lastTest.result).toLowerCase().includes('fit') && !String(lastTest.result).toLowerCase().includes('unfit') ? 'Fit' : 'Unfit')
    : '—'

  return {
    id:            s.slug || String(s.id),
    backendId:     s.id,
    wss:           s.water_scheme?.name || s.water_scheme_name || '—',
    phedDiv:       s.phed_division?.name || '—',
    district:      s.district?.name || '—',
    date:          fmtDate(s.sampled_at || s.analyzed_at),
    cause:         s.cause || 'Lab Test',
    value:         s.unfit_parameters || '—',
    actionStatus,
    round,
    retestResult,
    currentStatus: cs,
    isClosed:      s.is_closed || false,
    tests,
  }
}

function fmtDate(d) {
  if (!d) return '—'
  const dt = new Date(d)
  if (isNaN(dt)) return d
  return dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: '2-digit' }).replace(/ /g, '-')
}

// ── Filters / grouped view ────────────────────────────────────────
const filtered = computed(() => samples.value.filter(r => {
  const term = q.value.toLowerCase()
  const matchQ = !term ||
    r.id.toLowerCase().includes(term) ||
    r.wss.toLowerCase().includes(term) ||
    r.district.toLowerCase().includes(term)
  const matchS = !statusFilter.value || r.actionStatus === statusFilter.value
  return matchQ && matchS
}))

const grouped = computed(() => {
  const out = {}
  filtered.value.forEach(r => {
    const key = r.district || 'Unknown District'
    if (!out[key]) out[key] = []
    out[key].push(r)
  })
  return out
})

const summary = computed(() => ({
  total:       samples.value.length,
  noAction:    samples.value.filter(r => r.actionStatus === 'No Action Yet').length,
  actionTaken: samples.value.filter(r => r.actionStatus === 'Action Taken').length,
  renotified:  samples.value.filter(r => r.actionStatus === 'XEN Re-notified').length,
  fatePending: samples.value.filter(r => r.actionStatus === 'Fate Decision Req.').length,
  resolved:    samples.value.filter(r => r.actionStatus === 'Resolved').length,
}))

function ragClass(row) {
  if (row.actionStatus === 'Resolved')           return 'r-green'
  if (row.actionStatus === 'Fate Decision Req.') return 'r-red'
  if (row.actionStatus === 'XEN Re-notified')    return 'r-amber'
  if (row.actionStatus === 'Action Taken')       return 'r-amber'
  return 'r-red'
}
function statusStyle(row) {
  if (row.actionStatus === 'Resolved')           return 'background:#16a34a;color:#fff;border:none'
  if (row.actionStatus === 'Fate Decision Req.') return 'background:#9d174d;color:#fff;border:none'
  if (row.actionStatus === 'XEN Re-notified')    return 'background:#d97706;color:#fff;border:none'
  if (row.actionStatus === 'Action Taken')       return 'background:#d97706;color:#fff;border:none'
  return 'background:#dc2626;color:#fff;border:none'
}

// ── Register Retest modal ─────────────────────────────────────────
const showRetestModal = ref(false)
const retestTarget    = ref(null)
const retestLoading   = ref(false)
const retestForm      = ref(blankRetestForm())

function blankRetestForm() {
  const d = new Date(); d.setDate(d.getDate() + 3)
  return {
    date:                  new Date().toISOString().split('T')[0],
    time:                  '09:00',
    collected_in:          'Plastic Bottle',
    collected_by:          'Laboratory Staff',
    desired_test:          ['Physical', 'Physical & Chemical', 'Microbiological(MF)'],
    source_type:           'Pumping',
    source_sub_type:       'Tube Well',
    complaint:             'General Q.Analysis',
    temperature_in_celsius: 20,
    sampling_point:        'Source',
    reported_at:           d.toISOString().split('T')[0] + ' 09:00:00',
  }
}
function openRetest(row) {
  retestTarget.value = row
  retestForm.value   = blankRetestForm()
  showRetestModal.value = true
}
async function submitRetest() {
  if (!retestForm.value.date) { showToast('⚠️ Please enter a collection date.', 'error'); return }
  retestLoading.value = true
  try {
    const now = new Date()
    const sampled = new Date(`${retestForm.value.date}T${retestForm.value.time}:00`)
    const finalSampled = sampled > now ? now : sampled
    const pad = n => String(n).padStart(2, '0')
    const fmt = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`

    await sampleService.requestRetest(retestTarget.value.backendId, {
      source_type:            retestForm.value.source_type,
      source_sub_type:        retestForm.value.source_sub_type,
      complaint:              retestForm.value.complaint,
      desired_test:           retestForm.value.desired_test,
      sampling_point:         retestForm.value.sampling_point,
      collected_by:           retestForm.value.collected_by,
      collected_in:           retestForm.value.collected_in,
      temperature_in_celsius: retestForm.value.temperature_in_celsius,
      sampled_at:             fmt(finalSampled),
      reported_at:            retestForm.value.reported_at || fmt(new Date(finalSampled.getTime() + 3 * 24 * 60 * 60 * 1000)),
    })
    showRetestModal.value = false
    showToast(`✅ Retest registered for ${retestTarget.value.id}`, 'success')
    await load()
  } catch (e) {
    const msg = e?.response?.data?.message || e?.message || 'Failed to register retest'
    showToast('❌ ' + msg, 'error')
  } finally {
    retestLoading.value = false
  }
}

// ── Trail modal (reuses the existing XEN trail component) ────────
const showTrailModal = ref(false)
const trailSampleId  = ref(null)
function openTrail(row) {
  trailSampleId.value = row.backendId
  showTrailModal.value = true
}

// ── Fate decision modal ──────────────────────────────────────────
const showFateModal = ref(false)
const fateTarget    = ref(null)
const fateDecision  = ref('')
const fateForm      = ref({ authorisedBy: '', date: new Date().toISOString().split('T')[0], remarks: '', docRef: '' })
const fateSuccess   = ref(false)
const fateLoading   = ref(false)

function openFate(row) {
  fateTarget.value   = row
  fateDecision.value = ''
  fateSuccess.value  = false
  fateForm.value     = { authorisedBy: '', date: new Date().toISOString().split('T')[0], remarks: '', docRef: '' }
  showFateModal.value = true
}
async function submitFate() {
  if (!fateDecision.value)        { showToast('⚠️ Please select a decision.', 'error'); return }
  if (!fateForm.value.remarks)    { showToast('⚠️ Remarks are required.', 'error'); return }
  fateLoading.value = true
  try {
    await api.patch(`/water-samples/${fateTarget.value.backendId}/fate`, {
      decision:      fateDecision.value,
      authorised_by: fateForm.value.authorisedBy || null,
      decision_date: fateForm.value.date || null,
      remarks:       fateForm.value.remarks,
      doc_ref:       fateForm.value.docRef || null,
    })
    fateSuccess.value = true
    showToast('✅ Fate decision recorded.', 'success')
    await load()
  } catch (e) {
    const msg = e?.response?.data?.message || e?.message || 'Failed to record decision'
    showToast('❌ ' + msg, 'error')
  } finally {
    fateLoading.value = false
  }
}

function onTrailSaved() { load() }

function exportCsv() {
  if (!filtered.value.length) { showToast('⚠️ No data to export.', 'error'); return }
  const head = ['Sample ID', 'WSS', 'PHE Division', 'District', 'Date', 'Cause', 'Value', 'Status', 'Round', 'Retest Result']
  const rows = filtered.value.map(r => [r.id, r.wss, r.phedDiv, r.district, r.date, r.cause, r.value, r.actionStatus, r.round, r.retestResult])
  const csv = [head, ...rows].map(r => r.map(c => `"${(c ?? '').toString().replace(/"/g, '""')}"`).join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = `xen_unfit_trail_${new Date().toISOString().slice(0, 10)}.csv`
  a.click()
}
</script>

<template>
  <!-- ── Toast notification ── -->
  <Teleport to="body">
    <Transition name="toast-slide">
      <div v-if="toast.show"
           :style="`position:fixed;top:22px;right:24px;z-index:9999;min-width:300px;max-width:460px;
                    background:${toast.type === 'success' ? '#065f46' : '#991b1b'};
                    color:#fff;border-radius:8px;padding:14px 18px;
                    box-shadow:0 6px 32px rgba(0,0,0,.28);font-size:13px;display:flex;align-items:flex-start;gap:10px`">
        <span style="flex:1;line-height:1.5">{{ toast.message }}</span>
        <button @click="toast.show = false"
                style="background:rgba(255,255,255,.2);border:none;color:#fff;border-radius:4px;
                       padding:2px 8px;cursor:pointer;font-size:13px;margin-left:4px">✕</button>
      </div>
    </Transition>
  </Teleport>

  <div class="xd">
    <div class="xd-scope">
      <span class="pin">📍</span>
      <span class="t1">Unfit Trail — your division only.</span>
      <span class="sep">·</span>
      <span class="t2">Lab marks a sample <b>Unfit</b> → it lands here automatically. Use <b>Register Retest</b> or <b>Decide WSS Fate</b>.</span>
    </div>

    <!-- KPI cards -->
    <div class="xd-cards" style="grid-template-columns:repeat(6,1fr)">
      <div class="c" style="cursor:pointer" @click="statusFilter=''">
        <div class="lbl">TOTAL UNFIT</div>
        <div v-if="loading" class="skel val-skel"></div>
        <div v-else class="val">{{ summary.total }}</div>
      </div>
      <div class="c c-red" style="cursor:pointer" @click="statusFilter='No Action Yet'">
        <div class="lbl">NO ACTION</div>
        <div v-if="loading" class="skel val-skel"></div>
        <div v-else class="val">{{ summary.noAction }}</div>
      </div>
      <div class="c c-amber" style="cursor:pointer" @click="statusFilter='Action Taken'">
        <div class="lbl">ACTION TAKEN</div>
        <div v-if="loading" class="skel val-skel"></div>
        <div v-else class="val">{{ summary.actionTaken }}</div>
      </div>
      <div class="c" style="cursor:pointer" @click="statusFilter='XEN Re-notified'">
        <div class="lbl">RE-NOTIFIED</div>
        <div v-if="loading" class="skel val-skel"></div>
        <div v-else class="val">{{ summary.renotified }}</div>
      </div>
      <div class="c" style="cursor:pointer;background:#fff0f5" @click="statusFilter='Fate Decision Req.'">
        <div class="lbl">FATE DECISION</div>
        <div v-if="loading" class="skel val-skel"></div>
        <div v-else class="val" style="color:#9d174d">{{ summary.fatePending }}</div>
      </div>
      <div class="c c-green" style="cursor:pointer" @click="statusFilter='Resolved'">
        <div class="lbl">RESOLVED</div>
        <div v-if="loading" class="skel val-skel"></div>
        <div v-else class="val">{{ summary.resolved }}</div>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="xen-toolbar">
      <input v-model="q" type="text" placeholder="🔍 Sample ID, WSS, District…" style="min-width:240px" />
      <select v-model="statusFilter">
        <option value="">All Status</option>
        <option value="No Action Yet">No Action Yet</option>
        <option value="Action Taken">Action Taken</option>
        <option value="XEN Re-notified">XEN Re-notified</option>
        <option value="Fate Decision Req.">Fate Decision Req.</option>
        <option value="Resolved">Resolved</option>
      </select>
      <div class="spacer"></div>
      <button class="btn btn-sec btn-sm" @click="load" :disabled="loading">{{ loading ? '⏳' : '↺ Refresh' }}</button>
      <button class="btn-export" @click="exportCsv">⬇ Export</button>
    </div>

    <div v-if="errorMsg" style="background:#fee2e2;border:1px solid #fca5a5;border-radius:5px;padding:9px 14px;margin-bottom:10px;font-size:12.5px;color:#991b1b">
      ⚠ {{ errorMsg }}
    </div>

    <!-- Table -->
    <div class="panel">
      <table class="tbl">
        <thead>
          <tr>
            <th>Sample ID</th>
            <th>WSS</th>
            <th>PHE Div.</th>
            <th>Date</th>
            <th>Cause</th>
            <th>Value / Limit</th>
            <th>Status</th>
            <th style="text-align:center">Re-Stage</th>
            <th style="text-align:center">Re-Result</th>
            <th style="text-align:center">RAG</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <template v-if="loading">
            <SkelRow v-for="n in 7" :key="'ut' + n" :cols="[90, 160, 100, 80, 80, 80, 90, 40, 60, 30, 180]" />
          </template>
          <template v-else>
            <tr v-if="!filtered.length">
              <td colspan="11" class="empty">No unfit samples found.</td>
            </tr>
            <template v-for="(rows, district) in grouped" :key="district">
              <tr style="background:#f0f4ff;border-top:2px solid #1a6bbf">
                <td colspan="11" style="font-size:11px;font-weight:700;color:#1c2e44;padding:6px 12px;text-transform:uppercase;letter-spacing:.05em">
                  📍 {{ district }} District
                </td>
              </tr>
              <tr v-for="(row, i) in rows" :key="row.id"
                  :class="i % 2 === 1 ? 'alt' : ''"
                  :style="row.actionStatus === 'Fate Decision Req.' ? 'background:#fff0f5' : ''">
                <td class="sid" :style="row.actionStatus === 'Fate Decision Req.' ? 'color:#9d174d' : ''">{{ row.id }}</td>
                <td><b>{{ row.wss }}</b></td>
                <td style="font-size:11px">{{ row.phedDiv }}</td>
                <td style="white-space:nowrap;font-size:11px">{{ row.date }}</td>
                <td style="font-size:11px">{{ row.cause }}</td>
                <td class="mono" style="font-size:11px">{{ row.value }}</td>
                <td>
                  <span class="pill" :style="statusStyle(row)">{{ row.actionStatus }}</span>
                </td>
                <td style="text-align:center;font-size:11px;color:#64748b">{{ row.round > 0 ? 'R' + row.round : '—' }}</td>
                <td style="text-align:center">
                  <span v-if="row.retestResult !== '—'" class="pill"
                        :class="row.retestResult === 'Fit' ? 'st-green' : 'st-red'">{{ row.retestResult }}</span>
                  <span v-else style="color:#64748b;font-size:11px">—</span>
                </td>
                <td style="text-align:center">
                  <span style="font-size:16px" :class="ragClass(row)">●</span>
                </td>
                <td style="white-space:nowrap">
                  <template v-if="row.actionStatus === 'Fate Decision Req.'">
                    <button class="btn btn-xs" style="background:#9d174d;color:#fff;border:none;font-size:11px;margin-right:4px" @click="openFate(row)">📋 Decide Fate</button>
                    <button class="btn btn-sec btn-xs" style="font-size:11px" @click="openTrail(row)">👁 Trail</button>
                  </template>
                  <template v-else-if="row.actionStatus !== 'Resolved'">
                    <button class="btn btn-pri btn-xs" style="font-size:11px;margin-right:4px" @click="openRetest(row)">▶ Register Retest</button>
                    <button class="btn btn-sec btn-xs" style="font-size:11px" @click="openTrail(row)">👁 Trail</button>
                  </template>
                  <template v-else>
                    <button class="btn btn-sec btn-xs" style="font-size:11px" @click="openTrail(row)">👁 View Trail</button>
                  </template>
                </td>
              </tr>
            </template>
          </template>
        </tbody>
      </table>
    </div>

    <!-- Trail modal -->
    <XenTrailModal v-model="showTrailModal" :sample-id="trailSampleId" @saved="onTrailSaved" />

    <!-- RETEST MODAL -->
    <Teleport to="body">
      <div v-if="showRetestModal" @click.self="showRetestModal = false"
           style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:3200;align-items:center;justify-content:center;padding:16px">
        <div style="background:#fff;border-radius:10px;width:100%;max-width:620px;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,.28);font-family:'DM Sans',sans-serif">
          <div style="background:#1c2e44;color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between">
            <div>
              <div style="font-size:14px;font-weight:700">▶ Register Retest Sample</div>
              <div style="font-size:11px;opacity:.7;margin-top:2px">{{ retestTarget?.id }} — {{ retestTarget?.wss }}</div>
            </div>
            <button @click="showRetestModal = false" style="background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:5px;padding:5px 12px;cursor:pointer">✕</button>
          </div>
          <div style="padding:20px 24px">
            <div style="background:#fff3f3;border:1px solid #fca5a5;border-radius:6px;padding:10px 14px;margin-bottom:14px;font-size:11.5px;display:grid;grid-template-columns:repeat(4,1fr);gap:8px">
              <div><div style="font-size:10px;color:#64748b">Original Result</div><b style="color:#dc2626">❌ UNFIT</b></div>
              <div><div style="font-size:10px;color:#64748b">Cause</div><b>{{ retestTarget?.cause }}</b></div>
              <div><div style="font-size:10px;color:#64748b">Value</div><b>{{ retestTarget?.value }}</b></div>
              <div><div style="font-size:10px;color:#64748b">Date</div><b>{{ retestTarget?.date }}</b></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
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
          <div style="padding:12px 24px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:10px;background:#fafbfc">
            <button class="btn btn-sec" @click="showRetestModal = false">Cancel</button>
            <button class="btn btn-pri" @click="submitRetest" :disabled="retestLoading">{{ retestLoading ? '⏳ Registering…' : '▶ Register Retest' }}</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- FATE MODAL -->
    <Teleport to="body">
      <div v-if="showFateModal" @click.self="showFateModal = false"
           style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:3400;align-items:flex-start;justify-content:center;overflow-y:auto;padding:30px">
        <div style="background:#fff;border-radius:10px;width:100%;max-width:580px;margin:auto;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,.28);font-family:'DM Sans',sans-serif">
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
              <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:8px">Select Decision</div>
              <label v-for="opt in [
                { val:'monitor',      title:'🔄 Continue Monitoring',       desc:'Keep WSS operational. Schedule additional retests.', color:'#1d4ed8' },
                { val:'advisory',     title:'⚠ Issue Public Advisory',      desc:'WSS remains operational but public advised against drinking.', color:'#b45309' },
                { val:'decommission', title:'🚫 Decommission / Abandon WSS', desc:'WSS taken out of service permanently. Requires formal approval.', color:'#9d174d' },
              ]" :key="opt.val"
                style="display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border:2px solid #e2e8f0;border-radius:6px;cursor:pointer;margin-bottom:8px"
                :style="fateDecision === opt.val ? `border-color:${opt.color}` : ''"
                @click="fateDecision = opt.val">
                <input type="radio" :value="opt.val" v-model="fateDecision" style="margin-top:2px">
                <div>
                  <div style="font-size:12px;font-weight:600" :style="{ color: opt.color }">{{ opt.title }}</div>
                  <div style="font-size:11px;color:#64748b">{{ opt.desc }}</div>
                </div>
              </label>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
                <div class="fg2"><label>Authorising Officer</label><input type="text" v-model="fateForm.authorisedBy" placeholder="Name / Designation"></div>
                <div class="fg2"><label>Decision Date</label><input type="date" v-model="fateForm.date"></div>
                <div class="fg2" style="grid-column:1/-1"><label>Remarks / Justification *</label><textarea v-model="fateForm.remarks" rows="3" placeholder="State the basis for this decision…"></textarea></div>
                <div class="fg2" style="grid-column:1/-1"><label>Document Reference (optional)</label><input type="text" v-model="fateForm.docRef" placeholder="e.g. Field inspection report no."></div>
              </div>
              <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:14px">
                <button class="btn btn-sec" @click="showFateModal = false">Cancel</button>
                <button class="btn" style="background:#9d174d;color:#fff;border:none" @click="submitFate" :disabled="fateLoading">{{ fateLoading ? '⏳ Saving…' : '✔ Record Decision' }}</button>
              </div>
            </div>
            <div v-else style="text-align:center;padding:20px 0">
              <div style="font-size:32px;margin-bottom:8px">✅</div>
              <div style="font-size:14px;font-weight:700;color:#166534;margin-bottom:4px">Decision Recorded</div>
              <div style="font-size:12px;color:#64748b">WSS status updated. XEN has been notified.</div>
              <button class="btn btn-sec btn-sm" style="margin-top:14px" @click="showFateModal = false">Close</button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped lang="scss">
@use './xen-shared.scss' as *;

.mono { font-family: 'DM Mono', monospace; }
.r-green { color: #16a34a; }
.r-amber { color: #d97706; }
.r-red   { color: #dc2626; }

.btn-xs {
  padding: 4px 9px;
  font-size: 11px;
  border-radius: 4px;
  font-weight: 600;
}

.fg2 {
  display: flex;
  flex-direction: column;
  gap: 4px;
  label {
    font-size: 11px;
    color: #475569;
    font-weight: 600;
  }
  input, select, textarea {
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    padding: 6px 9px;
    font-size: 13px;
    font-family: inherit;
    &:focus {
      outline: none;
      border-color: #1a6bbf;
      box-shadow: 0 0 0 2px rgba(26, 107, 191, .15);
    }
  }
  textarea { resize: vertical; min-height: 56px; }
}

tr.alt { background: #fafbfc; }
</style>

<style>
/* Global so the toast in <Teleport to="body"> picks up the transition */
.toast-slide-enter-active,
.toast-slide-leave-active { transition: all 0.3s ease; }
.toast-slide-enter-from,
.toast-slide-leave-to     { opacity: 0; transform: translateX(60px); }
</style>
