import { api } from './api.js'

export const userService = {
  // Users CRUD
  getAll: () => api.get('/users'),
  getById: (id) => api.get(`/users/${id}`),
  create: (data) => api.post('/users', data),
  update: (id, data) => api.put(`/users/${id}`, data),
  remove: (id) => api.delete(`/users/${id}`),

  // Profile
  getProfile: () => api.get('/profile'),
  updateProfile: (data) => api.put('/profile', data),

  // Password
  updatePassword: (data) => api.put('/user-password', data),

  // Roles & Permissions
  getRoles: () => api.get('/roles'),
  getPermissions: () => api.get('/permissions'),
  getRolePermissions: (roleId) => api.get(`/role-has-permissions/${roleId}`),
  assignPermissions: (roleId, permissions) => api.post(`/assign/role/${roleId}/permission`, { permissions }),
}
