<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { api } from '../../../services/api.js'
import { dropdownService } from '../../../services/dropdownService.js'
import { useUserStore } from '../../../stores/useUserStore.js'

const userStore = useUserStore()

// State
const loading = ref(false)

const sbpSubmissions = ref([])
const pendingSbpLogs = ref([])
const showSbpModal = ref(false)
const showReconModal = ref(false)
const selectedSbpLogs = ref([])

// Lab dropdown — loaded from API; first option = All Labs for the report.
const labs = ref([])

// Form State (New Submission) — defaults derived from the logged-in user.
const sbpChallanNo = ref('')
const sbpDepositDate = ref(new Date().toISOString().split('T')[0])
const sbpPeriodFrom = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0])
const sbpPeriodTo = ref(new Date().toISOString().split('T')[0])
const sbpSubmittedBy = ref('')
const sbpRemarks = ref('')
const sbpSubmitting = ref(false)
const sbpLabId = ref(null)

// Permission gates
const canVerify = computed(() => userStore.hasPermission('verify_sbp_submissions'))

// Toast
const toast = ref({ show: false, message: '', type: 'success' })
let toastTimer = null
function showToast(message, type = 'success') {
  clearTimeout(toastTimer)
  toast.value = { show: true, message, type }
  toastTimer = setTimeout(() => { toast.value.show = false }, 4000)
}

// Report Filters
const reconFrom = ref('2026-01-01')
const reconTo = ref(new Date().toISOString().split('T')[0])
const reconLabId = ref('')  // '' = All Labs

// Summary Values
const summary = ref({
  collected: 0,
  submitted: 0,
  pending: 0
})

// Helpers
const formatNum = (num) => {
  if (num === null || num === undefined) return '0'
  return Number(num).toLocaleString('en-US')
}

const formatDate = (dateStr) => {
  if (!dateStr) return '—'
  const date = new Date(dateStr)
  return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: '2-digit' }).replace(/ /g, '-')
}

const getDaysDiff = (dateStr) => {
  const diff = new Date() - new Date(dateStr)
  const days = Math.floor(diff / (1000 * 60 * 60 * 24))
  return days > 0 ? days : 0
}

// Smart currency formatter for the top KPI cards. The old `/1_000_000 + "M"`
// pattern hid every amount under 100k (Rs 7,925 → "Rs 0.0M"). Switch units
// by magnitude so small labs and big labs both display meaningfully.
const formatMoney = (n) => {
  const v = Number(n) || 0
  if (v >= 10_000_000) return (v / 10_000_000).toFixed(2) + ' Cr'  // ≥ 1 crore
  if (v >= 1_000_000)  return (v / 1_000_000).toFixed(2) + ' M'    // ≥ 10 lakh
  if (v >= 1_000)      return (v / 1_000).toFixed(1) + ' K'        // ≥ 1k
  return v.toLocaleString('en-PK')                                  // raw with commas
}

const sbpSelectedTotal = computed(() => {
  return pendingSbpLogs.value
    .filter(l => selectedSbpLogs.value.includes(l.id))
    .reduce((sum, l) => sum + Number(l.paid || 0), 0)
})

// Backend's TimeStampAccessorTrait reformats `created_at` as a human string
// that JS Date() can't parse — so date math reads `created_at_iso` (raw ISO),
// while `created_at` is still used for display.
const pickIso = (l) => l?.created_at_iso || l?.created_at
const filteredPendingLogs = computed(() => {
  let result = pendingSbpLogs.value
  if (sbpPeriodFrom.value) {
    const from = new Date(sbpPeriodFrom.value)
    result = result.filter(l => new Date(pickIso(l)) >= from)
  }
  if (sbpPeriodTo.value) {
    // End-of-day so logs created later on the same calendar day still match.
    const to = new Date(sbpPeriodTo.value); to.setHours(23, 59, 59, 999)
    result = result.filter(l => new Date(pickIso(l)) <= to)
  }
  // Lab filter — once the user picks a lab on the form, the selectable
  // pending logs collapse to that lab's payments so we can't accidentally
  // bank cross-lab cash under the wrong submission. Loose equality in case
  // the API returns numeric ids as strings.
  if (sbpLabId.value) {
    result = result.filter(l => Number(l.water_sample_invoice?.water_sample?.laboratory_id) === Number(sbpLabId.value))
  }
  return result
})

