<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { assetService } from '../../../services/assetService.js'

// ── Toast (mirrors StockInventory.vue's pattern) ───────────────────────────
const toast = ref({ show: false, type: 'success', message: '' })
let toastTimer = null
function showToast(type, message, duration = 4000) {
  if (toastTimer) clearTimeout(toastTimer)
  toast.value = { show: true, type, message }
  toastTimer = setTimeout(() => { toast.value.show = false }, duration)
}
onUnmounted(() => { if (toastTimer) clearTimeout(toastTimer) })

// ── Equipment table ────────────────────────────────────────────────────────
const loading   = ref(false)
const errorMsg  = ref('')
const equipment = ref([])

function mapEquipment(a) {
  const nextCalib  = a.next_calibration_date || '—'
  const isOverdue  = nextCalib !== '—' && new Date(nextCalib) < new Date()
  // Equipment-specific fields live on laboratory_assets directly; the master
  // asset is eager-loaded under `asset` (so we can read kind/item_code/etc).
  // We also fall back to top-level scalars exposed by LaboratoryAssetResource.
  return {
    id:              a.id,
    assetId:         a.asset_id || a.id,
    kind:            a.asset?.kind || null,
    name:            a.asset?.name || a.name || '—',
    asset_code:      a.asset?.item_code || '—',
    model:           a.make_model || '—',
    serial_number:   a.serial_number || '—',
    purchased:       a.purchased_at ? String(a.purchased_at).split('T')[0] : '—',
    warranty_expiry: a.warranty_expiry ? String(a.warranty_expiry).split('T')[0] : null,
    purchase_value:  a.purchase_value || a.asset?.purchase_value || null,
    calibCycle:      a.calibration_cycle || '12 months',
    status:          a.status || 'Operational',
    nextCalib:       nextCalib,
    calibOverdue:    isOverdue,
  }
}

async function loadEquipment() {
  loading.value  = true
  errorMsg.value = ''
  try {
    const res  = await assetService.getLaboratoryAssets()
    const data = res.data?.data || res.data || []
    // SRS §2.7-3: Equipment Register shows ONLY lab instruments (kind=equipment).
    // Non-consumables (kind=inventory) belong on the Inventory tab instead.
    // No fallback — if kind is missing we exclude the row rather than risk
    // leaking inventory items onto the equipment register.
    equipment.value = Array.isArray(data)
      ? data
          .filter(a => a.asset?.kind === 'equipment')
          .map(mapEquipment)
      : []
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
    showToast('error', 'Please fill all required fields.')
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

    // Reflect changes in the table row (optimistic — keeps the UI snappy if
    // the reload is slow). loadEquipment() below is the source of truth.
    const eq = equipment.value.find(e => e.id === calibTarget.value.id)
    if (eq && updated) {
      eq.nextCalib    = updated.next_calibration_date || eq.nextCalib
      eq.calibOverdue = eq.nextCalib !== '—' && new Date(eq.nextCalib) < new Date()
      eq.status       = updated.status || eq.status
    }
    showCalibModal.value = false
    showToast('success', `Calibration logged for "${calibTarget.value?.name || 'equipment'}"`)
    await loadEquipment()
  } catch (e) {
    showToast('error', 'Failed to save calibration log: ' + (e?.response?.data?.message || e?.message || 'Unknown error'))
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
    showToast('error', 'Please fill all required fields.')
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

    // Optimistic row update — loadEquipment() below is the source of truth.
    const eq = equipment.value.find(e => e.id === repairTarget.value.id)
    if (eq && updated) eq.status = updated.status || eq.status

    showRepairModal.value = false
    showToast('success', `Repair logged for "${repairTarget.value?.name || 'equipment'}"`)
    await loadEquipment()
  } catch (e) {
    showToast('error', 'Failed to save repair log: ' + (e?.response?.data?.message || e?.message || 'Unknown error'))
    console.error('Repair save error:', e)
  } finally {
    repairSaving.value = false
  }
}

// ── Add Equipment modal ────────────────────────────────────────────────────
// Captures SRS §2.7-3 fields: shared identity goes onto the master `assets`
// row (kind='equipment'); the instance-specific fields (make_model, serial,
// purchase/warranty, calibration cycle) land on the per-lab `laboratory_assets`
// row. Backend AssetController::store does the 4-table write in one txn.
const showAddEquipmentModal = ref(false)
const addEquipmentForm = ref({
  name: '', item_code: '', category: '',
  make_model: '', serial_number: '',
  purchased_at: '', purchase_value: '', warranty_expiry: '',
  calibration_cycle: '12 months',
  status: 'Operational', location: '',
})
const addEquipmentErrors = ref({})
const addEquipmentSaving = ref(false)

