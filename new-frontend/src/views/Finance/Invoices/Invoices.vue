<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import axios from 'axios'
import { financeService } from '../../../services/financeService.js'
import { dropdownService } from '../../../services/dropdownService.js'
import { useUserStore } from '../../../stores/useUserStore.js'

const userStore = useUserStore()

// ── Toast (matches the pattern in Topbar.vue / DiariesDispatches.vue) ─
const toast = ref({ show: false, message: '', type: 'success' })
let toastTimer = null
function showToast(message, type = 'success') {
  clearTimeout(toastTimer)
  toast.value = { show: true, message, type }
  toastTimer = setTimeout(() => { toast.value.show = false }, 4000)
}

// State
const activeTab = ref('individual')
const loading = ref(false)
const errorMsg = ref('')

// Data
const summary = ref({
  total_invoiced: 0,
  total_collected: 0,
  total_outstanding: 0
})
const invoices = ref([])
const ledger = ref([])
const dues = ref([])

// Modals
const showPaymentModal = ref(false)
const showClubbedModal = ref(false)
// Separate from `loading` (which drives the page-level skeleton). When the
// user clicks Save / Confirm we don't want to flash the underlying table's
// skeleton — only the modal's own button needs to indicate progress.
const clubbedSaving = ref(false)
const paymentSaving = ref(false)

// Clubbed Invoice Modal State
const clubbedStep = ref(1)
// Today's date for sensible default invoice-date / period bounds.
const _today = new Date().toISOString().slice(0, 10)
const _firstOfMonth = (() => {
  const d = new Date(); d.setDate(1); return d.toISOString().slice(0, 10)
})()

const clubbedForm = ref({
  clientId: '',        // populated when user picks from the dropdown
  clientType: '',      // polymorphic invoiceable_type (Client vs User)
  clientName: '',      // cached for the step-2 caption
  labId: '',
  periodFrom: _firstOfMonth,
  periodTo: _today,
  invoiceDate: _today,
})
// Populated by /finance/unbilled-by-client/{id} when transitioning to step 2.
const unbilledSamples = ref([])
const selectAllSamples = computed({
  get: () => unbilledSamples.value.length > 0 && unbilledSamples.value.every(s => s.selected),
  set: (val) => unbilledSamples.value.forEach(s => s.selected = val)
})
const selectedCount = computed(() => unbilledSamples.value.filter(s => s.selected).length)
const selectedTotal = computed(() => unbilledSamples.value.filter(s => s.selected).reduce((acc, s) => acc + s.fee, 0))

const clubbedInvoicePreviewData = computed(() => {
  const selected = unbilledSamples.value.filter(s => s.selected)
  const items = {}
  
  selected.forEach(s => {
    const cat = s.category || 'General'
    if (!items[cat]) {
      items[cat] = { count: 0, rate: s.fee, samples: [] }
    }
    items[cat].count++
    items[cat].samples.push(s.slug.split('/').pop()) // e.g. 0081
  })
  
  let formulaParts = []
  let total = 0
  Object.keys(items).forEach(cat => {
    formulaParts.push(`${items[cat].count} × ${formatNum(items[cat].rate)}`)
    total += items[cat].count * items[cat].rate
  })
  
  return {
    items,
    formula: formulaParts.join(' + ') + ` = PKR ${formatNum(total)}`,
    total
  }
})

async function nextClubbedStep() {
  // Step 1 → Step 2: load real unbilled samples for the chosen client.
  // Replaces the hardcoded NESPAK demo data that used to live in
  // unbilledSamples; the backend filters by client + date range and is
  // already AuthScope-scoped so a lab-incharge only sees their lab's rows.
  if (clubbedStep.value === 1) {
    if (!clubbedForm.value.clientId) {
      showToast('⚠️ Please pick a client first.', 'error')
      return
    }
    // Cache the friendly name so the step-2 caption can show it without
    // re-looking-up the dropdown row.
    const picked = clubbedClients.value.find(c => String(c.id) === String(clubbedForm.value.clientId))
    if (picked) {
      clubbedForm.value.clientName = picked.name
      clubbedForm.value.clientType = picked.type
    }
    try {
      const res = await financeService.getUnbilledByClient(clubbedForm.value.clientId, {
        client_type: clubbedForm.value.clientType || 'App\\Models\\Client',
        date_from:   clubbedForm.value.periodFrom,
        date_to:     clubbedForm.value.periodTo,
      })
      const rows = res?.data?.data || res?.data || []
      unbilledSamples.value = (Array.isArray(rows) ? rows : []).map(r => ({
        id:       r.id,
        slug:     r.slug,
        // WSS name (PHE samples) or water_sample_address (private samples);
        // fall back to the lab code rather than a useless dash if neither.
        wss:      r.wss || r.location || (r.lab_code ? `Lab ${r.lab_code}` : '—'),
        date:     r.date,
        test:     r.category || '—',
        status:   r.status || '—',         // invoice status (Pending/Partial/etc)
        fee:      Number(r.amount || 0),
        selected: r.selected !== false,
      }))
      if (!unbilledSamples.value.length) {
        showToast('⚠️ No unbilled samples for this client in the selected period.', 'error')
        return
      }
    } catch (e) {
      showToast('❌ Failed to load samples: ' + (e?.response?.data?.message || e.message), 'error')
      return
    }
    clubbedStep.value = 2
    return
  }

  // Step 2 → Step 3: just enforce the ≥2 selection rule.
  if (clubbedStep.value === 2 && selectedCount.value < 2) {
    showToast('⚠️ Please select at least 2 samples to generate a clubbed invoice.', 'error')
    return
  }
  if (clubbedStep.value < 3) clubbedStep.value++
}

function prevClubbedStep() { if (clubbedStep.value > 1) clubbedStep.value-- }
function printClubbedInvoice() {
  window.print();
}
async function saveClubbedInvoice() {
  // F-08 — submit the REAL client identifier (resolved by the wizard) and
  // the REAL period dates. Reads from clubbedForm.clientId/clientType
  // which Step 1's "Next" handler populates from the chosen dropdown row.
  // (Previous version read `clubbedForm.client.invoiceable_id` — a field
  //  we never set — so the check always failed at Step 3.)
  const selected = unbilledSamples.value.filter(s => s.selected)
  if (selected.length < 2) {
    showToast('⚠️ Please select at least 2 receipts to generate a clubbed invoice.', 'error')
    return
  }
  if (!clubbedForm.value.clientId || !clubbedForm.value.clientType) {
    showToast('⚠️ Please pick a client first.', 'error')
    return
  }

  // Use `clubbedSaving` (modal-local) instead of the global `loading` so the
  // background table doesn't flash its skeleton while the POST is in flight.
  // The skeleton is only meant for initial / tab-switch fetches.
  clubbedSaving.value = true;
  try {
    const res = await financeService.createClubbedInvoice({
      invoice_ids: selected.map(s => s.id),
      client_id:   clubbedForm.value.clientId,
      client_type: clubbedForm.value.clientType,
      period_from: clubbedForm.value.periodFrom || null,
      period_to:   clubbedForm.value.periodTo   || null,
      description: 'Clubbed Invoice generated from system'
    });
    const slug = res?.data?.clubbed_slug || res?.data?.data?.clubbed_slug || '(slug unavailable)'
    // Refresh the table FIRST so the new clubbed row is in place by the time
    // we close the modal — the user sees the modal close and the new row
    // appear simultaneously, instead of "saved" → modal closes → blank table
    // → row appears.
    await fetchData();
    showClubbedModal.value = false;
    showToast('✅ Clubbed Invoice generated: ' + slug, 'success');
  } catch (err) {
    console.error(err);
    // Surface the SRS validation messages from F-13/F-15 directly.
    const e = err.response?.data
    const msg = e?.errors
      ? Object.values(e.errors).flat().join('\n')
      : (e?.message || err.message)
    showToast('❌ Failed to generate clubbed invoice: ' + msg, 'error');
  } finally {
    clubbedSaving.value = false;
  }
}
// Payment Form
const paymentForm = ref({
  invoiceId: null,
  amount: '',
  payment_mode: '',
  payment_date: new Date().toISOString().split('T')[0],
  reference: '',
  received_by: '',
  remarks: ''
})
const selectedInvoice = ref(null)

const remainingBalance = computed(() => {
  if (!selectedInvoice.value) return 0
  const val = selectedInvoice.value.balance - (paymentForm.value.amount || 0)
  return val < 0 ? 0 : val
})

// Filtering & Search
const searchQuery = ref('')
const selectedStatus = ref('all')
const selectedDistrict = ref('All Districts')

// Dynamic dropdowns — populated from scoped backend endpoints so each role
// only sees the labs/districts it should:
//   /all-laboratories → AuthScope::labs (lab-incharge: own lab; CE/SE/XEN:
//      labs in their circle; SA/manager: all)
//   /all-districts    → AuthScope::districts (same idea via district scope)
//   /finance/clients-with-unbilled → clients that have at least one unbilled
//      invoice the current user can see (already AuthScope-scoped server-side).
// Old hardcoded ['All Districts','Peshawar','Mardan',...] and the two
// placeholder client options ('NESPAK Ltd.', 'WAPDA Colony') are gone —
// they were stale and visible to every role regardless of scope.
const districts      = ref(['All Districts'])
const labs           = ref([{ id: '', name: 'All Labs' }])
const clubbedClients = ref([])  // [{ id, type, name }]