// Wire up the "select all" header checkbox to the current visible-pending set.
const allChecked = computed({
  get: () => filteredPendingLogs.value.length > 0
            && filteredPendingLogs.value.every(l => selectedSbpLogs.value.includes(l.id)),
  set: (val) => {
    if (val) {
      const ids = new Set(selectedSbpLogs.value)
      filteredPendingLogs.value.forEach(l => ids.add(l.id))
      selectedSbpLogs.value = [...ids]
    } else {
      const drop = new Set(filteredPendingLogs.value.map(l => l.id))
      selectedSbpLogs.value = selectedSbpLogs.value.filter(id => !drop.has(id))
    }
  },
})

// Reconciliation Report Computeds
const filteredSubmissionsReport = computed(() => {
  return sbpSubmissions.value.filter(s => {
    const d = new Date(s.deposit_date)
    const matchesDate = d >= new Date(reconFrom.value) && d <= new Date(reconTo.value)
    const matchesLab = !reconLabId.value || s.laboratory_id === reconLabId.value
    return matchesDate && matchesLab
  })
})

const filteredPendingReport = computed(() => {
  const from = new Date(reconFrom.value)
  const to = new Date(reconTo.value); to.setHours(23, 59, 59, 999)
  return pendingSbpLogs.value.filter(l => {
    const d = new Date(pickIso(l))
    const matchesDate = d >= from && d <= to
    const matchesLab = !reconLabId.value
      || Number(l.water_sample_invoice?.water_sample?.laboratory_id) === Number(reconLabId.value)
    return matchesDate && matchesLab
  })
})

const reconStats = computed(() => {
  const submitted = filteredSubmissionsReport.value.reduce((acc, s) => acc + (parseFloat(s.amount) || 0), 0)
  const pending = filteredPendingReport.value.reduce((acc, l) => acc + (parseFloat(l.paid) || 0), 0)
  const total = submitted + pending
  const submissionsCount = filteredSubmissionsReport.value.length
  const pendingCount = filteredPendingReport.value.length
  const totalInvoices = filteredSubmissionsReport.value.reduce((acc, s) => acc + (parseInt(s.invoice_logs_count) || 0), 0) + pendingCount

  return { submitted, pending, total, submissionsCount, pendingCount, totalInvoices }
})

// API Calls
async function fetchData() {
  loading.value = true
  try {
    const [subRes, pendingRes] = await Promise.all([
      api.get('/finance/sbp-submissions'),
      api.get('/finance/sbp-submissions/pending')
    ])

    // api service returns response.data directly because of axios.js interceptor
    // but the backend might return { data: [...] } or just [...]
    sbpSubmissions.value = subRes?.data || (Array.isArray(subRes) ? subRes : [])
    pendingSbpLogs.value = pendingRes?.data || (Array.isArray(pendingRes) ? pendingRes : [])

    // Update main summary
    const totalSub = sbpSubmissions.value.reduce((acc, s) => acc + parseFloat(s.amount), 0)
    const totalPen = pendingSbpLogs.value.reduce((acc, l) => acc + parseFloat(l.paid), 0)
    summary.value = {
      submitted: totalSub,
      pending: totalPen,
      collected: totalSub + totalPen
    }
  } catch (err) {
    console.error('Fetch error:', err)
    showToast('❌ Failed to load SBP data: ' + (err?.response?.data?.message || err.message), 'error')
  } finally {
    loading.value = false
  }
}

async function loadLabs() {
  try {
    const res = await dropdownService.getLaboratories()
    const rows = res?.data || (Array.isArray(res) ? res : [])
    labs.value = rows.map(l => ({ id: l.id, name: l.name }))
  } catch (e) {
    console.error('Failed to load labs:', e)
  }
}

// Initialise defaults from the logged-in user once the user store is ready.
function applyUserDefaults() {
  if (!sbpSubmittedBy.value) {
    sbpSubmittedBy.value = userStore.currentUser?.name || ''
  }
  if (!sbpLabId.value && userStore.laboratoryId) {
    sbpLabId.value = userStore.laboratoryId
  }
}

async function submitSbp() {
  if (selectedSbpLogs.value.length === 0) {
    showToast('⚠️ Pick at least one collection to submit.', 'error')
    return
  }
  if (!sbpLabId.value) {
    showToast('⚠️ Please select a lab.', 'error')
    return
  }
  if (!sbpChallanNo.value?.trim()) {
    showToast('⚠️ Challan / TR number is required.', 'error')
    return
  }
  sbpSubmitting.value = true
  try {
    await api.post('/finance/sbp-submissions', {
      log_ids: selectedSbpLogs.value,
      challan_no: sbpChallanNo.value,
      deposit_date: sbpDepositDate.value,
      period_from: sbpPeriodFrom.value,
      period_to: sbpPeriodTo.value,
      lab_id: sbpLabId.value,
      submitted_by_name: sbpSubmittedBy.value,
      remarks: sbpRemarks.value
    })
    await fetchData()
    showSbpModal.value = false
    selectedSbpLogs.value = []
    sbpChallanNo.value = ''
    sbpRemarks.value = ''
    showToast('✅ SBP submission recorded successfully.', 'success')
  } catch (err) {
    showToast('❌ Submission failed: ' + (err?.response?.data?.message || err.message), 'error')
  } finally {
    sbpSubmitting.value = false
  }
}

