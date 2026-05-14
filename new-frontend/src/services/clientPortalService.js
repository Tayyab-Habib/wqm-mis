import { api } from './api.js'

export const clientPortalService = {
  login:           (credentials) => api.post('/client-portal/login', credentials),
  logout:          ()            => api.post('/client-portal/logout'),
  me:              ()            => api.get('/client-portal/me'),
  getSamples:      ()            => api.get('/client-portal/samples'),
  getInvoices:     ()            => api.get('/client-portal/invoices'),
  getEmailReports: ()            => api.get('/client-portal/email-reports'),
  changePassword:  (data)        => api.put('/client-portal/change-password', data),
}
