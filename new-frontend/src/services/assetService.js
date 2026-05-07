import { api } from './api.js'

export const assetService = {
  // Materials / Stock (consumables)
  getMaterials: () => api.get('/laboratory/materials/all'),
  searchMaterials: (params = {}) => api.post('/search-material', params),
  getLaboratoryMaterials: () => api.get('/laboratory-materials'),
  updateLaboratoryMaterial: (id, data) => api.put(`/laboratory-materials/${id}`, data),

  // Assets / Equipment (non-consumables)
  getAssets: () => api.get('/laboratory/assets/all'),
  searchAssets: (params = {}) => api.post('/search-asset', params),
  getLaboratoryAssets: () => api.get('/laboratory-assets'),
  updateLaboratoryAsset: (id, data) => api.put(`/laboratory-assets/${id}`, data),

  // Asset maintenance schedules
  getMaintenanceSchedules: (assetId) => api.get(`/assets/${assetId}/maintenance-schedules`),
  updateMaintenanceStatus: (scheduleId, status) =>
    api.get(`/assets/${scheduleId}/maintenance-schedules/${status}`),

  // Inventory requests (demand & issuance)
  getInventories: () => api.get('/inventories'),
  getInventory: (id) => api.get(`/inventories/${id}`),
  createInventory: (data) => api.post('/inventories', data),
  getInventoryLog: (id) => api.get(`/inventory-logs/${id}`),

  // Inventory detail actions
  approveInventory: (detailId) => api.put(`/inventory-details/${detailId}/statuses/approve`),
  issueInventory: (detailId) => api.put(`/inventory-details/${detailId}/statuses/issue`),
  receiveInventory: (detailId, isReceived) =>
    api.get(`/inventory-details/${detailId}/received/${isReceived}`),

  // Purchase orders
  getPurchaseOrders: () => api.get('/purchase-orders'),
  searchPurchaseOrders: (params = {}) => api.post('/search-purchase-order', params),
  createPurchaseOrder: (data) => api.post('/purchase-orders', data),
  updatePurchaseOrderStatus: (id, data) => api.put(`/purchase-order-status/${id}`, data),
}
