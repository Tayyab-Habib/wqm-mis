<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { assetService } from '../../../services/assetService.js'

// ── Equipment table ────────────────────────────────────────────────────────
const loading   = ref(false)
const errorMsg  = ref('')
const equipment = ref([])

function mapEquipment(a) {
  const nextCalib  = a.next_calibration_date || '—'
  const isOverdue  = nextCalib !== '—' && new Date(nextCalib) < new Date()
  return {
    id:           a.id,
    assetId:      a.asset_id || a.id,
    name:         a.name || '—',
    model:        a.make_model || '—',
    purchased:    a.purchased_at ? a.purchased_at.split('T')[0] : '—',
    calibCycle:   a.calibration_cycle || '12 months',
    status:       a.status || 'Operational',
    nextCalib:    nextCalib,
    calibOverdue: isOverdue,
  }
}

async function loadEquipment() {
  loading.value  = true
  errorMsg.value = ''
  try {
    const res  = await assetService.getLaboratoryAssets()
    const data = res.data?.data || res.data || []
    equipment.value = Array.isArray(data) ? data.map(mapEquipment) : []
  } catch (e) {
    errorMsg.value = 'Failed to load equipment. Please try again.'
    console.error('Equipment load error:', e)
  } finally {
    loading.value = false
  }
}

// ── Summary counts ─────────────────────────────────────────────────────────
const totalCount      = computed(() => equipment.value.length)
const operationalCnt  = computed(() => equipment.value.filter(e => e.status === 'Operational').length)
const outOfOrderCnt   = computed(() => equipment.value.filter(e => e.status === 'Out of Order').length)
const underRepairCnt  = computed(() => equipment.value.filter(e => e.status === 'Under Repair').length)
const calibOverdueCnt = computed(() => equipment.value.filter(e => e.calibOverdue).length)

// Compute next-due date from a base date + cycle string
function computeNextDue(baseDate, cycleStr) {
  if (!baseDate) return ''
  const months = cycleStr?.includes('6') ? 6 : 12
  const d = new Date(baseDate)
  d.setMonth(d.getMonth() + months)
  return d.toISOString().split('T')[0]
}

// Format a date for display (YYYY-MM-DD → DD-Mon-YYYY)
function fmtDate(d) {
  if (!d) return '—'
  const dt = new Date(d)
  if (isNaN(dt)) return d
  return dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).replace(/ /g, '-')
}

function statusClass(s) {
  if (s === 'Operational')  return 'r-green'
  if (s === 'Out of Order' || s === 'Beyond Repair') return 'r-red'
  return 'r-amber'
}

// ── Calibration modal ──────────────────────────────────────────────────────
const showCalibModal  = ref(false)
const calibTarget     = ref(null)
const calibTab        = ref('new')
const calibSaving     = ref(false)
const calibHistLoading = ref(false)
const calibHistList   = ref([])

const calibForm = ref({
  date: '', by: '', result: '', ref: '', std: '', remarks: '', nextDue: ''
})

// Auto-compute nextDue whenever calibration date changes
watch(() => calibForm.value.date, (val) => {
  calibForm.value.nextDue = val ? computeNextDue(val, calibTarget.value?.calibCycle) : ''
})

function openCalib(eq) {
  calibTarget.value  = eq
  calibTab.value     = 'new'
  calibHistList.value = []
  const today = new Date().toISOString().split('T')[0]
  calibForm.value = {
    date:    today,
    by:      '',
    result:  '',
    ref:     '',
    std:     '',
    remarks: '',
    nextDue: computeNextDue(today, eq.calibCycle),
  }
  showCalibModal.value = true
}

// Load calibration history from backend
async function loadCalibHistory() {
  if (!calibTarget.value) return
  calibHistLoading.value = true
  try {
    const res = await assetService.getCalibrationLogs(calibTarget.value.id)
    calibHistList.value = res.data?.data || res.data || res || []
  } catch (e) {
    calibHistList.value = []
    console.error('Calib history load error:', e)
  } finally {
    calibHistLoading.value = false
  }
}

watch(calibTab, (tab) => { if (tab === 'hist') loadCalibHistory() })

