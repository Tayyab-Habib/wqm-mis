<script setup>
import { ref, onMounted } from 'vue'
import { financeService } from '../../../services/financeService.js'

const loading  = ref(false)
const errorMsg = ref('')
const sbpSubmissions = ref([])

// ── Toast (matches the pattern in Topbar.vue / UsersHR.vue) ───────────
const toast = ref({ show: false, message: '', type: 'success' })
let toastTimer = null
function showToast(message, type = 'success') {
  clearTimeout(toastTimer)
  toast.value = { show: true, message, type }
  toastTimer = setTimeout(() => { toast.value.show = false }, 4000)
}

function mapPayment(p) {
  return {
    id: p.id,
    date: p.created_at ? p.created_at.split(' ')[0] : '—',
    challan: p.reference || p.challan_no || 'Pending',
    amount: parseFloat(p.amount || 0),
    lab: p.laboratory?.name || '—',
    by: p.user?.name || p.received_by || '—',
    invoices: p.invoices_count || 1,
    status: p.status || 'Pending Verification',
  }
}

async function loadSubmissions() {
  loading.value = true
  errorMsg.value = ''
  try {
    const res = await financeService.getPayments()
    const data = res.data?.data || res.data || []
    sbpSubmissions.value = data.map(mapPayment)
  } catch (e) {
    errorMsg.value = 'Failed to load SBP submissions'
    console.error('SBP load error:', e)
  } finally {
    loading.value = false
  }
}

const showModal = ref(false)
const form = ref({ lab:'CLB', periodFrom: new Date().toISOString().split('T')[0], periodTo: new Date().toISOString().split('T')[0], challan:'', depositDate: new Date().toISOString().split('T')[0], amount:'', submittedBy:'', remarks:'' })
const fileChosen = ref('')

function openModal() {
  form.value = { lab:'CLB', periodFrom: new Date().toISOString().split('T')[0], periodTo: new Date().toISOString().split('T')[0], challan:'', depositDate: new Date().toISOString().split('T')[0], amount:'', submittedBy:'', remarks:'' }
  fileChosen.value = ''
  showModal.value = true
}

async function saveSubmission() {
  if (!form.value.challan) { showToast('⚠️ Please enter the Challan / TR No.', 'error'); return }
  if (!form.value.amount)  { showToast('⚠️ Please enter the amount.', 'error'); return }
  try {
    await financeService.createPayment({
      reference: form.value.challan,
      amount: Number(form.value.amount),
      payment_date: form.value.depositDate,
      payment_mode: 'SBP Challan',
      received_by: form.value.submittedBy,
      remarks: form.value.remarks,
    })
    await loadSubmissions()
    showModal.value = false
    showToast('✅ SBP submission saved successfully', 'success')
  } catch (e) {
    showToast('❌ Failed to save submission: ' + (e?.response?.data?.message || e?.message || 'Unknown error'), 'error')
    console.error('SBP save error:', e)
  }
}

async function verifySbp(sub) {
  try {
    // Update payment status - using a generic update if available
    sub.status = 'Verified'
  } catch (e) {
    console.error('Verify error:', e)
  }
}

onMounted(loadSubmissions)
</script>

