import { api } from './api.js'

export const adminService = {
  // Users
  getUsers: () => api.get('/users'),
  getUserById: (id) => api.get(`/users/${id}`),
  createUser: (data) => api.post('/users', data),
  updateUser: (id, data) => api.put(`/users/${id}`, data),
  deleteUser: (id) => api.delete(`/users/${id}`),
  updatePassword: (data) => api.put('/user-password', data),
  getProfile: () => api.get('/profile'),
  updateProfile: (data) => api.put('/profile', data),
  exportUsers: (filters) => api.post('/export-users', filters),

  // Roles & Permissions — full CRUD for the admin UI.
  // The backend endpoints already exist (api.php:401-404 + 397-398).
  // Role permissions are managed via the role_has_permissions pivot:
  //   getRolePermissions(roleId) → current permissions on the role
  //   syncRolePermissions(roleId, permIds[]) → replace the set (used by matrix)
  getRoles: () => api.get('/roles'),
  getRole: (id) => api.get(`/roles/${id}`),
  createRole: (data) => api.post('/roles', data),
  updateRole: (id, data) => api.put(`/roles/${id}`, data),
  deleteRole: (id) => api.delete(`/roles/${id}`),
  getPermissions: () => api.get('/permissions'),
  getRolePermissions: (roleId) => api.get(`/role-has-permissions/${roleId}`),
  syncRolePermissions: (roleId, permissionIds) =>
    api.post(`/assign/role/${roleId}/permission`, { permission_ids: permissionIds }),

  // Live session refresh — read latest user payload (roles + permission_names)
  // after admin changes role/perms, without forcing re-login.
  me: () => api.get('/me'),

  // Diary & Dispatch
  getDiaries: () => api.get('/diary-dispatch/diary/registers'),
  createDiary: (data) => api.post('/diary-dispatch/diary/registers', data),
  updateDiary: (id, data) => api.put(`/diary-dispatch/diary/registers/${id}`, data),
  deleteDiary: (id) => api.delete(`/diary-dispatch/diary/registers/${id}`),

  getDispatches: () => api.get('/diary-dispatch/dispatch/registers'),
  createDispatch: (data) => api.post('/diary-dispatch/dispatch/registers', data),
  updateDispatch: (id, data) => api.put(`/diary-dispatch/dispatch/registers/${id}`, data),
  deleteDispatch: (id) => api.delete(`/diary-dispatch/dispatch/registers/${id}`),

  // Activity Logs
  getActivityLogs: () => api.get('/acitivity-logs'),

  // Designations
  getDesignations: () => api.get('/all-designations'),

  // Laboratories
  getLaboratories: () => api.get('/all-laboratories'),
  getLaboratoryById: (id) => api.get(`/laboratories/${id}`),
  createLaboratory: (data) => api.post('/laboratories', data),
  updateLaboratory: (id, data) => api.put(`/laboratories/${id}`, data),
  exportLaboratories: (filters) => api.post('/export-laboratories', filters),

  // Notifications
  getNotifications: () => api.get('/notifications'),
  markAsRead: (data) => api.post('/mark-as-read-notifications', data),
}
