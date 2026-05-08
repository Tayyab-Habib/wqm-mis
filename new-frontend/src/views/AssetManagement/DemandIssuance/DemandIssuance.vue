<script setup>
import { ref, computed, onMounted } from 'vue'
import { assetService } from '../../../services/assetService.js'

const loading  = ref(false)
const errorMsg = ref('')
const demands  = ref([])
const stockItems = ref([])

function mapDemand(inv) {
  const detail = inv.inventory_details?.[0] || inv.inventoryDetails?.[0] || {}
  const itemName = detail.inventoryable?.name || detail.material?.name || detail.asset?.name || '—'
  const unit = detail.unit || ''
  const qty = detail.quantity ? `${parseFloat(detail.quantity)} ${unit}`.trim() : '—'
  const daysPending = inv.created_at
    ? Math.floor((Date.now() - new Date(inv.created_at)) / (1000 * 60 * 60 * 24))
    : 0
  return {
    id: inv.id,
    slug: inv.slug || `INV-${inv.id}`,
    detailId: detail.id || null,
    from: inv.laboratory?.name || '—',
    item: itemName,
    qty,
    urgency: inv.urgency || 'Routine',
    daysPending,
    status: inv.status || 'Pending',
  }
}

async function loadData() {
  loading.value = true
  errorMsg.value = ''
  try {
    const [invRes, matRes] = await Promise.all([
      assetService.getInventories(),
      assetService.getMaterials(),
    ])
    // Handle paginated response: { data: { data: [...] } }
    const invData = invRes.data?.data?.data || invRes.data?.data || invRes.data || []
    const matData = matRes.data?.data || matRes.data || []
    demands.value    = Array.isArray(invData) ? invData.map(mapDemand) : []
    stockItems.value = Array.isArray(matData) ? matData : []
  } catch (e) {
    errorMsg.value = 'Failed to load demand data'
    console.error('Demand load error:', e)
  } finally {
    loading.value = false
  }
}

async function approveDemand(demand) {
  if (!demand.detailId) return
  try {
    await assetService.approveInventory(demand.detailId)
    demand.status = 'Approved'
  } catch (e) {
    alert('Failed to approve: ' + (e?.message || 'Unknown error'))
    console.error('Approve error:', e)
  }
}

const showDemandModal = ref(false)
const demandForm = ref({ itemId: '', itemName: '', unit: '', qty: '', urgency: '', reqDate: '', remarks: '' })

async function saveDemand() {
  if (!demandForm.value.itemId || !demandForm.value.qty || !demandForm.value.urgency) {
    alert('Please fill all required fields.'); return
  }
  try {
    await assetService.createInventory({
      details: [{
        inventoryable_type: 'material',
        inventoryable_id: demandForm.value.itemId,
        quantity: parseFloat(demandForm.value.qty).toFixed(2),
        unit: demandForm.value.unit || 'pcs',
      }],
    })
    await loadData()
    showDemandModal.value = false
    demandForm.value = { itemId: '', itemName: '', unit: '', qty: '', urgency: '', reqDate: '', remarks: '' }
  } catch (e) {
    alert('Failed to submit demand: ' + (e?.response?.data?.message || e?.message || 'Unknown error'))
    console.error('Demand save error:', e)
  }
}

const pendingCount = computed(() => demands.value.filter(d => d.status === 'Pending').length)
const overdueCount = computed(() => demands.value.filter(d => d.daysPending >= 3).length)

function urgencyClass(u) {
  return u === 'Urgent' || u === 'Critical' ? 'r-red' : 'r-grey'
}

onMounted(loadData)
</script>

