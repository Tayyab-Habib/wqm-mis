import { api } from './api.js'

export const dashboardService = {
  // Get dashboard statistics (POST with filters)
  getStats: (filters = {}) => api.post('/dashboard', filters),

  // Get district-wise contaminants
  getDistrictContaminants: (filters = {}) => api.post('/district-wise-contaminants', filters),

  // Get water schemes testing status
  getWaterSchemesStatus: () => api.get('/water-schemes/testing/status'),
}
