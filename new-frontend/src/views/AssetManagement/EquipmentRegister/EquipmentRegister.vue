<script setup>
import { ref, onMounted } from 'vue'
import { assetService } from '../../../services/assetService.js'

const loading  = ref(false)
const errorMsg = ref('')
const equipment = ref([])

function mapEquipment(a) {
  const nextCalib = a.next_calibration_date || a.next_calib || '—'
  const isOverdue = nextCalib !== '—' && new Date(nextCalib) < new Date()
  return {
    id: a.id,
    assetId: a.asset_id || a.id,
    name: a.asset?.name || a.name || '—',
    model: a.asset?.model || a.model || '—',
    purchased: a.asset?.purchased_at ? a.asset.purchased_at.split(' ')[0] : (a.purchased_at ? a.purchased_at.split(' ')[0] : '—'),
    calibCycle: a.calibration_cycle || '12 months',
    status: a.status || 'Operational',
    nextCalib: nextCalib,
    calibOverdue: isOverdue,
  }
}

async function loadEquipment() {
  loading.value = true
  errorMsg.value = ''
  try {
    const res = await assetService.getLaboratoryAssets()
    const data = res.data?.data || res.data || []
    equipment.value = data.map(mapEquipment)
  } catch (e) {
    errorMsg.value = 'Failed to load equipment'
    console.error('Equipment load error:', e)
  } finally {
    loading.value = false
  }
}

// ── Calibration modal ─────────────────────────────────────────────────
const showCalibModal = ref(false)
const calibTarget    = ref(null)
const calibForm      = ref({ date:'', by:'', result:'', ref:'', std:'', remarks:'' })
const calibHistory   = ref({})
const calibTab       = ref('new')

function openCalib(eq) {
  calibTarget.value = eq
  calibTab.value    = 'new'
  calibForm.value   = { date: new Date().toISOString().split('T')[0], by:'', result:'', ref:'', std:'', remarks:'' }
  showCalibModal.value = true
}

async function saveCalib() {
  if (!calibForm.value.date || !calibForm.value.by || !calibForm.value.result) {
    alert('Please fill all required fields.'); return
  }
  try {
    await assetService.updateLaboratoryAsset(calibTarget.value.id, {
      calibration_date: calibForm.value.date,
      calibrated_by: calibForm.value.by,
      calibration_result: calibForm.value.result,
      calibration_ref: calibForm.value.ref,
      calibration_standard: calibForm.value.std,
      calibration_remarks: calibForm.value.remarks,
    })
    // Update local history
    const id = calibTarget.value.id
    if (!calibHistory.value[id]) calibHistory.value[id] = []
    calibHistory.value[id].push({ ...calibForm.value })
    // Update next calib date locally
    const months = calibTarget.value.calibCycle === '12 months' ? 12 : 6
    const d = new Date(calibForm.value.date)
    d.setMonth(d.getMonth() + months)
    const eq = equipment.value.find(e => e.id === id)
    if (eq) {
      eq.nextCalib = d.toISOString().split('T')[0]
      eq.status = calibForm.value.result === 'Pass' ? 'Operational' : 'Under Repair'
      eq.calibOverdue = false
    }
    showCalibModal.value = false
  } catch (e) {
    alert('Failed to save calibration: ' + (e?.message || 'Unknown error'))
    console.error('Calib save error:', e)
  }
}

// ── Repair modal ──────────────────────────────────────────────────────
const showRepairModal = ref(false)
const repairTarget    = ref(null)
const repairForm      = ref({ faultDate:'', fault:'', status:'', tech:'', resolvedDate:'', cost:'', remarks:'' })
const repairHistory   = ref({})
const repairTab       = ref('new')

function openRepair(eq) {
  repairTarget.value = eq
  repairTab.value    = 'new'
  repairForm.value   = { faultDate: new Date().toISOString().split('T')[0], fault:'', status:'', tech:'', resolvedDate:'', cost:'', remarks:'' }
  showRepairModal.value = true
}

