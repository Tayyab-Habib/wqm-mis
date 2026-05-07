import { api } from './api.js'

export const sampleService = {
  // Water Samples CRUD
  getAll: (params = {}) => api.post('/search-water-sample', params),
  getById: (id) => api.get(`/water-samples/${id}`),
  create: (data) => api.post('/water-samples', data),
  update: (id, data) => api.put(`/water-samples/${id}`, data),
  remove: (id) => api.delete(`/water-samples/${id}`),

  // Analysis queue (pending samples)
  getQueue: (isDraft = 0) => api.get(`/water-samples-queue/${isDraft}`),

  // Analysis results
  updateResults: (id, data) => api.put(`/water-sample-results/${id}`, data),

  // Retest
  requestRetest: (id) => api.post(`/water-sample-tests/${id}/retest`),

  // Start analysis
  startAnalysis: (id) => api.patch(`/water-sample-tests/${id}/start`),

  // Submit analysis
  analyze: (id, data) => api.put(`/water-sample-tests/${id}/analyze`, data),

  // Sample report
  getReport: (id) => api.get(`/water-samples/${id}/report`),

  // Sample details (test parameters)
  createDetail: (data) => api.post('/water-sample-details', data),
  updateDetail: (id, data) => api.put(`/water-sample-details/${id}`, data),

  // Invoices
  getInvoices: (params = {}) => api.post('/search-water-sample-invoices', params),
  getInvoice: (id) => api.get(`/water-sample-invoices/${id}`),
  updateInvoice: (id, data) => api.put(`/water-sample-invoices/${id}`, data),

  // Clients
  getClients: () => api.get('/get-clients'),
  searchClients: (params = {}) => api.post('/search-clients', params),
  createClient: (data) => api.post('/clients', data),
}
