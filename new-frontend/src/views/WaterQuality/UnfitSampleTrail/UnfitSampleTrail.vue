<script setup>
import { ref, computed, onMounted } from 'vue'
import { sampleService } from '../../../services/sampleService.js'
import { exportToExcel } from '../../../utils/exportHelpers.js'

const loading    = ref(false)
const errorMsg   = ref('')
const unfitSamples = ref([])

const showNotifLog   = ref(false)
const searchText     = ref('')
const statusFilter   = ref('')

// Map backend fields to display format
function mapUnfitSample(s, idx) {
  return {
    id: s.slug || s.id,
    backendId: s.id,
    wss: s.waterScheme?.name || s.water_sample_address || '—',
    div: s.phedDivision?.name || s.district?.name || '—',
    district: s.district?.name || '—',
    date: s.sampled_at ? s.sampled_at.split(' ')[0] : '—',
    cause: s.analysis_result_cause || s.current_status || '—',
    value: s.analysis_result_value || '—',
    status: s.current_status || 'No Action Yet',
    stage: s.retest_stage || '—',
    result: s.retest_result || '—',
    rag: s.current_status?.includes('Resolved') ? 'r-green'
       : s.current_status?.includes('Fate') ? 'r-red'
       : s.current_status?.includes('Action') ? 'r-amber'
       : 'r-red',
  }
}

async function loadUnfitSamples() {
  loading.value = true
  errorMsg.value = ''
  try {
    const res = await sampleService.getAll({ result: 'Unfit' })
    const data = res.data?.waterSamples?.data || res.data?.data || res.data || []
    unfitSamples.value = data.map(mapUnfitSample)
  } catch (e) {
    errorMsg.value = 'Failed to load unfit samples'
    console.error('Unfit load error:', e)
  } finally {
    loading.value = false
  }
}

const summary = computed(() => ({
  total: unfitSamples.value.length,
  noAction: unfitSamples.value.filter(r => r.status === 'No Action Yet').length,
  actionTaken: unfitSamples.value.filter(r => r.status?.includes('Action')).length,
  renotified: unfitSamples.value.filter(r => r.status?.includes('Re-notified')).length,
  fateDecision: unfitSamples.value.filter(r => r.status?.includes('Fate')).length,
  resolved: unfitSamples.value.filter(r => r.status?.includes('Resolved')).length,
}))

const notifLog = ref([])

// ── Retest modal ──────────────────────────────────────────────────────
const showRetestModal = ref(false)
const retestTarget    = ref(null)
const retestForm      = ref({ date: new Date().toISOString().split('T')[0], time:'09:00', containerType:'Sterile Bottle (250mL)', collectedBy:'Lab Staff', testType:'M — Microbial Only', correctiveAction:'' })

function openRetest(row) {
  retestTarget.value = row
  retestForm.value   = { date: new Date().toISOString().split('T')[0], time:'09:00', containerType:'Sterile Bottle (250mL)', collectedBy:'Lab Staff', testType:'M — Microbial Only', correctiveAction:'' }
  showRetestModal.value = true
}

async function submitRetest() {
  if (!retestForm.value.date) { alert('Please enter a collection date.'); return }
  try {
    await sampleService.requestRetest(retestTarget.value.backendId)
    await loadUnfitSamples()
    showRetestModal.value = false
  } catch (e) {
    alert('Failed to register retest: ' + (e.response?.data?.message || e.message))
    console.error('Retest error:', e)
  }
}

// ── WSS Fate modal ────────────────────────────────────────────────────
const showFateModal  = ref(false)
const fateTarget     = ref(null)
const fateDecision   = ref('')
const fateForm       = ref({ authorisedBy:'', date: new Date().toISOString().split('T')[0], remarks:'', docRef:'' })
const fateSuccess    = ref(false)

function openFate(row) {
  fateTarget.value  = row
  fateDecision.value = ''
  fateSuccess.value  = false
  showFateModal.value = true
}

function submitFate() {
  if (!fateDecision.value) { alert('Please select a decision.'); return }
  fateSuccess.value = true
}

