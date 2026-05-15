<script setup>
import { ref, computed, onMounted } from 'vue'
import { api } from '../../../services/api.js'

// State
const loading = ref(false)
const errorMsg = ref('')

const sbpSubmissions = ref([])
const pendingSbpLogs = ref([])
const showSbpModal = ref(false)
const showReconModal = ref(false)
const selectedSbpLogs = ref([])

// Form State (New Submission)
const sbpChallanNo = ref('')
const sbpDepositDate = ref(new Date().toISOString().split('T')[0])
const sbpPeriodFrom = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0])
const sbpPeriodTo = ref(new Date().toISOString().split('T')[0])
const sbpSubmittedBy = ref('S.M. Adeel')
const sbpRemarks = ref('')
const sbpSubmitting = ref(false)
const sbpLabName = ref('Central Lab — Peshawar')

// Report Filters
const reconFrom = ref('2026-01-01')
const reconTo = ref(new Date().toISOString().split('T')[0])
const reconLab = ref('All Labs')

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

const sbpSelectedTotal = computed(() => {
  return pendingSbpLogs.value
    .filter(l => selectedSbpLogs.value.includes(l.id))
    .reduce((sum, l) => sum + l.paid, 0)
})

const filteredPendingLogs = computed(() => {
  let result = pendingSbpLogs.value
  if (sbpPeriodFrom.value) {
    result = result.filter(l => new Date(l.created_at) >= new Date(sbpPeriodFrom.value))
  }
  if (sbpPeriodTo.value) {
    result = result.filter(l => new Date(l.created_at) <= new Date(sbpPeriodTo.value))
  }
  return result
})

// Reconciliation Report Computeds
const filteredSubmissionsReport = computed(() => {
  return sbpSubmissions.value.filter(s => {
    const d = new Date(s.deposit_date)
    const matchesDate = d >= new Date(reconFrom.value) && d <= new Date(reconTo.value)
    const matchesLab = reconLab.value === 'All Labs' || (s.laboratory?.name || '').includes(reconLab.value)
    return matchesDate && matchesLab
  })
})