async function saveRepair() {
  if (!repairForm.value.fault || !repairForm.value.status) {
    alert('Please fill all required fields.'); return
  }
  try {
    await assetService.updateLaboratoryAsset(repairTarget.value.id, {
      fault_date: repairForm.value.faultDate,
      fault_description: repairForm.value.fault,
      repair_status: repairForm.value.status,
      technician: repairForm.value.tech,
      resolved_date: repairForm.value.resolvedDate,
      repair_cost: repairForm.value.cost,
      repair_remarks: repairForm.value.remarks,
    })
    const id = repairTarget.value.id
    if (!repairHistory.value[id]) repairHistory.value[id] = []
    repairHistory.value[id].push({ ...repairForm.value })
    const statusMap = { Reported:'Reported', 'Under Repair':'Under Repair', Resolved:'Operational', 'Beyond Repair':'Beyond Repair' }
    const eq = equipment.value.find(e => e.id === id)
    if (eq) eq.status = statusMap[repairForm.value.status] || repairForm.value.status
    showRepairModal.value = false
  } catch (e) {
    alert('Failed to save repair log: ' + (e?.message || 'Unknown error'))
    console.error('Repair save error:', e)
  }
}

const showResolvedFields = (status) => ['Resolved','Beyond Repair'].includes(status)

function statusClass(s) {
  if (s === 'Operational') return 'r-green'
  if (s === 'Out of Order' || s === 'Beyond Repair') return 'r-red'
  return 'r-amber'
}

onMounted(loadEquipment)
</script>