async function loadFilterDropdowns() {
  try {
    const [districtsRes, labsRes, clientsRes] = await Promise.all([
      dropdownService.getDistricts(),
      dropdownService.getLaboratories(),
      financeService.getClientsWithUnbilled(),
    ])
    const distRows   = districtsRes?.data?.data || districtsRes?.data || []
    const labRows    = labsRes?.data?.data       || labsRes?.data       || []
    const clientRows = clientsRes?.data?.data    || clientsRes?.data    || []
    if (Array.isArray(distRows)) {
      districts.value = ['All Districts', ...distRows.map(d => d.name).filter(Boolean)]
    }
    if (Array.isArray(labRows)) {
      labs.value = [{ id: '', name: 'All Labs' }, ...labRows.map(l => ({ id: l.id, name: l.name }))]
    }
    if (Array.isArray(clientRows)) {
      // Backend returns { invoiceable_id, invoiceable_type, name, unbilled_count }
      // — invoiceable_type is the polymorphic class (App\Models\Client for
      // private clients, App\Models\User for PHE samples).
      clubbedClients.value = clientRows.map(c => ({
        id:           c.invoiceable_id ?? c.id,
        type:         c.invoiceable_type ?? c.type,
        name:         c.name || `Client #${c.invoiceable_id}`,
        unbilledCount: c.unbilled_count ?? 0,
      }))
    }
  } catch (e) {
    // Silent — keep defaults so the page still renders.
  }
}

// Ledger Filters
const ledgerFilterFrom = ref(new Date(new Date().getFullYear(), 0, 1).toISOString().split('T')[0])
const ledgerFilterTo = ref(new Date().toISOString().split('T')[0])

// Auto-fetch on ledger filter change. Date-range + lab + type are all
// client-side filters today (filteredLedger computed re-evaluates on any
// ref change) so technically no refetch is needed — but if a future filter
// goes server-side (e.g. lab_id) it'll just work. Debounced like LabSamples.
let ledgerFilterTimer = null
watch([ledgerFilterFrom, ledgerFilterTo], () => {
  if (loading.value) return
  clearTimeout(ledgerFilterTimer)
  ledgerFilterTimer = setTimeout(() => {
    if (activeTab.value === 'ledger') fetchData()
  }, 80)
})

// Reset all four ledger filters back to their default range + "All" values.
// Refetches immediately (bypassing the 80ms debounce) so the user sees
// instant feedback on Clear. Matches the LabSamples.clearFilters pattern.
function clearLedgerFilters() {
  ledgerFilterFrom.value = new Date(new Date().getFullYear(), 0, 1).toISOString().split('T')[0]
  ledgerFilterTo.value   = new Date().toISOString().split('T')[0]
  ledgerFilterLab.value  = 'All Labs'
  ledgerFilterType.value = 'All Types'
  if (activeTab.value === 'ledger') fetchData()
}

const formatDate = (dateStr) => {
  if (!dateStr || dateStr === '—') return '—'
  const date = new Date(dateStr)
  if (isNaN(date.getTime())) return dateStr
  return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: '2-digit' }).replace(/ /g, '-')
}
const ledgerFilterLab = ref('All Labs')
const ledgerFilterType = ref('All Types')

// Dues Filters
const duesFilterAgeing = ref('All')
const duesFilterLab = ref('All Labs')
const selectedDues = ref([])
const allDuesSelected = computed({
  get: () => filteredDues.value.length > 0 && selectedDues.value.length === filteredDues.value.length,
  set: (val) => {
    if (val) {
      selectedDues.value = filteredDues.value.map(d => d.id)
    } else {
      selectedDues.value = []
    }
  }
})

function sendReminder() {
  if (selectedDues.value.length === 0) {
    showToast('⚠️ Please select at least one invoice to send a reminder.', 'error');
    return;
  }
  showToast(`📧 Sending reminders for ${selectedDues.value.length} invoice(s)…`, 'success');
}

const filteredInvoices = computed(() => {
  let result = invoices.value
  
  if (activeTab.value === 'individual') {
    result = result.filter(inv => inv.type === 'individual')
  } else if (activeTab.value === 'clubbed') {
    result = result.filter(inv => inv.type === 'clubbed')
  }

  if (selectedStatus.value !== 'all') {
    result = result.filter(inv => inv.status.toLowerCase() === selectedStatus.value)
  }

  if (selectedDistrict.value !== 'All Districts') {
    result = result.filter(inv => inv.client.includes(selectedDistrict.value))
  }

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    result = result.filter(inv => 
      inv.slug.toLowerCase().includes(query) || 
      inv.client.toLowerCase().includes(query)
    )
  }

  return result
})

// ...

const filteredLedger = computed(() => {
  let result = [...ledger.value]
  
  // Apply Lab Filter
  if (ledgerFilterLab.value !== 'All Labs') {
    result = result.filter(tx => tx.lab === ledgerFilterLab.value)
  }
  
  // Apply Type Filter
  if (ledgerFilterType.value !== 'All Types') {
    if (ledgerFilterType.value === 'Invoice Raised') {
      result = result.filter(tx => tx.type.includes('Invoice'))
    } else if (ledgerFilterType.value === 'Payment Received') {
      result = result.filter(tx => tx.type.includes('Payment'))
    } else if (ledgerFilterType.value === 'SBP Deposit') {
      result = result.filter(tx => tx.type.includes('SBP'))
    }
  }

  // Apply Date Filter
  if (ledgerFilterFrom.value) {
    const fromDate = new Date(ledgerFilterFrom.value)
    result = result.filter(tx => new Date(tx.date) >= fromDate)
  }
  if (ledgerFilterTo.value) {
    const toDate = new Date(ledgerFilterTo.value)
    result = result.filter(tx => new Date(tx.date) <= toDate)
  }

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    result = result.filter(tx => 
      tx.txId.toLowerCase().includes(query) || 
      tx.client.toLowerCase().includes(query) ||
      tx.lab.toLowerCase().includes(query) ||
      (tx.note && tx.note.toLowerCase().includes(query))
    )
  }

  return result
})

const ledgerTotals = computed(() => {
  let debitSum = 0;
  let creditSum = 0;
  let invoiceCount = 0;
  let paymentCount = 0;

  filteredLedger.value.forEach(tx => {
    debitSum += tx.amountInvoiced || 0;
    creditSum += tx.amountReceived || 0;
    if (tx.type.includes('Invoice')) invoiceCount++;
    if (tx.type.includes('Payment') || tx.type.includes('SBP')) paymentCount++;
  })
  
  return {
    debitSum,
    creditSum,
    running: debitSum - creditSum,
    invoiceCount,
    paymentCount
  }
})

const duesWithAgeing = computed(() => {
  const now = new Date()
  return dues.value.map(d => {
    const dDate = new Date(d.date)
    const diffTime = Math.abs(now - dDate)
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
    
    return { ...d, ageingDays: isNaN(diffDays) ? 0 : diffDays, lastReminder: d.date }
  })
})

const filteredDues = computed(() => {
  let result = duesWithAgeing.value
  
  if (duesFilterLab.value !== 'All Labs') {
    result = result.filter(d => d.lab === duesFilterLab.value)
  }
  
  if (duesFilterAgeing.value !== 'All') {
    if (duesFilterAgeing.value === '0-30') {
      result = result.filter(d => d.ageingDays <= 30)
    } else if (duesFilterAgeing.value === '31-60') {
      result = result.filter(d => d.ageingDays > 30 && d.ageingDays <= 60)
    } else if (duesFilterAgeing.value === '61-90') {
      result = result.filter(d => d.ageingDays > 60 && d.ageingDays <= 90)
    } else if (duesFilterAgeing.value === '90+') {
      result = result.filter(d => d.ageingDays > 90)
    }
  }
  
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    result = result.filter(d => 
      d.slug.toLowerCase().includes(query) || 
      d.client.toLowerCase().includes(query)
    )
  }
  
  return result
})

const duesAgeingSummary = computed(() => {
  const buckets = {
    '0-30': { sum: 0, count: 0 },
    '31-60': { sum: 0, count: 0 },
    '61-90': { sum: 0, count: 0 },
    '90+': { sum: 0, count: 0 }
  }
  
  duesWithAgeing.value.forEach(d => {
    if (d.ageingDays <= 30) {
      buckets['0-30'].sum += d.balance
      buckets['0-30'].count++
    } else if (d.ageingDays <= 60) {
      buckets['31-60'].sum += d.balance
      buckets['31-60'].count++
    } else if (d.ageingDays <= 90) {
      buckets['61-90'].sum += d.balance
      buckets['61-90'].count++
    } else {
      buckets['90+'].sum += d.balance
      buckets['90+'].count++
    }
  })
  return buckets
})

const duesTotals = computed(() => {
  let totalSum = 0
  let balanceSum = 0
  filteredDues.value.forEach(d => {
    totalSum += d.total || 0
    balanceSum += d.balance || 0
  })
  return { totalSum, balanceSum }
})

// Formatting
const formatCompact = (val) => {
  if (val == null) return '0'
  if (val >= 1000000) return (val / 1000000).toFixed(1) + 'M'
  if (val >= 1000) return (val / 1000).toFixed(1) + 'K'
  return val.toString()
}

const formatNum = (val) => {
  if (val == null) return '0'
  return new Intl.NumberFormat('en-PK').format(val)
}


// API Calls
async function fetchData() {
  loading.value = true
  errorMsg.value = ''
  try {
    if (activeTab.value === 'individual' || activeTab.value === 'clubbed') {
      const res = await financeService.getFinanceInvoices()
      invoices.value = res.data?.invoices || []
      summary.value = res.data?.summary || summary.value
      
      // Also update unbilledSamples for the wizard
      if (res.data?.invoices) {
        unbilledSamples.value = res.data.invoices
          .filter(inv => !inv.is_clubbed && inv.status === 'Unpaid')
          .map(inv => ({
            id: inv.id,
            slug: inv.slug,
            date: inv.date,
            fee: inv.total,
            selected: false,
            category: inv.billing_summary?.items ? Object.keys(inv.billing_summary.items)[0] : 'General'
          }))
      }
    } else if (activeTab.value === 'ledger') {
      const res = await financeService.getFinanceLedger()
      ledger.value = res.data || []
    } else if (activeTab.value === 'dues') {
      const res = await financeService.getFinanceDues()
      dues.value = res.data || []
    }
  } catch (err) {
    console.error('Failed to fetch data:', err)
    errorMsg.value = 'Could not load finance data.'
  } finally {
    loading.value = false
  }
}

