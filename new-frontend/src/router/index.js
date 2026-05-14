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
    meta: { requiresAuth: true },
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
  // ── XEN Portal (separate layout, same login) ──────────────────────────
  {
    path: '/xen',
    component: () => import('../layouts/XenLayout.vue'),
    meta: { requiresAuth: true, portal: 'xen' },
    children: [
      { path: '', redirect: '/xen/dashboard' },
      { path: 'dashboard',      name: 'XenDashboard',    meta: { title: 'XEN Dashboard' },     component: () => import('../views/Xen/XenDashboard.vue') },
      { path: 'unfit-trail',    name: 'XenUnfitTrail',   meta: { title: 'Unfit Trail' },       component: () => import('../views/Xen/XenUnfitTrail.vue') },
      { path: 'retest-samples', name: 'XenRetestSamples',meta: { title: 'Retest Samples' },    component: () => import('../views/Xen/XenRetestSamples.vue') },
      { path: 'gsr',            name: 'XenGsr',          meta: { title: 'GSR — My Division' }, component: () => import('../views/Xen/XenGsr.vue') },
      { path: 'isr',            name: 'XenIsr',          meta: { title: 'Individual Sample Report' }, component: () => import('../views/Xen/XenIsr.vue') },
      { path: 'isr/:id',        name: 'XenIsrDetail',    meta: { title: 'Sample Report' },     component: () => import('../views/Xen/XenIsrDetail.vue') },
      { path: 'wss-register',   name: 'XenWssRegister',  meta: { title: 'WSS Register' },      component: () => import('../views/Xen/XenWssRegister.vue') },
      { path: 'settings',       name: 'XenSettings',     meta: { title: 'Settings' },          component: () => import('../views/Xen/XenSettings.vue') },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

// Auth guard
const XEN_ROLES = ['xen', 'ce', 'se', 'secretary']

router.beforeEach((to, from, next) => {
  const userStr = localStorage.getItem('user')
  const isAuthenticated = !!userStr
  let user = null
  try { user = userStr ? JSON.parse(userStr) : null } catch { user = null }
  // Read role_slug (additive XEN field) — falls back to role for safety
  const roleSlug = (user?.role_slug || user?.role || '').toString().toLowerCase()
  const isXen = XEN_ROLES.includes(roleSlug)

  if (to.meta.requiresAuth && !isAuthenticated) {
    next('/login')
  } else if (to.path === '/login' && isAuthenticated) {
    next(isXen ? '/xen/dashboard' : '/dashboard')
  } else {
    next()
  }
})

export default router
