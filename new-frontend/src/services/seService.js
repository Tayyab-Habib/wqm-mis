import { api } from './api.js'

export const seService = {
  me:            () => api.get('/se/me'),
  dashboard:     () => api.get('/se/dashboard'),
  retestSamples: () => api.get('/se/retest-samples'),

  unfitTrail: (params = {}) => {
    const qs = new URLSearchParams(params).toString()
    return api.get(`/se/unfit-trail${qs ? '?' + qs : ''}`)
  },
  gar: (params = {}) => {
    const qs = new URLSearchParams(params).toString()
    return api.get(`/se/gar${qs ? '?' + qs : ''}`)
  },
  gsr: (params = {}) => {
    const qs = new URLSearchParams(params).toString()
    return api.get(`/se/gsr${qs ? '?' + qs : ''}`)
  },
  isrList: (params = {}) => {
    const qs = new URLSearchParams(params).toString()
    return api.get(`/se/isr${qs ? '?' + qs : ''}`)
  },
  isrShow:     (id) => api.get(`/se/isr/${id}`),
  wssRegister: (params = {}) => {
    const qs = new URLSearchParams(params).toString()
    return api.get(`/se/wss-register${qs ? '?' + qs : ''}`)
  },

  // Trail + Log Action (mirror XEN endpoints, circle-scoped)
  trailDetail:   (id) => api.get(`/se/samples/${id}/trail`),
  requestRetest: (payload) => api.post('/se/actions/request-retest', payload),
}