function openAddEquipment() {
  addEquipmentForm.value = {
    name: '', item_code: '', category: '',
    make_model: '', serial_number: '',
    purchased_at: '', purchase_value: '', warranty_expiry: '',
    calibration_cycle: '12 months',
    status: 'Operational', location: '',
  }
  addEquipmentErrors.value = {}
  showAddEquipmentModal.value = true
}

async function saveEquipment() {
  addEquipmentErrors.value = {}
  const f = addEquipmentForm.value
  const errs = {}
  if (!f.name?.trim()) errs.name = ['Equipment Name is required']
  if (Object.keys(errs).length) { addEquipmentErrors.value = errs; return }

  const payload = {
    name:              f.name.trim(),
    kind:              'equipment',
    category:          f.category?.trim() || null,
    item_code:         f.item_code?.trim() || null,
    quantity:          '1.00',
    unit:              'Pcs',
    // AssetStatusEnum uses capitalised values — map UI text directly.
    status:            f.status || 'Active',
    condition:         'good',
    location:          f.location?.trim() || null,
    purchase_value:    f.purchase_value ? Number(f.purchase_value).toFixed(2) : null,
    // Equipment-specific (land on laboratory_assets via AssetController::store)
    make_model:        f.make_model?.trim() || null,
    serial_number:     f.serial_number?.trim() || null,
    purchased_at:      f.purchased_at || null,
    warranty_expiry:   f.warranty_expiry || null,
    calibration_cycle: f.calibration_cycle || null,
  }

  addEquipmentSaving.value = true
  try {
    await assetService.createAsset(payload)
    showAddEquipmentModal.value = false
    showToast('success', `Equipment "${payload.name}" added`)
    await loadEquipment()
  } catch (err) {
    const status = err?.response?.status
    if (status === 422) {
      const errors = err.response?.data?.errors || {}
      addEquipmentErrors.value = errors
      const firstError = Object.values(errors)[0]?.[0]
      showToast('error', firstError || err.response?.data?.message || 'Please fix the highlighted fields')
    } else if (status === 403) {
      showToast('error', '403 — You do not have permission to add equipment')
    } else {
      showToast('error', err?.response?.data?.message || `Failed to save equipment (HTTP ${status || 'unknown'})`)
    }
  } finally {
    addEquipmentSaving.value = false
  }
}

// ── Edit Equipment modal ────────────────────────────────────────────────────
// Updates BOTH the master `assets` row (name, item_code, category, location,
// condition, status) AND the per-lab `laboratory_assets` row (make_model,
// serial_number, purchased_at, warranty_expiry, purchase_value, calibration_cycle).
// Two PUTs run sequentially in saveEditEquipment().
const showEditEquipmentModal = ref(false)
const editEquipmentSaving = ref(false)
const editEquipmentForm = ref({
  // ids
  laboratory_asset_id: null,
  asset_id: null,
  // master assets fields
  name: '', item_code: '', category: '', condition: 'good', location: '',
  // laboratory_assets fields
  make_model: '', serial_number: '',
  purchased_at: '', purchase_value: '', warranty_expiry: '',
  calibration_cycle: '12 months',
  status: 'Operational',
})

function openEditEquipment(eq) {
  editEquipmentForm.value = {
    laboratory_asset_id: eq.id,
    asset_id:            eq.assetId,
    name:                eq.name && eq.name !== '—' ? eq.name : '',
    item_code:           eq.asset_code && eq.asset_code !== '—' ? eq.asset_code : '',
    category:            '',
    condition:           'good',
    location:            '',
    make_model:          eq.model && eq.model !== '—' ? eq.model : '',
    serial_number:       eq.serial_number && eq.serial_number !== '—' ? eq.serial_number : '',
    purchased_at:        eq.purchased && eq.purchased !== '—' ? eq.purchased : '',
    purchase_value:      eq.purchase_value || '',
    warranty_expiry:     eq.warranty_expiry || '',
    calibration_cycle:   eq.calibCycle || '12 months',
    status:              eq.status || 'Operational',
  }
  showEditEquipmentModal.value = true
}

