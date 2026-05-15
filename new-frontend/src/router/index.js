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
      { path: 'water-quality/analysis-entry',      name: 'AnalysisEntry',      meta: { title: 'Water Quality / Analysis Entry', roles: ['system-administrator','system-manager','lab-incharge','laboratory-assistant'] },       component: () => import('../views/WaterQuality/AnalysisEntry/AnalysisEntry.vue') },
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
      { path: 'finance/sbp-submissions', name: 'SBPSubmissions', meta: { title: 'Finance / SBP Submissions', roles: ['system-administrator','system-manager'] }, component: () => import('../views/Finance/SBPSubmissions/SBPSubmissions.vue') },

      // Asset Management
      { path: 'assets/stock-inventory',   name: 'StockInventory',   meta: { title: 'Assets / Stock & Inventory' },   component: () => import('../views/AssetManagement/StockInventory/StockInventory.vue') },
      { path: 'assets/equipment-register', name: 'EquipmentRegister', meta: { title: 'Assets / Equipment Register' }, component: () => import('../views/AssetManagement/EquipmentRegister/EquipmentRegister.vue') },
      { path: 'assets/demand-issuance',   name: 'DemandIssuance',   meta: { title: 'Assets / Demand & Issuance' },   component: () => import('../views/AssetManagement/DemandIssuance/DemandIssuance.vue') },

      // Admin (RBAC-gated)
      { path: 'admin/users-hr',           name: 'UsersHR',           meta: { title: 'Admin / Users & HR', roles: ['system-administrator'] },           component: () => import('../views/Admin/UsersHR/UsersHR.vue') },
      { path: 'admin/kpi-framework',      name: 'KPIFramework',      meta: { title: 'Admin / KPI Framework', roles: ['system-administrator','system-manager','view-only-admin'] },        component: () => import('../views/Admin/KPIFramework/KPIFramework.vue') },
      { path: 'admin/diaries-dispatches', name: 'DiariesDispatches', meta: { title: 'Admin / Diaries & Dispatches', roles: ['system-administrator','system-manager','lab-incharge','junior-clerk'] }, component: () => import('../views/Admin/DiariesDispatches/DiariesDispatches.vue') },

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

  // ── CE Portal (standalone placeholder — full dashboard coming later) ──
  {
    path: '/ce/dashboard',
    name: 'CeDashboard',
    component: () => import('../views/Ce/CeDashboard.vue'),
    meta: { requiresAuth: true, title: 'CE Dashboard', roles: ['chief-engineer'] },
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
// XEN portal is shared by SE and XEN (and the legacy short slugs for back-compat).
// CE has its own standalone placeholder at /ce/dashboard.
const XEN_ROLES = ['xen', 'se', 'secretary', 'superintending-engineer']
const CE_ROLES  = ['chief-engineer', 'ce']

router.beforeEach((to, from, next) => {
  const userStr = localStorage.getItem('user')
  const isAuthenticated = !!userStr
  let user = null
  try { user = userStr ? JSON.parse(userStr) : null } catch { user = null }
  // Read role_slug (additive XEN field) — falls back to role for safety
  const roleSlug = (user?.role_slug || user?.role || '').toString().toLowerCase()
  const isXen    = XEN_ROLES.includes(roleSlug)
  const isCe     = CE_ROLES.includes(roleSlug)
  const isClient = user?.user_type === 'client'

  // Not logged in — redirect to login
  if (to.meta.requiresAuth && !isAuthenticated) {
    return next('/login')
  }

  // Already logged in — redirect away from login page based on user type
  if (to.path === '/login' && isAuthenticated) {
    if (isCe)     return next('/ce/dashboard')
    if (isXen)    return next('/xen/dashboard')
    if (isClient) return next('/client-portal/results')
    return next('/dashboard')
  }

  // Client trying to access admin area
  if (to.meta.requiresAdmin && isClient) {
    return next('/client-portal/results')
  }

  // Admin trying to access client portal
  if (to.meta.requiresClient && !isClient) {
    return next('/dashboard')
  }

  // RBAC: per-route role gating. Route can declare `meta.roles: ['system-administrator', 'lab-incharge']`.
  // If the user has none of the listed roles, redirect them home. SA always allowed.
  if (Array.isArray(to.meta.roles) && to.meta.roles.length > 0 && roleSlug !== 'system-administrator') {
    const userRole = String(user?.role || '').toLowerCase().replace(/\s+/g, '-')
    if (!to.meta.roles.includes(userRole)) {
      if (isCe)     return next('/ce/dashboard')
      if (isXen)    return next('/xen/dashboard')
      if (isClient) return next('/client-portal/results')
      return next('/dashboard')
    }
  }

  next()
})

export default router