// ── Filtered rows ─────────────────────────────────────────────────────
const filteredRows = computed(() => unfitSamples.value.filter(r => {
  const matchSearch = !searchText.value || r.id.toLowerCase().includes(searchText.value.toLowerCase()) || r.wss.toLowerCase().includes(searchText.value.toLowerCase())
  const matchStatus = !statusFilter.value || r.status.includes(statusFilter.value)
  return matchSearch && matchStatus
}))

const summaryCards = [
  { label:'Total Unfit',           key:'total',        cls:'c-red',   filter:'' },
  { label:'No Action Yet',         key:'noAction',     cls:'c-red',   filter:'No Action Yet' },
  { label:'Action Taken',          key:'actionTaken',  cls:'c-amber', filter:'Action Taken' },
  { label:'Re-notified',           key:'renotified',   cls:'',        filter:'Re-notified' },
  { label:'Fate Decision Pending', key:'fateDecision', cls:'',        filter:'Fate Decision' },
  { label:'Resolved',              key:'resolved',     cls:'c-green', filter:'Resolved' },
]

function ragClass(rag) { return rag }

function exportUnfitSamples() {
  if (!filteredRows.value.length) {
    alert('No data to export.')
    return
  }
  
  const exportData = filteredRows.value.map(r => ({
    'Sample ID': r.id,
    'WSS Name': r.wss,
    'PHE Division': r.div,
    'District': r.district,
    'Result Date': r.date,
    'Cause': r.cause,
    'Value / Limit': r.value,
    'Status': r.status,
    'Retest Stage': r.stage,
    'Retest Result': r.result
  }))
  
  exportToExcel(exportData, 'Unfit_Sample_Trail', { includeTimestamp: true })
}

onMounted(loadUnfitSamples)
</script>

