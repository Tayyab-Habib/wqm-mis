import { api } from './api.js'

export const wssService = {
  // Water Schemes CRUD
  getAll: () => api.get('/water-schemes'),
  getById: (id) => api.get(`/water-schemes/${id}`),
  create: (data) => api.post('/water-schemes', data),
  update: (id, data) => api.put(`/water-schemes/${id}`, data),
  remove: (id) => api.delete(`/water-schemes/${id}`),

  // WSS samples
  getSamples: (id) => api.get(`/water-schemes/${id}/water-samples`),

  // WSS schedules
  getSchedule: (id) => api.get(`/water-schemes/${id}/schedules`),
  updateScheduleStatus: (scheduleId, status) =>
    api.get(`/water-schemes/${scheduleId}/schedules/${status}`),

  // WSS status
  updateStatus: (id, isActive) => api.get(`/water-schemes/${id}/status/${isActive}`),

  // Testing status
  getTestingStatus: () => api.get('/water-schemes/testing/status'),

  // Search
  search: (filters) => api.post('/search-water-scheme', filters),

  // Dropdown
  getDropdown: () => api.get('/water-schemes-dropdowns'),
  getSchemeSamples: () => api.get('/water-schemes-samples'),
}
