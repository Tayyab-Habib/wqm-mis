import { api } from './api.js'

/**
 * Settings module service — CRUD wrappers for the locality + lookup
 * tables exposed in the Settings sidebar group.
 *
 * Convention: payloads that include a file (e.g. a Province logo) are sent
 * as FormData; everything else is plain JSON. The backend axios interceptor
 * returns `response.data` directly, so callers see the unwrapped body.
 */
export const settingsService = {
  // ── Provinces ──────────────────────────────────────────────────────
  getProvinces:   ()          => api.get('/provinces'),
  getProvince:    (id)        => api.get(`/provinces/${id}`),
  createProvince: (formData)  => api.post('/provinces', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  }),
  updateProvince: (id, formData) => {
    // Laravel ignores PUT on multipart unless we override with _method.
    formData.append('_method', 'PUT')
    return api.post(`/provinces/${id}`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
  deleteProvince: (id)        => api.delete(`/provinces/${id}`),

  // ── Divisions ─────────────────────────────────────────────────────
  getDivisions:   ()          => api.get('/divisions'),
  getDivision:    (id)        => api.get(`/divisions/${id}`),
  createDivision: (data)      => api.post('/divisions', data),
  updateDivision: (id, data)  => api.put(`/divisions/${id}`, data),
  deleteDivision: (id)        => api.delete(`/divisions/${id}`),

  // ── Districts ─────────────────────────────────────────────────────
  getDistricts:   ()          => api.get('/districts'),
  getDistrict:    (id)        => api.get(`/districts/${id}`),
  createDistrict: (data)      => api.post('/districts', data),
  updateDistrict: (id, data)  => api.put(`/districts/${id}`, data),
  deleteDistrict: (id)        => api.delete(`/districts/${id}`),

  // ── Tehsils ───────────────────────────────────────────────────────
  getTehsils:     ()          => api.get('/tehsils'),
  getTehsil:      (id)        => api.get(`/tehsils/${id}`),
  createTehsil:   (data)      => api.post('/tehsils', data),
  updateTehsil:   (id, data)  => api.put(`/tehsils/${id}`, data),
  deleteTehsil:   (id)        => api.delete(`/tehsils/${id}`),

  // ── Union Councils ────────────────────────────────────────────────
  getUnionCouncils:   ()          => api.get('/union-councils'),
  getUnionCouncil:    (id)        => api.get(`/union-councils/${id}`),
  createUnionCouncil: (data)      => api.post('/union-councils', data),
  updateUnionCouncil: (id, data)  => api.put(`/union-councils/${id}`, data),
  deleteUnionCouncil: (id)        => api.delete(`/union-councils/${id}`),

  // ── Designations ──────────────────────────────────────────────────
  getDesignations:    ()          => api.get('/designations'),
  getDesignation:     (id)        => api.get(`/designations/${id}`),
  createDesignation:  (data)      => api.post('/designations', data),
  updateDesignation:  (id, data)  => api.put(`/designations/${id}`, data),
  deleteDesignation:  (id)        => api.delete(`/designations/${id}`),

  // ── Water Parameters (tests table) ───────────────────────────────
  getWaterParameters:    ()          => api.get('/tests'),
  getWaterParameter:     (id)        => api.get(`/tests/${id}`),
  createWaterParameter:  (data)      => api.post('/tests', data),
  updateWaterParameter:  (id, data)  => api.put(`/tests/${id}`, data),
  deleteWaterParameter:  (id)        => api.delete(`/tests/${id}`),

  // ── Abbreviations ────────────────────────────────────────────────
  getAbbreviations:    ()          => api.get('/abbreviations'),
  getAbbreviation:     (id)        => api.get(`/abbreviations/${id}`),
  createAbbreviation:  (data)      => api.post('/abbreviations', data),
  updateAbbreviation:  (id, data)  => api.put(`/abbreviations/${id}`, data),
  deleteAbbreviation:  (id)        => api.delete(`/abbreviations/${id}`),

  // ── Units ─────────────────────────────────────────────────────────
  getUnits:           ()          => api.get('/units'),
  getUnit:            (id)        => api.get(`/units/${id}`),
  createUnit:         (data)      => api.post('/units', data),
  updateUnit:         (id, data)  => api.put(`/units/${id}`, data),
  deleteUnit:         (id)        => api.delete(`/units/${id}`),

  // ── Complaint Types (backend exposes only index/store/destroy) ────
  getComplaintTypes:    ()       => api.get('/complaint-types'),
  createComplaintType:  (data)   => api.post('/complaint-types', data),
  deleteComplaintType:  (id)     => api.delete(`/complaint-types/${id}`),
}
