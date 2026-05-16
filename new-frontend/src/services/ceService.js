import { api } from './api.js'

export const ceService = {
  me:              () => api.get('/ce/me'),
  dashboard:       () => api.get('/ce/dashboard'),
  circleDetail:    (id) => api.get(`/ce/circles/${id}`),
  escalatedCases:  () => api.get('/ce/escalated-cases'),
  persistentUnfit: () => api.get('/ce/persistent-unfit'),

  gar: (params = {}) => {
    const qs = new URLSearchParams(params).toString()
    return api.get(`/ce/gar${qs ? '?' + qs : ''}`)
  },
  wssRegister: (params = {}) => {
    const qs = new URLSearchParams(params).toString()
    return api.get(`/ce/wss-register${qs ? '?' + qs : ''}`)
  },
}