<template>
  <div>
    <!-- Auto-notification banner -->
    <div class="abar blue" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:10px">
      <span>🔔 <b>Auto-Notification:</b> XEN of the relevant PHE Division is automatically notified via MIS Dashboard alert &amp; official email within 15 minutes of an unfit result being recorded.</span>
      <button class="btn btn-sec btn-xs" @click="showNotifLog = !showNotifLog">📋 View Notification Log</button>
    </div>

    <!-- Notification log -->
    <div v-if="showNotifLog" style="background:#f0f7ff;border:1px solid #b3d1f0;border-radius:6px;padding:14px 18px;margin-bottom:10px;font-size:11.5px">
      <div style="font-weight:700;color:var(--navy);margin-bottom:8px;font-size:12px">📬 XEN Notification Log (System-generated)</div>
      <div class="tbl-wrap">
        <table style="font-size:11px">
          <thead>
            <tr style="background:var(--navy2);color:#fff">
              <th>Sample ID</th><th>WSS / District</th><th>PHE Div.</th><th>XEN</th><th>Type</th><th>Notified At</th><th>Channel</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(n, i) in notifLog" :key="i" :class="i%2===1?'alt':''">
              <td class="mono">{{ n.sampleId }}</td>
              <td>{{ n.wss }}</td>
              <td>{{ n.div }}</td>
              <td>{{ n.xen }}</td>
              <td><span class="rag r-blue" style="font-size:10px">{{ n.type }}</span></td>
              <td>{{ n.at }}</td>
              <td>{{ n.channel }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Workflow diagram -->
    <div class="panel" style="margin-bottom:10px;padding:10px 14px">
      <div style="font-size:10.5px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px">Unfit Sample Resolution Workflow</div>
      <div style="display:flex;align-items:center;gap:0;flex-wrap:nowrap;overflow-x:auto;padding-bottom:4px">
        <div v-for="(step, i) in ['Unfit Result','XEN Notified','XEN Action','Retest','Outcome']" :key="step" style="display:flex;align-items:center">
          <div style="text-align:center;min-width:76px;padding:0 4px">
            <div style="width:26px;height:26px;border-radius:50%;background:var(--navy);color:#fff;display:flex;align-items:center;justify-content:center;margin:0 auto 3px;font-size:11px;font-weight:700">{{ i+1 }}</div>
            <div style="font-size:10px;font-weight:600;color:var(--navy)">{{ step }}</div>
          </div>
          <div v-if="i < 4" style="flex:1;height:2px;background:#cbd5e1;min-width:12px"></div>
        </div>
      </div>
    </div>

    <!-- Summary cards -->
    <div class="cards" style="grid-template-columns:repeat(6,1fr);margin-bottom:10px">
      <div v-for="card in summaryCards" :key="card.label"
           class="card" :class="card.cls" style="cursor:pointer"
           @click="statusFilter = card.filter">
        <div class="c-lbl">{{ card.label }}</div>
        <div class="c-val">{{ summary[card.key] }}</div>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
      <input type="text" v-model="searchText" placeholder="🔍 Sample ID, WSS, District…">
      <select v-model="statusFilter" style="border:1px solid var(--input-border);border-radius:4px;padding:6px 8px;font-size:12px;font-family:inherit">
        <option value="">All Status</option>
        <option value="Action Taken">⏳ Action Taken — Retest Due</option>
        <option value="No Action Yet">🔴 No Action Yet</option>
        <option value="Re-notified">⚠ Re-notified (Escalation)</option>
        <option value="Fate Decision">📋 Fate Decision Pending</option>
        <option value="Resolved">✅ Resolved</option>
      </select>
      <div v-if="statusFilter" style="font-size:11.5px;font-weight:600;padding:4px 10px;background:#fff3cd;border:1px solid #f4c236;border-radius:5px;color:#7a4f00;display:flex;align-items:center;gap:6px">
        Filtered: {{ statusFilter }}
        <span @click="statusFilter=''" style="cursor:pointer;color:var(--red)">✕ Clear</span>
      </div>
      <div class="tsp"></div>
      <button class="btn btn-sec btn-sm" @click="exportUnfitSamples">⬇ Export</button>
    </div>

    <!-- Main table -->
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th>Sample ID</th><th>WSS Name</th><th>PHE Div.</th><th>Result Date</th>
            <th>Cause</th><th>Value / Limit</th><th>Status</th><th>Re-Stage</th>
            <th>Re-Result</th><th>RAG</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, i) in filteredRows" :key="row.id"
              :class="i%2===1?'alt':''"
              :style="row.status.includes('Fate') ? 'background:#fff0f5' : ''">
            <td class="mono" :style="row.status.includes('Fate') ? 'color:#9d174d;font-weight:600' : ''">{{ row.id }}</td>
            <td>{{ row.wss }}</td>
            <td>{{ row.div }}</td>
            <td>{{ row.date }}</td>
            <td>{{ row.cause }}</td>
            <td class="mono">{{ row.value }}</td>
            <td><span class="rag" :class="ragClass(row.rag)">{{ row.status }}</span></td>
            <td class="mono" :style="row.stage==='—'?'color:var(--muted)':''">{{ row.stage }}</td>
            <td>
              <span class="rag" :class="row.result==='Fit ✔'?'r-green':row.result==='—'||row.result==='Pending'?'r-grey':'r-red'">{{ row.result }}</span>
            </td>
            <td><span class="rag" :class="ragClass(row.rag)">●</span></td>
            <td>
              <button v-if="row.status.includes('Fate')"
                      class="btn btn-xs" style="background:#9d174d;color:#fff;border:none"
                      @click="openFate(row)">📋 Decide WSS Fate</button>
              <button v-else-if="row.status === 'Action Taken' || row.status === 'XEN Action #2'"
                      class="btn btn-pri btn-xs"
                      @click="openRetest(row)">▶ Register Retest</button>
              <button v-else class="btn btn-sec btn-xs">👁 View Trail</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ── RETEST MODAL ── -->
    <Teleport to="body">
      <div v-if="showRetestModal" @click.self="showRetestModal = false"
           style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center">
        <div style="background:#fff;border-radius:8px;padding:20px;max-width:640px;width:96%;max-height:80vh;overflow-y:auto;position:relative">
          <button @click="showRetestModal = false" style="position:absolute;top:14px;right:16px;cursor:pointer;font-size:18px;color:var(--muted);background:none;border:none">✕</button>
          <h2 style="margin-bottom:4px">▶ Register Retest Sample</h2>
          <div style="font-size:11.5px;color:var(--muted);margin-bottom:12px">
            Linked to original: <b class="mono">{{ retestTarget?.id }}</b> — {{ retestTarget?.wss }}
          </div>

          <!-- Original result summary -->
          <div style="background:#fff3f3;border:1px solid #f5c6c6;border-radius:5px;padding:10px 14px;margin-bottom:14px;font-size:11.5px;display:grid;grid-template-columns:repeat(4,1fr);gap:6px">
            <div><span style="color:var(--muted);font-size:10.5px">Original Result</span><br><b style="color:var(--red)">❌ UNFIT</b></div>
            <div><span style="color:var(--muted);font-size:10.5px">Parameter</span><br><b>{{ retestTarget?.cause }}</b></div>
            <div><span style="color:var(--muted);font-size:10.5px">Value / Limit</span><br><b class="mono">{{ retestTarget?.value }}</b></div>
            <div><span style="color:var(--muted);font-size:10.5px">Original Date</span><br><b>{{ retestTarget?.date }}</b></div>
          </div>

          <div class="abar green" style="margin-bottom:12px">✅ XEN action logged: Chlorination performed</div>

          <div class="form-grid c2">
            <div class="fg2">
              <label>Original Sample ID</label>
              <div style="border:1px solid var(--input-border);border-radius:4px;padding:6px 9px;background:var(--sky2);font-weight:600;font-family:monospace">{{ retestTarget?.id }}</div>
            </div>
            <div class="fg2">
              <label>Retest Sample ID (auto)</label>
              <div style="border:1px solid var(--input-border);border-radius:4px;padding:6px 9px;background:var(--sky2);color:var(--blue);font-weight:700;font-family:monospace">{{ retestTarget?.id }}-R1 <span style="font-size:10px;font-family:inherit;font-weight:400;color:var(--muted)">[RETEST]</span></div>
            </div>
            <div class="fg2">
              <label>Collection Date *</label>
              <input type="date" v-model="retestForm.date">
            </div>
            <div class="fg2">
              <label>Collection Time</label>
              <input type="time" v-model="retestForm.time">
            </div>
            <div class="fg2">
              <label>Container Type</label>
              <select v-model="retestForm.containerType">
                <option value="Sterile Bottle (250mL)">Sterile Bottle (250mL)</option>
                <option value="Polyethylene (500mL)">Polyethylene (500mL)</option>
                <option value="Glass Bottle">Glass Bottle</option>
              </select>
            </div>
            <div class="fg2">
              <label>Collected By</label>
              <select v-model="retestForm.collectedBy">
                <option value="Lab Staff">Lab Staff</option>
                <option value="Client / XEN Staff">Client / XEN Staff</option>
              </select>
            </div>
            <div class="fg2">
              <label>Test Type *</label>
              <select v-model="retestForm.testType">
                <option value="M — Microbial Only">M — Microbial Only (as per original unfit cause)</option>
                <option value="PCM — Full">PCM — Full (Physical + Chemical + Microbial)</option>
                <option value="PC — Physical + Chemical">PC — Physical + Chemical</option>
              </select>
            </div>
            <div class="fg2 span2">
              <label>Corrective Action Taken (by XEN) *</label>
              <textarea v-model="retestForm.correctiveAction" rows="2" placeholder="e.g. Chlorination performed on 09-Mar-2026. Pipe leakage at junction repaired on 10-Mar-2026."></textarea>
            </div>
          </div>

          <div style="display:flex;gap:8px;margin-top:14px">
            <button class="btn btn-pri" @click="submitRetest">▶ Register Retest Sample</button>
            <button class="btn btn-sec" @click="showRetestModal = false">Cancel</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── WSS FATE MODAL ── -->
    <Teleport to="body">
      <div v-if="showFateModal" @click.self="showFateModal = false"
           style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:3200;align-items:flex-start;justify-content:center;overflow-y:auto;padding:30px">
        <div style="background:#fff;border-radius:8px;padding:22px 24px;max-width:580px;width:97%;position:relative;margin:auto">
          <button @click="showFateModal = false" style="position:absolute;top:14px;right:16px;background:rgba(0,0,0,.07);border:none;border-radius:5px;padding:4px 10px;cursor:pointer;font-size:13px">✕</button>
          <h2 style="font-size:15px;font-weight:700;color:#9d174d;margin-bottom:4px">📋 WSS Fate Decision</h2>
          <div style="font-size:11.5px;color:var(--muted);margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid var(--border)">
            <b>{{ fateTarget?.wss }}</b> · {{ fateTarget?.div }} ·
            <span class="rag r-amber" style="font-size:10.5px">Chemical — {{ fateTarget?.cause }}</span><br>
            <span style="color:#9d174d;font-size:11px">⚠ Persistently Unfit: 3 consecutive retest failures (R1–R3).</span>
          </div>

          <!-- Contamination history -->
          <div style="background:#fff5f5;border:1px solid #fca5a5;border-radius:6px;padding:10px 14px;margin-bottom:14px;font-size:11.5px">
            <div style="font-weight:700;color:#991b1b;margin-bottom:6px">Contamination History</div>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;text-align:center">
              <div><div style="font-size:9.5px;color:var(--muted)">Original</div><div style="font-weight:700;color:#991b1b">88 µg/L</div></div>
              <div><div style="font-size:9.5px;color:var(--muted)">Retest R1</div><div style="font-weight:700;color:#991b1b">79 µg/L</div></div>
              <div><div style="font-size:9.5px;color:var(--muted)">Retest R2</div><div style="font-weight:700;color:#991b1b">83 µg/L</div></div>
              <div><div style="font-size:9.5px;color:var(--muted)">Retest R3</div><div style="font-weight:700;color:#991b1b">91 µg/L</div></div>
            </div>
            <div style="margin-top:6px;font-size:10.5px;color:var(--muted)">WHO / NEQS Limit: 50 µg/L &nbsp;|&nbsp; All values significantly above limit.</div>
          </div>

          <!-- Decision form -->
          <div v-if="!fateSuccess">
            <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px">Select Decision</div>

            <label v-for="opt in [
              { val:'monitor',      title:'🔄 Continue Monitoring',    desc:'Keep WSS operational. Schedule additional retests.', color:'var(--blue)', bg:'#dbeeff' },
              { val:'advisory',     title:'⚠ Issue Public Advisory',   desc:'WSS remains partially operational but public is advised against drinking.', color:'#b45309', bg:'#fef3c7' },
              { val:'decommission', title:'🚫 Decommission / Abandon WSS', desc:'WSS is taken out of service permanently. Requires formal approval.', color:'#9d174d', bg:'#fce7f3' },
            ]" :key="opt.val"
              style="display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border:2px solid var(--border);border-radius:6px;cursor:pointer;margin-bottom:8px;transition:border-color .15s"
              :style="fateDecision === opt.val ? `border-color:${opt.color}` : ''"
              @click="fateDecision = opt.val">
              <input type="radio" name="wss-fate" :value="opt.val" v-model="fateDecision" style="margin-top:2px">
              <div>
                <div style="font-size:12px;font-weight:600" :style="{ color: opt.color }">{{ opt.title }}</div>
                <div style="font-size:11px;color:var(--muted)">{{ opt.desc }}</div>
              </div>
            </label>

            <div class="form-grid c2" style="margin-top:12px;margin-bottom:14px">
              <div class="fg2"><label>Authorising Officer</label><input type="text" v-model="fateForm.authorisedBy"></div>
              <div class="fg2"><label>Decision Date</label><input type="date" v-model="fateForm.date"></div>
              <div class="fg2 span2"><label>Remarks / Justification *</label><textarea v-model="fateForm.remarks" rows="3" placeholder="State the basis for this decision…"></textarea></div>
              <div class="fg2 span2"><label>Supporting Document Reference (optional)</label><input type="text" v-model="fateForm.docRef" placeholder="e.g. Field inspection report no., XEN letter ref."></div>
            </div>

            <div style="display:flex;gap:8px;justify-content:flex-end">
              <button class="btn btn-sec" @click="showFateModal = false">Cancel</button>
              <button class="btn" style="background:#9d174d;color:#fff;border:none" @click="submitFate">✔ Record Decision</button>
            </div>
          </div>

          <!-- Success state -->
          <div v-if="fateSuccess" style="text-align:center;padding:16px 0">
            <div style="font-size:28px;margin-bottom:8px">✅</div>
            <div style="font-size:13px;font-weight:700;color:#166534;margin-bottom:4px">Decision Recorded</div>
            <div style="font-size:11.5px;color:var(--muted)">WSS status has been updated. XEN has been notified of the decision.</div>
            <button class="btn btn-sec btn-sm" style="margin-top:12px" @click="showFateModal = false">Close</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