async function saveCalib() {
  if (!calibForm.value.date || !calibForm.value.by || !calibForm.value.result) {
    alert('Please fill all required fields.')
    return
  }
  calibSaving.value = true
  try {
    const payload = {
      laboratory_asset_id: calibTarget.value.id,
      calibration_date:    calibForm.value.date,
      calibrated_by:       calibForm.value.by,
      result:              calibForm.value.result,
      certificate_ref:     calibForm.value.ref  || null,
      standard_used:       calibForm.value.std  || null,
      next_due_date:       calibForm.value.nextDue || null,
      remarks:             calibForm.value.remarks || null,
    }
    const res = await assetService.createCalibrationLog(payload)
    const updated = res.laboratory_asset || res.data?.laboratory_asset

    // Reflect changes in the table row
    const eq = equipment.value.find(e => e.id === calibTarget.value.id)
    if (eq && updated) {
      eq.nextCalib    = updated.next_calibration_date || eq.nextCalib
      eq.calibOverdue = eq.nextCalib !== '—' && new Date(eq.nextCalib) < new Date()
      eq.status       = updated.status || eq.status
    }
    showCalibModal.value = false
  } catch (e) {
    alert('Failed to save calibration log: ' + (e?.response?.data?.message || e?.message || 'Unknown error'))
    console.error('Calib save error:', e)
  } finally {
    calibSaving.value = false
  }
}

// ── Repair modal ────────────────────────────────────────────────────────────
const showRepairModal   = ref(false)
const repairTarget      = ref(null)
const repairTab         = ref('new')
const repairSaving      = ref(false)
const repairHistLoading = ref(false)
const repairHistList    = ref([])

const repairForm = ref({
  faultDate: '', fault: '', status: '', tech: '', resolvedDate: '', cost: '', remarks: ''
})

function openRepair(eq) {
  repairTarget.value  = eq
  repairTab.value     = 'new'
  repairHistList.value = []
  repairForm.value = {
    faultDate:    new Date().toISOString().split('T')[0],
    fault:        '',
    status:       '',
    tech:         '',
    resolvedDate: '',
    cost:         '',
    remarks:      '',
  }
  showRepairModal.value = true
}

// Load repair history from backend
async function loadRepairHistory() {
  if (!repairTarget.value) return
  repairHistLoading.value = true
  try {
    const res = await assetService.getRepairLogs(repairTarget.value.id)
    repairHistList.value = res.data?.data || res.data || res || []
  } catch (e) {
    repairHistList.value = []
    console.error('Repair history load error:', e)
  } finally {
    repairHistLoading.value = false
  }
}

watch(repairTab, (tab) => { if (tab === 'hist') loadRepairHistory() })

const showResolvedFields = (status) => ['Resolved', 'Beyond Repair'].includes(status)

async function saveRepair() {
  if (!repairForm.value.fault || !repairForm.value.status) {
    alert('Please fill all required fields.')
    return
  }
  repairSaving.value = true
  try {
    const payload = {
      laboratory_asset_id: repairTarget.value.id,
      fault_date:          repairForm.value.faultDate,
      fault_description:   repairForm.value.fault,
      repair_status:       repairForm.value.status,
      technician:          repairForm.value.tech         || null,
      resolved_date:       repairForm.value.resolvedDate || null,
      repair_cost:         repairForm.value.cost         || null,
      remarks:             repairForm.value.remarks      || null,
    }
    const res = await assetService.createRepairLog(payload)
    const updated = res.laboratory_asset || res.data?.laboratory_asset

    // Reflect status change in the table row
    const eq = equipment.value.find(e => e.id === repairTarget.value.id)
    if (eq && updated) eq.status = updated.status || eq.status

    showRepairModal.value = false
  } catch (e) {
    alert('Failed to save repair log: ' + (e?.response?.data?.message || e?.message || 'Unknown error'))
    console.error('Repair save error:', e)
  } finally {
    repairSaving.value = false
  }
}

onMounted(loadEquipment)
</script>

