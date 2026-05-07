import { api } from './api.js'

export const waterSchemeService = {
  // Water Schemes CRUD
  getAll: (params = {}) => api.post('/search-water-scheme', params),
  getById: (id) => api.get(`/water-schemes/${id}`),
  create: (data) => api.post('/water-schemes', data),
  update: (id, data) => api.put(`/water-schemes/${id}`, data),

  // Get samples for a specific WSS
  getSamples: (id) => api.get(`/water-schemes/${id}/water-samples`),

  // WSS with samples (for WSS details view)
  getWithSamples: () => api.get('/water-schemes-samples'),

  // Schedules
  getSchedules: (id) => api.get(`/water-schemes/${id}/schedules`),
  createSchedule: (data) => api.post('/water-scheme-schedules', data),
  updateSchedule: (id, data) => api.put(`/water-scheme-schedules/${id}`, data),
  updateScheduleStatus: (id, status) => api.get(`/water-schemes/${id}/schedules/${status}`),

  // Status
  updateStatus: (id, isActive) => api.get(`/water-schemes/${id}/status/${isActive}`),

  // Testing status
  getTestingStatus: () => api.get('/water-schemes/testing/status'),
}
