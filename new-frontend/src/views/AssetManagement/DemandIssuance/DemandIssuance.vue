<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { assetService } from '../../../services/assetService.js'
import { dropdownService } from '../../../services/dropdownService.js'

const loading  = ref(false)
const errorMsg = ref('')
const demands  = ref([])
const stockItems = ref([])
const savingId = ref(null)

const toast = ref({ show: false, type: 'success', message: '' })
let toastTimer = null
function showToast(type, message, duration = 4000) {
  if (toastTimer) clearTimeout(toastTimer)
  toast.value = { show: true, type, message }
  toastTimer = setTimeout(() => { toast.value.show = false }, duration)
}

function statusClass(s) {
  const v = String(s).toLowerCase()
  if (['issued', 'received'].includes(v)) return 'r-green'
  if (['approved', 'partially_approved', 'partially_issued'].includes(v)) return 'r-blue'
  if (v === 'rejected') return 'r-red'
  return 'r-amber'
}

function urgencyClass(u) {
  const v = String(u).toLowerCase()
  return v === 'urgent' || v === 'critical' ? 'r-red' : 'r-grey'
}

function prettyStatus(s) {
  return String(s || 'pending').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}

// Each inventory detail becomes its own row — important for auto-spawned
// remainder details (Mardan asks 200, admin issues 40 → original detail closes
// with "Issued: 40 of 200", new remainder detail opens at qty 160 pending).
function mapDemand(inv) {
  const details = inv.inventory_details || inv.inventoryDetails || []
  if (!details.length) return []
  const daysPending = inv.created_at
    ? Math.floor((Date.now() - new Date(inv.created_at)) / (1000 * 60 * 60 * 24))
    : 0
  return details.map((detail, idx) => {
    const itemName = detail.inventoryable?.name || detail.material?.name || detail.asset?.name || '—'
    const unit = detail.unit || ''
    const requestedQty = parseFloat(detail.quantity) || 0
    const approvedQty  = parseFloat(detail.approved_quantity) || 0
    const detailStatus = detail.status || 'pending'
    // Partial fulfillment: issued less than requested. Display-only label —
    // the backend enum doesn't have a 'partially_issued' value yet.
    const isPartial = detailStatus === 'issued' && approvedQty > 0 && approvedQty < requestedQty
    return {
      id: `${inv.id}-${detail.id || idx}`,    // composite key so multiple details under one demand don't collide
      slug: inv.slug || `DMD-${inv.id}`,
      detailId: detail.id || null,
      detailStatus,
      detailQty: requestedQty,
      approvedQty,
      isPartial,
      // Issuing-lab's available qty (already excludes expired batches). null = not a material.
      centralAvailable: detail.central_available_qty !== undefined && detail.central_available_qty !== null
        ? parseFloat(detail.central_available_qty)
        : null,
      from: inv.laboratory?.name || '—',
      item: itemName,
      qty: requestedQty ? `${requestedQty} ${unit}`.trim() : '—',
      unit,
      urgency: inv.urgency || 'routine',
      justification: inv.justification || '',
      daysPending,
      status: isPartial ? 'partially_issued' : detailStatus,
    }
  })
}

async function loadData() {
  loading.value = true
  errorMsg.value = ''
  try {
    // Demand list is lab-scoped by the backend controller.
    // Materials dropdown comes from the FULL master catalog (cross-lab) so users
    // can request items their lab doesn't currently stock.
    const [invRes, matRes] = await Promise.all([
      assetService.getInventories(),
      dropdownService.getMaterialsDropdown(),
    ])
    const invData = invRes.data?.data?.data || invRes.data?.data || invRes.data || []
    const matData = matRes.data?.data || matRes.data || []
    demands.value = Array.isArray(invData) ? invData.flatMap(mapDemand) : []

    stockItems.value = (Array.isArray(matData) ? matData : [])
      .map(m => ({ id: m.id, name: m.name || '(unnamed)', unit: m.unit || 'pcs' }))
      .sort((a, b) => a.name.localeCompare(b.name))
  } catch (e) {
    errorMsg.value = 'Failed to load demand data'
    console.error('Demand load error:', e)
  } finally {
    loading.value = false
  }
}

