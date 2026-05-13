<script setup>
import { ref, computed, onMounted } from 'vue'
import { clientPortalService } from '../../services/clientPortalService.js'

const loading  = ref(true)
const errorMsg = ref('')
const invoices = ref([])

async function load() {
  loading.value  = true
  errorMsg.value = ''
  try {
    const res = await clientPortalService.getInvoices()
    invoices.value = res.data || res
  } catch (e) {
    errorMsg.value = e.response?.data?.message || 'Failed to load billing data.'
  } finally {
    loading.value = false
  }
}

const totalBilled  = computed(() => invoices.value.reduce((s, i) => s + (Number(i.price)      || 0), 0))
const totalPaid    = computed(() => invoices.value.reduce((s, i) => s + (Number(i.net_amount)  || 0), 0))
const totalBalance = computed(() => invoices.value.reduce((s, i) => s + (Number(i.balance)     || 0), 0))

function statusClass(status) {
  if (!status) return 'badge-pending'
  const s = String(status).toLowerCase()
  if (s === 'paid')    return 'badge-fit'
  if (s === 'partial') return 'badge-amber'
  if (s === 'unpaid')  return 'badge-unfit'
  return 'badge-pending'
}

function resultClass(result) {
  if (!result) return 'badge-pending'
  const r = String(result).toLowerCase()
  if (r === 'fit'   || r === '1') return 'badge-fit'
  if (r === 'unfit' || r === '2') return 'badge-unfit'
  return 'badge-pending'
}
function resultLabel(result) {
  if (!result) return 'Pending'
  const r = String(result).toLowerCase()
  if (r === 'fit'   || r === '1') return 'Fit'
  if (r === 'unfit' || r === '2') return 'Unfit'
  return result
}

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function fmtCurrency(val) {
  if (val == null) return '—'
  return 'PKR ' + Number(val).toLocaleString('en-PK', { minimumFractionDigits: 0 })
}

onMounted(load)
</script>

<template>
  <div class="cb-page">
    <!-- Page header -->
    <div class="cp-page-header">
      <div>
        <h1 class="cp-page-title">Billing & Invoices</h1>
        <p class="cp-page-sub">Track your test charges, payments and outstanding balances</p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="cp-loading">
      <div class="cp-spinner"></div>
      <span>Loading billing data…</span>
    </div>

    <!-- Error -->
    <div v-else-if="errorMsg" class="cp-alert cp-alert--error">
      <span>⚠️</span> {{ errorMsg }}
    </div>

    <template v-else>
      <!-- Empty -->
      <div v-if="!invoices.length" class="cp-empty">
        <div class="cp-empty-icon">💳</div>
        <div class="cp-empty-title">No invoices yet</div>
        <div class="cp-empty-sub">Your billing records will appear here once invoices are generated.</div>
      </div>

      <template v-else>
        <!-- Summary cards -->
        <div class="cb-summary">
          <div class="cb-summary-card cb-summary-card--blue">
            <div class="cb-summary-icon">🧾</div>
            <div class="cb-summary-lbl">Total Billed</div>
            <div class="cb-summary-val">{{ fmtCurrency(totalBilled) }}</div>
          </div>
          <div class="cb-summary-card cb-summary-card--green">
            <div class="cb-summary-icon">✅</div>
            <div class="cb-summary-lbl">Total Paid</div>
            <div class="cb-summary-val">{{ fmtCurrency(totalPaid) }}</div>
          </div>
          <div class="cb-summary-card" :class="totalBalance > 0 ? 'cb-summary-card--red' : 'cb-summary-card--grey'">
            <div class="cb-summary-icon">{{ totalBalance > 0 ? '⚠️' : '🎉' }}</div>
            <div class="cb-summary-lbl">Outstanding Balance</div>
            <div class="cb-summary-val">{{ fmtCurrency(totalBalance) }}</div>
          </div>
        </div>

        <!-- Table -->
        <div class="cp-card">
          <div class="cp-table-wrap">
            <table class="cp-table">
              <thead>
                <tr>
                  <th>Invoice / Sample</th>
                  <th>Sample Name</th>
                  <th>Sampled</th>
                  <th>Test Result</th>
                  <th style="text-align:right">Amount</th>
                  <th style="text-align:right">Discount</th>
                  <th style="text-align:right">Paid</th>
                  <th style="text-align:right">Balance</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="inv in invoices" :key="inv.id" class="cp-table-row">
                  <td><span class="cp-mono">{{ inv.slug || ('INV-' + inv.id) }}</span></td>
                  <td class="cp-muted">{{ inv.sample_name || '—' }}</td>
                  <td class="cp-mono">{{ fmtDate(inv.sampled_at) }}</td>
                  <td><span class="cp-badge" :class="resultClass(inv.result)">{{ resultLabel(inv.result) }}</span></td>
                  <td class="cp-mono" style="text-align:right">{{ fmtCurrency(inv.price) }}</td>
                  <td class="cp-mono cp-muted" style="text-align:right">
                    {{ inv.discount_percentage ? inv.discount_percentage + '%' : '—' }}
                  </td>
                  <td class="cp-mono" style="text-align:right;color:#16a34a;font-weight:600">
                    {{ fmtCurrency(inv.net_amount) }}
                  </td>
                  <td class="cp-mono" style="text-align:right"
                      :style="Number(inv.balance) > 0 ? 'color:#dc2626;font-weight:700' : 'color:#64748b'">
                    {{ fmtCurrency(inv.balance) }}
                  </td>
                  <td><span class="cp-badge" :class="statusClass(inv.status)">{{ inv.status || 'Unpaid' }}</span></td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="cb-tfoot">
                  <td colspan="4">TOTAL</td>
                  <td style="text-align:right">{{ fmtCurrency(totalBilled) }}</td>
                  <td></td>
                  <td style="text-align:right">{{ fmtCurrency(totalPaid) }}</td>
                  <td style="text-align:right" :style="totalBalance > 0 ? 'color:#fca5a5' : ''">
                    {{ fmtCurrency(totalBalance) }}
                  </td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </template>
    </template>
  </div>
</template>

<style scoped>
.cb-page { display: flex; flex-direction: column; gap: 20px; }

.cb-summary {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 14px;
}
.cb-summary-card {
  border-radius: 12px;
  padding: 20px 22px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  box-shadow: 0 2px 8px rgba(0,0,0,.07);
}
.cb-summary-card--blue  { background: linear-gradient(135deg, #eff6ff, #dbeafe); border: 1px solid #bfdbfe; }
.cb-summary-card--green { background: linear-gradient(135deg, #f0fdf4, #dcfce7); border: 1px solid #86efac; }
.cb-summary-card--red   { background: linear-gradient(135deg, #fef2f2, #fee2e2); border: 1px solid #fca5a5; }
.cb-summary-card--grey  { background: linear-gradient(135deg, #f8fafc, #f1f5f9); border: 1px solid #e2e8f0; }
.cb-summary-icon { font-size: 22px; }
.cb-summary-lbl  { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: .5px; font-weight: 600; }
.cb-summary-val  { font-size: 20px; font-weight: 800; color: #0f2d5e; letter-spacing: -.3px; }

.cb-tfoot td {
  background: #0f2d5e;
  color: #fff;
  font-weight: 700;
  font-size: 12px;
  padding: 10px 14px;
}
</style>
