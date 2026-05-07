<script setup>
import { ref, computed, onMounted } from 'vue'
import { financeService } from '../../../services/financeService.js'

const loading  = ref(false)
const errorMsg = ref('')
const activeTab = ref(0)

// ── Data from backend ─────────────────────────────────────────────────
const invoicesList = ref([])

function mapInvoice(inv) {
  return {
    id: inv.id,
    slug: inv.slug || String(inv.id),
    client: inv.collectable?.name || inv.collectable?.organization_name || '—',
    lab: inv.laboratory?.name || '—',
    date: inv.created_at ? inv.created_at.split(' ')[0] : '—',
    samples: inv.water_sample_id ? 1 : 0,
    total: parseFloat(inv.price || 0),
    received: parseFloat(inv.paid || 0),
    balance: parseFloat(inv.balance || 0),
    status: inv.status || 'Unpaid',
    type: 'individual',
  }
}

async function loadInvoices() {
  loading.value = true
  errorMsg.value = ''
  try {
    const res = await financeService.getInvoices({})
    const data = res.data?.data || res.data || []
    invoicesList.value = data.map(mapInvoice)
  } catch (e) {
    errorMsg.value = 'Failed to load invoices'
    console.error('Invoices load error:', e)
  } finally {
    loading.value = false
  }
}

const totals = computed(() => ({
  invoiced:    invoicesList.value.reduce((s, i) => s + i.total, 0),
  collected:   invoicesList.value.reduce((s, i) => s + i.received, 0),
  outstanding: invoicesList.value.reduce((s, i) => s + i.balance, 0),
}))

// ── Payment modal ─────────────────────────────────────────────────────
const showPayModal = ref(false)
const payTarget    = ref(null)
const payAmount    = ref(0)
const payMode      = ref('')
const payRef       = ref('')
const payBy        = ref('')
const payDate      = ref(new Date().toISOString().split('T')[0])

function openPay(inv) {
  payTarget.value = inv
  payAmount.value = inv.balance
  payMode.value   = ''
  payRef.value    = ''
  showPayModal.value = true
}

async function savePayment() {
  if (!payMode.value) { alert('Please select a payment mode.'); return }
  if (!payAmount.value || payAmount.value <= 0) { alert('Please enter a valid amount.'); return }
  try {
    await financeService.createPayment({
      water_sample_invoice_id: payTarget.value.id,
      amount: Number(payAmount.value),
      payment_mode: payMode.value,
      reference: payRef.value,
      received_by: payBy.value,
      payment_date: payDate.value,
    })
    await loadInvoices()
    showPayModal.value = false
  } catch (e) {
    alert('Failed to record payment: ' + (e?.message || 'Unknown error'))
    console.error('Payment error:', e)
  }
}

// ── Clubbed invoice wizard ────────────────────────────────────────────
const showClubbedModal = ref(false)
const ciStep = ref(1)
const ciClient = ref('')
const ciSelectedInvs = ref([])
const ciTotal = computed(() => ciSelectedInvs.value.reduce((s, inv) => s + inv.total, 0))

const unpaidInvoices = computed(() => invoicesList.value.filter(i => i.balance > 0))

function openClubbed() { ciStep.value = 1; ciSelectedInvs.value = []; showClubbedModal.value = true }
function ciConfirm() {
  showClubbedModal.value = false
  loadInvoices()
}

// ── Ledger ────────────────────────────────────────────────────────────
const ledgerTypeFilter = ref('')
const ledger = ref([])

const filteredLedger = computed(() => {
  return ledger.value.filter(r => !ledgerTypeFilter.value || r.type === ledgerTypeFilter.value)
})
const ledgerRunning = computed(() => {
  let running = 0
  return filteredLedger.value.map(r => {
    running += (r.debit || 0) - (r.credit || 0)
    return { ...r, running }
  })
})

// ── Dues ─────────────────────────────────────────────────────────────
const duesSearch = ref('')
const dueInvoices = computed(() => invoicesList.value.filter(i => i.balance > 0 && (
  !duesSearch.value || i.client.toLowerCase().includes(duesSearch.value.toLowerCase()) || i.slug.toLowerCase().includes(duesSearch.value.toLowerCase())
)))