<template>
  <div>
    <!-- Alert bar: overdue calibrations -->
    <div v-if="calibOverdueCnt > 0" class="abar red">
      🔴 <b>{{ calibOverdueCnt }} equipment item{{ calibOverdueCnt > 1 ? 's' : '' }}</b>
      {{ calibOverdueCnt > 1 ? 'have' : 'has' }} overdue calibration(s)
    </div>

    <!-- Summary cards -->
    <div class="cards" style="grid-template-columns:repeat(5,1fr)">
      <div class="card">       <div class="c-lbl">Total Instruments</div><div class="c-val">{{ totalCount }}</div></div>
      <div class="card c-green"><div class="c-lbl">Operational</div>      <div class="c-val">{{ operationalCnt }}</div></div>
      <div class="card c-red">  <div class="c-lbl">Out of Order</div>     <div class="c-val">{{ outOfOrderCnt }}</div></div>
      <div class="card c-amber"><div class="c-lbl">Under Repair</div>     <div class="c-val">{{ underRepairCnt }}</div></div>
      <div class="card c-red">  <div class="c-lbl">Calib. Overdue</div>   <div class="c-val">{{ calibOverdueCnt }}</div></div>
    </div>

    <div style="height:8px"></div>

    <!-- Equipment Table -->
    <div class="tbl-wrap">
      <!-- Loading state -->
      <div v-if="loading" style="text-align:center;padding:40px;color:var(--muted)">
        ⏳ Loading equipment…
      </div>

      <!-- Error state -->
      <div v-else-if="errorMsg" style="text-align:center;padding:40px;color:var(--red)">
        ⚠️ {{ errorMsg }}
        <br><button class="btn btn-sec btn-xs" style="margin-top:10px" @click="loadEquipment">Retry</button>
      </div>

      <!-- Empty state -->
      <div v-else-if="!equipment.length" style="text-align:center;padding:40px;color:var(--muted)">
        📋 No equipment records found for your laboratory.
      </div>

      <!-- Data table -->
      <table v-else>
        <thead>
          <tr>
            <th>S#</th>
            <th>Equipment Name</th>
            <th>Make / Model</th>
            <th>Asset Code</th>
            <th>Purchase Date</th>
            <th>Calib. Cycle</th>
            <th>Status</th>
            <th>Next Calib. Due</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(eq, i) in equipment" :key="eq.id" :class="i % 2 === 1 ? 'alt' : ''">
            <td>{{ i + 1 }}</td>
            <td><b>{{ eq.name }}</b></td>
            <td>{{ eq.model }}</td>
            <td class="mono">{{ eq.id }}</td>
            <td>{{ fmtDate(eq.purchased) }}</td>
            <td>{{ eq.calibCycle }}</td>
            <td><span class="rag" :class="statusClass(eq.status)">{{ eq.status }}</span></td>
            <td :style="eq.calibOverdue ? 'color:var(--red);font-weight:700' : ''">
              {{ fmtDate(eq.nextCalib) }}
            </td>
            <td>
              <button class="btn btn-sec btn-xs" @click="openCalib(eq)">📋 Calib.</button>
              <button class="btn btn-sec btn-xs" style="margin-left:4px" @click="openRepair(eq)">🔧 Repair</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ── CALIBRATION MODAL ──────────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="showCalibModal" @click.self="showCalibModal = false"
           style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:3300;align-items:center;justify-content:center;overflow-y:auto;padding:20px">
        <div style="background:#fff;border-radius:8px;width:100%;max-width:640px;padding:24px 28px;position:relative;margin:auto">
          <button @click="showCalibModal = false"
                  style="position:absolute;top:14px;right:16px;background:rgba(0,0,0,.07);border:none;border-radius:5px;padding:4px 10px;cursor:pointer;font-size:13px">✕</button>
          <h2 style="margin-bottom:2px">📋 Calibration Log</h2>
          <div style="font-size:11.5px;color:var(--muted);margin-bottom:4px">
            {{ calibTarget?.name }} · {{ calibTarget?.model }} · {{ calibTarget?.id }}
          </div>
          <div style="font-size:11px;font-weight:600;color:var(--navy);background:#eff6ff;border:1px solid #bfdbfe;border-radius:5px;padding:4px 10px;display:inline-block;margin-bottom:12px">
            🔁 Calib. Cycle: {{ calibTarget?.calibCycle }}
          </div>

          <!-- Tabs -->
          <div style="display:flex;gap:0;border-bottom:2px solid var(--border);margin-bottom:16px">
            <div @click="calibTab = 'new'" style="padding:7px 18px;cursor:pointer;font-size:12px;font-weight:600;"
                 :style="calibTab === 'new'  ? 'border-bottom:2px solid var(--navy);margin-bottom:-2px;color:var(--navy)' : 'color:var(--muted)'">
              ➕ Log Calibration
            </div>
            <div @click="calibTab = 'hist'" style="padding:7px 18px;cursor:pointer;font-size:12px;font-weight:600;"
                 :style="calibTab === 'hist' ? 'border-bottom:2px solid var(--navy);margin-bottom:-2px;color:var(--navy)' : 'color:var(--muted)'">
              📜 History
            </div>
          </div>

          <!-- New calibration form -->
          <div v-if="calibTab === 'new'" class="form-grid c2">
            <div class="fg2">
              <label>Calibration Date *</label>
              <input type="date" v-model="calibForm.date">
            </div>
            <div class="fg2">
              <label>Next Due Date <span style="color:var(--muted);font-weight:400">(auto — from calib. cycle)</span></label>
              <input type="text" :value="fmtDate(calibForm.nextDue)" readonly
                     style="background:#f8fafc;cursor:not-allowed;color:var(--navy);font-weight:600">
            </div>
            <div class="fg2">
              <label>Calibrated By *</label>
              <input type="text" v-model="calibForm.by" placeholder="Name / Organisation">
            </div>
            <div class="fg2">
              <label>Result *</label>
              <select v-model="calibForm.result">
                <option value="">— Select —</option>
                <option value="Pass">Pass ✅</option>
                <option value="Conditional Pass">Conditional Pass ⚠</option>
                <option value="Fail">Fail ❌</option>
              </select>
            </div>
            <div class="fg2">
              <label>Certificate / Ref. No.</label>
              <input type="text" v-model="calibForm.ref" placeholder="e.g. CALIB/26/0031">
            </div>
            <div class="fg2">
              <label>Standard Used</label>
              <input type="text" v-model="calibForm.std" placeholder="e.g. NIST SRM 3128">
            </div>
            <div class="fg2" style="grid-column:1/-1">
              <label>Remarks</label>
              <input type="text" v-model="calibForm.remarks" placeholder="Any deviations, adjustments, or notes">
            </div>
          </div>

          <!-- Calibration history -->
          <div v-if="calibTab === 'hist'">
            <div v-if="calibHistLoading" style="text-align:center;padding:24px;color:var(--muted)">⏳ Loading history…</div>
            <div v-else-if="!calibHistList.length" style="text-align:center;color:var(--muted);padding:24px">No calibration records yet.</div>
            <div class="tbl-wrap" v-else>
              <table>
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Calibrated By</th>
                    <th>Result</th>
                    <th>Next Due</th>
                    <th>Cert. / Ref.</th>
                    <th>Standard Used</th>
                    <th>Remarks</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(r, i) in calibHistList" :key="r.id ?? i" :class="i % 2 === 1 ? 'alt' : ''">
                    <td>{{ fmtDate(r.calibration_date) }}</td>
                    <td>{{ r.calibrated_by }}</td>
                    <td>
                      <span class="rag" :class="r.result === 'Pass' ? 'r-green' : r.result === 'Fail' ? 'r-red' : 'r-amber'">
                        {{ r.result }}
                      </span>
                    </td>
                    <td style="font-weight:600;color:var(--navy)">{{ fmtDate(r.next_due_date) }}</td>
                    <td class="mono">{{ r.certificate_ref || '—' }}</td>
                    <td style="font-size:11px">{{ r.standard_used || '—' }}</td>
                    <td style="font-size:11px;color:var(--muted)">{{ r.remarks || '—' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div v-if="calibTab === 'new'" style="display:flex;gap:10px;justify-content:flex-end;margin-top:18px">
            <button class="btn btn-sec" @click="showCalibModal = false">Cancel</button>
            <button class="btn btn-pri" @click="saveCalib" :disabled="calibSaving">
              {{ calibSaving ? '⏳ Saving…' : '💾 Save' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── REPAIR MODAL ───────────────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="showRepairModal" @click.self="showRepairModal = false"
           style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:3300;align-items:center;justify-content:center;overflow-y:auto;padding:20px">
        <div style="background:#fff;border-radius:8px;width:100%;max-width:640px;padding:24px 28px;position:relative;margin:auto">
          <button @click="showRepairModal = false"
                  style="position:absolute;top:14px;right:16px;background:rgba(0,0,0,.07);border:none;border-radius:5px;padding:4px 10px;cursor:pointer;font-size:13px">✕</button>
          <h2 style="margin-bottom:2px">🔧 Repair Log</h2>
          <div style="font-size:11.5px;color:var(--muted);margin-bottom:14px">
            {{ repairTarget?.name }} · {{ repairTarget?.model }} · {{ repairTarget?.id }}
          </div>

          <!-- Tabs -->
          <div style="display:flex;gap:0;border-bottom:2px solid var(--border);margin-bottom:16px">
            <div @click="repairTab = 'new'" style="padding:7px 18px;cursor:pointer;font-size:12px;font-weight:600;"
                 :style="repairTab === 'new'  ? 'border-bottom:2px solid var(--navy);margin-bottom:-2px;color:var(--navy)' : 'color:var(--muted)'">
              ➕ Log / Update
            </div>
            <div @click="repairTab = 'hist'" style="padding:7px 18px;cursor:pointer;font-size:12px;font-weight:600;"
                 :style="repairTab === 'hist' ? 'border-bottom:2px solid var(--navy);margin-bottom:-2px;color:var(--navy)' : 'color:var(--muted)'">
              📜 History
            </div>
          </div>

          <!-- New repair form -->
          <div v-if="repairTab === 'new'" class="form-grid c2">
            <div class="fg2">
              <label>Fault Reported Date *</label>
              <input type="date" v-model="repairForm.faultDate">
            </div>
            <div class="fg2">
              <label>Fault Description *</label>
              <input type="text" v-model="repairForm.fault" placeholder="e.g. Display blank, motor noise…">
            </div>
            <div class="fg2">
              <label>Repair Status *</label>
              <select v-model="repairForm.status">
                <option value="">— Select —</option>
                <option value="Reported">Reported — Awaiting Technician</option>
                <option value="Under Repair">Under Repair — In Progress</option>
                <option value="Resolved">Resolved — Back in Service</option>
                <option value="Beyond Repair">Beyond Repair — Condemn</option>
              </select>
            </div>
            <div class="fg2">
              <label>Technician / Vendor</label>
              <input type="text" v-model="repairForm.tech" placeholder="Name or company">
            </div>
            <div class="fg2" v-if="showResolvedFields(repairForm.status)">
              <label>Date Resolved</label>
              <input type="date" v-model="repairForm.resolvedDate">
            </div>
            <div class="fg2" v-if="showResolvedFields(repairForm.status)">
              <label>Repair Cost (Rs.)</label>
              <input type="number" v-model="repairForm.cost" placeholder="0" min="0">
            </div>
            <div class="fg2 span2">
              <label>Remarks</label>
              <input type="text" v-model="repairForm.remarks" placeholder="Parts replaced, pending items…">
            </div>
          </div>

          <!-- Repair history -->
          <div v-if="repairTab === 'hist'">
            <div v-if="repairHistLoading" style="text-align:center;padding:24px;color:var(--muted)">⏳ Loading history…</div>
            <div v-else-if="!repairHistList.length" style="text-align:center;color:var(--muted);padding:24px">No repair records yet.</div>
            <div class="tbl-wrap" v-else>
              <table>
                <thead>
                  <tr>
                    <th>Fault Date</th>
                    <th>Fault</th>
                    <th>Technician</th>
                    <th>Status</th>
                    <th>Resolved</th>
                    <th>Cost (Rs.)</th>
                    <th>Remarks</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(r, i) in repairHistList" :key="r.id ?? i" :class="i % 2 === 1 ? 'alt' : ''">
                    <td>{{ fmtDate(r.fault_date) }}</td>
                    <td style="font-size:11px">{{ r.fault_description }}</td>
                    <td style="font-size:11px">{{ r.technician || '—' }}</td>
                    <td>
                      <span class="rag"
                            :class="r.repair_status === 'Resolved' ? 'r-green' : r.repair_status === 'Beyond Repair' ? 'r-red' : 'r-amber'">
                        {{ r.repair_status }}
                      </span>
                    </td>
                    <td>{{ fmtDate(r.resolved_date) }}</td>
                    <td class="mono">{{ r.repair_cost ?? '—' }}</td>
                    <td style="font-size:11px;color:var(--muted)">{{ r.remarks || '—' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div v-if="repairTab === 'new'" style="display:flex;gap:10px;justify-content:flex-end;margin-top:18px">
            <button class="btn btn-sec" @click="showRepairModal = false">Cancel</button>
            <button class="btn btn-pri" @click="saveRepair" :disabled="repairSaving">
              {{ repairSaving ? '⏳ Saving…' : '💾 Save' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
