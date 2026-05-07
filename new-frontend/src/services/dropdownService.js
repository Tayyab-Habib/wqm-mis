import { api } from './api.js'

export const dropdownService = {
  // Location
  getDivisions: () => api.get('/all-divisions'),
  getDistricts: () => api.get('/all-districts'),
  getProvinces: () => api.get('/provinces'),
  getTehsils: () => api.get('/tehsils'),
  getLocality: () => api.get('/locality'),
  getRegions: () => api.get('/regions'),
  getCircles: () => api.get('/circles'),
  getPhedDivisions: () => api.get('/phed-divisions'),
  getSubDivisions: () => api.get('/sub-divisions'),
  getHubLabs: () => api.get('/hub-labs'),

  // Laboratories
  getLaboratories: () => api.get('/all-laboratories'),

  // Water Schemes
  getWaterSchemes: () => api.get('/water-schemes-dropdowns'),

  // Users
  getLaboratoryUsers: () => api.get('/laboratory-users'),
  getDesignations: () => api.get('/all-designations'),
  getRoles: () => api.get('/all-roles'),

  // Sample-related
  getSourceTypes: () => api.get('/source-types'),
  getSourceSubTypes: () => api.get('/source-sub-types'),
  getSamplingPoints: () => api.get('/sampling-points'),
  getCollectableTypes: () => api.get('/collectable-types'),
  getCollectedBy: () => api.get('/collected-by-status'),
  getCollectedIn: () => api.get('/collected-in-status'),
  getReasonForTesting: () => api.get('/reason-for-testing-status'),
  getDesiredTests: () => api.get('/desired-testing-status'),
  getTestTypes: () => api.get('/test-types'),
  getTestParameters: () => api.get('/test-parameters'),
  getTestFrequencies: () => api.get('/test-frequencies'),
  getOnDemandTests: () => api.get('/on-demand-tests'),
  getPhysicalParameters: () => api.get('/physical-parameters'),
  getWaterSampleStatus: () => api.get('/water-sample-status'),
  getWaterSampleResults: () => api.get('/water-sample-results'),
  getWaterSampleInvoiceStatus: () => api.get('/water-sample-invoice-status'),

  // Finance
  getInvoiceableTypes: () => api.get('/invoiceable-types'),
  getPaymentableTypes: () => api.get('/paymentable-types'),
  getUnits: (type) => api.get(`/units/${type}`),

  // Assets
  getAssetStatus: () => api.get('/asset-status'),
  getAssetMaintenanceStatus: () => api.get('/asset-maintenance-status'),
  getMaterialStatus: () => api.get('/material-status'),
  getInventoryDetailStatus: () => api.get('/inventory_detail-status'),
  getIssuable: (data) => api.post('/issuable', data),
  getPurchasable: (data) => api.post('/purchasable', data),
  getStockable: (data) => api.post('/stockables', data),

  // Diary & Dispatch
  getDiaryDispatches: () => api.get('/all-diary-dispatches'),

  // Genders & Employment
  getGenders: () => api.get('/genders'),
  getEmploymentStatus: () => api.get('/employment-status'),
}