<template>
  <div>
    <!-- Workflow steps -->
    <div class="wf-steps">
      <div class="wf-step wf-done"><div class="wf-num">✓</div><div class="wf-t">Step 1</div><div class="wf-d">Demand Raised</div></div>
      <div class="wf-step wf-active"><div class="wf-num">2</div><div class="wf-t">Step 2</div><div class="wf-d">Central Lab Review</div></div>
      <div class="wf-step"><div class="wf-num">3</div><div class="wf-t">Step 3</div><div class="wf-d">Stock Out</div></div>
      <div class="wf-step"><div class="wf-num">4</div><div class="wf-t">Step 4</div><div class="wf-d">Auto-Credit</div></div>
      <div class="wf-step"><div class="wf-num">5</div><div class="wf-t">Step 5</div><div class="wf-d">Status Trail</div></div>
    </div>

    <div class="abar amber">⚠ {{ overdueCount }} demands pending &gt; 3 working days — action required</div>

    <div class="sh" style="display:flex;align-items:center;justify-content:space-between">
      <div>
        <h2>Pending Demand Queue — Central Lab</h2>
        <span class="cnt">{{ pendingCount }} pending</span>
      </div>
      <button class="btn btn-pri btn-sm" @click="showDemandModal = true">+ Raise Demand</button>
    </div>

    <div class="tbl-wrap">
      <table>
        <thead>
          <tr><th>Demand ID</th><th>From Lab</th><th>Item</th><th>Qty</th><th>Urgency</th><th>Days Pending</th><th>Status</th><th>Action</th></tr>
        </thead>
        <tbody>
          <tr v-for="(d, i) in demands" :key="d.id" :class="i%2===1?'alt':''">
            <td class="mono">{{ d.id }}</td>
            <td>{{ d.from }}</td>
            <td>{{ d.item }}</td>
            <td>{{ d.qty }}</td>
            <td><span class="rag" :class="urgencyClass(d.urgency)">{{ d.urgency }}</span></td>
            <td :style="d.daysPending >= 3 ? 'color:var(--red);font-weight:700' : ''">
              {{ d.daysPending }} <span v-if="d.daysPending >= 3">⚠</span>
            </td>
            <td><span class="rag" :class="d.status==='Approved'?'r-green':'r-amber'">{{ d.status }}</span></td>
            <td>
              <template v-if="d.status === 'Pending'">
                <button class="btn btn-green btn-xs" @click="approveDemand(d)">✓ Approve</button>
                <button class="btn btn-sec btn-xs" style="margin-left:4px">✗ Reject</button>
              </template>
              <span v-else style="font-size:11px;color:var(--green)">✅ Approved</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ── RAISE DEMAND MODAL ── -->
    <Teleport to="body">
      <div v-if="showDemandModal" @click.self="showDemandModal = false"
           style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:3300;align-items:center;justify-content:center;overflow-y:auto;padding:20px">
        <div style="background:#fff;border-radius:8px;width:100%;max-width:540px;padding:24px 28px;position:relative;margin:auto">
          <button @click="showDemandModal = false" style="position:absolute;top:14px;right:16px;background:rgba(0,0,0,.07);border:none;border-radius:5px;padding:4px 10px;cursor:pointer;font-size:13px">✕</button>
          <h2 style="margin-bottom:2px">⚡ Raise Demand</h2>
          <div style="font-size:11.5px;color:var(--muted);margin-bottom:16px">Demand will be submitted to Central Lab (Peshawar) for approval.</div>
          <div class="form-grid c2">
            <div class="fg2 span2">
              <label>Item *</label>
              <select v-model="demandForm.itemId" @change="() => { const m = stockItems.find(i => i.id == demandForm.itemId); demandForm.unit = m?.unit || 'pcs'; demandForm.itemName = m?.name || '' }">
                <option value="">— Select Item —</option>
                <option v-for="item in stockItems" :key="item.id" :value="item.id">{{ item.name }} ({{ item.unit || 'pcs' }})</option>
              </select>
            </div>
            <div class="fg2"><label>Quantity *</label><input type="number" v-model="demandForm.qty" min="1" placeholder="e.g. 10"></div>
            <div class="fg2">
              <label>Urgency *</label>
              <select v-model="demandForm.urgency">
                <option value="">— Select —</option>
                <option>Routine</option><option>Urgent</option><option>Critical — Immediate</option>
              </select>
            </div>
            <div class="fg2"><label>Required By Date</label><input type="date" v-model="demandForm.reqDate"></div>
            <div class="fg2 span2"><label>Justification / Remarks</label><input type="text" v-model="demandForm.remarks" placeholder="e.g. Stock depleted, upcoming field campaign…"></div>
          </div>
          <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:18px">
            <button class="btn btn-sec" @click="showDemandModal = false">Cancel</button>
            <button class="btn btn-pri" @click="saveDemand">📤 Submit Demand</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