// Watchers
watch(activeTab, () => {
  searchQuery.value = ''
  selectedStatus.value = 'all'
  fetchData()
})

// Payment Workflow
function openPaymentModal(invoice) {
  selectedInvoice.value = invoice
  paymentForm.value = {
    invoiceId: invoice.id,
    amount: invoice.balance,
    payment_mode: '',
    payment_date: new Date().toISOString().split('T')[0],
    reference: '',
    received_by: '',
    remarks: ''
  }
  showPaymentModal.value = true
}

async function submitPayment() {
  // F-03 — send the full audit payload the backend now persists:
  // amount, payment_mode, payment_date, receipt_no, received_by, remarks.
  if (!paymentForm.value.amount || paymentForm.value.amount <= 0) {
    showToast('⚠️ Please enter a valid amount.', 'error')
    return
  }
  if (paymentForm.value.amount > selectedInvoice.value.balance) {
    showToast('⚠️ Amount cannot exceed outstanding balance.', 'error')
    return
  }
  const allowedModes = ['Cash', 'Cheque', 'Bank Transfer', 'Online']
  if (!allowedModes.includes(paymentForm.value.payment_mode)) {
    showToast('⚠️ Please select a valid payment mode: ' + allowedModes.join(', '), 'error')
    return
  }

  // Use the modal-local flag so the background table doesn't flash its
  // skeleton (same fix applied to saveClubbedInvoice). The skeleton is
  // reserved for genuine fetch/tab-switch loads.
  paymentSaving.value = true
  try {
    await financeService.recordPayment(paymentForm.value.invoiceId, {
      amount:       paymentForm.value.amount,
      payment_mode: paymentForm.value.payment_mode,
      payment_date: paymentForm.value.payment_date,
      receipt_no:   paymentForm.value.reference, // backend accepts both `receipt_no` and `reference`
      received_by:  paymentForm.value.received_by,
      remarks:      paymentForm.value.remarks,
    })
    // Refresh the underlying table FIRST so the row's updated balance is
    // visible the moment the modal closes and the success toast appears.
    await fetchData()
    showPaymentModal.value = false
    showToast(`✅ Payment of Rs ${paymentForm.value.amount} recorded successfully`, 'success')
  } catch (err) {
    console.error('Payment failed:', err)
    const e = err.response?.data
    const msg = e?.errors
      ? Object.values(e.errors).flat().join('\n')
      : (e?.message || 'Payment failed. Please try again.')
    showToast('❌ ' + msg, 'error')
  } finally {
    paymentSaving.value = false
  }
}

// Actions
function printInvoice(inv) {
  // In a real app, this would open the specific invoice PDF
  window.print()
}

