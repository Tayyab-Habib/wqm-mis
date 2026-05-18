import { api } from './api.js'

/**
 * KPI Framework — admin-side endpoints for the 4 manual KPIs
 * (001/007/008/009) and matrix fetch.
 *
 * The matrix itself comes from /dashboard/lab-kpis (admin is unscoped
 * there, so all labs are returned with manual values + module values
 * merged). We reuse it instead of duplicating the catalog/computation.
 */
export const kpiFrameworkService = {
  matrix:  ()         => api.post('/dashboard/lab-kpis', {}),
  labs:    ()         => api.get('/admin/kpi-framework/labs'),
  save:    (payload)  => api.post('/admin/kpi-framework/save', payload),
  history: (params)   => api.get('/admin/kpi-framework/history', { params }),
}