// Print / PDF: drop the print-mode class so @media print picks up the
// stripped-down layout, fire the browser print dialog (which offers
// "Save as PDF"), then restore the class on next tick.
function printReport() {
  document.body.classList.add('sbp-print-mode')
  // setTimeout 0 lets the class apply before the print dialog snapshots layout.
  setTimeout(() => {
    window.print()
    document.body.classList.remove('sbp-print-mode')
  }, 50)
}

// Per-row guard so a double-click can't fire two verifies on the same row.
const verifyingId = ref(null)

async function verifySbp(sub) {
  if (sub.status === 'verified' || verifyingId.value === sub.id) return
  verifyingId.value = sub.id
  try {
    await api.post(`/finance/sbp-submissions/${sub.id}/verify`)
    await fetchData()
    showToast(`✅ Submission ${sub.submission_slug} verified successfully.`, 'success')
  } catch (err) {
    // Backend returns a clear message for segregation-of-duties (you can't
    // verify your own submission) and 403 (non-admin). Surface it verbatim.
    showToast('❌ ' + (err?.response?.data?.message || 'Verification failed.'), 'error')
  } finally {
    verifyingId.value = null
  }
}

onMounted(() => {
  applyUserDefaults()
  fetchData()
  loadLabs()
})

// User store hydrates async — re-apply defaults when it lands.
watch(() => userStore.currentUser, applyUserDefaults)
</script>