async function exportData() {
  // F-18 — for the invoices tab use the real server-side xlsx export.
  // Ledger / Dues still fall through to the legacy CSV builder below
  // (those reports are not in the SRS xlsx scope).
  if (activeTab.value === 'individual' || activeTab.value === 'clubbed') {
    try {
      const blob = await financeService.exportRevenueXlsx({
        status: selectedStatus.value !== 'all' ? selectedStatus.value : undefined,
      })
      const url = window.URL.createObjectURL(new Blob([blob]))
      const link = document.createElement('a')
      const now = new Date()
      const mon = now.toLocaleString('en-GB', { month: 'short' })
      const yy  = String(now.getFullYear()).slice(-2)
      link.href = url
      link.download = `Finance_AllLabs_${mon}${yy}.xlsx`
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)
      showToast('✅ Export downloaded', 'success')
      return
    } catch (err) {
      console.error('xlsx export failed, falling back to CSV', err)
      showToast('⚠️ Server export failed — falling back to CSV', 'error')
    }
  }

  let csvContent = "data:text/csv;charset=utf-8,";
  let rows = [];

  if (activeTab.value === 'individual' || activeTab.value === 'clubbed') {
    rows.push(["Invoice ID", "Client", "Lab", "Date", "Samples", "Amount (PKR)", "Received", "Balance", "Status"]);
    filteredInvoices.value.forEach(inv => {
      rows.push([
        inv.slug,
        `"${inv.client}"`,
        `"${inv.lab}"`,
        inv.date,
        inv.samples || 1,
        inv.total,
        inv.received,
        inv.balance,
        inv.status
      ]);
    });
  } else if (activeTab.value === 'ledger') {
    rows.push(["Date", "Transaction ID", "Type", "Client", "Lab", "Debit (Rs)", "Credit (Rs)", "Running Balance (Rs)", "Notes"]);
    filteredLedger.value.forEach(tx => {
      rows.push([
        tx.date,
        tx.txId,
        tx.type,
        `"${tx.client}"`,
        `"${tx.lab}"`,
        tx.debit || 0,
        tx.credit || 0,
        tx.runningBalance,
        `"${tx.note || ''}"`
      ]);
    });
  } else if (activeTab.value === 'dues') {
    rows.push(["Invoice ID", "Client", "Lab", "Invoice Date", "Amount (PKR)", "Balance (PKR)", "Ageing Days", "Last Reminder"]);
    filteredDues.value.forEach(due => {
      rows.push([
        due.slug,
        `"${due.client}"`,
        `"${due.lab}"`,
        due.date,
        due.total,
        due.balance,
        due.ageingDays,
        due.lastReminder
      ]);
    });
  }

  rows.forEach(rowArray => {
    let row = rowArray.join(",");
    csvContent += row + "\r\n";
  });

  if (rows.length <= 1) {
    showToast('⚠️ Nothing to export — no rows for the current filters', 'error')
    return
  }

  const encodedUri = encodeURI(csvContent);
  const link = document.createElement("a");
  link.setAttribute("href", encodedUri);
  link.setAttribute("download", `finance_export_${activeTab.value}_${new Date().toISOString().split('T')[0]}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  showToast(`✅ Exported ${rows.length - 1} row(s) as CSV`, 'success')
}

onMounted(() => {
  fetchData()
  loadFilterDropdowns()
})
</script>

<template>
  <div class="invoices-page">

    <!-- Toast notification (matches project-wide pattern: Topbar / UsersHR /
         DiariesDispatches / LabSamples / RolesPermissions / SBPSubmissions).
         The 11 showToast() call sites in this file were firing silently
         until this render block landed. -->
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

    <!-- KPI Summary Cards -->
    <div class="kpi-cards print-hide">
      <div class="kpi-card top-orange">
        <div class="kpi-label">TOTAL INVOICED (YTD)</div>
        <div class="kpi-value text-orange"><span>₨</span> {{ formatCompact(summary.total_invoiced) }}</div>
      </div>
      <div class="kpi-card top-green">
        <div class="kpi-label">COLLECTED</div>
        <div class="kpi-value text-green"><span>₨</span> {{ formatCompact(summary.total_collected) }}</div>
      </div>
      <div class="kpi-card top-red">
        <div class="kpi-label">OUTSTANDING DUES</div>
        <div class="kpi-value text-red"><span>₨</span> {{ formatCompact(summary.total_outstanding) }}</div>
      </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="tabs-nav print-hide">
      <button class="tab-btn" :class="{ active: activeTab === 'individual' }" @click="activeTab = 'individual'">
        <span class="icon">📄</span> Individual Invoices
      </button>
      <button class="tab-btn" :class="{ active: activeTab === 'clubbed' }" @click="activeTab = 'clubbed'">
        <span class="icon">📎</span> Clubbed Invoices
      </button>
      <button class="tab-btn" :class="{ active: activeTab === 'ledger' }" @click="activeTab = 'ledger'">
        <span class="icon">📊</span> Revenue Ledger
      </button>
      <button class="tab-btn" :class="{ active: activeTab === 'dues' }" @click="activeTab = 'dues'">
        <span class="icon">💸</span> Dues Register
      </button>
    </div>

    <!-- Toolbar Filters -->
    <div v-if="activeTab !== 'ledger'" class="filters-toolbar print-hide">
      <div class="left-filters">
        <div class="search-box">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="search-icon"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
          <input type="text" v-model="searchQuery" placeholder="Client name, Invoice ID..." />
        </div>
        
        <select v-if="activeTab === 'individual' || activeTab === 'clubbed'" v-model="selectedStatus" class="status-select">
          <option value="all">All Status</option>
          <option value="paid">Paid</option>
          <option value="partial">Partial</option>
          <option value="unpaid">Unpaid</option>
        </select>

        <select v-if="activeTab === 'individual' || activeTab === 'clubbed'" v-model="selectedDistrict" class="status-select">
          <option v-for="d in districts" :key="d" :value="d">{{ d }}</option>
        </select>

        <select v-if="activeTab === 'dues'" v-model="duesFilterAgeing" class="status-select">
          <option value="All">All Days</option>
          <option value="0-30">0-30 days</option>
          <option value="31-60">31-60 days</option>
          <option value="61-90">61-90 days</option>
          <option value="90+">90+ days</option>
        </select>
        
        <select v-if="activeTab === 'dues'" v-model="duesFilterLab" class="status-select">
          <option v-for="l in labs" :key="l.id || 'all'" :value="l.name">{{ l.name }}</option>
        </select>
      </div>

      <div class="right-actions d-flex gap-2">
        <button v-if="activeTab === 'clubbed'" class="btn btn-blue" @click="showClubbedModal = true">+ Generate Clubbed Invoice</button>
        <button v-if="activeTab === 'dues'" class="btn btn-outline d-inline-flex align-items-center gap-2" @click="sendReminder"><span style="font-size: 16px;">✉</span> Send Reminder</button>
        <button class="btn btn-outline" @click="exportData">⬇ Export</button>
      </div>
    </div>

    <!-- Dues Summary Boxes -->
    <div v-if="activeTab === 'dues'" class="dues-summary-boxes d-flex gap-3 mb-4 print-hide">
      <div class="dues-box box-0-30">
        <div class="box-title">0–30 Days</div>
        <div class="box-val text-green">Rs {{ formatNum(duesAgeingSummary['0-30'].sum) }}</div>
        <div class="box-sub">{{ duesAgeingSummary['0-30'].count }} invoices</div>
      </div>
      <div class="dues-box box-31-60">
        <div class="box-title">31–60 Days</div>
        <div class="box-val text-orange">Rs {{ formatNum(duesAgeingSummary['31-60'].sum) }}</div>
        <div class="box-sub">{{ duesAgeingSummary['31-60'].count }} invoices</div>
      </div>
      <div class="dues-box box-61-90">
        <div class="box-title">61–90 Days</div>
        <div class="box-val text-red">Rs {{ formatNum(duesAgeingSummary['61-90'].sum) }}</div>
        <div class="box-sub">{{ duesAgeingSummary['61-90'].count }} invoices</div>
      </div>
      <div class="dues-box box-90-plus">
        <div class="box-title">90+ Days ⚠</div>
        <div class="box-val text-light-red">Rs {{ formatNum(duesAgeingSummary['90+'].sum) }}</div>
        <div class="box-sub">{{ duesAgeingSummary['90+'].count }} invoice{{ duesAgeingSummary['90+'].count !== 1 ? 's' : '' }}</div>
      </div>
    </div>

    <!-- Ledger Specific Filters & Boxes -->
    <div v-if="activeTab === 'ledger'" class="ledger-specific-controls print-hide">
      <div class="ledger-filters d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex align-items-center gap-2" style="font-size: 13px;">
          <span>From</span> <input type="date" v-model="ledgerFilterFrom" class="lf-input" style="width:140px" />
          <span>To</span> <input type="date" v-model="ledgerFilterTo" class="lf-input" style="width:140px" />
          <select v-model="ledgerFilterLab" class="lf-input" style="width:150px">
            <option v-for="l in labs" :key="l.id || 'all'" :value="l.name">{{ l.name }}</option>
          </select>
          <select v-model="ledgerFilterType" class="lf-input" style="width:160px">
            <option>All Types</option>
            <option>Invoice Raised</option>
            <option>Payment Received</option>
          </select>
        </div>
        <div class="d-flex gap-2">
          <!-- Apply button removed — filters auto-fetch on change via a
               debounced watcher (same pattern as LabSamples + GAR). -->
          <button class="lf-btn-clear" @click="clearLedgerFilters">✕ Clear Filters</button>
          <button class="btn btn-outline" style="padding: 6px 14px; border-radius: 6px;" @click="exportData">⬇ Export .xlsx</button>
          <button class="btn btn-outline" style="padding: 6px 14px; border-radius: 6px;" @click="printInvoice()">🖨 Print</button>
        </div>
      </div>

      <div class="ledger-summary-boxes d-flex gap-3 mb-4">
        <div class="ledger-box box-blue" style="flex:1;">
          <div class="box-title">Opening Balance</div>
          <div class="box-val text-blue">Rs 0</div>
          <div class="box-sub">{{ formatDate(ledgerFilterFrom) }}</div>
        </div>
        <div class="ledger-box box-gray" style="flex:2;">
          <div class="box-title">Total Invoiced</div>
          <div class="box-val text-dark">Rs {{ formatNum(ledgerTotals.debitSum) }}</div>
          <div class="box-sub">{{ ledgerTotals.invoiceCount }} invoices</div>
        </div>
        <div class="ledger-box box-green" style="flex:2;">
          <div class="box-title">Total Collected</div>
          <div class="box-val text-green">Rs {{ formatNum(ledgerTotals.creditSum) }}</div>
          <div class="box-sub">{{ ledgerTotals.paymentCount }} payments</div>
        </div>
        <div class="ledger-box box-orange" style="flex:1;">
          <div class="box-title">Closing Balance (Dues)</div>
          <div class="box-val" :class="ledgerTotals.running > 0 ? 'text-red' : 'text-green'">Rs {{ formatNum(Math.abs(ledgerTotals.running)) }}</div>
          <div class="box-sub">{{ formatDate(ledgerFilterTo) }}</div>
        </div>
      </div>
    </div>

    <!-- Error state -->
    <div v-if="errorMsg" class="abar red print-hide">⚠ {{ errorMsg }}</div>

    <!-- Skeleton: header visible, 6 shimmer rows while loading. Matches
         the pattern in DiariesDispatches / RolesPermissions. -->
    <div v-if="loading" class="data-table-container print-hide">
      <table class="data-table">
        <thead>
          <tr>
            <th v-for="n in 10" :key="'skh-' + n"><div class="fin-skel" style="width:70%;height:10px"></div></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in 6" :key="'sk-' + r">
            <td v-for="c in 10" :key="'sk-' + r + '-' + c"><div class="fin-skel" style="height:12px"></div></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Main Table -->
    <div v-if="!loading" class="data-table-container">
      <table class="data-table">

        <!-- Headers based on active tab -->
        <thead v-if="activeTab === 'individual' || activeTab === 'clubbed'">
          <tr>
            <th>Invoice ID</th>
            <th>Client / WSS</th>
            <th>Lab</th>
            <th>Date</th>
            <th>Samples</th>
            <th>Amount (PKR)</th>
            <th>Received</th>
            <th>Balance</th>
            <th>Status</th>
            <th class="print-hide">Action</th>
          </tr>
        </thead>
        <thead v-else-if="activeTab === 'ledger'">
          <tr class="ledger-header-row">
            <th>Date</th>
            <th>Receipt #</th>
            <th>Type</th>
            <th>Client Name</th>
            <th>Laboratory</th>
            <th class="text-right">Invoiced</th>
            <th class="text-right">Received</th>
            <th class="text-right">Balance Due</th>
            <th>Mode</th>
            <th>Recorded By</th>
            <th>Notes</th>
          </tr>
        </thead>
        <thead v-else-if="activeTab === 'dues'">
          <tr class="ledger-header-row">
            <th style="width: 40px; text-align: center;"><input type="checkbox" v-model="allDuesSelected" /></th>
            <th>Invoice ID</th>
            <th>Client</th>
            <th>Lab</th>
            <th>Invoice Date</th>
            <th class="text-right">Amount (PKR)</th>
            <th class="text-right">Balance (PKR)</th>
            <th class="text-center">Ageing</th>
            <th>Last Reminder</th>
            <th class="text-center print-hide">Actions</th>
          </tr>
        </thead>

        <tbody>
          <!-- Individual & Clubbed Invoices -->
          <template v-if="activeTab === 'individual' || activeTab === 'clubbed'">
            <tr v-if="filteredInvoices.length === 0">
              <td colspan="10" class="text-center text-muted empty-state">No invoices found.</td>
            </tr>
            <tr v-for="(inv, i) in filteredInvoices" :key="inv.id" :class="i % 2 === 1 ? 'alt-row' : ''">
              <td class="mono fw-600 text-dark">{{ inv.slug }}</td>
              <td>
                {{ inv.client }}
                <span v-if="inv.type === 'clubbed'" style="color: #3b82f6; font-style: italic; font-size: 11px; margin-left: 4px;">[Clubbed]</span>
              </td>
              <td>{{ inv.lab }}</td>
              <td>{{ formatDate(inv.date) }}</td>
              <td>{{ inv.samples || 1 }}</td>
              <td class="fw-500">{{ formatNum(inv.total) }}</td>
              <td class="fw-500">{{ formatNum(inv.received) }}</td>
              <td class="fw-500">{{ formatNum(inv.balance) }}</td>
              <td>
                <span class="status-badge" :class="inv.status.toLowerCase()">{{ inv.status === 'Pending' ? 'Unpaid' : inv.status }}</span>
              </td>
              <td class="print-hide action-cell">
                <button v-if="inv.balance <= 0" class="btn-action btn-print" @click="printInvoice(inv)">🖨 Print</button>
                <button v-else class="btn-action btn-record" @click="openPaymentModal(inv)">💳 Record Payment</button>
              </td>
            </tr>
          </template>

          <template v-else-if="activeTab === 'ledger'">
            <tr v-if="filteredLedger.length === 0">
              <td colspan="11" class="text-center text-muted empty-state">No transactions found.</td>
            </tr>
            <tr v-for="(tx, i) in filteredLedger" :key="tx.txId + i" :class="[i % 2 === 1 ? 'alt-row' : '']">
              <td>{{ formatDate(tx.date) }}</td>
              <td class="mono fw-600 text-dark">{{ tx.txId }}</td>
              <td><span class="status-badge" :class="tx.type.toLowerCase().replace(' ', '-')">{{ tx.type }}</span></td>
              <td>{{ tx.client }}</td>
              <td>{{ tx.lab }}</td>
              <td class="fw-600 text-dark text-right">{{ tx.amountInvoiced ? formatNum(tx.amountInvoiced) : '—' }}</td>
              <td class="fw-600 text-green text-right">{{ tx.amountReceived ? formatNum(tx.amountReceived) : '—' }}</td>
              <td class="fw-600 text-right" :class="tx.balanceDue > 0 ? 'text-red' : 'text-green'">{{ formatNum(tx.balanceDue) }}</td>
              <td><span style="font-size: 11px;">{{ tx.paymentMode }}</span></td>
              <td><span style="font-size: 11px;">{{ tx.recordedBy }}</span></td>
              <td style="font-size: 11px; color: #64748b; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ tx.note }}</td>
            </tr>
            <tr v-if="filteredLedger.length > 0" class="ledger-totals-row">
              <td colspan="5">TOTALS (Filtered View)</td>
              <td class="text-right">₨ {{ formatNum(ledgerTotals.debitSum) }}</td>
              <td class="text-right">₨ {{ formatNum(ledgerTotals.creditSum) }}</td>
              <td class="text-right">₨ {{ formatNum(ledgerTotals.running) }}</td>
              <td colspan="3"></td>
            </tr>
          </template>
          
          <!-- Dues Register -->
          <template v-else-if="activeTab === 'dues'">
            <tr v-if="filteredDues.length === 0">
              <td colspan="10" class="text-center text-muted empty-state">No outstanding dues found.</td>
            </tr>
            <tr v-for="(due, i) in filteredDues" :key="due.id" :class="i % 2 === 1 ? 'alt-row' : ''">
              <td class="text-center"><input type="checkbox" :value="due.id" v-model="selectedDues" /></td>
              <td class="mono fw-600 text-dark">{{ due.slug }}</td>
              <td>{{ due.client }}</td>
              <td>{{ due.lab }}</td>
               <td>{{ formatDate(due.date) }}</td>
              <td class="fw-500 text-right">{{ formatNum(due.total) }}</td>
              <td class="fw-600 text-dark text-right">{{ formatNum(due.balance) }}</td>
              <td class="text-center">
                <span class="ageing-badge" :class="due.ageingDays > 60 ? (due.ageingDays > 90 ? 'danger' : 'warning') : 'success'">{{ due.ageingDays }} days</span>
              </td>
               <td style="font-size:12px; color:#64748b;">{{ formatDate(due.lastReminder) }}</td>
              <td class="print-hide action-cell text-center" style="white-space: nowrap;">
                 <button class="btn btn-blue d-inline-flex align-items-center" style="padding:4px 8px; font-size:12px; border-radius:4px; margin-right:4px;" @click="openPaymentModal(due)">💳 Pay</button>
                 <button class="btn btn-outline d-inline-flex align-items-center justify-content-center" style="padding:4px 8px; border-radius:4px;" @click="sendReminder()"><span style="font-size:14px;">✉</span></button>
              </td>
            </tr>
            <tr v-if="filteredDues.length > 0" class="ledger-totals-row">
              <td colspan="5">TOTAL OUTSTANDING</td>
              <td class="text-right">₨ {{ formatNum(duesTotals.totalSum) }}</td>
              <td class="text-right">₨ {{ formatNum(duesTotals.balanceSum) }}</td>
              <td colspan="3"></td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- Payment Modal -->
    <Teleport to="body">
      <div v-if="showPaymentModal" class="modal-overlay open" @click.self="showPaymentModal = false">
        <div class="modal payment-modal">
          <div class="modal-header">
            <div class="header-top">
              <h2 class="d-flex align-items-center gap-2">💳 Record Payment</h2>
              <button class="btn-close-modal" @click="showPaymentModal = false">✕ Close</button>
            </div>
            <div class="modal-subtitle">{{ selectedInvoice?.slug }} · {{ selectedInvoice?.client }}</div>
          </div>
          <div class="modal-body">
            <div class="payment-info-box">
              <div class="info-col">
                <span class="info-label">Invoice Total</span>
                <strong class="info-value text-blue">Rs {{ formatNum(selectedInvoice?.total) }}</strong>
              </div>
              <div class="info-col">
                <span class="info-label">Already Received</span>
                <strong class="info-value text-green">Rs {{ formatNum(selectedInvoice?.received) }}</strong>
              </div>
              <div class="info-col">
                <span class="info-label">Outstanding Balance</span>
                <strong class="info-value text-red">Rs {{ formatNum(selectedInvoice?.balance) }}</strong>
              </div>
            </div>

            <div class="form-grid c2 mt-4">
              <div class="fg2">
                <label>Payment Date *</label>
                <input type="date" v-model="paymentForm.payment_date" class="form-input" />
              </div>
              <div class="fg2">
                <label>Payment Mode *</label>
                <select v-model="paymentForm.payment_mode" class="form-input">
                  <option value="" disabled>— Select —</option>
                  <option value="Cash">Cash</option>
                  <option value="Cheque">Cheque</option>
                  <option value="Bank Transfer">Bank Transfer</option>
                  <option value="SBP Challan">SBP Challan</option>
                </select>
              </div>
              <div class="fg2">
                <label>Amount Received (PKR) *</label>
                <input type="number" v-model.number="paymentForm.amount" :max="selectedInvoice?.balance" class="form-input" />
              </div>
              <div class="fg2">
                <label>Reference / Cheque No.</label>
                <input type="text" v-model="paymentForm.reference" placeholder="e.g. CHQ-00412" class="form-input" />
              </div>
            </div>

            <div class="remaining-box mt-3" :class="remainingBalance <= 0 ? 'bg-green-light' : 'bg-gray-light'">
               <span class="remaining-label">Remaining after this payment:</span>
               <div class="d-flex align-items-center gap-2">
                 <strong class="remaining-value text-green">Rs {{ formatNum(remainingBalance) }}</strong>
                 <span v-if="remainingBalance <= 0" class="badge-paid">→ Paid ✔</span>
               </div>
            </div>

            <div class="form-grid c1 mt-4">
               <div class="fg1">
                 <label>Received By *</label>
                 <input type="text" v-model="paymentForm.received_by" placeholder="Name" class="form-input" />
               </div>
               <div class="fg1 mt-3">
                 <label>Remarks</label>
                 <textarea v-model="paymentForm.remarks" placeholder="Optional notes..." class="form-input" rows="3"></textarea>
               </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-outline" @click="showPaymentModal = false">Cancel</button>
            <button v-write="'add_payments'" class="btn btn-blue" @click="submitPayment" :disabled="paymentSaving">
               💾 {{ paymentSaving ? '⏳ Saving…' : 'Save Payment' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>


    <!-- Clubbed Invoice Wizard Modal -->
    <Teleport to="body">
       <div v-if="showClubbedModal" class="modal-overlay open" @click.self="showClubbedModal = false">
        <div class="modal clubbed-modal" :class="{'step3-modal': clubbedStep === 3}">
          
          <div class="modal-header">
            <div class="header-top d-flex justify-content-between align-items-center">
              <div>
                <h2 class="d-flex align-items-center gap-2 mb-0" style="color: white; margin-bottom: 0;">📎 Generate Clubbed Invoice</h2>
                <div class="modal-subtitle">Select a client and samples to bundle into one invoice</div>
              </div>
              <button class="btn-close-modal" @click="showClubbedModal = false">✕ Close</button>
            </div>
          </div>

          <!-- Stepper -->
          <div class="stepper">
            <div class="step" :class="{ active: clubbedStep === 1 }">① Client & Period</div>
            <div class="step" :class="{ active: clubbedStep === 2 }">② Select Samples</div>
            <div class="step" :class="{ active: clubbedStep === 3 }">③ Preview & Confirm</div>
          </div>

          <div class="modal-body p-4" style="background: #fff;">
            
            <!-- Step 1 -->
            <div v-if="clubbedStep === 1" class="step-content">
              <div class="form-grid c2 mt-2">
                <div class="fg2">
                  <label>Client Name *</label>
                  <select v-model="clubbedForm.clientId" class="form-input">
                    <option value="" disabled>— Select Client —</option>
                    <option v-if="!clubbedClients.length" value="" disabled>
                      No clients with unbilled samples in your scope
                    </option>
                    <option v-for="c in clubbedClients" :key="c.type + ':' + c.id" :value="c.id">
                      {{ c.name }}
                    </option>
                  </select>
                </div>
                <div class="fg2">
                  <label>Lab</label>
                  <select v-model="clubbedForm.labId" class="form-input">
                    <option v-for="l in labs.filter(x => x.id)" :key="l.id" :value="l.name">{{ l.name }}</option>
                  </select>
                </div>
                <div class="fg2">
                  <label>Period From *</label>
                  <input type="date" v-model="clubbedForm.periodFrom" class="form-input" />
                </div>
                <div class="fg2">
                  <label>Period To *</label>
                  <input type="date" v-model="clubbedForm.periodTo" class="form-input" />
                </div>
                <div class="fg2">
                  <label>Invoice Date</label>
                  <input type="date" v-model="clubbedForm.invoiceDate" class="form-input" />
                </div>
              </div>
            </div>

            <!-- Step 2 -->
            <div v-if="clubbedStep === 2" class="step-content">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="text-muted" style="font-size: 14px;">
                  Showing unbilled samples for
                  <strong class="text-dark" style="color: #0f172a;">{{ clubbedForm.clientName || '—' }}</strong>
                  <span v-if="clubbedForm.periodFrom && clubbedForm.periodTo"> · {{ clubbedForm.periodFrom }} → {{ clubbedForm.periodTo }}</span>
                </div>
                <label class="d-flex align-items-center gap-2" style="font-size: 14px; cursor: pointer; color: #0f172a; font-weight: 500;">
                  <input type="checkbox" v-model="selectAllSamples" /> Select All
                </label>
              </div>

              <div class="data-table-container mt-3">
                <table class="data-table compact-table">
                  <thead>
                    <tr>
                      <th style="width: 40px; text-align: center;">☑</th>
                      <th>Sample ID</th>
                      <th>WSS / Location</th>
                      <th>Collection Date</th>
                      <th>Test Type</th>
                      <th>Status</th>
                      <th>Fee (PKR)</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(s, i) in unbilledSamples" :key="s.id" :class="i % 2 === 1 ? 'alt-row' : ''">
                      <td style="text-align: center;"><input type="checkbox" v-model="s.selected" /></td>
                      <td class="mono fw-600">{{ s.slug }}</td>
                      <td>{{ s.wss }}</td>
                      <td>{{ s.date }}</td>
                      <td><span class="status-badge invoice" style="border: none; background: #e0f2fe; color: #0284c7;">{{ s.test }}</span></td>
                      <td>
                        <span class="status-badge" style="border: none;"
                              :style="s.status === 'Paid' ? 'background:#dcfce7;color:#166534' : s.status === 'Partially Paid' ? 'background:#fef3c7;color:#92400e' : 'background:#fee2e2;color:#991b1b'">
                          {{ s.status }}
                        </span>
                      </td>
                      <td class="mono">{{ formatNum(s.fee) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              
              <div class="d-flex align-items-center justify-content-between mt-3 pb-2 pt-2 border-top">
                <div style="font-size: 14px;">
                  Selected: <strong>{{ selectedCount }} samples</strong> <span class="mx-2">·</span> Total: <strong class="text-dark" style="color: #0f172a; font-size: 15px;">PKR {{ formatNum(selectedTotal) }}</strong>
                </div>
              </div>
            </div>

            <!-- Step 3 Preview -->
            <div v-if="clubbedStep === 3" class="step-content invoice-preview-container">
               <div class="invoice-preview" id="printable-invoice">
                 <div class="invoice-header-box">
                   <div class="logo-ph left">🏛</div>
                   <div class="header-text">
                     <div class="gov">GOVERNMENT OF KHYBER PAKHTUNKHWA</div>
                     <div class="dept">Public Health Engineering Department</div>
                     <div class="lab">{{ userStore.currentUser?.laboratory?.name || 'Water-Quality Laboratory' }}</div>
                     <div class="address">{{ clubbedForm.labId || userStore.currentUser?.laboratory?.name || '—' }}</div>
                   </div>
                   <div class="logo-ph right">💧</div>
                 </div>
                 <!-- Slug is generated server-side on save (per-lab sequence via
                      FinanceSlugService::nextClubbedSlug). Show a placeholder
                      so users know it'll be assigned, not a fake fixed number. -->
                 <div class="inv-no">No: <em style="color:#64748b">auto-generated on save</em></div>
                 <div class="inv-title">
                   <strong>INVOICE</strong><br/>
                   <span class="text-blue">WATER QUALITY TESTING SERVICES</span>
                 </div>

                 <table class="inv-details-table">
                   <tbody>
                     <tr>
                       <td class="label">Source / Client Name</td>
                       <td><strong>{{ clubbedForm.clientName || '—' }}</strong></td>
                       <td class="label">Invoice Date</td>
                       <td>{{ clubbedForm.invoiceDate || '—' }}</td>
                     </tr>
                     <tr>
                       <td class="label">Lab</td>
                       <td>{{ clubbedForm.labId || userStore.currentUser?.laboratory?.name || '—' }}</td>
                       <td class="label">No. of Samples</td>
                       <td><strong>{{ selectedCount }}</strong></td>
                     </tr>
                     <tr>
                       <td class="label">Client Type</td>
                       <td>
                         {{ clubbedForm.clientType?.includes('Client') ? 'Private / Walk-in Client' : 'PHE Internal' }}
                       </td>
                       <td class="label">Period Covered</td>
                       <td>{{ clubbedForm.periodFrom || '—' }} → {{ clubbedForm.periodTo || '—' }}</td>
                     </tr>
                     <tr>
                       <td class="label">Online Viewing (Password)</td>
                       <td><em style="color:#64748b; font-size: 12px;">Auto-generated on save (SMS gateway not yet configured — see SmsService stub)</em></td>
                       <td class="label">Generated By</td>
                       <td>{{ userStore.currentUser?.name || '—' }}</td>
                     </tr>
                   </tbody>
                 </table>

                 <div class="text-blue mt-3 mb-2" style="font-weight: 600; font-size: 13px; color: #1e3a8a;">Billing Summary (grouped by test category & rate):</div>
                 
                 <table class="billing-summary-table">
                    <thead>
                      <tr>
                        <th>S#</th>
                        <th>Test Category / Description</th>
                        <th>Receipt IDs Range</th>
                        <th>No. of Samples</th>
                        <th>Rate / Sample</th>
                        <th>Amount (PKR)</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(cat, name, idx) in clubbedInvoicePreviewData.items" :key="name">
                        <td>{{ idx + 1 }}</td>
                        <td><strong>{{ name }}</strong></td>
                        <td class="mono">{{ cat.samples.join(', ') }}</td>
                        <td><strong>{{ cat.count }}</strong></td>
                        <td>{{ formatNum(cat.rate) }}</td>
                        <td>{{ formatNum(cat.count * cat.rate) }}</td>
                      </tr>
                      
                      <tr class="calc-row">
                        <td colspan="6">
                          <em style="color: #64748b; font-size: 13px;">
                            Formula: {{ clubbedInvoicePreviewData.formula }}
                          </em>
                        </td>
                      </tr>
                      
                      <tr>
                        <td colspan="5" class="text-right">Service Charges</td>
                        <td class="mono">0</td>
                      </tr>
                      <tr class="total-row bg-dark text-white">
                        <td colspan="5" style="text-align: right; font-weight: bold; padding: 12px;">TOTAL CHARGES</td>
                        <td class="mono fw-bold" style="padding: 12px; font-size: 16px;">{{ formatNum(clubbedInvoicePreviewData.total) }}</td>
                      </tr>
                    </tbody>
                  </table>

                 <div class="footer-note-section mt-4 pt-4">
                   <div class="note">
                     <em>Note: This is a consolidated clubbed invoice. The {{ selectedCount }} individual receipts above will be marked as 'Included in Clubbed Invoice' on save and cannot be reused in another clubbed invoice.</em>
                   </div>
                   <div class="signatory">
                     <div class="line"></div>
                     <strong>{{ userStore.currentUser?.name || '—' }}</strong><br/>
                     Authorized Signatory / Stamp
                   </div>
                 </div>

                 <div class="barcode mt-4">
                   | | | | | | | | | | | | | | | | | | | | | | | | | |<br/>
                   <em style="color:#64748b">Barcode generated on save</em>
                 </div>

               </div>
            </div>

          </div>
          <div class="modal-footer clubbed-footer">
            <button v-if="clubbedStep > 1" class="btn btn-outline" @click="prevClubbedStep">← Back</button>
            <!-- spacer pushes the primary actions to the right edge regardless
                 of whether Back is visible (step 1 has only Next) -->
            <div class="clubbed-footer-spacer"></div>
            <button v-if="clubbedStep === 3" class="btn btn-outline" @click="printClubbedInvoice">🖨 Print PDF</button>
            <button v-if="clubbedStep < 3" class="btn btn-blue" @click="nextClubbedStep">
               Next: {{ clubbedStep === 1 ? 'Select Samples' : 'Preview' }} →
            </button>
            <button v-if="clubbedStep === 3" v-write="'add_invoices'" class="btn btn-blue"
                    style="background: #0ea5e9; border-color: #0284c7;"
                    :disabled="clubbedSaving"
                    @click="saveClubbedInvoice">
               {{ clubbedSaving ? '⏳ Saving…' : '✅ Confirm & Save Invoice' }}
            </button>
          </div>
        </div>
       </div>
    </Teleport>

  </div>
</template>

<style lang="scss" scoped>
@use '../../../assets/styles/variables' as *;

.invoices-page {
  display: flex;
  flex-direction: column;
  background: #f8fafc;
  min-height: 100vh;
}

/* 1. KPI Cards — sized to match the project-standard `.card` rule in
   src/assets/styles/_global.scss so Finance doesn't feel oversized
   compared to Dashboard / WSS / AssetManagement. */
.kpi-cards {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 10px;
  margin-bottom: 14px;
}

.kpi-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 12px 14px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
  position: relative;
  overflow: hidden;

  &::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
  }

  &.top-orange::before { background-color: #d97706; }
  &.top-green::before { background-color: #16a34a; }
  &.top-red::before { background-color: #dc2626; }
  &.top-grey::before { background-color: #94a3b8; }
}

.kpi-label {
  font-size: 10px;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 5px;
}

.kpi-value {
  font-size: 21px;
  font-weight: 700;
  font-family: 'DM Mono', monospace;
  display: flex;
  align-items: baseline;
  gap: 3px;
  line-height: 1;

  span {
    font-size: 12px;
    font-weight: 600;
  }

  &.text-orange { color: #b45309; }
  &.text-green { color: #166534; }
  &.text-red { color: #b91c1c; }
  &.text-brown { color: #92400e; }
}

/* 2. Tabs Navigation */
.tabs-nav {
  display: flex;
  border-bottom: 1px solid #e2e8f0;
  margin-bottom: 16px;
  gap: 24px;
}

.tab-btn {
  background: transparent;
  border: none;
  padding: 10px 4px;
  font-size: 14px;
  font-weight: 600;
  color: #64748b;
  cursor: pointer;
  position: relative;
  display: flex;
  align-items: center;
  gap: 6px;

  .icon {
    font-size: 16px;
    filter: grayscale(100%);
    opacity: 0.6;
  }

  &:hover {
    color: #0f172a;
    .icon { filter: grayscale(0%); opacity: 1; }
  }

  &.active {
    color: #1e40af;
    
    .icon { filter: grayscale(0%); opacity: 1; }

    &::after {
      content: '';
      position: absolute;
      bottom: -1px;
      left: 0;
      right: 0;
      height: 3px;
      background-color: #3b82f6;
      border-radius: 3px 3px 0 0;
    }
  }
}

/* 3. Toolbar Filters — tightened paddings/fonts to match other modules */
.filters-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 14px;
  padding: 10px 12px;
  background: #f8fafc;
  border-radius: 6px;
  border: 1px solid #e2e8f0;
}

.left-filters {
  display: flex;
  gap: 8px;
}

.search-box {
  position: relative;
  display: flex;
  align-items: center;

  .search-icon {
    position: absolute;
    left: 10px;
    color: #94a3b8;
  }

  input {
    padding: 6px 10px 6px 30px;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
    font-size: 12.5px;
    width: 220px;
    color: #334155;
    background: #fff;
    outline: none;

    &:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
    }
  }
}

.status-select {
  padding: 6px 28px 6px 10px;
  border: 1px solid #cbd5e1;
  border-radius: 5px;
  font-size: 12.5px;
  color: #334155;
  background: #fff;
  cursor: pointer;
  outline: none;

  &:focus {
    border-color: #3b82f6;
  }
}

.right-actions {
  display: flex;
  gap: 8px;
}

.btn-blue {
  background: #1e5ba3;
  color: #fff;
  border: none;
  padding: 6px 12px;
  border-radius: 4px;
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;

  &:hover { background: #174883; }
}

.btn-outline {
  background: #fff;
  color: #334155;
  border: 1px solid #cbd5e1;
  padding: 6px 12px;
  border-radius: 4px;
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;

  &:hover { background: #f8fafc; border-color: #94a3b8; }
}

/* 4. Table — sized to match project standard (LabSamples / SBPSubmissions /
   DiariesDispatches): 12.5px font, 9×10 padding. Container allows
   horizontal scroll so 11-column ledger rows don't get clipped. */
.data-table-container {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  overflow-x: auto;
  box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 12.5px;

  thead {
    background: #1e3a5f;
    color: #fff;

    th {
      padding: 9px 10px;
      font-weight: 600;
      white-space: nowrap;
      font-size: 11.5px;
      letter-spacing: .02em;
    }
  }

  tbody {
    tr {
      border-bottom: 1px solid #e2e8f0;
      transition: background 0.15s;

      &:last-child { border-bottom: none; }
      
      &.alt-row {
        background-color: #f1f5f9;
      }

      &:hover {
        background-color: #f8fafc;
      }
    }

    td {
      padding: 9px 10px;
      color: #334155;
      vertical-align: middle;
    }
  }
}

.fw-600 { font-weight: 600; }
.fw-500 { font-weight: 500; }
.text-dark { color: #0f172a; }
.text-red { color: #dc2626; }
.text-green { color: #16a34a; }
.mono { font-family: 'DM Mono', monospace; }
.empty-state { padding: 40px !important; font-style: italic; }

/* 5. Status Badges & Action Buttons */
.status-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 4px 12px;
  border-radius: 50px;
  font-size: 11.5px;
  font-weight: 600;
  letter-spacing: 0.02em;

  &.paid, &.payment { background: #dcfce7; color: #166534; }
  &.partial { background: #fef3c7; color: #b45309; }
  &.unpaid, &.pending { background: #fee2e2; color: #991b1b; }
  &.invoice { background: #e0f2fe; color: #0369a1; }
}

.action-cell {
  width: 120px;
}

.btn-action {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;

  &.btn-record {
    background: #1a6bbf;
    color: #fff;
    border: none;
    &:hover { background: #14559c; }
  }

  &.btn-print {
    background: #fff;
    color: #475569;
    border: 1px solid #cbd5e1;
    &:hover { background: #f8fafc; border-color: #94a3b8; }
  }
}

/* Modals & Forms — tightened to match other modules in the app.
   Flex column + capped height so on short viewports the footer (Save
   Payment button) stays visible and the body scrolls internally. */
.payment-modal {
  width: 520px;
  padding: 0;
  border-radius: 6px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;

  .modal-header {
    background: #1f2937;
    color: #fff;
    padding: 12px 18px;
    position: relative;
    flex-shrink: 0;

    .header-top {
      display: flex;
      justify-content: space-between;
      align-items: center;

      h2 { color: #fff; margin: 0; font-size: 15px; display: flex; align-items: center; }
    }
    .modal-subtitle { font-size: 11.5px; color: #9ca3af; margin-top: 3px; }

    .btn-close-modal {
      /* Match the project-wide blue (`.btn-blue` = #1e5ba3) so modal close
         visually belongs to the same control family as Generate / Submit. */
      background: #1e5ba3;
      color: #fff;
      border: 1px solid #1e5ba3;
      padding: 3px 10px;
      border-radius: 4px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      &:hover { background: #174a87; border-color: #174a87; }
    }
  }

  .modal-body {
    padding: 16px 18px;
    background: #fff;
    flex: 1;
    overflow-y: auto;
    min-height: 0;
  }

  .payment-info-box {
    display: flex;
    justify-content: space-between;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 5px;
    padding: 10px 14px;
    margin-bottom: 14px;

    .info-col {
      display: flex;
      flex-direction: column;
      align-items: center;

      .info-label { font-size: 11px; color: #64748b; margin-bottom: 3px; }
      .info-value { font-size: 13.5px; font-weight: 700; }
    }
  }

  .form-grid {
    &.c2 {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px 16px;
    }
    label {
      display: block;
      font-size: 11.5px;
      font-weight: 600;
      color: #475569;
      margin-bottom: 4px;
    }
    .form-input {
      width: 100%;
      padding: 6px 10px;
      border: 1px solid #cbd5e1;
      border-radius: 4px;
      font-size: 12.5px;
      color: #0f172a;
      outline: none;
      &:focus { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1); }
    }
  }

  .remaining-box {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    border-radius: 5px;
    border: 1px solid #e2e8f0;
    margin-top: 10px;

    &.bg-green-light { background: #f0fdf4; border-color: #bbf7d0; }
    &.bg-gray-light { background: #f8fafc; }

    .remaining-label { font-size: 12px; color: #475569; }
    .remaining-value { font-size: 13.5px; font-weight: 700; }

    .badge-paid {
      background: #bbf7d0;
      color: #166534;
      padding: 3px 8px;
      border-radius: 16px;
      font-size: 11px;
      font-weight: 600;
    }
  }

  .modal-footer {
    padding: 10px 18px;
    background: #fff;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: flex-start;
    gap: 8px;
    flex-shrink: 0;
  }
  /* Clubbed-invoice footer: Back stays glued left, primary buttons (Next,
     Print, Confirm) flow to the right. Spacer takes the remaining inline
     space whether or not Back is visible. */
  .clubbed-footer {
    align-items: center;
    flex-wrap: nowrap;
  }
  .clubbed-footer-spacer { flex: 1 1 auto; min-width: 0; }
}

.text-blue { color: #1e3a8a; }
.text-green { color: #166534; }
.text-red { color: #b91c1c; }
.d-flex { display: flex; }
.align-items-center { align-items: center; }
.justify-content-between { justify-content: space-between; }
.justify-content-start { justify-content: flex-start; }
.gap-2 { gap: 8px; }
.mt-3 { margin-top: 12px; }
.mt-4 { margin-top: 16px; }

/* Clubbed Invoice Modal Styles — sized down to match other module modals */
.clubbed-modal {
  width: 760px !important;
  max-width: 95vw;
}
/* Step 3 (Preview & Confirm) is wider so the full invoice template fits
   without horizontal cropping, and taller so the Back / Print / Confirm
   footer is always reachable without scrolling past the preview body. */
.clubbed-modal.step3-modal {
  width: 1080px !important;
  max-width: 96vw;
  max-height: 92vh;
  display: flex;
  flex-direction: column;
}
.modal-subtitle {
  color: #94a3b8;
  font-size: 13px;
  margin-top: 4px;
}
.stepper {
  display: flex;
  border-bottom: 1px solid #cbd5e1;
  background: #f8fafc;
}
.stepper .step {
  flex: 1;
  text-align: center;
  padding: 14px 0;
  font-size: 14px;
  color: #64748b;
  font-weight: 500;
  border-bottom: 3px solid transparent;
  transition: all 0.2s ease;
}
.stepper .step.active {
  color: #1e3a8a;
  font-weight: 600;
  border-bottom-color: #1e3a8a;
}
.step-content {
  padding: 8px 0;
}
.compact-table th, .compact-table td {
  padding: 10px 12px;
  font-size: 13px;
}
.status-badge.invoice {
  background: #e0f2fe;
  color: #0369a1;
  border-color: #bae6fd;
}
.invoice-preview-container {
  max-height: 60vh;
  overflow-y: auto;
  padding: 20px;
  background: #f1f5f9;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
}
.invoice-preview {
  background: #fff;
  padding: 40px;
  box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
  color: #000;
  font-family: Arial, sans-serif;
  margin: 0 auto;
}
.invoice-header-box {
  display: flex;
  justify-content: space-between;
  align-items: center;
  text-align: center;
  margin-bottom: 20px;
}
.logo-ph {
  font-size: 40px;
}
.logo-ph.left { color: #d4af37; }
.logo-ph.right { color: #38bdf8; }
.header-text .gov { font-weight: bold; font-size: 16px; color: #1e3a8a; letter-spacing: 1px; }
.header-text .dept { font-weight: bold; font-size: 15px; color: #1e3a8a; margin-top: 2px; }
.header-text .lab { font-size: 14px; margin-top: 4px; }
.header-text .address { font-size: 12px; color: #475569; margin-top: 4px; }
.inv-no {
  font-weight: bold;
  font-size: 14px;
  color: #1e3a8a;
  border-bottom: 2px solid #1e3a8a;
  padding-bottom: 10px;
  margin-bottom: 15px;
}
.inv-title {
  text-align: center;
  margin-bottom: 20px;
  font-size: 16px;
}
.inv-details-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 20px;
  font-size: 13px;
}
.inv-details-table td {
  border: 1px solid #cbd5e1;
  padding: 8px 10px;
  line-height: 1.4;
}
.inv-details-table td.label {
  background: #f8fafc;
  font-weight: 600;
  width: 20%;
  color: #0f172a;
}
.inv-summary-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
  margin-bottom: 20px;
}
.inv-summary-table th {
  background: #1e3a8a;
  color: #fff;
  padding: 10px 8px;
  text-align: left;
  border: 1px solid #1e3a8a;
}
.inv-summary-table td {
  border: 1px solid #cbd5e1;
  padding: 10px 8px;
}
.calc-row td {
  background: #f8fafc;
  color: #475569;
}
.total-row td {
  background: #1e3a8a !important;
  color: #fff !important;
  font-weight: bold;
}
.footer-note-section {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: #475569;
  line-height: 1.5;
}
.footer-note-section .note { width: 60%; }
.signatory { width: 30%; text-align: center; }
.signatory .line { border-top: 1px solid #000; margin-bottom: 5px; margin-top: 30px; }
.barcode { text-align: center; font-size: 14px; color: #475569; letter-spacing: 2px; }

/* Print Overrides */
@media print {
  .print-hide { display: none !important; }
  .invoices-page { background: #fff; }
  .data-table-container { border: none; box-shadow: none; }
  .data-table {
    border: 1px solid #000;
    thead th { background-color: #f1f5f9 !important; color: #000 !important; -webkit-print-color-adjust: exact; border: 1px solid #000; }
    tbody td { border: 1px solid #000; }
  }
  .status-badge { border: 1px solid #000; background: transparent !important; color: #000 !important; }
  body::before { content: "WQM-MIS Finance Report"; display: block; text-align: center; font-size: 16pt; font-weight: bold; margin-bottom: 20px; }
}

/* Ledger Specific Styles */
.ledger-filters {
  background: #f8fafc;
  padding: 16px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  margin-bottom: 20px;
}
.ledger-specific-controls {
  margin-top: 10px;
  margin-bottom: 20px;
}
/* Ledger summary cards — sized to match other modules (LabSamples /
   SBPSubmissions / DiariesDispatches): 11px label, 17px value, 10.5px
   sub-text. Padding tightened to 10×12 from the previous 16. */
.ledger-box {
  background: #fff;
  border-radius: 6px;
  padding: 10px 12px;
  text-align: center;
  border: 1px solid #e2e8f0;
  box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}
.ledger-box.box-blue { border-top: 3px solid #3b82f6; background: #eff6ff; }
.ledger-box.box-gray { border-top: 3px solid #94a3b8; background: #f8fafc; }
.ledger-box.box-green { border-top: 3px solid #22c55e; background: #f0fdf4; }
.ledger-box.box-orange { border-top: 3px solid #f97316; background: #fff7ed; }

.ledger-box .box-title {
  font-size: 10.5px;
  color: #64748b;
  text-transform: uppercase;
  font-weight: 600;
  letter-spacing: .03em;
  margin-bottom: 5px;
}
.ledger-box .box-val {
  font-size: 17px;
  font-weight: 700;
  margin-bottom: 3px;
  line-height: 1.15;
}
.ledger-box .box-sub {
  font-size: 10.5px;
  color: #64748b;
}

.text-right {
  text-align: right;
}

.bg-yellow-light {
  background-color: #fef3c7 !important;
}

.ledger-header-row th {
  background-color: #1e3a8a !important;
  color: #fff !important;
  border-bottom: none;
}

.ledger-totals-row td {
  background-color: #1e3a8a !important;
  color: #fff !important;
  font-weight: bold;
  font-size: 14px;
  border: none;
}

.status-badge.sbp {
  background: #fef08a;
  color: #854d0e;
  border-color: #fde047;
}

/* Dues ageing cards — sized to match ledger summary cards / other modules
   (LabSamples / SBPSubmissions). Was 16px padding + 20px value; now
   10×12 padding + 17px value to keep visual density consistent. */
.dues-summary-boxes { margin-bottom: 14px; margin-top: 8px; }
.dues-box {
  flex: 1;
  border-radius: 6px;
  padding: 10px 12px;
  text-align: center;
  border: 1px solid #e2e8f0;
}
.dues-box.box-0-30 { background: #f0fdf4; border-color: #bbf7d0; }
.dues-box.box-31-60 { background: #fffbeb; border-color: #fde68a; }
.dues-box.box-61-90 { background: #fef2f2; border-color: #fecaca; }
.dues-box.box-90-plus { background: #450a0a; border-color: #450a0a; color: #fff; }

.dues-box .box-title {
  font-size: 10.5px;
  font-weight: 600;
  letter-spacing: .03em;
  margin-bottom: 5px;
  color: inherit;
  opacity: 0.85;
}
.dues-box .box-val {
  font-size: 17px;
  font-weight: 700;
  margin-bottom: 3px;
  line-height: 1.15;
}
.dues-box .box-sub { font-size: 10.5px; opacity: 0.85; }
.dues-box.box-90-plus .box-val { color: #fca5a5; }

/* Ledger filters — rounded inputs matching the LabSamples / GAR / report
   filter style. Was using a hard-cornered `.form-input` from inside the
   modal scope which made these look out of place vs the rest of the app. */
.ledger-filters .lf-input {
  padding: 6px 10px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 12.5px;
  font-family: inherit;
  background: #fff;
  color: #0f172a;
  outline: none;
  transition: border-color .12s, box-shadow .12s;
}
.ledger-filters .lf-input:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.12);
}

/* Clear Filters button — red outline + ✕ icon, matching the pattern used
   in LabSamples and the project's clear-filters convention. */
.ledger-filters .lf-btn-clear,
.lf-btn-clear {
  background: #fff;
  color: #dc2626;
  border: 1px solid #fca5a5;
  border-radius: 6px;
  padding: 6px 14px;
  font-size: 12px;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: all .12s;
}
.ledger-filters .lf-btn-clear:hover,
.lf-btn-clear:hover {
  background: #fef2f2;
  border-color: #dc2626;
}

.ageing-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
}
.ageing-badge.success { background: #dcfce7; color: #166534; }
.ageing-badge.warning { background: #fee2e2; color: #991b1b; }
.ageing-badge.danger { background: #991b1b; color: #fff; }

/* Skeleton shimmer for loading tables — matches the pattern used in
   DiariesDispatches / RolesPermissions across the project. */
.fin-skel {
  background: linear-gradient(90deg, #f1f5f9 0%, #e2e8f0 50%, #f1f5f9 100%);
  background-size: 200% 100%;
  animation: fin-shimmer 1.4s infinite ease-in-out;
  border-radius: 4px;
  width: 100%;
  height: 14px;
}
@keyframes fin-shimmer {
  0%   { background-position: -200% 0; }
  100% { background-position: 200% 0; }
}

/* Toast transition — global so the Teleport target picks it up. */
.toast-slide-enter-active,
.toast-slide-leave-active { transition: all 0.3s ease; }
.toast-slide-enter-from,
.toast-slide-leave-to     { opacity: 0; transform: translateX(60px); }

</style>

<!-- ────────────────────────────────────────────────────────────────────
     Non-scoped block. `<Teleport to="body">` moves the modal DOM out of
     this component's scope, so scoped SCSS rules above (especially nested
     ones like `.invoices-page .modal-header .btn-close-modal`) don't
     reliably reach the modal. The selectors below are intentionally
     specific to the clubbed-modal class so they don't bleed.
     ──────────────────────────────────────────────────────────────────── -->
<style>
/* Close button — match project-wide blue (.btn-blue = #1e5ba3).
   Hits both .clubbed-modal AND .payment-modal close buttons. */
.clubbed-modal .btn-close-modal,
.payment-modal .btn-close-modal {
  background: #1e5ba3 !important;
  color: #fff !important;
  border: 1px solid #1e5ba3 !important;
  padding: 4px 12px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}
.clubbed-modal .btn-close-modal:hover,
.payment-modal .btn-close-modal:hover {
  background: #174a87 !important;
  border-color: #174a87 !important;
}

/* Footer layout — Back on far left, primary actions flush right, all on
   one row. The spacer absorbs the remaining inline space. */
.clubbed-modal .clubbed-footer {
  display: flex !important;
  flex-direction: row !important;
  align-items: center;
  flex-wrap: nowrap;
  gap: 8px;
  padding: 12px 18px;
  background: #fff;
  border-top: 1px solid #e2e8f0;
}
.clubbed-modal .clubbed-footer-spacer {
  flex: 1 1 auto;
  min-width: 0;
}

/* Step-2 table — many columns, narrow modal. Allow horizontal scroll on
   small screens without clipping the rightmost columns (Fee, Result). */
.clubbed-modal .data-table-container {
  overflow-x: auto;
}
.clubbed-modal .compact-table {
  min-width: 760px; /* keeps columns from squishing too aggressively */
}
.clubbed-modal .compact-table th,
.clubbed-modal .compact-table td {
  white-space: nowrap;
  padding: 9px 10px;
}

/* Step 3 modal — wider + height-capped so the full invoice template fits
   without horizontal clipping AND the footer (Back / Print / Confirm) is
   always reachable. The preview body gets its own scroll INSIDE the modal
   so the footer never disappears off-screen. */
.clubbed-modal.step3-modal {
  width: 1080px !important;
  max-width: 96vw;
  max-height: 92vh;
  display: flex !important;
  flex-direction: column !important;
}
.clubbed-modal.step3-modal .modal-body {
  flex: 1 1 auto;
  min-height: 0;          /* required for flex children to shrink */
  overflow-y: auto;
}
.clubbed-modal.step3-modal .invoice-preview-container {
  max-height: none;       /* container takes its size from modal-body */
}
</style>
