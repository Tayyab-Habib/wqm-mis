import { api } from './api.js'

export const financeService = {
  // Water Sample Invoices
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

  // Export invoices
  exportInvoices: (params = {}) => api.post('/export-water-sample-invoices', params),
}
