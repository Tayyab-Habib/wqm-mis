import { api } from './api.js'

/**
 * Quality / Compliance modules backing the KPI Framework:
 *   - KPI-001 PT Rounds
 *   - KPI-007 Staff Trainings
 *   - KPI-008 Audit Checklist (items + inspections)
 *   - KPI-009 Verification Visits
 */
export const qualityService = {
  // ── Training Register (KPI-007) ────────────────────────────────────────
  trainings: {
    list:     (params)        => api.get('/quality/staff-trainings', { params }),
    create:   (payload)       => api.post('/quality/staff-trainings', payload),
    update:   (id, payload)   => api.put(`/quality/staff-trainings/${id}`, payload),
    remove:   (id)            => api.delete(`/quality/staff-trainings/${id}`),
    labStaff: (labId)         => api.get(`/quality/staff-trainings/lab-staff/${labId}`),
  },

  // ── Verification Log (KPI-009) ─────────────────────────────────────────
  verification: {
    list:    (params)         => api.get('/quality/verification-visits', { params }),
    show:    (id)             => api.get(`/quality/verification-visits/${id}`),
    create:  (payload)        => api.post('/quality/verification-visits', payload),
    remove:  (id)             => api.delete(`/quality/verification-visits/${id}`),
  },

  // ── Audit Checklist (KPI-008) ──────────────────────────────────────────
  audit: {
    items: {
      list:   (params)        => api.get('/quality/audit/items', { params }),
      create: (payload)       => api.post('/quality/audit/items', payload),
      update: (id, payload)   => api.put(`/quality/audit/items/${id}`, payload),
      remove: (id)            => api.delete(`/quality/audit/items/${id}`),
    },
    inspections: {
      list:   (params)        => api.get('/quality/audit/inspections', { params }),
      show:   (id)            => api.get(`/quality/audit/inspections/${id}`),
      create: (payload)       => api.post('/quality/audit/inspections', payload),
      remove: (id)            => api.delete(`/quality/audit/inspections/${id}`),
    },
  },

  // ── PT Module (KPI-001) ────────────────────────────────────────────────
  pt: {
    list:    (params)               => api.get('/quality/pt-rounds', { params }),
    show:    (id)                   => api.get(`/quality/pt-rounds/${id}`),
    create:  (payload)              => api.post('/quality/pt-rounds', payload),
    close:   (id)                   => api.post(`/quality/pt-rounds/${id}/close`),
    remove:  (id)                   => api.delete(`/quality/pt-rounds/${id}`),
    submit:  (roundId, labId, body) => api.post(`/quality/pt-rounds/${roundId}/submit/${labId}`, body),
  },
}
