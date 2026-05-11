import { api } from './api.js'

export const assetService = {
  // ── Materials / Stock (consumables) ───────────────────────────────────────
  getMaterials:           ()           => api.get('/laboratory/materials/all'),
  searchMaterials:        (params = {}) => api.post('/search-material', params),
  getLaboratoryMaterials: ()           => api.get('/laboratory-materials'),
  updateLaboratoryMaterial: (id, data) => api.put(`/laboratory-materials/${id}`, data),
  createMaterial:         (data)       => api.post('/materials', data),
  updateMaterial:         (id, data)   => api.put(`/materials/${id}`, data),
  logStockOut:            (data)       => api.post('/stock-out', data),

  // ── Equipment / Lab Assets (non-consumables) ──────────────────────────────
  getAssets:            ()           => api.get('/laboratory/assets/all'),
  searchAssets:         (params = {}) => api.post('/search-asset', params),
  getLaboratoryAssets:  ()           => api.get('/laboratory-assets'),
  getLaboratoryAsset:   (id)         => api.get(`/laboratory-assets/${id}`),
  updateLaboratoryAsset: (id, data)  => api.put(`/laboratory-assets/${id}`, data),
  createAsset:          (data)       => api.post('/assets', data),
  updateAsset:          (id, data)   => api.put(`/assets/${id}`, data),
  logInventoryOut:      (data)       => api.post('/inventory-out', data),

  // ── Calibration Logs ──────────────────────────────────────────────────────
  /** GET all calibration logs for one piece of equipment */
  getCalibrationLogs:  (laboratoryAssetId) =>
    api.get(`/laboratory-assets/${laboratoryAssetId}/calibration-logs`),

  /** POST a new calibration log */
  createCalibrationLog: (data) => api.post('/equipment-calibration-logs', data),

  // ── Repair Logs ───────────────────────────────────────────────────────────
  /** GET all repair logs for one piece of equipment */
  getRepairLogs:  (laboratoryAssetId) =>
    api.get(`/laboratory-assets/${laboratoryAssetId}/repair-logs`),

  /** POST a new repair log */
  createRepairLog: (data) => api.post('/equipment-repair-logs', data),

  // ── Asset maintenance schedules (legacy) ─────────────────────────────────
  getMaintenanceSchedules: (assetId) =>
    api.get(`/assets/${assetId}/maintenance-schedules`),
  updateMaintenanceStatus: (scheduleId, status) =>
    api.get(`/assets/${scheduleId}/maintenance-schedules/${status}`),

  // ── Inventory requests (demand & issuance) ────────────────────────────────
  getInventories:    ()       => api.get('/inventories'),
  getInventory:      (id)     => api.get(`/inventories/${id}`),
  createInventory:   (data)   => api.post('/inventories', data),
  getInventoryLog:   (id)     => api.get(`/inventory-logs/${id}`),

  // ── Inventory detail actions ──────────────────────────────────────────────
  approveInventory: (detailId, comment = null) =>
    api.put(`/inventory-details/${detailId}/statuses/approve`, { status: 'approved', comment }),
  rejectInventory:  (detailId, comment) =>
    api.put(`/inventory-details/${detailId}/statuses/approve`, { status: 'rejected', comment }),
  issueInventory:   (detailId, quantity, comment = null) =>
    api.put(`/inventory-details/${detailId}/statuses/issue`, { status: 'issued', quantity: Number(quantity).toFixed(2), comment }),
  receiveInventory: (detailId, isReceived) =>
    api.get(`/inventory-details/${detailId}/received/${isReceived}`),

  // ── Purchase orders ───────────────────────────────────────────────────────
  getPurchaseOrders:        ()       => api.get('/purchase-orders'),
  searchPurchaseOrders:     (params = {}) => api.post('/search-purchase-order', params),
  createPurchaseOrder:      (data)   => api.post('/purchase-orders', data),
  updatePurchaseOrderStatus:(id, data) => api.put(`/purchase-order-status/${id}`, data),
}