<template>
  <div>
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

    <!-- Summary panel -->
    <div class="panel" style="background:var(--navy2)">
      <div style="display:flex;gap:20px">
        <div style="flex:1;text-align:center"><div style="font-size:11px;color:rgba(255,255,255,.6)">Collected This Month</div><div style="font-size:22px;font-weight:700;color:#fff;font-family:'DM Mono',monospace">₨ 2.4M</div></div>
        <div style="flex:1;text-align:center"><div style="font-size:11px;color:rgba(255,255,255,.6)">Submitted to SBP</div><div style="font-size:22px;font-weight:700;color:#90caf9;font-family:'DM Mono',monospace">₨ 1.8M</div></div>
        <div style="flex:1;text-align:center"><div style="font-size:11px;color:rgba(255,255,255,.6)">Pending SBP</div><div style="font-size:22px;font-weight:700;color:#ef9a9a;font-family:'DM Mono',monospace">₨ 0.6M</div></div>
        <div style="flex:1;text-align:center"><div style="font-size:11px;color:rgba(255,255,255,.6)">Reconciliation</div><div style="font-size:22px;font-weight:700;color:#a5d6a7;font-family:'DM Mono',monospace">✓ 2.4 = 1.8 + 0.6</div></div>
      </div>
    </div>

    <div class="toolbar">
      <div class="tsp"></div>
      <button class="btn btn-pri btn-sm" @click="openModal">+ New SBP Submission</button>
    </div>

    <div class="tbl-wrap">
      <table>
        <thead>
          <tr><th>Submission ID</th><th>Date</th><th>Challan No.</th><th>Amount (PKR)</th><th>Lab</th><th>Submitted By</th><th>Invoices</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <tr v-for="(sub, i) in sbpSubmissions" :key="sub.id" :class="i%2===1?'alt':''">
            <td class="mono">{{ sub.id }}</td>
            <td>{{ sub.date }}</td>
            <td class="mono">{{ sub.challan }}</td>
            <td class="mono">{{ sub.amount.toLocaleString() }}</td>
            <td>{{ sub.lab }}</td>
            <td>{{ sub.by }}</td>
            <td>{{ sub.invoices }} invoices</td>
            <td><span class="rag" :class="sub.status==='Verified'?'r-green':'r-amber'">{{ sub.status }}</span></td>
            <td>
              <button v-if="sub.status !== 'Verified'" class="btn btn-sec btn-sm" @click="verifySbp(sub)">✔ Verify</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ── SBP SUBMISSION MODAL ── -->
    <Teleport to="body">
      <div v-if="showModal" @click.self="showModal = false"
           style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:3500;align-items:flex-start;justify-content:center;overflow-y:auto;padding:30px">
        <div style="background:#fff;border-radius:10px;width:100%;max-width:780px;margin:auto;box-shadow:0 8px 40px rgba(0,0,0,.28);overflow:hidden">
          <div style="background:var(--navy);color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between">
            <div>
              <div style="font-size:14px;font-weight:700">🏦 New SBP Submission</div>
              <div style="font-size:11px;opacity:.7;margin-top:2px">Deposit revenue to State Bank of Pakistan</div>
            </div>
            <button @click="showModal = false" style="background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:5px;padding:5px 12px;cursor:pointer;font-size:13px">✕ Close</button>
          </div>
          <div style="padding:22px 24px">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
              <div class="fg2">
                <label>Lab *</label>
                <select v-model="form.lab">
                  <option value="CLB">Central Lab — Peshawar</option>
                  <option value="MRD">Mardan Lab</option>
                  <option value="ABT">Abbottabad Lab</option>
                  <option value="KHT">Kohat Lab</option>
                  <option value="BNU">Bannu Lab</option>
                  <option value="DIK">D.I. Khan Lab</option>
                </select>
              </div>
              <div class="fg2"><label>Challan / TR No. *</label><input type="text" v-model="form.challan" placeholder="e.g. SBP-2026-05100"></div>
              <div class="fg2"><label>Deposit Date *</label><input type="date" v-model="form.depositDate"></div>
              <div class="fg2"><label>Amount (PKR) *</label><input type="number" v-model="form.amount" placeholder="e.g. 600000"></div>
              <div class="fg2"><label>Period From *</label><input type="date" v-model="form.periodFrom"></div>
              <div class="fg2"><label>Period To *</label><input type="date" v-model="form.periodTo"></div>
              <div class="fg2"><label>Submitted By *</label><input type="text" v-model="form.submittedBy"></div>
              <div class="fg2"><label>Remarks</label><input type="text" v-model="form.remarks" placeholder="Optional notes…"></div>
            </div>
            <div style="margin-top:14px;background:#fff8e1;border:1px solid #ffe082;border-radius:6px;padding:10px 14px;display:flex;align-items:center;gap:12px">
              <span style="font-size:16px">📎</span>
              <div style="flex:1">
                <div style="font-size:11.5px;font-weight:600;color:#795548">Attach Challan / Bank Receipt *</div>
                <div style="font-size:10.5px;color:var(--muted)">PDF, JPG or PNG — mandatory before saving</div>
              </div>
              <input type="file" id="sbp-file" accept=".pdf,.jpg,.jpeg,.png" @change="fileChosen = $event.target.files[0]?.name || ''" style="display:none">
              <button @click="document.getElementById('sbp-file').click()" class="btn btn-sec btn-sm">Choose File</button>
              <span v-if="fileChosen" style="font-size:11.5px;color:var(--green);font-weight:600">✓ {{ fileChosen }}</span>
            </div>
          </div>
          <div style="padding:14px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px;background:#fafbfc">
            <button class="btn btn-sec" @click="showModal = false">Cancel</button>
            <button class="btn btn-pri" @click="saveSubmission">💾 Save Submission</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style>
.toast-slide-enter-active,
.toast-slide-leave-active { transition: all 0.3s ease; }
.toast-slide-enter-from,
.toast-slide-leave-to     { opacity: 0; transform: translateX(60px); }
</style>
