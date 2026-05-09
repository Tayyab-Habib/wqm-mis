import { api } from './api.js'

export const financeService = {
  // ── Finance Module (new dedicated endpoints) ──────────────────────────
  /** GET /api/finance/invoices — returns { invoices, summary } */
  getFinanceInvoices: (params = {}) => api.get('/finance/invoices', { params }),

  /** GET /api/finance/ledger — returns double-entry ledger rows */
  getFinanceLedger: () => api.get('/finance/ledger'),

  /** GET /api/finance/dues — returns overdue invoices */
  getFinanceDues: (params = {}) => api.get('/finance/dues', { params }),

  /** POST /api/finance/record-payment/{id} — record payment against invoice */
  recordPayment: (invoiceId, data) => api.post(`/finance/record-payment/${invoiceId}`, data),

  // ── Legacy / Water Sample Invoice endpoints (kept for other views) ────
  getInvoices: (params = {}) => api.post('/search-water-sample-invoices', params),
  getInvoice: (id) => api.get(`/water-sample-invoices/${id}`),
  updateInvoice: (id, data) => api.put(`/water-sample-invoices/${id}`, data),

  // General Invoices (non-water-sample)
  getGeneralInvoices: () => api.get('/invoices'),
  getGeneralInvoice: (id) => api.get(`/invoices/${id}`),
  createGeneralInvoice: (data) => api.post('/invoices', data),
  updateGeneralInvoice: (id, data) => api.put(`/invoices/${id}`, data),

  // Payments
  getPayments: () => api.get('/payments'),
  searchPayments: (params = {}) => api.post('/search-payment', params),
  createPayment: (data) => api.post('/payments', data),
  getPayment: (id) => api.get(`/payments/${id}`),
  generatePaymentPdf: (id) => api.get(`/payment/${id}/pdf-report`),

  // Export
  exportInvoices: (params = {}) => api.post('/export-water-sample-invoices', params),
}

