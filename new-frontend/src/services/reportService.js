import { api } from './api.js'

export const reportService = {
  // CE-wise report (Annexure-7)
  getCEWiseReport: (filters) => api.post('/reports/ce-wise', filters),

  // Parameter-wise report (PWR)
  getPWRReport: (filters) => api.post('/reports/pwr', filters),

  // Water Quality Analysis Report (GAR / GSR / ASR)
  getWaterQualityAnalysis: (filters) => api.post('/reports/water-quality-analysis', filters),

  // Central Laboratory Water Quality Report
  getCentralLabReport: (filters) => api.post('/reports/central-laboratory-water-quality', filters),

  // Laboratory-wise Analysis Report
  getLaboratoryAnalysis: (filters) => api.post('/reports/laboratory-water-quality-analysis', filters),

  // Contaminant-wise map report
  getContaminantMap: (filters) => api.post('/reports/contaminant-wise/map', filters),

  // Individual sample report
  getIndividualSample: (year, division, collectableType, id) =>
    api.get(`/water-samples/${year}/${division}/${collectableType}/${id}`),

  // Search water sample results (for PWR / CE-wise)
  searchResults: (filters) => api.post('/search-water-sample-results', filters),

  // Export
  exportResults: (filters) => api.post('/water-sample/export', filters),

  // Graph data
  getGraph: (filters) => api.post('/water-sample/graph', filters),
}