<template>
  <div>
    <div class="abar red">
      🔴 <b>Turbidimeter Hach 2100Q</b> — calibration overdue &nbsp;|&nbsp; <b>Autoclave</b> repair open 28 days without resolution
    </div>

    <div class="cards" style="grid-template-columns:repeat(5,1fr)">
      <div class="card"><div class="c-lbl">Total Instruments</div><div class="c-val">{{ equipment.length }}</div></div>
      <div class="card c-green"><div class="c-lbl">Operational</div><div class="c-val">{{ equipment.filter(e=>e.status==='Operational').length }}</div></div>
      <div class="card c-red"><div class="c-lbl">Out of Order</div><div class="c-val">{{ equipment.filter(e=>e.status==='Out of Order').length }}</div></div>
      <div class="card c-amber"><div class="c-lbl">Under Repair</div><div class="c-val">{{ equipment.filter(e=>e.status==='Under Repair').length }}</div></div>
      <div class="card c-red"><div class="c-lbl">Calib. Overdue</div><div class="c-val">{{ equipment.filter(e=>e.calibOverdue).length }}</div></div>
    </div>

    <div style="height:8px"></div>

    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th>S#</th><th>Equipment Name</th><th>Make / Model</th><th>Asset Code</th>
            <th>Purchase Date</th><th>Calib. Cycle</th><th>Status</th><th>Next Calib. Due</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(eq, i) in equipment" :key="eq.id" :class="i%2===1?'alt':''">
            <td>{{ i+1 }}</td>
            <td><b>{{ eq.name }}</b></td>
            <td>{{ eq.model }}</td>
            <td class="mono">{{ eq.id }}</td>
            <td>{{ eq.purchased }}</td>
            <td>{{ eq.calibCycle }}</td>
            <td><span class="rag" :class="statusClass(eq.status)">{{ eq.status }}</span></td>
            <td :style="eq.calibOverdue ? 'color:var(--red);font-weight:700' : eq.nextCalib.includes('⚠') ? 'color:var(--amber);font-weight:600' : ''">
              {{ eq.nextCalib }}
            </td>
            <td>
              <button class="btn btn-sec btn-xs" @click="openCalib(eq)">📋 Calib.</button>
              <button class="btn btn-sec btn-xs" style="margin-left:4px" @click="openRepair(eq)">🔧 Repair</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ── CALIBRATION MODAL ── -->
    <Teleport to="body">
      <div v-if="showCalibModal" @click.self="showCalibModal = false"
           style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:3300;align-items:center;justify-content:center;overflow-y:auto;padding:20px">
        <div style="background:#fff;border-radius:8px;width:100%;max-width:640px;padding:24px 28px;position:relative;margin:auto">
          <button @click="showCalibModal = false" style="position:absolute;top:14px;right:16px;background:rgba(0,0,0,.07);border:none;border-radius:5px;padding:4px 10px;cursor:pointer;font-size:13px">✕</button>
          <h2 style="margin-bottom:2px">📋 Calibration Log</h2>
          <div style="font-size:11.5px;color:var(--muted);margin-bottom:4px">{{ calibTarget?.name }} · {{ calibTarget?.model }} · {{ calibTarget?.id }}</div>
          <div style="font-size:11px;font-weight:600;color:var(--navy);background:#eff6ff;border:1px solid #bfdbfe;border-radius:5px;padding:4px 10px;display:inline-block;margin-bottom:12px">
            🔁 Calib. Cycle: {{ calibTarget?.calibCycle }}
          </div>
          <!-- Tabs -->
          <div style="display:flex;gap:0;border-bottom:2px solid var(--border);margin-bottom:16px">
            <div @click="calibTab='new'"  style="padding:7px 18px;cursor:pointer;font-size:12px;font-weight:600;" :style="calibTab==='new'  ? 'border-bottom:2px solid var(--navy);margin-bottom:-2px;color:var(--navy)' : 'color:var(--muted)'">➕ Log Calibration</div>
            <div @click="calibTab='hist'" style="padding:7px 18px;cursor:pointer;font-size:12px;font-weight:600;" :style="calibTab==='hist' ? 'border-bottom:2px solid var(--navy);margin-bottom:-2px;color:var(--navy)' : 'color:var(--muted)'">📜 History</div>
          </div>
          <div v-if="calibTab === 'new'" class="form-grid c2">
            <div class="fg2"><label>Calibration Date *</label><input type="date" v-model="calibForm.date"></div>
            <div class="fg2"><label>Calibrated By *</label><input type="text" v-model="calibForm.by" placeholder="Name / Organisation"></div>
            <div class="fg2">
              <label>Result *</label>
              <select v-model="calibForm.result">
                <option value="">— Select —</option>
                <option value="Pass">Pass ✅</option>
                <option value="Conditional Pass">Conditional Pass ⚠</option>
                <option value="Fail">Fail ❌</option>
              </select>
            </div>
            <div class="fg2"><label>Certificate / Ref. No.</label><input type="text" v-model="calibForm.ref" placeholder="e.g. CALIB/26/0031"></div>
            <div class="fg2"><label>Standard Used</label><input type="text" v-model="calibForm.std" placeholder="e.g. NIST SRM 3128"></div>
            <div class="fg2"><label>Remarks</label><input type="text" v-model="calibForm.remarks" placeholder="Any deviations, adjustments…"></div>
          </div>
          <div v-if="calibTab === 'hist'">
            <div v-if="!calibHistory[calibTarget?.id]?.length" style="text-align:center;color:var(--muted);padding:20px">No calibration records yet.</div>
            <div class="tbl-wrap" v-else>
              <table>
                <thead><tr><th>Date</th><th>Calibrated By</th><th>Result</th><th>Cert. / Ref.</th><th>Remarks</th></tr></thead>
                <tbody>
                  <tr v-for="(r, i) in calibHistory[calibTarget?.id]" :key="i" :class="i%2===1?'alt':''">
                    <td>{{ r.date }}</td><td>{{ r.by }}</td>
                    <td><span class="rag" :class="r.result==='Pass'?'r-green':r.result==='Fail'?'r-red':'r-amber'">{{ r.result }}</span></td>
                    <td class="mono">{{ r.ref }}</td><td style="font-size:11px;color:var(--muted)">{{ r.remarks }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div v-if="calibTab === 'new'" style="display:flex;gap:10px;justify-content:flex-end;margin-top:18px">
            <button class="btn btn-sec" @click="showCalibModal = false">Cancel</button>
            <button class="btn btn-pri" @click="saveCalib">💾 Save</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── REPAIR MODAL ── -->
    <Teleport to="body">
      <div v-if="showRepairModal" @click.self="showRepairModal = false"
           style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:3300;align-items:center;justify-content:center;overflow-y:auto;padding:20px">
        <div style="background:#fff;border-radius:8px;width:100%;max-width:640px;padding:24px 28px;position:relative;margin:auto">
          <button @click="showRepairModal = false" style="position:absolute;top:14px;right:16px;background:rgba(0,0,0,.07);border:none;border-radius:5px;padding:4px 10px;cursor:pointer;font-size:13px">✕</button>
          <h2 style="margin-bottom:2px">🔧 Repair Log</h2>
          <div style="font-size:11.5px;color:var(--muted);margin-bottom:14px">{{ repairTarget?.name }} · {{ repairTarget?.model }} · {{ repairTarget?.id }}</div>
          <div style="display:flex;gap:0;border-bottom:2px solid var(--border);margin-bottom:16px">
            <div @click="repairTab='new'"  style="padding:7px 18px;cursor:pointer;font-size:12px;font-weight:600;" :style="repairTab==='new'  ? 'border-bottom:2px solid var(--navy);margin-bottom:-2px;color:var(--navy)' : 'color:var(--muted)'">➕ Log / Update</div>
            <div @click="repairTab='hist'" style="padding:7px 18px;cursor:pointer;font-size:12px;font-weight:600;" :style="repairTab==='hist' ? 'border-bottom:2px solid var(--navy);margin-bottom:-2px;color:var(--navy)' : 'color:var(--muted)'">📜 History</div>
          </div>
          <div v-if="repairTab === 'new'" class="form-grid c2">
            <div class="fg2"><label>Fault Reported Date *</label><input type="date" v-model="repairForm.faultDate"></div>
            <div class="fg2"><label>Fault Description *</label><input type="text" v-model="repairForm.fault" placeholder="e.g. Display blank, motor noise…"></div>
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
            <div class="fg2"><label>Technician / Vendor</label><input type="text" v-model="repairForm.tech" placeholder="Name or company"></div>
            <div class="fg2" v-if="showResolvedFields(repairForm.status)"><label>Date Resolved</label><input type="date" v-model="repairForm.resolvedDate"></div>
            <div class="fg2" v-if="showResolvedFields(repairForm.status)"><label>Repair Cost (Rs.)</label><input type="number" v-model="repairForm.cost" placeholder="0"></div>
            <div class="fg2 span2"><label>Remarks</label><input type="text" v-model="repairForm.remarks" placeholder="Parts replaced, pending items…"></div>
          </div>
          <div v-if="repairTab === 'hist'">
            <div v-if="!repairHistory[repairTarget?.id]?.length" style="text-align:center;color:var(--muted);padding:20px">No repair records yet.</div>
            <div class="tbl-wrap" v-else>
              <table>
                <thead><tr><th>Fault Date</th><th>Fault</th><th>Technician</th><th>Status</th><th>Resolved</th><th>Cost (Rs.)</th></tr></thead>
                <tbody>
                  <tr v-for="(r, i) in repairHistory[repairTarget?.id]" :key="i" :class="i%2===1?'alt':''">
                    <td>{{ r.faultDate }}</td><td style="font-size:11px">{{ r.fault }}</td><td style="font-size:11px">{{ r.tech }}</td>
                    <td><span class="rag" :class="r.status==='Resolved'?'r-green':r.status==='Beyond Repair'?'r-red':'r-amber'">{{ r.status }}</span></td>
                    <td>{{ r.resolvedDate || '—' }}</td><td class="mono">{{ r.cost || '—' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div v-if="repairTab === 'new'" style="display:flex;gap:10px;justify-content:flex-end;margin-top:18px">
            <button class="btn btn-sec" @click="showRepairModal = false">Cancel</button>
            <button class="btn btn-pri" @click="saveRepair">💾 Save</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