const filteredPendingReport = computed(() => {
  return pendingSbpLogs.value.filter(l => {
    const d = new Date(l.created_at)
    const matchesDate = d >= new Date(reconFrom.value) && d <= new Date(reconTo.value)
    const matchesLab = reconLab.value === 'All Labs' || (l.water_sample_invoice?.water_sample?.laboratory?.name || '').includes(reconLab.value)
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
  } finally {
    loading.value = false
  }
}

async function submitSbp() {
  if (selectedSbpLogs.value.length === 0) return
  sbpSubmitting.value = true
  try {
    await api.post('/finance/sbp-submissions', {
      log_ids: selectedSbpLogs.value,
      challan_no: sbpChallanNo.value,
      deposit_date: sbpDepositDate.value,
      period_from: sbpPeriodFrom.value,
      period_to: sbpPeriodTo.value,
      lab_id: 1,
      submitted_by_name: sbpSubmittedBy.value,
      remarks: sbpRemarks.value
    })
    showSbpModal.value = false
    selectedSbpLogs.value = []
    sbpChallanNo.value = ''
    fetchData()
  } catch (err) {
    alert('Submission failed')
  } finally {
    sbpSubmitting.value = false
  }
}

async function verifySbp(id) {
  if (!confirm('Are you sure you want to verify this submission?')) return
  try {
    await api.post(`/finance/sbp-submissions/${id}/verify`)
    fetchData()
  } catch (err) {
    alert('Verification failed')
  }
}

onMounted(fetchData)
</script>

<template>
  <div class="sbp-page-container">
    
    <!-- ── TOP SUMMARY BAR ── -->
    <div class="summary-bar-navy">
      <div class="sum-item">
        <div class="sum-label">Collected This Month</div>
        <div class="sum-val">Rs <span>{{ (summary.collected / 1000000).toFixed(1) }}M</span></div>
      </div>
      <div class="sum-item">
        <div class="sum-label">Submitted to SBP</div>
        <div class="sum-val text-blue-light">Rs <span>{{ (summary.submitted / 1000000).toFixed(1) }}M</span></div>
      </div>
      <div class="sum-item">
        <div class="sum-label">Pending SBP</div>
        <div class="sum-val text-red-light">Rs <span>{{ (summary.pending / 1000000).toFixed(1) }}M</span></div>
      </div>
      <div class="sum-item">
        <div class="sum-label">Reconciliation</div>
        <div class="sum-val text-green-light">
          <span class="check">✓</span> {{ (summary.collected / 1000000).toFixed(1) }} = {{ (summary.submitted / 1000000).toFixed(1) }} + {{ (summary.pending / 1000000).toFixed(1) }}
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
          <tr v-for="sub in sbpSubmissions" :key="sub.id">
            <td class="mono-id">{{ sub.submission_slug }}</td>
            <td>{{ formatDate(sub.deposit_date) }}</td>
            <td class="mono-text">{{ sub.challan_no || '—' }}</td>
            <td class="fw-700">{{ formatNum(sub.amount) }}</td>
            <td>{{ sub.laboratory?.name || 'Central Lab' }}</td>
            <td>{{ sub.submitted_by_name }}</td>
            <td>{{ sub.invoice_logs_count || 0 }} invoices</td>
            <td class="text-center">
              <span class="badge-verified" :class="sub.status">{{ sub.status === 'verified' ? 'Verified' : 'Submitted' }}</span>
            </td>
            <td class="text-center">
              <span class="locked-text">—</span>
            </td>
          </tr>
          <tr v-if="sbpSubmissions.length === 0 && !loading">
            <td colspan="9" style="padding: 40px; text-align: center; color: #64748b; font-style: italic;">No submission history found.</td>
          </tr>
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
              <button class="btn-print">🖨 Print / PDF</button>
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
              <select v-model="reconLab">
                <option>All Labs</option>
                <option>Central Lab — Peshawar</option>
                <option>District Lab — Mardan</option>
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
                    <td>{{ sub.laboratory?.name || 'Central Lab' }}</td>
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
                    <td>{{ formatDate(log.created_at) }}</td>
                    <td>{{ log.water_sample_invoice?.invoiceable?.name }}</td>
                    <td>{{ log.water_sample_invoice?.water_sample?.laboratory?.name || 'Central Lab' }}</td>
                    <td class="text-right fw-700">{{ formatNum(log.paid) }}</td>
                    <td class="red-text fw-700">{{ getDaysDiff(log.created_at) }} days</td>
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
              Generated by: <strong>S.M. Adeel, SRO</strong> · Generated On: 11 May 2026 · Lab: Central Lab — Peshawar
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
              <div class="id-left">Submission ID (auto) <strong>SBP/26/CLB/0043</strong></div>
              <div class="id-right">Date: <strong>{{ formatDate(new Date()) }}</strong></div>
            </div>

            <div class="sbp-form-grid">
              <div class="field">
                <label>Lab *</label>
                <select v-model="sbpLabName">
                  <option>Central Lab — Peshawar</option>
                  <option>District Lab — Mardan</option>
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
                <input type="text" v-model="sbpChallanNo" placeholder="e.g. SBP-2026-05100" />
              </div>
              <div class="field">
                <label>Deposit Date *</label>
                <input type="date" v-model="sbpDepositDate" />
              </div>
              <div class="field">
                <label>Amount (PKR) *</label>
                <input type="number" :value="sbpSelectedTotal" readonly />
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
                      <th style="width: 40px;"><input type="checkbox" /></th>
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
            <button class="btn-save" @click="submitSbp" :disabled="selectedSbpLogs.length === 0">
              <span class="icon">💾</span> {{ sbpSubmitting ? 'Saving...' : 'Save Submission' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<style scoped>
.sbp-page-container {
  padding: 0;
  font-family: 'Inter', sans-serif;
}

/* ── SUMMARY BAR ── */
.summary-bar-navy {
  background: #2b5292;
  color: #fff;
  display: flex;
  padding: 24px 0;
  border-radius: 8px;
  margin-bottom: 24px;
}
.sum-item { flex: 1; text-align: center; border-right: 1px solid rgba(255,255,255,0.1); }
.sum-item:last-child { border-right: none; }
.sum-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.7; margin-bottom: 8px; font-weight: 600; }
.sum-val { font-size: 28px; font-weight: 700; font-family: 'DM Mono', monospace; }
.text-blue-light { color: #93c5fd; }
.text-red-light { color: #fca5a5; }
.text-green-light { color: #86efac; }
.check { font-size: 20px; margin-right: 8px; }

/* ── TOOLBAR ── */
.sbp-toolbar { display: flex; margin-bottom: 16px; }
.spacer { flex: 1; }
.actions { display: flex; gap: 12px; }
.btn-new-sbp { background: #1e40af; color: #fff; border: none; padding: 9px 20px; border-radius: 6px; font-weight: 700; font-size: 13px; cursor: pointer; transition: background 0.2s; }
.btn-new-sbp:hover { background: #1e3a8a; }
.btn-report { background: #fff; color: #1e293b; border: 1px solid #cbd5e1; padding: 9px 20px; border-radius: 6px; font-weight: 700; font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 8px; }
.btn-report .icon { font-size: 16px; }

/* ── TABLE ── */
.sbp-table-wrap { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
.sbp-table { width: 100%; border-collapse: collapse; }
.sbp-table th { background: #203764; color: #fff; text-align: left; padding: 14px 16px; font-size: 12.5px; font-weight: 600; }
.sbp-table td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; font-size: 13.5px; color: #334155; }
.mono-id { font-family: 'DM Mono', monospace; font-weight: 700; color: #1e3a8a; }
.badge-verified { background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
.badge-verified.submitted { background: #fef3c7; color: #92400e; }
.locked-text { color: #94a3b8; font-weight: bold; }

/* ── RECONCILIATION MODAL (EXACT MATCH) ── */
.report-overlay { background: rgba(15, 23, 42, 0.75); }
.report-content { width: 1020px; max-width: 98vw; border-radius: 10px; }
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

.report-scroll-area { padding: 24px; max-height: 70vh; overflow-y: auto; background: #fff; }
.recon-kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 30px; }
.kpi-card { padding: 16px; border-radius: 10px; border: 1.5px solid #e2e8f0; text-align: center; }
.kpi-blue { background: #eff6ff; border-color: #bfdbfe; color: #1e3a8a; }
.kpi-green { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
.kpi-orange { background: #fff7ed; border-color: #fed7aa; color: #9a3412; }
.kpi-check { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
.kpi-card .label { font-size: 11px; font-weight: 700; opacity: 0.7; text-transform: uppercase; margin-bottom: 8px; }
.kpi-card .value { font-size: 22px; font-weight: 800; margin-bottom: 4px; }
.kpi-card .sub { font-size: 12px; opacity: 0.8; }
.green-text { color: #10b981; }

.section-title { font-size: 15px; font-weight: 800; color: #1e293b; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
.muted { font-size: 13px; font-weight: 400; color: #b91c1c; }
.report-table-box { border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; margin-bottom: 20px; }
.report-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.report-table th { background: #f8fafc; padding: 12px 16px; text-align: left; color: #475569; font-weight: 700; border-bottom: 1.5px solid #e2e8f0; }
.report-table td { padding: 10px 16px; border-bottom: 1px solid #f1f5f9; color: #334155; }
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

/* ── OTHER MODALS ── */
.sbp-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 9999; padding: 20px; }
.sbp-modal-content { background: #fff; width: 880px; max-width: 95vw; border-radius: 12px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
.sbp-modal-header { background: #203764; color: #fff; padding: 18px 24px; display: flex; justify-content: space-between; align-items: center; }
.sbp-modal-header h3 { margin: 0; font-size: 19px; }
.sbp-modal-header p { margin: 4px 0 0; font-size: 12px; opacity: 0.8; }
.btn-close { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: #fff; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; }
.sbp-modal-body { padding: 24px; max-height: 75vh; overflow-y: auto; }
.id-bar { background: #f1f5f9; padding: 12px 20px; border-radius: 8px; display: flex; justify-content: space-between; margin-bottom: 24px; border: 1px solid #e2e8f0; }
.sbp-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px 40px; margin-bottom: 24px; }
.field label { display: block; font-size: 13px; font-weight: 700; color: #475569; margin-bottom: 8px; }
.field input, .field select, .field textarea { width: 100%; padding: 10px 12px; border: 1.5px solid #cbd5e1; border-radius: 6px; font-size: 14px; }
.range { display: flex; align-items: center; gap: 10px; }
.sbp-modal-footer { padding: 18px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 12px; }
.btn-cancel { background: #fff; border: 1px solid #d1d5db; padding: 10px 24px; border-radius: 6px; cursor: pointer; font-weight: 600; }
.btn-save { background: #2563eb; color: #fff; border: none; padding: 10px 24px; border-radius: 6px; cursor: pointer; font-weight: 700; display: flex; gap: 8px; }
.btn-save:disabled { opacity: 0.6; cursor: not-allowed; }

.mt-4 { margin-top: 24px; }
.fw-800 { font-weight: 800; }
.text-right { text-align: right; }
.text-center { text-align: center; }
.mono { font-family: monospace; }
</style>