async function approveDemand(demand) {
  if (!demand.detailId) return
  savingId.value = demand.id
  try {
    await assetService.approveInventory(demand.detailId)
    showToast('success', `✅ Demand ${demand.slug} approved`)
    await loadData()
  } catch (e) {
    showToast('error', e?.response?.data?.message || 'Failed to approve demand')
  } finally {
    savingId.value = null
  }
}

// ─── Reject modal ──────────────────────────────────────────────────────────
const rejectModal = ref({ open: false, demand: null, reason: '' })
function openReject(demand) {
  if (!demand.detailId) return
  rejectModal.value = { open: true, demand, reason: '' }
}
async function confirmReject() {
  const { demand, reason } = rejectModal.value
  if (!demand) return
  savingId.value = demand.id
  rejectModal.value.open = false
  try {
    await assetService.rejectInventory(demand.detailId, reason || null)
    showToast('success', `Demand ${demand.slug} rejected`)
    await loadData()
  } catch (e) {
    showToast('error', e?.response?.data?.message || 'Failed to reject demand')
  } finally {
    savingId.value = null
  }
}

// ─── Issue confirm modal ───────────────────────────────────────────────────
// `issueQty` defaults to the requested qty but is editable so the approver can
// partially fulfill when their lab's available stock is less than the request.
const issueModal = ref({ open: false, demand: null, issueQty: 0, error: '' })
function openIssue(demand) {
  if (!demand.detailId) return
  // Default the issue qty to min(requested, available) so partial fulfillment
  // is one click away when stock is short.
  const requested = Number(demand.detailQty) || 0
  const available = demand.centralAvailable != null ? Number(demand.centralAvailable) : requested
  issueModal.value = { open: true, demand, issueQty: Math.min(requested, available), error: '' }
}
async function confirmIssue() {
  const demand = issueModal.value.demand
  if (!demand) return
  const requested = Number(demand.detailQty) || 0
  const available = demand.centralAvailable != null ? Number(demand.centralAvailable) : Infinity
  const qty       = Number(issueModal.value.issueQty)
  if (!qty || qty <= 0)         { issueModal.value.error = 'Quantity must be greater than 0';   return }
  if (qty > requested)          { issueModal.value.error = `Cannot issue more than requested (${requested})`; return }
  if (qty > available)          { issueModal.value.error = `Only ${available} available (excluding expired)`; return }
  savingId.value = demand.id
  issueModal.value.open = false
  try {
    await assetService.issueInventory(demand.detailId, qty)
    showToast('success', `📦 Issued ${qty} ${demand.unit} for ${demand.slug} — credited to ${demand.from}`)
    await loadData()
  } catch (e) {
    showToast('error', e?.response?.data?.message || 'Failed to issue demand')
  } finally {
    savingId.value = null
  }
}

// ─── Raise Demand modal ────────────────────────────────────────────────────
const showDemandModal = ref(false)
const demandForm = ref({ itemId: '', itemName: '', unit: '', qty: '', urgency: 'routine', justification: '' })
const demandErrors = ref({})
const demandSaving = ref(false)

async function saveDemand() {
  demandErrors.value = {}
  const f = demandForm.value
  const errs = {}
  if (!f.itemId)                errs.material_id = ['Item is required']
  if (!f.qty || Number(f.qty) <= 0) errs.quantity = ['Quantity must be greater than 0']
  if (!f.urgency)               errs.urgency = ['Urgency is required']
  if (Object.keys(errs).length) { demandErrors.value = errs; return }

  demandSaving.value = true
  try {
    await assetService.createInventory({
      urgency:       f.urgency,
      justification: f.justification || null,
      details: [{
        inventoryable_type: 'material',
        inventoryable_id:   f.itemId,
        quantity:           Number(f.qty).toFixed(2),
        unit:               f.unit || 'pcs',
      }],
    })
    showDemandModal.value = false
    showToast('success', `📤 Demand submitted for "${f.itemName}" — pending Central Lab review`)
    demandForm.value = { itemId: '', itemName: '', unit: '', qty: '', urgency: 'routine', justification: '' }
    await loadData()
  } catch (err) {
    const status = err?.response?.status
    if (status === 422) {
      demandErrors.value = err.response.data?.errors || {}
      showToast('error', 'Please fix the highlighted fields')
    } else {
      showToast('error', err?.response?.data?.message || 'Failed to submit demand')
    }
  } finally {
    demandSaving.value = false
  }
}

