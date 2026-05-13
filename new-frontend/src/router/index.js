import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('../views/Auth/Login.vue'),
    meta: { requiresAuth: false },
  },
  {
    path: '/',
    component: () => import('../layouts/MainLayout.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
    children: [
      { path: '', redirect: '/dashboard' },
      { path: 'dashboard',                       name: 'Dashboard',             meta: { title: 'Dashboard' },                       component: () => import('../views/Dashboard/Dashboard.vue') },

      // Water Quality
      { path: 'water-quality/sample-registration', name: 'SampleRegistration', meta: { title: 'Water Quality / Sample Registration' }, component: () => import('../views/WaterQuality/SampleRegistration/SampleRegistration.vue') },
      { path: 'water-quality/analysis-entry',      name: 'AnalysisEntry',      meta: { title: 'Water Quality / Analysis Entry' },       component: () => import('../views/WaterQuality/AnalysisEntry/AnalysisEntry.vue') },
      { path: 'water-quality/unfit-sample-trail',  name: 'UnfitSampleTrail',   meta: { title: 'Water Quality / Unfit Sample Trail' },    component: () => import('../views/WaterQuality/UnfitSampleTrail/UnfitSampleTrail.vue') },

      // Reports
      { path: 'reports/individual-sample', name: 'IndividualSampleReport', meta: { title: 'Reports / Individual Sample Report' }, component: () => import('../views/Reports/IndividualSampleReport/IndividualSampleReport.vue') },
      { path: 'reports/gar',  name: 'GAR',          meta: { title: 'Reports / GAR (Abstract)' },          component: () => import('../views/Reports/GAR/GAR.vue') },
      { path: 'reports/gsr',  name: 'GSR',          meta: { title: 'Reports / GSR (Summary)' },           component: () => import('../views/Reports/GSR/GSR.vue') },
      { path: 'reports/asr',  name: 'ASR',          meta: { title: 'Reports / ASR (Analysis Summary)' },  component: () => import('../views/Reports/ASR/ASR.vue') },
      { path: 'reports/ce-wise', name: 'CEWiseReport', meta: { title: 'Reports / CE-Wise Report' },       component: () => import('../views/Reports/CEWiseReport/CEWiseReport.vue') },
      { path: 'reports/pwr',  name: 'PWR',          meta: { title: 'Reports / PWR (Parameter-wise)' },    component: () => import('../views/Reports/PWR/PWR.vue') },
      { path: 'reports/wss-map', name: 'WSSMap',    meta: { title: 'Reports / WSS Map' },                 component: () => import('../views/Reports/WSSMap/WSSMap.vue') },

      // Finance
      { path: 'finance/invoices',       name: 'Invoices',       meta: { title: 'Finance / Invoices' },        component: () => import('../views/Finance/Invoices/Invoices.vue') },
      { path: 'finance/sbp-submissions', name: 'SBPSubmissions', meta: { title: 'Finance / SBP Submissions' }, component: () => import('../views/Finance/SBPSubmissions/SBPSubmissions.vue') },

      // Asset Management
      { path: 'assets/stock-inventory',   name: 'StockInventory',   meta: { title: 'Assets / Stock & Inventory' },   component: () => import('../views/AssetManagement/StockInventory/StockInventory.vue') },
      { path: 'assets/equipment-register', name: 'EquipmentRegister', meta: { title: 'Assets / Equipment Register' }, component: () => import('../views/AssetManagement/EquipmentRegister/EquipmentRegister.vue') },
      { path: 'assets/demand-issuance',   name: 'DemandIssuance',   meta: { title: 'Assets / Demand & Issuance' },   component: () => import('../views/AssetManagement/DemandIssuance/DemandIssuance.vue') },

      // Admin
      { path: 'admin/users-hr',           name: 'UsersHR',           meta: { title: 'Admin / Users & HR' },           component: () => import('../views/Admin/UsersHR/UsersHR.vue') },
      { path: 'admin/kpi-framework',      name: 'KPIFramework',      meta: { title: 'Admin / KPI Framework' },        component: () => import('../views/Admin/KPIFramework/KPIFramework.vue') },
      { path: 'admin/diaries-dispatches', name: 'DiariesDispatches', meta: { title: 'Admin / Diaries & Dispatches' }, component: () => import('../views/Admin/DiariesDispatches/DiariesDispatches.vue') },

      // WSS Details
      { path: 'wss-details', name: 'WSSDetails', meta: { title: 'Water Scheme Details' }, component: () => import('../views/WSSDetails/WSSDetails.vue') },
    ],
  },

  // ── Client Portal ──────────────────────────────────────────────────
  {
    path: '/client-portal',
    component: () => import('../layouts/ClientPortalLayout.vue'),
    meta: { requiresAuth: true, requiresClient: true },
    children: [
      { path: '',              redirect: '/client-portal/results' },
      { path: 'results',       name: 'ClientResults',      meta: { title: 'My Results' },      component: () => import('../views/ClientPortal/ClientResults.vue') },
      { path: 'email-reports', name: 'ClientEmailReports', meta: { title: 'Email Reports' },   component: () => import('../views/ClientPortal/ClientEmailReports.vue') },
      { path: 'billing',       name: 'ClientBilling',      meta: { title: 'Billing' },         component: () => import('../views/ClientPortal/ClientBilling.vue') },
      { path: 'profile',       name: 'ClientProfile',      meta: { title: 'My Profile' },      component: () => import('../views/ClientPortal/ClientProfile.vue') },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

// Auth guard
router.beforeEach((to, from, next) => {
  const userStr = localStorage.getItem('user')
  const isAuthenticated = !!userStr
  const user = userStr ? JSON.parse(userStr) : null
  const isClient = user?.user_type === 'client'

  // Not logged in — redirect to login
  if (to.meta.requiresAuth && !isAuthenticated) {
    return next('/login')
  }

  // Already logged in — redirect away from login page
  if (to.path === '/login' && isAuthenticated) {
    return next(isClient ? '/client-portal/results' : '/dashboard')
  }

  // Client trying to access admin area
  if (to.meta.requiresAdmin && isClient) {
    return next('/client-portal/results')
  }

  // Admin trying to access client portal
  if (to.meta.requiresClient && !isClient) {
    return next('/dashboard')
  }

  next()
})

export default router