async function saveEditEquipment() {
  const f = editEquipmentForm.value
  if (!f.name?.trim()) {
    showToast('error', 'Equipment Name is required')
    return
  }

  editEquipmentSaving.value = true
  try {
    // 1. Update master assets row (only fields that live there).
    const masterPayload = {
      name:      f.name.trim(),
      kind:      'equipment',
      category:  f.category?.trim() || null,
      item_code: f.item_code?.trim() || null,
      condition: f.condition || 'good',
      location:  f.location?.trim() || null,
      // status on the master is the AssetStatusEnum — keep it 'Active' so the
      // lab-level status (Operational/Out of Order/etc.) governs equipment UX.
      status:    'Active',
    }
    await assetService.updateAsset(f.asset_id, masterPayload)

    // 2. Update per-lab equipment fields.
    const labPayload = {
      make_model:        f.make_model?.trim() || null,
      serial_number:     f.serial_number?.trim() || null,
      purchased_at:      f.purchased_at || null,
      warranty_expiry:   f.warranty_expiry || null,
      purchase_value:    f.purchase_value ? Number(f.purchase_value).toFixed(2) : null,
      calibration_cycle: f.calibration_cycle || null,
      status:            f.status || null,
    }
    await assetService.updateLaboratoryAsset(f.laboratory_asset_id, labPayload)

    showEditEquipmentModal.value = false
    showToast('success', `Equipment "${f.name}" updated`)
    await loadEquipment()
  } catch (err) {
    const status = err?.response?.status
    if (status === 422) {
      const errors = err.response?.data?.errors || {}
      const firstError = Object.values(errors)[0]?.[0]
      showToast('error', firstError || err.response?.data?.message || 'Please fix the highlighted fields')
    } else if (status === 403) {
      showToast('error', '403 — You do not have permission to edit equipment')
    } else {
      showToast('error', err?.response?.data?.message || `Failed to update equipment (HTTP ${status || 'unknown'})`)
    }
  } finally {
    editEquipmentSaving.value = false
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

    <!-- Toolbar: + Add Equipment -->
    <div style="display:flex;justify-content:flex-end;margin-bottom:10px">
      <button class="btn btn-pri" @click="openAddEquipment">+ Add Equipment</button>
    </div>

    <!-- Equipment Table -->
    <div class="tbl-wrap">
      <!-- Skeleton loading state -->
      <table v-if="loading">
        <thead>
          <tr>
            <th>S#</th><th>Equipment Name</th><th>Make / Model</th><th>Serial No.</th>
            <th>Asset Code</th><th>Purchase Date</th><th>Warranty Expiry</th>
            <th>Calib. Cycle</th><th>Status</th><th>Next Calib. Due</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="i in 6" :key="'sk-' + i" class="sk-row">
            <td><span class="sk-bar sk-xs"></span></td>
            <td><span class="sk-bar sk-lg"></span></td>
            <td><span class="sk-bar sk-md"></span></td>
            <td><span class="sk-bar sk-sm"></span></td>
            <td><span class="sk-bar sk-sm"></span></td>
            <td><span class="sk-bar sk-md"></span></td>
            <td><span class="sk-bar sk-md"></span></td>
            <td><span class="sk-bar sk-sm"></span></td>
            <td><span class="sk-bar sk-sm"></span></td>
            <td><span class="sk-bar sk-md"></span></td>
            <td><span class="sk-bar sk-md"></span></td>
          </tr>
        </tbody>
      </table>

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
            <th style="min-width:180px;max-width:220px">Make / Model</th>
            <th>Serial No.</th>
            <th>Asset Code</th>
            <th>Purchase Date</th>
            <th>Warranty Expiry</th>
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
            <td>
              <div class="cell-scroll" :title="eq.model">{{ eq.model }}</div>
            </td>
            <td class="mono">{{ eq.serial_number || '—' }}</td>
            <td class="mono">{{ eq.asset_code }}</td>
            <td>{{ fmtDate(eq.purchased) }}</td>
            <td>{{ fmtDate(eq.warranty_expiry) || '—' }}</td>
            <td>{{ eq.calibCycle }}</td>
            <td><span class="rag" :class="statusClass(eq.status)">{{ eq.status }}</span></td>
            <td :style="eq.calibOverdue ? 'color:var(--red);font-weight:700' : ''">
              {{ fmtDate(eq.nextCalib) }}
            </td>
            <td style="min-width:230px;white-space:nowrap">
              <div style="display:inline-flex;flex-wrap:nowrap;gap:6px">
                <button class="btn btn-sec btn-xs" @click="openCalib(eq)">📋 Calib.</button>
                <button class="btn btn-sec btn-xs" @click="openRepair(eq)">🔧 Repair</button>
                <button class="btn btn-sec btn-xs" @click="openEditEquipment(eq)">✎ Edit</button>
              </div>
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
            <div class="tbl-wrap" v-if="calibHistLoading">
              <table>
                <thead>
                  <tr><th>Date</th><th>Calibrated By</th><th>Result</th><th>Next Due</th><th>Cert. / Ref.</th><th>Standard Used</th></tr>
                </thead>
                <tbody>
                  <tr v-for="i in 4" :key="'ck-' + i" class="sk-row">
                    <td><span class="sk-bar sk-md"></span></td>
                    <td><span class="sk-bar sk-md"></span></td>
                    <td><span class="sk-bar sk-sm"></span></td>
                    <td><span class="sk-bar sk-md"></span></td>
                    <td><span class="sk-bar sk-sm"></span></td>
                    <td><span class="sk-bar sk-md"></span></td>
                  </tr>
                </tbody>
              </table>
            </div>
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
            <div class="tbl-wrap" v-if="repairHistLoading">
              <table>
                <thead>
                  <tr><th>Fault Date</th><th>Description</th><th>Status</th><th>Technician</th><th>Resolved</th><th>Cost</th></tr>
                </thead>
                <tbody>
                  <tr v-for="i in 4" :key="'rk-' + i" class="sk-row">
                    <td><span class="sk-bar sk-md"></span></td>
                    <td><span class="sk-bar sk-lg"></span></td>
                    <td><span class="sk-bar sk-sm"></span></td>
                    <td><span class="sk-bar sk-md"></span></td>
                    <td><span class="sk-bar sk-md"></span></td>
                    <td><span class="sk-bar sk-sm"></span></td>
                  </tr>
                </tbody>
              </table>
            </div>
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

    <!-- ─── Add Equipment Modal ─── -->
    <Teleport to="body">
      <div v-if="showAddEquipmentModal" @click.self="showAddEquipmentModal = false"
           style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:3300;align-items:center;justify-content:center;overflow-y:auto;padding:20px">
        <div style="background:#fff;border-radius:8px;width:100%;max-width:720px;padding:24px 28px;position:relative;margin:auto">
          <button @click="showAddEquipmentModal = false"
                  style="position:absolute;top:14px;right:16px;background:rgba(0,0,0,.07);border:none;border-radius:5px;padding:4px 10px;cursor:pointer;font-size:13px">✕</button>
          <h2 style="margin-bottom:2px">🧪 Add Equipment</h2>
          <div style="font-size:11.5px;color:var(--muted);margin-bottom:14px;font-style:italic">
            New lab instrument (kind=equipment). Identity goes to <code>assets</code>; instance details go to <code>laboratory_assets</code>.
          </div>

          <div class="form-grid c2">
            <div class="fg2" style="grid-column:1/-1">
              <label>Equipment Name *</label>
              <input type="text" v-model="addEquipmentForm.name" placeholder="e.g. Atomic Absorption Spectrophotometer" />
            </div>

            <div class="fg2">
              <label>Asset Code</label>
              <input type="text" v-model="addEquipmentForm.item_code" placeholder="e.g. EQ-AAS-001" />
            </div>
            <div class="fg2">
              <label>Category</label>
              <select v-model="addEquipmentForm.category">
                <option value="">— Select Category —</option>
                <option value="Analytical Instruments">Analytical Instruments</option>
                <option value="Microbiology Equipment">Microbiology Equipment</option>
                <option value="Sample Prep">Sample Prep</option>
                <option value="Safety / Lab Support">Safety / Lab Support</option>
                <option value="Other">Other</option>
              </select>
            </div>

            <div class="fg2">
              <label>Make / Model</label>
              <input type="text" v-model="addEquipmentForm.make_model" placeholder="e.g. Shimadzu AA-7000" />
            </div>
            <div class="fg2">
              <label>Serial No.</label>
              <input type="text" v-model="addEquipmentForm.serial_number" placeholder="e.g. SN-AA-001" />
            </div>

            <div class="fg2">
              <label>Purchase Date</label>
              <input type="date" v-model="addEquipmentForm.purchased_at" />
            </div>
            <div class="fg2">
              <label>Warranty Expiry</label>
              <input type="date" v-model="addEquipmentForm.warranty_expiry" />
            </div>

            <div class="fg2">
              <label>Purchase Value (Rs.)</label>
              <input type="number" min="0" step="0.01" v-model="addEquipmentForm.purchase_value" placeholder="e.g. 4500000" />
            </div>
            <div class="fg2">
              <label>Calibration Cycle</label>
              <select v-model="addEquipmentForm.calibration_cycle">
                <option value="6 months">6 months</option>
                <option value="12 months">12 months</option>
                <option value="24 months">24 months</option>
              </select>
            </div>

            <div class="fg2">
              <label>Status</label>
              <select v-model="addEquipmentForm.status">
                <option value="Active">Active</option>
                <option value="Under_service">Under Service</option>
                <option value="Broken">Broken</option>
                <option value="InActive">Inactive</option>
              </select>
            </div>
            <div class="fg2">
              <label>Location</label>
              <input type="text" v-model="addEquipmentForm.location" placeholder="e.g. Central Lab Peshawar — Main Lab" />
            </div>
          </div>

          <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:18px">
            <button class="btn btn-sec" @click="showAddEquipmentModal = false" :disabled="addEquipmentSaving">Cancel</button>
            <button class="btn btn-pri" @click="saveEquipment" :disabled="addEquipmentSaving">
              {{ addEquipmentSaving ? '⏳ Saving…' : '💾 Save Equipment' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ─── Edit Equipment Modal ─── -->
    <Teleport to="body">
      <div v-if="showEditEquipmentModal" @click.self="showEditEquipmentModal = false"
           style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:3300;align-items:center;justify-content:center;overflow-y:auto;padding:20px">
        <div style="background:#fff;border-radius:8px;width:100%;max-width:720px;padding:24px 28px;position:relative;margin:auto">
          <button @click="showEditEquipmentModal = false"
                  style="position:absolute;top:14px;right:16px;background:rgba(0,0,0,.07);border:none;border-radius:5px;padding:4px 10px;cursor:pointer;font-size:13px">✕</button>
          <h2 style="margin-bottom:2px">✎ Edit Equipment</h2>
          <div style="font-size:11.5px;color:var(--muted);margin-bottom:14px;font-style:italic">
            Master identity goes to <code>assets</code>; instance details go to <code>laboratory_assets</code>.
          </div>

          <div class="form-grid c2">
            <div class="fg2" style="grid-column:1/-1">
              <label>Equipment Name *</label>
              <input type="text" v-model="editEquipmentForm.name" placeholder="e.g. Atomic Absorption Spectrophotometer" />
            </div>

            <div class="fg2">
              <label>Asset Code</label>
              <input type="text" v-model="editEquipmentForm.item_code" placeholder="e.g. EQ-AAS-001" />
            </div>
            <div class="fg2">
              <label>Category</label>
              <select v-model="editEquipmentForm.category">
                <option value="">— Select Category —</option>
                <option value="Analytical Instruments">Analytical Instruments</option>
                <option value="Microbiology Equipment">Microbiology Equipment</option>
                <option value="Sample Prep">Sample Prep</option>
                <option value="Safety / Lab Support">Safety / Lab Support</option>
                <option value="Other">Other</option>
              </select>
            </div>

            <div class="fg2">
              <label>Make / Model</label>
              <input type="text" v-model="editEquipmentForm.make_model" placeholder="e.g. Shimadzu AA-7000" />
            </div>
            <div class="fg2">
              <label>Serial No.</label>
              <input type="text" v-model="editEquipmentForm.serial_number" placeholder="e.g. SN-AA-001" />
            </div>

            <div class="fg2">
              <label>Purchase Date</label>
              <input type="date" v-model="editEquipmentForm.purchased_at" />
            </div>
            <div class="fg2">
              <label>Warranty Expiry</label>
              <input type="date" v-model="editEquipmentForm.warranty_expiry" />
            </div>

            <div class="fg2">
              <label>Purchase Value (Rs.)</label>
              <input type="number" min="0" step="0.01" v-model="editEquipmentForm.purchase_value" placeholder="e.g. 4500000" />
            </div>
            <div class="fg2">
              <label>Calibration Cycle</label>
              <select v-model="editEquipmentForm.calibration_cycle">
                <option value="6 months">6 months</option>
                <option value="12 months">12 months</option>
                <option value="24 months">24 months</option>
              </select>
            </div>

            <div class="fg2">
              <label>Status</label>
              <select v-model="editEquipmentForm.status">
                <option value="Operational">Operational</option>
                <option value="Out of Order">Out of Order</option>
                <option value="Under Repair">Under Repair</option>
                <option value="Beyond Repair">Beyond Repair</option>
              </select>
            </div>
            <div class="fg2">
              <label>Condition</label>
              <select v-model="editEquipmentForm.condition">
                <option value="good">Good</option>
                <option value="fair">Fair</option>
                <option value="poor">Poor</option>
                <option value="condemned">Condemned</option>
              </select>
            </div>

            <div class="fg2" style="grid-column:1/-1">
              <label>Location</label>
              <input type="text" v-model="editEquipmentForm.location" placeholder="e.g. Central Lab Peshawar — Main Lab" />
            </div>
          </div>

          <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:18px">
            <button class="btn btn-sec" @click="showEditEquipmentModal = false" :disabled="editEquipmentSaving">Cancel</button>
            <button class="btn btn-pri" @click="saveEditEquipment" :disabled="editEquipmentSaving">
              {{ editEquipmentSaving ? '⏳ Saving…' : '💾 Save Changes' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ─── Toast (same pattern as StockInventory.vue) ─── -->
    <Teleport to="body">
      <transition name="toast-slide">
        <div v-if="toast.show" class="eq-toast" :class="`eq-toast-${toast.type}`">
          <span class="eq-toast-msg">{{ toast.message }}</span>
          <button class="eq-toast-close" type="button" @click="toast.show = false">✕</button>
        </div>
      </transition>
    </Teleport>
  </div>
</template>

<style scoped>
/* Toast — fixed top-right, slides in/out, success/error variants */
.eq-toast {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 5000;
  display: flex;
  align-items: center;
  gap: 14px;
  min-width: 280px;
  max-width: 420px;
  padding: 12px 16px;
  border-radius: 6px;
  background: #fff;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, .2);
  font-size: 14px;
  font-weight: 500;
}
.eq-toast-success { border-left: 4px solid #176b3a; color: #176b3a; }
.eq-toast-error   { border-left: 4px solid #9d1b20; color: #9d1b20; }
.eq-toast-msg     { flex: 1; }
.eq-toast-close {
  width: 22px; height: 22px;
  padding: 0; border: 0;
  border-radius: 4px;
  background: transparent;
  color: inherit;
  font-size: 14px; line-height: 1;
  cursor: pointer; opacity: .6;
}
.eq-toast-close:hover { opacity: 1; }

.toast-slide-enter-active,
.toast-slide-leave-active { transition: transform .25s ease, opacity .25s ease; }
.toast-slide-enter-from,
.toast-slide-leave-to     { transform: translateX(40px); opacity: 0; }

/* Constrain long Make/Model strings so they don't bloat the row. The cell
   shows the first ~3 lines and reveals the rest via a vertical scrollbar.
   Full string is also surfaced as a tooltip via the `title` attribute. */
.cell-scroll {
  max-width: 220px;
  max-height: 4.2em;       /* ~3 lines of content */
  overflow-y: auto;
  overflow-x: hidden;
  word-break: break-word;
  line-height: 1.4;
  padding-right: 4px;
  scrollbar-width: thin;
}

.cell-scroll::-webkit-scrollbar { width: 6px; }
.cell-scroll::-webkit-scrollbar-thumb { background: #c8d5e2; border-radius: 3px; }
.cell-scroll::-webkit-scrollbar-thumb:hover { background: #9fb3c8; }

/* Skeleton loading rows — shimmer placeholder pattern. */
.sk-row td { padding: 10px 11px; }
.sk-bar {
  display: inline-block;
  height: 11px;
  width: 70px;
  border-radius: 3px;
  background: linear-gradient(90deg, #eef2f7 0%, #f7fafc 50%, #eef2f7 100%);
  background-size: 200% 100%;
  animation: skShimmer 1.2s ease-in-out infinite;
  vertical-align: middle;
}
.sk-bar.sk-xs { width: 18px; }
.sk-bar.sk-sm { width: 42px; }
.sk-bar.sk-md { width: 70px; }
.sk-bar.sk-lg { width: 140px; }
@keyframes skShimmer {
  0%   { background-position: 100% 0; }
  100% { background-position: -100% 0; }
}
</style>
