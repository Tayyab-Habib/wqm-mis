import { api } from './api.js'

export const xenService = {
  me: () => api.get('/xen/me'),
  dashboard: () => api.get('/xen/dashboard'),
  trail: (type = 'unfit') => api.get(`/xen/trail?type=${type}`),
  trailDetail: (id) => api.get(`/xen/samples/${id}/trail`),
  requestRetest: (payload) => api.post('/xen/actions/request-retest', payload),

  wssRegister: (params = {}) => {
    const qs = new URLSearchParams(params).toString()
    return api.get(`/xen/wss-register${qs ? '?' + qs : ''}`)
  },
  gsr: (params = {}) => {
    const qs = new URLSearchParams(params).toString()
    return api.get(`/xen/gsr${qs ? '?' + qs : ''}`)
  },
  isrList: (params = {}) => {
    const qs = new URLSearchParams(params).toString()
    return api.get(`/xen/isr${qs ? '?' + qs : ''}`)
  },
  isrShow: (id) => api.get(`/xen/isr/${id}`),
  retestSamples: () => api.get('/xen/retest-samples'),
  overdueWss: () => api.get('/xen/overdue-wss'),
  notifications: () => api.get('/xen/notifications'),
  updateSettings: (payload) => api.put('/xen/settings', payload),
}