const pendingCount = computed(() => demands.value.filter(d => String(d.status).toLowerCase() === 'pending').length)
const overdueCount = computed(() => demands.value.filter(d => d.daysPending >= 3 && String(d.status).toLowerCase() === 'pending').length)

onMounted(loadData)
onUnmounted(() => { if (toastTimer) clearTimeout(toastTimer) })
</script>

<template>
  <div class="demand-page">
    <!-- Workflow steps -->
    <div class="wf-steps">
      <div class="wf-step wf-done"><div class="wf-num">✓</div><div class="wf-t">Step 1</div><div class="wf-d">Demand Raised</div></div>
      <div class="wf-step wf-active"><div class="wf-num">2</div><div class="wf-t">Step 2</div><div class="wf-d">Central Lab Review</div></div>
      <div class="wf-step"><div class="wf-num">3</div><div class="wf-t">Step 3</div><div class="wf-d">Stock Out</div></div>
      <div class="wf-step"><div class="wf-num">4</div><div class="wf-t">Step 4</div><div class="wf-d">Auto-Credit</div></div>
      <div class="wf-step"><div class="wf-num">5</div><div class="wf-t">Step 5</div><div class="wf-d">Status Trail</div></div>
    </div>

    <div v-if="overdueCount" class="abar amber">⚠ {{ overdueCount }} demands pending &gt; 3 working days — action required</div>

    <div class="sh">
      <div>
        <h2>Pending Demand Queue — Central Lab</h2>
        <span class="cnt">{{ pendingCount }} pending</span>
      </div>
      <button class="btn btn-pri btn-sm" @click="showDemandModal = true">+ Raise Demand</button>
    </div>

    <div v-if="loading" class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th>Demand ID</th><th>From Lab</th><th>Item</th><th>Qty</th>
            <th>Urgency</th><th>Days Pending</th><th>Status</th><th>Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="i in 6" :key="'sk-' + i" class="sk-row">
            <td><span class="sk-bar sk-sm"></span></td>
            <td><span class="sk-bar sk-md"></span></td>
            <td><span class="sk-bar sk-lg"></span></td>
            <td><span class="sk-bar sk-sm"></span></td>
            <td><span class="sk-bar sk-sm"></span></td>
            <td><span class="sk-bar sk-xs"></span></td>
            <td><span class="sk-bar sk-sm"></span></td>
            <td><span class="sk-bar sk-md"></span></td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-else-if="errorMsg" class="empty-state error">
      {{ errorMsg }}
      <button class="btn btn-sec btn-sm" @click="loadData" style="margin-left:12px">Retry</button>
    </div>

    <div v-else class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th>Demand ID</th>
            <th>From Lab</th>
            <th>Item</th>
            <th>Qty</th>
            <th>Urgency</th>
            <th>Days Pending</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(d, i) in demands" :key="d.id" :class="i%2===1?'alt':''">
            <td class="mono">{{ d.slug }}</td>
            <td>{{ d.from }}</td>
            <td>
              <b>{{ d.item }}</b>
              <div v-if="d.justification" class="muted-sm">{{ d.justification }}</div>
            </td>
            <td>
              <span v-if="d.isPartial" style="display:inline-flex;flex-direction:column;line-height:1.2">
                <span style="font-weight:600;color:#0f766e">Issued: {{ d.approvedQty }} {{ d.unit }}</span>
                <span style="font-size:11px;color:#6b7280">of {{ d.detailQty }} requested</span>
              </span>
              <span v-else>{{ d.qty }}</span>
            </td>
            <td><span class="rag" :class="urgencyClass(d.urgency)">{{ prettyStatus(d.urgency) }}</span></td>
            <td :style="d.daysPending >= 3 && String(d.status).toLowerCase() === 'pending' ? 'color:#9d1b20;font-weight:700' : ''">
              {{ d.daysPending }} <span v-if="d.daysPending >= 3 && String(d.status).toLowerCase() === 'pending'">⚠</span>
            </td>
            <td><span class="rag" :class="statusClass(d.detailStatus)">{{ prettyStatus(d.detailStatus) }}</span></td>
            <td>
              <template v-if="String(d.detailStatus).toLowerCase() === 'pending'">
                <button class="btn btn-green btn-xs" :disabled="savingId === d.id" @click="approveDemand(d)">✓ Approve</button>
                <button class="btn btn-red btn-xs" :disabled="savingId === d.id" @click="openReject(d)">✗ Reject</button>
              </template>
              <template v-else-if="String(d.detailStatus).toLowerCase() === 'approved'">
                <button class="btn btn-pri btn-xs" :disabled="savingId === d.id" @click="openIssue(d)">📦 Issue Stock</button>
              </template>
              <template v-else-if="String(d.detailStatus).toLowerCase() === 'issued'">
                <span class="muted-sm" style="color:#176b3a">✅ Issued — auto-credited</span>
              </template>
              <template v-else-if="String(d.detailStatus).toLowerCase() === 'rejected'">
                <span class="muted-sm" style="color:#9d1b20">✗ Rejected</span>
              </template>
            </td>
          </tr>
          <tr v-if="!demands.length">
            <td colspan="8" style="text-align:center;color:#94a3b8;padding:32px;font-style:italic">
              No demand requests yet. Click "+ Raise Demand" to submit one.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Raise Demand Modal -->
    <Teleport to="body">
      <div v-if="showDemandModal" @click.self="showDemandModal = false" class="modal-overlay">
        <div class="modal-content">
          <div class="modal-header">
            <h3>⚡ Raise Demand</h3>
            <button class="close-btn" type="button" @click="showDemandModal = false">✕</button>
          </div>
          <div class="modal-body">
            <p class="modal-desc">Demand will be submitted to Central Lab (Peshawar) for approval.</p>

            <div class="form-group full-width">
              <label>Item <span class="req">*</span></label>
              <select v-model="demandForm.itemId" class="form-select" :class="{ 'has-error': demandErrors.material_id }"
                @change="() => { const m = stockItems.find(i => i.id == demandForm.itemId); demandForm.unit = m?.unit || 'pcs'; demandForm.itemName = m?.name || '' }">
                <option value="">&mdash; Select Item &mdash;</option>
                <option v-for="item in stockItems" :key="item.id" :value="item.id">{{ item.name }} ({{ item.unit || 'pcs' }})</option>
              </select>
              <div v-if="demandErrors.material_id" class="field-error">{{ demandErrors.material_id[0] }}</div>
            </div>

            <div class="form-row">
              <div class="form-group half-width">
                <label>Quantity <span class="req">*</span></label>
                <input type="number" step="0.01" min="0" v-model="demandForm.qty" placeholder="e.g. 10" class="form-input" :class="{ 'has-error': demandErrors.quantity }" />
                <div v-if="demandErrors.quantity" class="field-error">{{ demandErrors.quantity[0] }}</div>
              </div>
              <div class="form-group half-width">
                <label>Urgency <span class="req">*</span></label>
                <select v-model="demandForm.urgency" class="form-select" :class="{ 'has-error': demandErrors.urgency }">
                  <option value="routine">Routine</option>
                  <option value="urgent">Urgent</option>
                </select>
                <div v-if="demandErrors.urgency" class="field-error">{{ demandErrors.urgency[0] }}</div>
              </div>
            </div>

            <div class="form-group full-width">
              <label>Justification</label>
              <textarea v-model="demandForm.justification" placeholder="e.g. Stock depleted, upcoming field campaign…" class="form-textarea" rows="3"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-pri" type="button" :disabled="demandSaving" @click="saveDemand">
              <span class="btn-icon">📤</span> {{ demandSaving ? 'Submitting…' : 'Submit Demand' }}
            </button>
            <button class="btn btn-sec outline" type="button" :disabled="demandSaving" @click="showDemandModal = false">Cancel</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Issue Confirmation Modal -->
    <Teleport to="body">
      <div v-if="issueModal.open" @click.self="issueModal.open = false" class="modal-overlay">
        <div class="modal-content" style="max-width:480px">
          <div class="modal-header">
            <h3>📦 Issue Stock</h3>
            <button class="close-btn" type="button" @click="issueModal.open = false">✕</button>
          </div>
          <div class="modal-body">
            <p class="modal-desc">This will deduct from your lab's stock and auto-credit the receiving lab. Expired batches are excluded from the Available total.</p>
            <div class="confirm-line"><b>Demand:</b> <span class="mono">{{ issueModal.demand?.slug }}</span></div>
            <div class="confirm-line"><b>Item:</b> {{ issueModal.demand?.item }}</div>
            <div class="confirm-line"><b>Requested:</b> {{ issueModal.demand?.qty }}</div>
            <div class="confirm-line" v-if="issueModal.demand?.centralAvailable != null">
              <b>Available at your lab:</b>
              <span :style="{ color: issueModal.demand.centralAvailable < issueModal.demand.detailQty ? '#b91c1c' : '#16a34a', fontWeight: 600 }">
                {{ issueModal.demand.centralAvailable }} {{ issueModal.demand.unit }}
              </span>
            </div>
            <div class="confirm-line"><b>Issue to:</b> {{ issueModal.demand?.from }}</div>
            <div style="margin-top:12px">
              <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Quantity to Issue <span style="color:#b91c1c">*</span></label>
              <input
                type="number"
                step="0.01"
                min="0"
                :max="Math.min(issueModal.demand?.detailQty || 0, issueModal.demand?.centralAvailable ?? issueModal.demand?.detailQty ?? 0)"
                v-model.number="issueModal.issueQty"
                @input="issueModal.error = ''"
                class="form-input"
                style="width:100%;padding:6px 10px;border:1px solid #d1d5db;border-radius:4px"
              />
              <div v-if="issueModal.error" style="color:#b91c1c;font-size:11.5px;margin-top:6px">⚠️ {{ issueModal.error }}</div>
              <div v-else-if="issueModal.demand?.centralAvailable != null && issueModal.demand.centralAvailable < issueModal.demand.detailQty"
                   style="color:#92400e;font-size:11.5px;margin-top:6px">
                Stock is short — partial fulfillment will be recorded.
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-pri" type="button" @click="confirmIssue">
              <span class="btn-icon">📦</span> Confirm Issue
            </button>
            <button class="btn btn-sec outline" type="button" @click="issueModal.open = false">Cancel</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Reject Reason Modal -->
    <Teleport to="body">
      <div v-if="rejectModal.open" @click.self="rejectModal.open = false" class="modal-overlay">
        <div class="modal-content" style="max-width:480px">
          <div class="modal-header">
            <h3>✗ Reject Demand</h3>
            <button class="close-btn" type="button" @click="rejectModal.open = false">✕</button>
          </div>
          <div class="modal-body">
            <p class="modal-desc">The field lab will be notified that this demand was rejected.</p>
            <div class="confirm-line"><b>Demand:</b> <span class="mono">{{ rejectModal.demand?.slug }}</span></div>
            <div class="confirm-line"><b>Item:</b> {{ rejectModal.demand?.item }} ({{ rejectModal.demand?.qty }})</div>
            <div class="form-group full-width" style="margin-top:14px">
              <label>Reason for rejection <span class="req">*</span></label>
              <textarea v-model="rejectModal.reason" placeholder="e.g. Out of stock; pending procurement"
                class="form-textarea" rows="3"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-red" type="button" :disabled="!rejectModal.reason?.trim()" @click="confirmReject">
              <span class="btn-icon">✗</span> Confirm Reject
            </button>
            <button class="btn btn-sec outline" type="button" @click="rejectModal.open = false">Cancel</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Toast -->
    <Teleport to="body">
      <transition name="toast-slide">
        <div v-if="toast.show" class="toast" :class="`toast-${toast.type}`">
          <span class="toast-msg">{{ toast.message }}</span>
          <button class="toast-close" type="button" @click="toast.show = false">✕</button>
        </div>
      </transition>
    </Teleport>
  </div>