const typeColors = { Invoice:'#e0f0ff', Payment:'#e8f5e9', SBP:'#fef9c3' }

// Use invoicesList as the store.invoices equivalent
const store = { invoices: invoicesList, totals, ledger }

onMounted(loadInvoices)
</script>

<template>
  <div>
    <!-- KPI Cards -->
    <div class="cards cards-4" style="margin-bottom:14px">
      <div class="card c-gold"><div class="c-lbl">Total Invoiced (YTD)</div><div class="c-val">₨ {{ (totals.invoiced/1000000).toFixed(1) }}M</div></div>
      <div class="card c-green"><div class="c-lbl">Collected</div><div class="c-val">₨ {{ (totals.collected/1000000).toFixed(1) }}M</div></div>
      <div class="card c-red"><div class="c-lbl">Outstanding Dues</div><div class="c-val">₨ {{ (totals.outstanding/1000).toFixed(0) }}K</div></div>
      <div class="card c-amber"><div class="c-lbl">Pending SBP</div><div class="c-val">₨ —</div></div>
    </div>

    <!-- Tabs -->
    <div class="tabs" style="margin-bottom:0">
      <div v-for="(tab, i) in ['📄 Individual Invoices','📎 Clubbed Invoices','📊 Revenue Ledger','💸 Dues Register']" :key="i"
           class="tab" :class="{ active: activeTab === i }" @click="activeTab = i">{{ tab }}</div>
    </div>

    <!-- ── TAB 0: Individual Invoices ── -->
    <div v-if="activeTab === 0">
      <div class="toolbar">
        <div class="tsp"></div>
        <button class="btn btn-pri btn-sm" @click="openClubbed">+ Generate Clubbed Invoice</button>
        <button class="btn btn-sec btn-sm">⬇ Export</button>
      </div>
      <div class="tbl-wrap">
        <table>
          <thead>
            <tr>
              <th>Invoice ID</th><th>Client / WSS</th><th>Lab</th><th>Date</th>
              <th>Samples</th><th>Amount (PKR)</th><th>Received</th><th>Balance</th><th>Status</th><th>Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(inv, i) in invoicesList" :key="inv.id" :class="i % 2 === 1 ? 'alt' : ''">
              <td class="mono">{{ inv.slug || inv.id }}</td>
              <td>{{ inv.client }} <i v-if="inv.type==='clubbed'" style="font-size:10px;color:var(--blue)">[Clubbed]</i></td>
              <td>{{ inv.lab }}</td>
              <td>{{ inv.date }}</td>
              <td>{{ inv.samples }}</td>
              <td class="mono">{{ inv.total.toLocaleString() }}</td>
              <td class="mono">{{ inv.received.toLocaleString() }}</td>
              <td class="mono">{{ inv.balance.toLocaleString() }}</td>
              <td>
                <span class="rag" :class="inv.status==='Paid'?'r-green':inv.status==='Partial'?'r-amber':'r-red'">{{ inv.status }}</span>
              </td>
              <td>
                <button v-if="inv.balance > 0" class="btn btn-pri btn-xs" @click="openPay(inv)">💳 Record Payment</button>
                <button v-else class="btn btn-sec btn-xs">🖨 Print</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── TAB 1: Clubbed Invoices ── -->
    <div v-if="activeTab === 1">
      <div class="toolbar">
        <div class="tsp"></div>
        <button class="btn btn-pri btn-sm" @click="openClubbed">+ Generate Clubbed Invoice</button>
      </div>
      <div class="tbl-wrap">
        <table>
          <thead>
            <tr><th>Clubbed Invoice ID</th><th>Client</th><th>Lab</th><th>Date</th><th>Samples</th><th>Total (PKR)</th><th>Balance</th><th>Status</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <tr v-for="(inv, i) in invoicesList.filter(i => i.type === 'clubbed')" :key="inv.id" :class="i%2===1?'alt':''">
              <td class="mono">{{ inv.id }}</td>
              <td>{{ inv.client }}</td>
              <td>{{ inv.lab }}</td>
              <td>{{ inv.date }}</td>
              <td>{{ inv.samples }}</td>
              <td class="mono">{{ inv.total.toLocaleString() }}</td>
              <td class="mono">{{ inv.balance.toLocaleString() }}</td>
              <td><span class="rag" :class="inv.status==='Paid'?'r-green':inv.status==='Partial'?'r-amber':'r-red'">{{ inv.status }}</span></td>
              <td>
                <button v-if="inv.balance > 0" class="btn btn-pri btn-xs" @click="openPay(inv)">💳 Record Payment</button>
                <button v-else class="btn btn-sec btn-xs">🖨 Print</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── TAB 2: Revenue Ledger ── -->
    <div v-if="activeTab === 2">
      <div class="toolbar">
        <select v-model="ledgerTypeFilter" style="border:1px solid var(--input-border);border-radius:4px;padding:6px 9px;font-size:12px;font-family:inherit">
          <option value="">All Types</option>
          <option value="Invoice">Invoice Raised</option>
          <option value="Payment">Payment Received</option>
          <option value="SBP">SBP Deposit</option>
        </select>
        <div class="tsp"></div>
        <button class="btn btn-sec btn-sm">⬇ Export .xlsx</button>
      </div>
      <div class="tbl-wrap">
        <table style="font-size:11.5px">
          <thead>
            <tr>
              <th>Date</th><th>Transaction ID</th><th>Type</th><th>Client / Reference</th><th>Lab</th>
              <th style="text-align:right">Debit (₨)</th><th style="text-align:right">Credit (₨)</th><th style="text-align:right">Running Balance (₨)</th><th>Notes</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(r, i) in ledgerRunning" :key="r.txId" :style="{ background: typeColors[r.type] || '#fff' }">
              <td style="font-size:11px">{{ r.date }}</td>
              <td class="mono" style="font-size:11px">{{ r.txId }}</td>
              <td><span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:4px;background:rgba(0,0,0,.06)">{{ r.type }}</span></td>
              <td style="font-size:11.5px">{{ r.client }}</td>
              <td style="font-size:11px;color:var(--muted)">{{ r.lab }}</td>
              <td class="mono" style="text-align:right;color:var(--red)">{{ r.debit ? r.debit.toLocaleString() : '—' }}</td>
              <td class="mono" style="text-align:right;color:var(--green)">{{ r.credit ? r.credit.toLocaleString() : '—' }}</td>
              <td class="mono" style="text-align:right;font-weight:600">{{ Math.abs(r.running).toLocaleString() }} {{ r.running > 0 ? 'Dr' : 'Cr' }}</td>
              <td style="font-size:10.5px;color:var(--muted)">{{ r.note }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── TAB 3: Dues Register ── -->
    <div v-if="activeTab === 3">
      <div class="toolbar">
        <input type="text" v-model="duesSearch" placeholder="🔍 Client name, Invoice ID…">
        <div class="tsp"></div>
        <button class="btn btn-sec btn-sm">⬇ Export</button>
      </div>
      <div class="tbl-wrap">
        <table style="font-size:11.5px">
          <thead>
            <tr><th>Invoice ID</th><th>Client</th><th>Lab</th><th>Invoice Date</th><th style="text-align:right">Amount (PKR)</th><th style="text-align:right">Balance (PKR)</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <tr v-for="(inv, i) in dueInvoices" :key="inv.id" :class="i%2===1?'alt':''">
              <td class="mono">{{ inv.id }}</td>
              <td>{{ inv.client }}</td>
              <td>{{ inv.lab }}</td>
              <td>{{ inv.date }}</td>
              <td class="mono" style="text-align:right">{{ inv.total.toLocaleString() }}</td>
              <td class="mono" style="text-align:right">{{ inv.balance.toLocaleString() }}</td>
              <td>
                <button class="btn btn-pri btn-xs" @click="openPay(inv)">💳 Pay</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── PAYMENT MODAL ── -->
    <Teleport to="body">
      <div v-if="showPayModal" @click.self="showPayModal = false"
           style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:3600;align-items:flex-start;justify-content:center;overflow-y:auto;padding:30px">
        <div style="background:#fff;border-radius:10px;width:100%;max-width:560px;margin:auto;box-shadow:0 8px 40px rgba(0,0,0,.28);overflow:hidden">
          <div style="background:var(--navy);color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between">
            <div>
              <div style="font-size:14px;font-weight:700">💳 Record Payment</div>
              <div style="font-size:11px;opacity:.7;margin-top:2px">{{ payTarget?.id }} · {{ payTarget?.client }}</div>
            </div>
            <button @click="showPayModal = false" style="background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:5px;padding:5px 12px;cursor:pointer;font-size:13px">✕ Close</button>
          </div>
          <div style="padding:22px 24px">
            <!-- Summary -->
            <div style="background:#f0f4ff;border:1px solid #c7d7f5;border-radius:7px;padding:12px 16px;margin-bottom:20px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;text-align:center">
              <div><div style="font-size:10px;color:var(--muted)">Invoice Total</div><div class="mono" style="font-size:15px;font-weight:700;color:var(--navy)">₨ {{ payTarget?.total?.toLocaleString() }}</div></div>
              <div><div style="font-size:10px;color:var(--muted)">Already Received</div><div class="mono" style="font-size:15px;font-weight:700;color:var(--green)">₨ {{ payTarget?.received?.toLocaleString() }}</div></div>
              <div><div style="font-size:10px;color:var(--muted)">Outstanding Balance</div><div class="mono" style="font-size:15px;font-weight:700;color:var(--red)">₨ {{ payTarget?.balance?.toLocaleString() }}</div></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
              <div class="fg2"><label>Payment Date *</label><input type="date" v-model="payDate"></div>
              <div class="fg2">
                <label>Payment Mode *</label>
                <select v-model="payMode">
                  <option value="">— Select —</option>
                  <option>Cash</option><option>Bank Transfer</option><option>Cheque</option><option>SBP Challan</option><option>Online / EFT</option>
                </select>
              </div>
              <div class="fg2"><label>Amount Received (PKR) *</label><input type="number" v-model="payAmount" :max="payTarget?.balance"></div>
              <div class="fg2"><label>Reference / Cheque No.</label><input type="text" v-model="payRef" placeholder="e.g. CHQ-00412"></div>
              <div class="fg2"><label>Received By *</label><input type="text" v-model="payBy"></div>
            </div>
          </div>
          <div style="padding:14px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px;background:#fafbfc">
            <button class="btn btn-sec" @click="showPayModal = false">Cancel</button>
            <button class="btn btn-pri" @click="savePayment">💾 Save Payment</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── CLUBBED INVOICE WIZARD ── -->
    <Teleport to="body">
      <div v-if="showClubbedModal" @click.self="showClubbedModal = false"
           style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:3000;align-items:center;justify-content:center;overflow-y:auto;padding:20px">
        <div style="background:#fff;border-radius:10px;width:100%;max-width:820px;box-shadow:0 8px 40px rgba(0,0,0,.25);margin:auto;overflow:hidden">
          <div style="background:var(--navy);color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between">
            <div><div style="font-size:14px;font-weight:700">📎 Generate Clubbed Invoice</div></div>
            <button @click="showClubbedModal = false" style="background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:5px;padding:5px 10px;cursor:pointer;font-size:13px">✕ Close</button>
          </div>
          <!-- Step indicator -->
          <div style="display:flex;background:#f5f7fa;border-bottom:1px solid var(--border)">
            <div v-for="(s, i) in ['① Client & Period','② Select Samples','③ Preview & Confirm']" :key="i"
                 @click="ciStep = i+1"
                 style="flex:1;padding:10px;text-align:center;font-size:11.5px;cursor:pointer"
                 :style="ciStep === i+1 ? 'font-weight:700;color:var(--navy);border-bottom:3px solid var(--navy)' : 'color:var(--muted);border-bottom:3px solid transparent'">
              {{ s }}
            </div>
          </div>
          <div style="padding:20px">
            <!-- Step 1 -->
            <div v-if="ciStep === 1">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
                <div class="fg2"><label>Client Name *</label>
                  <select v-model="ciClient">
                    <option value="">— Select Client —</option>
                    <option>NESPAK Ltd.</option><option>Al-Noor Hospital</option><option>WAPDA Colony</option><option>Khan Brothers Pvt.</option>
                  </select>
                </div>
                <div class="fg2"><label>Lab</label><select><option>Peshawar (Central)</option><option>Mardan</option></select></div>
                <div class="fg2"><label>Period From *</label><input type="date" value="2026-03-01"></div>
                <div class="fg2"><label>Period To *</label><input type="date" value="2026-03-10"></div>
              </div>
              <div style="text-align:right"><button class="btn btn-pri" @click="ciStep = 2">Next: Select Samples →</button></div>
            </div>
            <!-- Step 2 -->
            <div v-if="ciStep === 2">
              <div style="font-size:12px;color:var(--muted);margin-bottom:10px">Showing unbilled samples for <b style="color:var(--navy)">{{ ciClient || 'selected client' }}</b></div>
              <div class="tbl-wrap">
                <table style="font-size:11.5px">
                  <thead><tr><th>☑</th><th>Invoice ID</th><th>Date</th><th>Samples</th><th style="text-align:right">Amount (PKR)</th></tr></thead>
                  <tbody>
                    <tr v-for="inv in unpaidInvoices" :key="inv.id">
                      <td><input type="checkbox" v-model="ciSelectedInvs" :value="inv"></td>
                      <td class="mono">{{ inv.id }}</td>
                      <td>{{ inv.date }}</td>
                      <td>{{ inv.samples }}</td>
                      <td class="mono" style="text-align:right">{{ inv.total.toLocaleString() }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;padding-top:10px;border-top:2px solid var(--border)">
                <div style="font-size:13px">Selected: <b>{{ ciSelectedInvs.length }}</b> invoices &nbsp;·&nbsp; Total: <b style="color:var(--navy)">PKR {{ ciTotal.toLocaleString() }}</b></div>
                <div style="display:flex;gap:8px">
                  <button class="btn btn-sec" @click="ciStep = 1">← Back</button>
                  <button class="btn btn-pri" @click="ciStep = 3">Next: Preview →</button>
                </div>
              </div>
            </div>
            <!-- Step 3 -->
            <div v-if="ciStep === 3">
              <div style="border:1px solid var(--border);border-radius:6px;padding:20px;font-size:12px;margin-bottom:14px">
                <div style="text-align:center;border-bottom:3px solid var(--navy);padding-bottom:12px;margin-bottom:14px">
                  <div style="font-size:13px;font-weight:800;color:var(--navy);text-transform:uppercase">Government of Khyber Pakhtunkhwa</div>
                  <div style="font-size:12px;font-weight:700;color:var(--navy2)">Public Health Engineering Department</div>
                  <div style="font-size:15px;font-weight:800;color:var(--navy);margin:4px 0">INVOICE</div>
                  <div style="font-size:12px;font-weight:700;color:var(--blue)">WATER QUALITY TESTING SERVICES</div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px">
                  <div><b>Client:</b> {{ ciClient }}</div>
                  <div><b>Date:</b> 13-Mar-2026</div>
                  <div><b>Invoices Bundled:</b> {{ ciSelectedInvs.length }}</div>
                  <div><b>Total Samples:</b> {{ ciSelectedInvs.reduce((s,i) => s+i.samples, 0) }}</div>
                </div>
                <div style="background:var(--navy);color:#fff;padding:9px 10px;font-weight:800;font-size:13px;display:flex;justify-content:space-between">
                  <span>TOTAL CHARGES</span>
                  <span>PKR {{ ciTotal.toLocaleString() }}</span>
                </div>
              </div>
              <div style="display:flex;justify-content:space-between">
                <button class="btn btn-sec" @click="ciStep = 2">← Back</button>
                <div style="display:flex;gap:8px">
                  <button class="btn btn-sec">🖨 Print PDF</button>
                  <button class="btn btn-pri" @click="ciConfirm">✅ Confirm &amp; Save Invoice</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
