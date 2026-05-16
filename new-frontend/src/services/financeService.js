import { api } from './api.js'

export const financeService = {
  // ── Revenue register / ledger / dues ────────────────────────────────────
  getFinanceInvoices: (params = {}) => api.get('/finance/invoices', { params }),
  getFinanceLedger:    ()             => api.get('/finance/ledger'),
  getFinanceDues:      (params = {})  => api.get('/finance/dues', { params }),

  // F-05 — Revenue Summary Report endpoint
  getRevenueSummary:   (params = {})  => api.get('/finance/revenue-summary', { params }),

  // F-06 / F-07 — Dashboard cards
  getDashboardCard:    (params = {})  => api.get('/finance/dashboard-card', { params }),

  // F-08 — Clubbed Invoice wizard
  getClientsWithUnbilled: () => api.get('/finance/clients-with-unbilled'),
  getUnbilledByClient:    (clientId, params = {}) =>
    api.get(`/finance/unbilled-by-client/${clientId}`, { params }),
  createClubbedInvoice:   (data) => api.post('/finance/clubbed-invoice', data),

  // F-03 — Record payment with full audit fields
  recordPayment: (invoiceId, data) => api.post(`/finance/record-payment/${invoiceId}`, data),

  // F-10 / F-12 — Clubbed invoice PDF (returns binary)
  downloadClubbedInvoicePdf: (invoiceId) =>
    api.get(`/finance/clubbed-invoices/${invoiceId}/pdf`, { responseType: 'blob' }),

  // F-18 — Real .xlsx export
  exportRevenueXlsx: (params = {}) =>
    api.get('/finance/invoices/export', { params, responseType: 'blob' }),

  // F-17 — Discount admin
  getDiscount:    () => api.get('/finance/discount'),
  updateDiscount: (value) => api.put('/finance/discount', { value }),

  // SBP submissions
  getSbpSubmissions: () => api.get('/finance/sbp-submissions'),
  getPendingSbpLogs: () => api.get('/finance/sbp-submissions/pending'),
  createSbpSubmission: (data) => api.post('/finance/sbp-submissions', data),
  verifySbpSubmission: (id) => api.post(`/finance/sbp-submissions/${id}/verify`),

  // ── Legacy / Water Sample Invoice endpoints ─────────────────────────────
  getInvoices:    (params = {}) => api.post('/search-water-sample-invoices', params),
  getInvoice:     (id) => api.get(`/water-sample-invoices/${id}`),
  updateInvoice:  (id, data) => api.put(`/water-sample-invoices/${id}`, data),

  // General Invoices (non-water-sample)
  getGeneralInvoices:    () => api.get('/invoices'),
  getGeneralInvoice:     (id) => api.get(`/invoices/${id}`),
  createGeneralInvoice:  (data) => api.post('/invoices', data),
  updateGeneralInvoice:  (id, data) => api.put(`/invoices/${id}`, data),

  // Payments (purchase-order side)
  getPayments:     () => api.get('/payments'),
  searchPayments:  (params = {}) => api.post('/search-payment', params),
  createPayment:   (data) => api.post('/payments', data),
  getPayment:      (id) => api.get(`/payments/${id}`),
  generatePaymentPdf: (id) => api.get(`/payment/${id}/pdf-report`),

  // Legacy CSV-style export (kept for backwards compat — prefer exportRevenueXlsx)
  exportInvoices: (params = {}) => api.post('/export-water-sample-invoices', params),
}