</template>

<style scoped lang="scss">
.demand-page {
  padding: 18px 22px;
  background: #eef4fb;
  min-height: 100vh;
  font-family: 'Inter', sans-serif;
  color: #172235;
}

// Workflow steps
.wf-steps {
  display: flex;
  gap: 8px;
  margin-bottom: 18px;
  padding: 14px;
  background: #fff;
  border: 1px solid #c8d5e2;
  border-radius: 6px;
}
.wf-step {
  flex: 1;
  text-align: center;
  padding: 10px 8px;
  border-radius: 5px;
  background: #f1f5f9;
  color: #64748b;

  .wf-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    margin-bottom: 6px;
    border-radius: 50%;
    background: #cbd5e1;
    color: #fff;
    font-weight: 800;
    font-size: 13px;
  }
  .wf-t { font-size: 11px; font-weight: 700; letter-spacing: .05em; }
  .wf-d { font-size: 12px; margin-top: 2px; }
}
.wf-done { background: #dcfce7; color: #15803d; .wf-num { background: #16a34a; } }
.wf-active { background: #dbeafe; color: #1e40af; .wf-num { background: #2563eb; } }

.abar {
  padding: 10px 14px;
  margin-bottom: 14px;
  border-radius: 5px;
  font-size: 13px;
  font-weight: 600;

  &.amber { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
}

.sh {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;

  h2 { margin: 0; font-size: 18px; font-weight: 800; color: #1d365c; }
  .cnt { display: inline-block; margin-top: 4px; padding: 2px 10px; border-radius: 999px; background: #fef3c7; color: #92400e; font-size: 12px; font-weight: 700; }
}

.tbl-wrap {
  overflow: hidden;
  border: 1px solid #b8c7d4;
  border-radius: 6px;
  background: #fff;

  table { width: 100%; border-collapse: collapse; }

  th, td {
    padding: 8px 12px;
    border-bottom: 1px solid #d6dee7;
    font-size: 13px;
    text-align: left;
    vertical-align: middle;
  }

  th { background: #1f4168; color: #fff; font-size: 12px; font-weight: 800; }

  tr.alt td { background: #f6faff; }
  tr:last-child td { border-bottom: 0; }

  .mono { font-family: 'Courier New', ui-monospace, monospace; color: #1d365c; font-weight: 700; }
  .muted-sm { color: #64748b; font-size: 11px; margin-top: 2px; }
}

.rag {
  display: inline-flex;
  padding: 2px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;

  &.r-green { background: #c9f8d9; color: #1f7943; }
  &.r-amber { background: #ffe28b; color: #a4490d; }
  &.r-blue  { background: #dbeafe; color: #1e40af; }
  &.r-red   { background: #ffc4c4; color: #a51f25; }
  &.r-grey  { background: #e2e8f0; color: #475569; }
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  border: 0;
  border-radius: 4px;
  font-weight: 700;
  cursor: pointer;
  font-family: inherit;

  &:disabled { opacity: .5; cursor: not-allowed; }
}
.btn-sm { padding: 7px 14px; font-size: 13px; }
.btn-xs { padding: 4px 8px; font-size: 11px; margin-right: 4px; }
.btn-pri { background: #126fc8; color: #fff; &:hover:not(:disabled) { background: #105fad; } }
.btn-sec { background: #fff; color: #475569; border: 1px solid #cbd5e1; &:hover:not(:disabled) { background: #f1f5f9; } }
.btn-green { background: #16a34a; color: #fff; &:hover:not(:disabled) { background: #15803d; } }
.btn-red   { background: #dc2626; color: #fff; &:hover:not(:disabled) { background: #b91c1c; } }

.empty-state {
  padding: 28px;
  border: 1px solid #b8c7d4;
  border-radius: 6px;
  background: #fff;
  color: #5b6b82;
  text-align: center;

  &.error { border-color: #fca5a5; color: #991b1b; }
}

// Modal
.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 3300;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(15, 23, 42, .4);
  padding: 20px;
}
.modal-content {
  width: min(560px, calc(100vw - 32px));
  max-height: 90vh;
  overflow: hidden;
  background: #fff;
  border-radius: 6px;
  box-shadow: 0 20px 50px -10px rgba(0, 0, 0, .25);
  display: flex;
  flex-direction: column;
}
.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 22px 2px;

  h3 { margin: 0; font-size: 18px; font-weight: 800; color: #0f172a; }
}
.close-btn {
  width: 28px;
  height: 28px;
  border: 1px solid #e2e8f0;
  border-radius: 4px;
  background: #f1f5f9;
  color: #64748b;
  cursor: pointer;
  &:hover { background: #e2e8f0; }
}
.modal-body {
  flex: 1;
  overflow-y: auto;
  padding: 0 22px 16px;
}
.modal-desc {
  margin: 2px 0 16px;
  color: #64748b;
  font-size: 12px;
  font-style: italic;
}

.confirm-line {
  padding: 4px 0;
  font-size: 13px;
  color: #334155;

  b { color: #0f172a; margin-right: 6px; }
  .mono { font-family: 'Courier New', ui-monospace, monospace; color: #1d365c; }
}
.modal-footer {
  display: flex;
  gap: 10px;
  padding: 12px 22px 16px;
  border-top: 1px solid #e2e8f0;
}

.form-row { display: flex; gap: 14px; margin-bottom: 12px; }
.form-group {
  display: flex;
  flex-direction: column;
  gap: 5px;

  &.full-width { width: 100%; margin-bottom: 12px; }
  &.half-width { flex: 1; min-width: 0; }

  label { color: #1e293b; font-size: 13px; font-weight: 700; }
  .req { color: #dc2626; }

  .form-input, .form-select, .form-textarea {
    padding: 8px 11px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    outline: none;
    background: #fff;
    color: #334155;
    font-size: 13px;
    font-family: inherit;

    &:focus { border-color: #2c76be; box-shadow: 0 0 0 2px rgba(44, 118, 190, .12); }
    &.has-error { border-color: #dc2626; }
  }
  .form-textarea { resize: vertical; min-height: 64px; }
  .field-error { color: #dc2626; font-size: 12px; font-weight: 500; margin-top: 4px; }
}

.btn-icon { font-size: 13px; line-height: 1; }

// Toast
.toast {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 4000;
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

  &.toast-success { border-left: 4px solid #176b3a; color: #176b3a; }
  &.toast-error   { border-left: 4px solid #9d1b20; color: #9d1b20; }

  .toast-msg { flex: 1; }
  .toast-close {
    width: 22px; height: 22px; padding: 0;
    border: 0; border-radius: 4px;
    background: transparent; color: inherit;
    font-size: 14px; line-height: 1; cursor: pointer; opacity: .6;
    &:hover { opacity: 1; }
  }
}
.toast-slide-enter-active,
.toast-slide-leave-active { transition: transform .25s ease, opacity .25s ease; }
.toast-slide-enter-from,
.toast-slide-leave-to { transform: translateX(40px); opacity: 0; }

/* Skeleton loading rows — shared shimmer placeholder pattern. */
.sk-row { td { padding: 10px 11px; } }
.sk-bar {
  display: inline-block;
  height: 11px;
  width: 70px;
  border-radius: 3px;
  background: linear-gradient(90deg, #eef2f7 0%, #f7fafc 50%, #eef2f7 100%);
  background-size: 200% 100%;
  animation: skShimmer 1.2s ease-in-out infinite;
  vertical-align: middle;

  &.sk-xs { width: 18px; }
  &.sk-sm { width: 42px; }
  &.sk-md { width: 70px; }
  &.sk-lg { width: 140px; }
}
@keyframes skShimmer {
  0%   { background-position: 100% 0; }
  100% { background-position: -100% 0; }
}
</style>
