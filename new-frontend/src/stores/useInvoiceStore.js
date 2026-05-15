import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { financeService } from '../services/financeService.js'

/**
 * D-06 — Mock data removed.
 *
 * Previously this store carried six hard-coded invoice rows plus mock SBP
 * submissions and ledger entries, while parallel views (Invoices.vue) were
 * already calling the live API. Risk: any view that imported the store
 * would silently show stale demo data in production.
 *
 * Now the store is the single source of truth and pulls from the Finance
 * Module API. Views may import it instead of calling financeService
 * directly.
 */
export const useInvoiceStore = defineStore('invoices', () => {
  const invoices = ref([])
  const sbpSubmissions = ref([])
  const ledger = ref([])
  const summary = ref({
    total_invoiced: 0,
    total_collected: 0,
    total_outstanding: 0,
    submitted_to_sbp: 0,
    pending_sbp: 0,
  })
  const loading = ref(false)
  const error = ref(null)

  const totals = computed(() => ({
    invoiced:    summary.value.total_invoiced,
    collected:   summary.value.total_collected,
    outstanding: summary.value.total_outstanding,
  }))

  async function fetchInvoices(params = {}) {
    loading.value = true
    error.value = null
    try {
      const res = await financeService.getFinanceInvoices(params)
      invoices.value = res?.data?.invoices ?? []
      // F-05 summary uses revenue-summary when available, else light summary.
      try {
        const sum = await financeService.getRevenueSummary(params)
        summary.value = sum?.data ?? summary.value
      } catch (_) {
        summary.value = { ...summary.value, ...(res?.data?.summary ?? {}) }
      }
    } catch (e) {
      error.value = e?.message ?? 'Failed to load invoices'
    } finally {
      loading.value = false
    }
  }

  async function fetchLedger() {
    loading.value = true
    try {
      const res = await financeService.getFinanceLedger()
      ledger.value = res?.data ?? []
    } finally {
      loading.value = false
    }
  }

  async function fetchSbp() {
    loading.value = true
    try {
      const res = await financeService.getSbpSubmissions()
      sbpSubmissions.value = res?.data ?? res ?? []
    } finally {
      loading.value = false
    }
  }

  async function recordPayment(invoiceId, payload) {
    await financeService.recordPayment(invoiceId, payload)
    await fetchInvoices()
  }

  async function createClubbedInvoice(payload) {
    const res = await financeService.createClubbedInvoice(payload)
    await fetchInvoices()
    return res
  }

  return {
    invoices, sbpSubmissions, ledger, summary, totals, loading, error,
    fetchInvoices, fetchLedger, fetchSbp,
    recordPayment, createClubbedInvoice,
  }
})
