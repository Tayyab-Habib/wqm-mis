import { api } from './api.js'

export const secretaryService = {
  me:              () => api.get('/secretary/me'),
  dashboard:       () => api.get('/secretary/dashboard'),
  ceUnfit:         (regionId) => api.get(`/secretary/ce/${regionId}`),
  fateDecisions:   () => api.get('/secretary/fate-decisions'),
  persistentUnfit: () => api.get('/secretary/persistent-unfit'),

  gar: (params = {}) => {
    const qs = new URLSearchParams(params).toString()
    return api.get(`/secretary/gar${qs ? '?' + qs : ''}`)
  },
  wssRegister: (params = {}) => {
    const qs = new URLSearchParams(params).toString()
    return api.get(`/secretary/wss-register${qs ? '?' + qs : ''}`)
  },
}
