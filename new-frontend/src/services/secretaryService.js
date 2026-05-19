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

  // Record the Fate Decision for a WSS sample. Reuses the existing
  // PATCH /water-samples/{id}/fate endpoint that was originally
  // wired for the XEN UI; the Secretary is now the approving authority.
  // Payload: { decision: 'monitor'|'advisory'|'decommission', remarks, authorised_by?, decision_date?, doc_ref? }
  recordFate: (sampleId, payload) => api.patch(`/water-samples/${sampleId}/fate`, payload),
}