<template>
  <div class="sbp-page-container">
    
    <!-- ── TOP SUMMARY BAR ──
         Numbers reflect the user's RBAC-visible lab set. Labels are
         all-time totals (not month-filtered) so the reconciliation
         math `collected = submitted + pending` always balances. -->
    <div class="summary-bar-navy">
      <div class="sum-item">
        <div class="sum-label">Total Collected</div>
        <div class="sum-val">Rs <span>{{ formatMoney(summary.collected) }}</span></div>
      </div>
      <div class="sum-item">
        <div class="sum-label">Submitted to SBP</div>
        <div class="sum-val text-blue-light">Rs <span>{{ formatMoney(summary.submitted) }}</span></div>
      </div>
      <div class="sum-item">
        <div class="sum-label">Pending SBP</div>
        <div class="sum-val text-red-light">Rs <span>{{ formatMoney(summary.pending) }}</span></div>
      </div>
      <div class="sum-item">
        <div class="sum-label">Reconciliation</div>
        <div class="sum-val text-green-light">
          <span class="check">✓</span> {{ formatMoney(summary.collected) }} = {{ formatMoney(summary.submitted) }} + {{ formatMoney(summary.pending) }}
        </div>
      </div>
    </div>

    <!-- ── TOOLBAR ── -->
    <div class="sbp-toolbar">
      <div class="spacer"></div>
      <div class="actions">
        <button class="btn-new-sbp" @click="showSbpModal = true">+ New SBP Submission</button>
        <button class="btn-report" @click="showReconModal = true">
          <span class="icon">📊</span> Reconciliation Report
        </button>
      </div>
    </div>

    <!-- ── DATA TABLE ── -->
    <div class="sbp-table-wrap">
      <table class="sbp-table">
        <thead>
          <tr>
            <th>Submission ID</th>
            <th>Date</th>
            <th>Challan No.</th>
            <th>Amount (PKR)</th>
            <th>Lab</th>
            <th>Submitted By</th>
            <th>Invoices</th>
            <th class="text-center">Status</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <!-- Skeleton: 5 shimmer rows while loading. Matches the project
               pattern (DiariesDispatches / RolesPermissions / Invoices). -->
          <template v-if="loading">
            <tr v-for="r in 5" :key="'sk-' + r">
              <td v-for="c in 9" :key="'sk-' + r + '-' + c"><div class="sbp-skel"></div></td>
            </tr>
          </template>
          <template v-else>
            <tr v-for="sub in sbpSubmissions" :key="sub.id">
              <td class="mono-id">{{ sub.submission_slug }}</td>
              <td>{{ formatDate(sub.deposit_date) }}</td>
              <td class="mono-text">{{ sub.challan_no || '—' }}</td>
              <td class="fw-700">{{ formatNum(sub.amount) }}</td>
              <td>{{ sub.laboratory?.name || '—' }}</td>
              <td>{{ sub.submitted_by_name }}</td>
              <td>{{ sub.invoice_logs_count || 0 }} invoices</td>
              <td class="text-center">
                <span class="badge-verified" :class="sub.status">{{ sub.status === 'verified' ? 'Verified' : 'Submitted' }}</span>
              </td>
              <td class="text-center">
                <button
                  v-if="sub.status !== 'verified' && canVerify"
                  v-write="'verify_sbp_submissions'"
                  class="btn-verify"
                  :disabled="verifyingId === sub.id"
                  @click="verifySbp(sub)"
                >{{ verifyingId === sub.id ? '⏳ Verifying…' : '✓ Verify' }}</button>
                <span v-else class="locked-text">—</span>
              </td>
            </tr>
            <tr v-if="sbpSubmissions.length === 0">
              <td colspan="9" style="padding: 30px; text-align: center; color: #64748b; font-style: italic;">No submission history found.</td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- ── RECONCILIATION REPORT MODAL (EXACT MATCH) ── -->
    <Teleport to="body">
      <div v-if="showReconModal" class="sbp-modal-overlay report-overlay">
        <div class="sbp-modal-content report-content">
          <!-- Report Header -->
          <div class="report-header">
            <div class="header-left">
              <div class="title">📊 SBP Reconciliation Report</div>
              <div class="subtitle">Revenue collected vs. deposited to State Bank of Pakistan</div>
            </div>
            <div class="header-right">
              <button class="btn-print" @click="printReport">🖨 Print / PDF</button>
              <button class="btn-close-report" @click="showReconModal = false">✕ Close</button>
            </div>
          </div>

          <!-- Report Filters -->
          <div class="report-filters">
            <div class="filter-group">
              <label>From</label>
              <input type="date" v-model="reconFrom" />
            </div>
            <div class="filter-group">
              <label>To</label>
              <input type="date" v-model="reconTo" />
            </div>
            <div class="filter-group">
              <label>Lab</label>
              <select v-model="reconLabId">
                <option :value="''">All Labs</option>
                <option v-for="l in labs" :key="l.id" :value="l.id">{{ l.name }}</option>
              </select>
            </div>
            <button class="btn-generate" @click="fetchData">⚙ Generate</button>
          </div>

          <div class="report-scroll-area">
            <!-- Summary KPI Cards (Premium Style) -->
            <div class="recon-kpis">
              <div class="kpi-card kpi-blue">
                <div class="label">Total Invoiced</div>
                <div class="value">Rs {{ formatNum(reconStats.total) }}</div>
                <div class="sub">{{ reconStats.totalInvoices }} invoices</div>
              </div>
              <div class="kpi-card kpi-green">
                <div class="label">Submitted to SBP</div>
                <div class="value">Rs {{ formatNum(reconStats.submitted) }}</div>
                <div class="sub">{{ reconStats.submissionsCount }} submissions</div>
              </div>
              <div class="kpi-card kpi-orange">
                <div class="label">Pending Deposit</div>
                <div class="value">Rs {{ formatNum(reconStats.pending) }}</div>
                <div class="sub">{{ reconStats.pendingCount }} invoices</div>
              </div>
              <div class="kpi-card kpi-check">
                <div class="label">Balance Check</div>
                <div class="value green-text">✓ Balanced</div>
                <div class="sub">Invoiced = Submitted + Pending</div>
              </div>
            </div>

            <!-- Submission Log Section -->
            <div class="section-title">📁 Submission Log</div>
            <div class="report-table-box">
              <table class="report-table navy-head">
                <thead>
                  <tr>
                    <th>Submission ID</th>
                    <th>Deposit Date</th>
                    <th>Challan No.</th>
                    <th>Lab</th>
                    <th class="text-right">Amount (PKR)</th>
                    <th>Invoices</th>
                    <th>Submitted By</th>
                    <th class="text-center">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="sub in filteredSubmissionsReport" :key="sub.id">
                    <td class="mono-id">{{ sub.submission_slug }}</td>
                    <td>{{ formatDate(sub.deposit_date) }}</td>
                    <td class="mono">{{ sub.challan_no || 'Pending' }}</td>
                    <td>{{ sub.laboratory?.name || '—' }}</td>
                    <td class="text-right fw-700">{{ formatNum(sub.amount) }}</td>
                    <td>{{ sub.invoice_logs_count }} invoices</td>
                    <td>{{ sub.submitted_by_name }}</td>
                    <td class="text-center">
                      <span class="badge-status" :class="sub.status">{{ sub.status === 'verified' ? 'Verified' : 'Pending' }}</span>
                    </td>
                  </tr>
                  <!-- Total Row -->
                  <tr class="total-row">
                    <td colspan="4">TOTAL</td>
                    <td class="text-right">{{ formatNum(reconStats.submitted) }}</td>
                    <td colspan="3">{{ reconStats.totalInvoices - reconStats.pendingCount }} invoices</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Undeposited Invoices Section -->
            <div class="section-title mt-4">⌛ Undeposited Invoices <span class="muted">(not yet submitted to SBP)</span></div>
            <div class="report-table-box">
              <table class="report-table cream-rows">
                <thead>
                  <tr>
                    <th>Invoice No.</th>
                    <th>Date</th>
                    <th>Client</th>
                    <th>Lab</th>
                    <th class="text-right">Amount (PKR)</th>
                    <th>Days Outstanding</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="log in filteredPendingReport" :key="log.id">
                    <td class="mono-id">{{ log.water_sample_invoice?.water_sample?.slug }}</td>
                    <td>{{ formatDate(pickIso(log)) }}</td>
                    <td>{{ log.water_sample_invoice?.invoiceable?.name }}</td>
                    <td>{{ log.water_sample_invoice?.water_sample?.laboratory?.name || '—' }}</td>
                    <td class="text-right fw-700">{{ formatNum(log.paid) }}</td>
                    <td class="red-text fw-700">{{ getDaysDiff(pickIso(log)) }} days</td>
                  </tr>
                  <tr class="summary-footer">
                    <td colspan="4" class="fw-700 text-brown">Total Undeposited</td>
                    <td class="text-right fw-700 text-brown">{{ formatNum(reconStats.pending) }}</td>
                    <td></td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Report Footer Info -->
            <div class="report-footer-meta">
              Generated by: <strong>{{ userStore.currentUser?.name || '—' }}</strong>
              · Generated On: {{ formatDate(new Date()) }}
              · Lab: {{ reconLabId ? (labs.find(l => l.id === reconLabId)?.name || '—') : 'All Labs' }}
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── NEW SUBMISSION MODAL ── -->
    <Teleport to="body">
      <div v-if="showSbpModal" class="sbp-modal-overlay">
        <div class="sbp-modal-content">
          <div class="sbp-modal-header">
            <div class="header-info">
              <h3>🏛 New SBP Submission</h3>
              <p>Deposit revenue to State Bank of Pakistan</p>
            </div>
            <button @click="showSbpModal = false" class="btn-close">✕ Close</button>
          </div>

          <div class="sbp-modal-body">
            <div class="id-bar">
              <div class="id-left">Submission ID <em style="color:#64748b;font-weight:500">(auto-generated on save)</em></div>
              <div class="id-right">Date: <strong>{{ formatDate(new Date()) }}</strong></div>
            </div>

            <div class="sbp-form-grid">
              <div class="field">
                <label>Lab *</label>
                <select v-model="sbpLabId">
                  <option :value="null" disabled>Select lab…</option>
                  <option v-for="l in labs" :key="l.id" :value="l.id">{{ l.name }}</option>
                </select>
              </div>
              <div class="field">
                <label>Period *</label>
                <div class="range">
                  <input type="date" v-model="sbpPeriodFrom" />
                  <span>to</span>
                  <input type="date" v-model="sbpPeriodTo" />
                </div>
              </div>
              <div class="field">
                <label>Challan / TR No. *</label>
                <input type="text" v-model="sbpChallanNo" placeholder="From bank deposit slip" />
                <small class="field-hint">Enter the challan / TR number printed on the State Bank deposit slip.</small>
              </div>
              <div class="field">
                <label>Deposit Date *</label>
                <input type="date" v-model="sbpDepositDate" />
              </div>
              <div class="field">
                <label>Amount (PKR) * <span class="readonly-pill">auto</span></label>
                <div class="amount-display">
                  <span class="amount-prefix">₨</span>
                  <span class="amount-value">{{ formatNum(sbpSelectedTotal) }}</span>
                </div>
                <small class="field-hint">Sum of the receipts you tick below. Tick rows in "Invoices Included" to add them.</small>
              </div>
              <div class="field">
                <label>Submitted By *</label>
                <input type="text" v-model="sbpSubmittedBy" />
              </div>
            </div>

            <div class="invoice-selection-area">
              <label>Invoices Included in this Submission *</label>
              <div class="selection-table-box">
                <table>
                  <thead>
                    <tr>
                      <th style="width: 40px;"><input type="checkbox" v-model="allChecked" /></th>
                      <th>Invoice No.</th>
                      <th>Client</th>
                      <th class="text-right">Amount</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="log in filteredPendingLogs" :key="log.id">
                      <td><input type="checkbox" :value="log.id" v-model="selectedSbpLogs" /></td>
                      <td class="mono-bold">{{ log.water_sample_invoice?.water_sample?.slug }}</td>
                      <td>{{ log.water_sample_invoice?.invoiceable?.name }}</td>
                      <td class="text-right fw-700">Rs {{ formatNum(log.paid) }}</td>
                    </tr>
                    <tr v-if="filteredPendingLogs.length === 0">
                      <td colspan="4" class="empty">No pending collections found.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div class="selection-summary">
                Selected: <strong>{{ selectedSbpLogs.length }} invoices</strong> | Total: <strong>Rs {{ formatNum(sbpSelectedTotal) }}</strong>
              </div>
            </div>
          </div>

          <div class="sbp-modal-footer">
            <button class="btn-cancel" @click="showSbpModal = false">Cancel</button>
            <button v-write="'submit_sbp_submissions'" class="btn-save" @click="submitSbp" :disabled="selectedSbpLogs.length === 0 || sbpSubmitting">
              <span class="icon">💾</span> {{ sbpSubmitting ? 'Saving...' : 'Save Submission' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Toast notification (project-wide pattern). -->
    <Teleport to="body">
      <Transition name="toast-slide">
        <div v-if="toast.show"
             :style="`position:fixed;top:22px;right:24px;z-index:10000;min-width:300px;max-width:460px;
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

  </div>
</template>

<style scoped>
.sbp-page-container {
  padding: 0;
  font-family: 'Inter', sans-serif;
}

/* ── SUMMARY BAR — sized to match project standard ── */
.summary-bar-navy {
  background: #2b5292;
  color: #fff;
  display: flex;
  padding: 14px 0;
  border-radius: 6px;
  margin-bottom: 14px;
}
.sum-item { flex: 1; text-align: center; border-right: 1px solid rgba(255,255,255,0.1); }
.sum-item:last-child { border-right: none; }
.sum-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.7; margin-bottom: 5px; font-weight: 600; }
.sum-val { font-size: 21px; font-weight: 700; font-family: 'DM Mono', monospace; line-height: 1; }
.text-blue-light { color: #93c5fd; }
.text-red-light { color: #fca5a5; }
.text-green-light { color: #86efac; }
.check { font-size: 16px; margin-right: 6px; }

/* ── TOOLBAR ── */
.sbp-toolbar { display: flex; margin-bottom: 12px; }
.spacer { flex: 1; }
.actions { display: flex; gap: 8px; }
.btn-new-sbp { background: #1e40af; color: #fff; border: none; padding: 6px 14px; border-radius: 5px; font-weight: 700; font-size: 12.5px; cursor: pointer; transition: background 0.2s; }
.btn-new-sbp:hover { background: #1e3a8a; }
.btn-report { background: #fff; color: #1e293b; border: 1px solid #cbd5e1; padding: 6px 14px; border-radius: 5px; font-weight: 700; font-size: 12.5px; cursor: pointer; display: flex; align-items: center; gap: 6px; }
.btn-report .icon { font-size: 14px; }

/* ── TABLE ── */
.sbp-table-wrap { background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
.sbp-table { width: 100%; border-collapse: collapse; }
.sbp-table th { background: #203764; color: #fff; text-align: left; padding: 10px 12px; font-size: 11.5px; font-weight: 600; }
.sbp-table td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; font-size: 12.5px; color: #334155; }
.mono-id { font-family: 'DM Mono', monospace; font-weight: 700; color: #1e3a8a; }
.badge-verified { background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
.badge-verified.submitted { background: #fef3c7; color: #92400e; }
.locked-text { color: #94a3b8; font-weight: bold; }
.btn-verify { background: #16a34a; color: #fff; border: none; padding: 5px 12px; border-radius: 4px; font-weight: 700; font-size: 11.5px; cursor: pointer; }
.btn-verify:hover:not(:disabled) { background: #15803d; }
.btn-verify:disabled { background: #94a3b8; cursor: not-allowed; opacity: 0.7; }

/* ── RECONCILIATION MODAL (EXACT MATCH) ── */
.report-overlay { background: rgba(15, 23, 42, 0.75); padding: 12px; }
.report-content { width: 1280px; max-width: 98vw; max-height: 96vh; border-radius: 10px; display: flex; flex-direction: column; }
.report-header { background: #203764; color: #fff; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
.report-header .title { font-size: 18px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
.report-header .subtitle { font-size: 12px; opacity: 0.85; margin-top: 3px; }
.header-right { display: flex; gap: 10px; }
.btn-print { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: #fff; padding: 7px 16px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; }
.btn-close-report { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: #fff; padding: 7px 16px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; }

.report-filters { background: #f8fafc; padding: 16px 24px; display: flex; align-items: flex-end; gap: 20px; border-bottom: 1px solid #e2e8f0; }
.filter-group { display: flex; flex-direction: column; gap: 6px; }
.filter-group label { font-size: 12px; font-weight: 700; color: #64748b; }
.filter-group input, .filter-group select { padding: 8px 12px; border: 1.5px solid #cbd5e1; border-radius: 6px; font-size: 13.5px; min-width: 150px; }
.btn-generate { background: #2563eb; color: #fff; border: none; padding: 9px 18px; border-radius: 6px; font-weight: 700; font-size: 13px; cursor: pointer; }

.report-scroll-area { padding: 16px 18px; flex: 1; min-height: 0; overflow-y: auto; overflow-x: auto; background: #fff; }
.recon-kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 18px; }
.kpi-card { padding: 12px 14px; border-radius: 6px; border: 1px solid #e2e8f0; text-align: center; }
.kpi-blue { background: #eff6ff; border-color: #bfdbfe; color: #1e3a8a; }
.kpi-green { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
.kpi-orange { background: #fff7ed; border-color: #fed7aa; color: #9a3412; }
.kpi-check { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
.kpi-card .label { font-size: 10px; font-weight: 700; opacity: 0.7; text-transform: uppercase; margin-bottom: 5px; }
.kpi-card .value { font-size: 18px; font-weight: 800; margin-bottom: 3px; line-height: 1; }
.kpi-card .sub { font-size: 11px; opacity: 0.8; }
.green-text { color: #10b981; }

.section-title { font-size: 15px; font-weight: 800; color: #1e293b; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
.muted { font-size: 13px; font-weight: 400; color: #b91c1c; }
.report-table-box { border: 1px solid #e2e8f0; border-radius: 8px; overflow-x: auto; margin-bottom: 20px; }
.report-table { width: 100%; border-collapse: collapse; font-size: 12.5px; min-width: 900px; }
.report-table th { background: #f8fafc; padding: 9px 12px; text-align: left; color: #475569; font-weight: 700; border-bottom: 1.5px solid #e2e8f0; white-space: nowrap; }
.report-table td { padding: 7px 12px; border-bottom: 1px solid #f1f5f9; color: #334155; }
.navy-head th { background: #203764; color: #fff; border-bottom: none; }
.total-row { background: #2b5292; color: #fff; font-weight: 800; font-size: 14px; }
.total-row td { padding: 14px 16px; border: none; }
.cream-rows tr:nth-child(odd) { background: #fff; }
.cream-rows tr:nth-child(even) { background: #fffcf0; }
.summary-footer { background: #fff7ed !important; }
.text-brown { color: #92400e; }
.red-text { color: #b91c1c; }
.badge-status { padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
.badge-status.verified { background: #dcfce7; color: #15803d; }
.badge-status.pending { background: #fef3c7; color: #92400e; }

.report-footer-meta { margin-top: 30px; padding-top: 16px; border-top: 1px solid #f1f5f9; font-size: 12px; color: #64748b; text-align: left; }

/* ── OTHER MODALS — sized down to match other module modals ── */
.sbp-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 9999; padding: 20px; }
.sbp-modal-content { background: #fff; width: 720px; max-width: 95vw; border-radius: 8px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
.sbp-modal-header { background: #203764; color: #fff; padding: 12px 18px; display: flex; justify-content: space-between; align-items: center; }
.sbp-modal-header h3 { margin: 0; font-size: 15px; }
.sbp-modal-header p { margin: 3px 0 0; font-size: 11px; opacity: 0.8; }
.btn-close { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: #fff; padding: 4px 10px; border-radius: 5px; cursor: pointer; font-size: 12px; }
.sbp-modal-body { padding: 16px 18px; max-height: 72vh; overflow-y: auto; }
.id-bar { background: #f1f5f9; padding: 8px 14px; border-radius: 5px; display: flex; justify-content: space-between; margin-bottom: 14px; border: 1px solid #e2e8f0; font-size: 12px; }
.sbp-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 18px; margin-bottom: 14px; }
.field label { display: block; font-size: 11.5px; font-weight: 700; color: #475569; margin-bottom: 4px; }
.field input, .field select, .field textarea { width: 100%; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 12.5px; }
.field-hint { display: block; margin-top: 3px; font-size: 10.5px; color: #64748b; line-height: 1.35; }
.readonly-pill { display: inline-block; margin-left: 6px; font-size: 9.5px; font-weight: 700; color: #1e3a8a; background: #dbeafe; padding: 1px 6px; border-radius: 8px; text-transform: uppercase; letter-spacing: 0.04em; vertical-align: middle; }
.amount-display { display: flex; align-items: baseline; gap: 6px; padding: 7px 10px; border: 1px dashed #cbd5e1; border-radius: 4px; background: #f8fafc; min-height: 32px; }
.amount-prefix { font-size: 13px; font-weight: 600; color: #64748b; }
.amount-value { font-size: 16px; font-weight: 800; color: #1e3a8a; font-family: 'DM Mono', monospace; }
.range { display: flex; align-items: center; gap: 8px; }
.sbp-modal-footer { padding: 10px 18px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 8px; }
.btn-cancel { background: #fff; border: 1px solid #d1d5db; padding: 6px 14px; border-radius: 5px; cursor: pointer; font-weight: 600; font-size: 12.5px; }
.btn-save { background: #2563eb; color: #fff; border: none; padding: 6px 14px; border-radius: 5px; cursor: pointer; font-weight: 700; display: flex; gap: 6px; font-size: 12.5px; align-items: center; }
.btn-save:disabled { opacity: 0.6; cursor: not-allowed; }

.mt-4 { margin-top: 24px; }
.fw-800 { font-weight: 800; }
.text-right { text-align: right; }
.text-center { text-align: center; }
.mono { font-family: monospace; }

/* Skeleton shimmer for the SBP table while loading — matches the
   pattern used in DiariesDispatches / RolesPermissions / Invoices. */
.sbp-skel {
  background: linear-gradient(90deg, #f1f5f9 0%, #e2e8f0 50%, #f1f5f9 100%);
  background-size: 200% 100%;
  animation: sbp-shimmer 1.4s infinite ease-in-out;
  border-radius: 4px;
  width: 100%;
  height: 12px;
}
@keyframes sbp-shimmer {
  0%   { background-position: -200% 0; }
  100% { background-position: 200% 0; }
}
</style>

<!-- Non-scoped: Teleported toast lives on document.body and won't pick up
     scoped styles. Match the project-wide toast-slide transition. -->
<style>
.toast-slide-enter-active,
.toast-slide-leave-active { transition: all 0.3s ease; }
.toast-slide-enter-from,
.toast-slide-leave-to { opacity: 0; transform: translateX(100%); }

/* ── Print / PDF stylesheet for the Reconciliation Report ──────────────
   When printReport() runs we add `.sbp-print-mode` to <body>, which hides
   the app shell and the modal chrome, and lifts the report content out of
   its fixed-position overlay so the browser print engine paginates it. */
@media print {
  body.sbp-print-mode > *:not(.sbp-modal-overlay) { display: none !important; }
  body.sbp-print-mode .sbp-modal-overlay:not(.report-overlay) { display: none !important; }
  body.sbp-print-mode .report-overlay {
    position: static !important;
    background: #fff !important;
    padding: 0 !important;
    inset: auto !important;
  }
  body.sbp-print-mode .report-content {
    width: 100% !important;
    max-width: 100% !important;
    max-height: none !important;
    box-shadow: none !important;
    border-radius: 0 !important;
    display: block !important;
  }
  body.sbp-print-mode .report-header {
    background: #fff !important;
    color: #000 !important;
    border-bottom: 2px solid #000 !important;
  }
  body.sbp-print-mode .report-header .title,
  body.sbp-print-mode .report-header .subtitle { color: #000 !important; }
  /* Hide controls that are noise on paper */
  body.sbp-print-mode .header-right,
  body.sbp-print-mode .report-filters { display: none !important; }
  body.sbp-print-mode .report-scroll-area {
    max-height: none !important;
    overflow: visible !important;
    padding: 16px 0 !important;
  }
  body.sbp-print-mode .report-table-box {
    overflow: visible !important;
    page-break-inside: avoid;
  }
  body.sbp-print-mode .report-table { min-width: 0 !important; font-size: 11px !important; }
  body.sbp-print-mode .navy-head th { background: #1e3a8a !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  body.sbp-print-mode .recon-kpis { page-break-inside: avoid; }
  body.sbp-print-mode .kpi-card { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
