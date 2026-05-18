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
      // Lab-incharge sample-overview screen — gated to lab-incharge so other
      // roles don't accidentally land here via direct URL.
      { path: 'water-quality/lab-samples',         name: 'LabSamples',         meta: { title: 'Water Quality / Water Samples', roles: ['lab-incharge'] },                                                              component: () => import('../views/WaterQuality/LabSamples/LabSamples.vue') },
      // Unfit Sample Trail: XEN/SE-only by design (notifications + retest workflow
      // are theirs). Other roles, including admins, cannot reach this URL.
      { path: 'water-quality/unfit-sample-trail',  name: 'UnfitSampleTrail',   meta: { title: 'Water Quality / Unfit Sample Trail', roles: ['xen', 'superintending-engineer', 'se', 'secretary'] },    component: () => import('../views/WaterQuality/UnfitSampleTrail/UnfitSampleTrail.vue') },

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
      { path: 'finance/sbp-submissions', name: 'SBPSubmissions', meta: { title: 'Finance / SBP Submissions', permissions: ['view_sbp_submissions'] }, component: () => import('../views/Finance/SBPSubmissions/SBPSubmissions.vue') },

      // Asset Management
      { path: 'assets/stock-inventory',   name: 'StockInventory',   meta: { title: 'Assets / Stock & Inventory' },   component: () => import('../views/AssetManagement/StockInventory/StockInventory.vue') },
      { path: 'assets/equipment-register', name: 'EquipmentRegister', meta: { title: 'Assets / Equipment Register' }, component: () => import('../views/AssetManagement/EquipmentRegister/EquipmentRegister.vue') },
      { path: 'assets/demand-issuance',   name: 'DemandIssuance',   meta: { title: 'Assets / Demand & Issuance' },   component: () => import('../views/AssetManagement/DemandIssuance/DemandIssuance.vue') },

      // Admin (RBAC-gated) — admin pages stay on `roles` since they're
      // adminOnly by design. Diaries/Dispatches uses `permissions` so any
      // custom role granted view_diaries can land there.
      { path: 'admin/users-hr',            name: 'UsersHR',            meta: { title: 'Admin / Users & HR',           roles: ['system-administrator'] },                                           component: () => import('../views/Admin/UsersHR/UsersHR.vue') },
      { path: 'admin/roles-permissions',   name: 'RolesPermissions',   meta: { title: 'Admin / Roles & Permissions',  roles: ['system-administrator'] },                                           component: () => import('../views/Admin/RolesPermissions/RolesPermissions.vue') },
      { path: 'admin/kpi-framework',       name: 'KPIFramework',       meta: { title: 'Admin / KPI Framework',        permissions: ['view_kpi_framework'] },                                       component: () => import('../views/Admin/KPIFramework/KPIFramework.vue') },
      { path: 'admin/staff-trainings',     name: 'StaffTrainings',     meta: { title: 'Quality / Training Register',  permissions: ['view_staff_trainings'] },                                     component: () => import('../views/Admin/StaffTrainings/StaffTrainings.vue') },
      { path: 'admin/verification-visits', name: 'VerificationVisits', meta: { title: 'Quality / Verification Log',   permissions: ['view_verification_visits'] },                                 component: () => import('../views/Admin/VerificationVisits/VerificationVisits.vue') },
      { path: 'admin/audit-checklist',     name: 'AuditChecklist',     meta: { title: 'Quality / SOP Audit',          permissions: ['view_audit_inspections'] },                                   component: () => import('../views/Admin/AuditChecklist/AuditChecklist.vue') },
      { path: 'admin/pt-rounds',           name: 'PtRounds',           meta: { title: 'Quality / PT Rounds',          permissions: ['view_pt_rounds'] },                                           component: () => import('../views/Admin/PtRounds/PtRounds.vue') },
      { path: 'admin/diaries-dispatches',  name: 'DiariesDispatches',  meta: { title: 'Admin / Diaries & Dispatches', permissions: ['view_diaries', 'view_dispatches'] },                          component: () => import('../views/Admin/DiariesDispatches/DiariesDispatches.vue') },

      // Settings (system-administrator only — placeholder pages for now)
      { path: 'settings/provinces',        name: 'SettingsProvinces',       meta: { title: 'Settings / Provinces',        roles: ['system-administrator'] }, component: () => import('../views/Settings/Provinces/Provinces.vue') },
      { path: 'settings/divisions',        name: 'SettingsDivisions',       meta: { title: 'Settings / Divisions',        roles: ['system-administrator'] }, component: () => import('../views/Settings/Divisions/Divisions.vue') },
      { path: 'settings/districts',        name: 'SettingsDistricts',       meta: { title: 'Settings / Districts',        roles: ['system-administrator'] }, component: () => import('../views/Settings/Districts/Districts.vue') },
      { path: 'settings/tehsils',          name: 'SettingsTehsils',         meta: { title: 'Settings / Tehsils',          roles: ['system-administrator'] }, component: () => import('../views/Settings/Tehsils/Tehsils.vue') },
      { path: 'settings/union-councils',   name: 'SettingsUnionCouncils',   meta: { title: 'Settings / Union Councils',   roles: ['system-administrator'] }, component: () => import('../views/Settings/UnionCouncils/UnionCouncils.vue') },
      { path: 'settings/designations',     name: 'SettingsDesignations',    meta: { title: 'Settings / Designations',     roles: ['system-administrator'] }, component: () => import('../views/Settings/Designations/Designations.vue') },
      { path: 'settings/water-parameters', name: 'SettingsWaterParameters', meta: { title: 'Settings / Water Parameters', roles: ['system-administrator'] }, component: () => import('../views/Settings/WaterParameters/WaterParameters.vue') },
      { path: 'settings/abbreviations',    name: 'SettingsAbbreviations',   meta: { title: 'Settings / Abbreviations',    roles: ['system-administrator'] }, component: () => import('../views/Settings/Abbreviations/Abbreviations.vue') },
      { path: 'settings/units',            name: 'SettingsUnits',           meta: { title: 'Settings / Units',            roles: ['system-administrator'] }, component: () => import('../views/Settings/Units/Units.vue') },
      { path: 'settings/complaint-type',   name: 'SettingsComplaintType',   meta: { title: 'Settings / Complaint Type',   roles: ['system-administrator'] }, component: () => import('../views/Settings/ComplaintType/ComplaintType.vue') },
      { path: 'settings/discounts',        name: 'SettingsDiscounts',       meta: { title: 'Settings / Discounts',        roles: ['system-administrator'] }, component: () => import('../views/Settings/Discounts/Discounts.vue') },

      // WSS Details
      { path: 'wss-details', name: 'WSSDetails', meta: { title: 'Water Scheme Details' }, component: () => import('../views/WSSDetails/WSSDetails.vue') },
    ],
  },
  // ── XEN Portal (separate layout, same login) ──────────────────────────
  // Shared by xen and superintending-engineer roles. Every child route is
  // permission-gated; admin can lock individual screens via the Module
  // Access grid. The umbrella view_xen_portal grants entry; per-screen
  // perms control which tabs they can open. ISR list + detail share one
  // perm (view_xen_isr). Settings page gated by view_xen_settings, but
  // the actual PUT call is separately gated by update_xen_settings.
  {
    path: '/xen',
    component: () => import('../layouts/XenLayout.vue'),
    meta: { requiresAuth: true, portal: 'xen', permissions: ['view_xen_portal'] },
    children: [
      { path: '', redirect: '/xen/dashboard' },
      { path: 'dashboard',      name: 'XenDashboard',    meta: { title: 'XEN Dashboard',              permissions: ['view_xen_dashboard'] },        component: () => import('../views/Xen/XenDashboard.vue') },
      { path: 'unfit-trail',    name: 'XenUnfitTrail',   meta: { title: 'Unfit Trail',                permissions: ['view_xen_unfit_trail'] },      component: () => import('../views/Xen/XenUnfitTrail.vue') },
      { path: 'retest-samples', name: 'XenRetestSamples',meta: { title: 'Retest Samples',             permissions: ['view_xen_retest_samples'] },   component: () => import('../views/Xen/XenRetestSamples.vue') },
      { path: 'gsr',            name: 'XenGsr',          meta: { title: 'GSR — My Division',          permissions: ['view_xen_gsr'] },              component: () => import('../views/Xen/XenGsr.vue') },
      { path: 'isr',            name: 'XenIsr',          meta: { title: 'Individual Sample Report',   permissions: ['view_xen_isr'] },              component: () => import('../views/Xen/XenIsr.vue') },
      { path: 'isr/:id',        name: 'XenIsrDetail',    meta: { title: 'Sample Report',              permissions: ['view_xen_isr'] },              component: () => import('../views/Xen/XenIsrDetail.vue') },
      { path: 'wss-register',   name: 'XenWssRegister',  meta: { title: 'WSS Register',               permissions: ['view_xen_wss_register'] },     component: () => import('../views/Xen/XenWssRegister.vue') },
      { path: 'settings',       name: 'XenSettings',     meta: { title: 'Settings',                   permissions: ['view_xen_settings'] },         component: () => import('../views/Xen/XenSettings.vue') },
    ],
  },

  // ── CE Portal (Chief Engineer — region-scoped oversight) ──────────────
  // Full nested portal from the ce-dashboard branch. Replaces the earlier
  // standalone placeholder at /ce/dashboard.
  //
  // Every child route is permission-gated so admin can lock individual
  // screens for the CE via the Module Access grid. The umbrella
  // view_ce_portal grants entry; per-screen perms control which tabs
  // they can open.
  {
    path: '/ce',
    component: () => import('../layouts/CeLayout.vue'),
    meta: { requiresAuth: true, portal: 'ce', permissions: ['view_ce_portal'] },
    children: [
      { path: '', redirect: '/ce/dashboard' },
      { path: 'dashboard',        name: 'CeDashboard',       meta: { title: 'Dashboard',             permissions: ['view_ce_dashboard'] },         component: () => import('../views/Ce/CeDashboard.vue') },
      { path: 'circles/:id',      name: 'CeCircleDetail',    meta: { title: 'SE Circle',             permissions: ['view_ce_circle_detail'] },     component: () => import('../views/Ce/CeCircleDetail.vue') },
      { path: 'escalated-cases',  name: 'CeEscalatedCases',  meta: { title: 'CE Escalated Cases',    permissions: ['view_ce_escalated_cases'] },   component: () => import('../views/Ce/CeEscalatedCases.vue') },
      { path: 'persistent-unfit', name: 'CePersistentUnfit', meta: { title: 'Persistent Unfit WSS',  permissions: ['view_ce_persistent_unfit'] },  component: () => import('../views/Ce/CePersistentUnfit.vue') },
      { path: 'gar',              name: 'CeGar',             meta: { title: 'GAR — My Area',         permissions: ['view_ce_gar'] },               component: () => import('../views/Ce/CeGar.vue') },
      { path: 'wss-register',     name: 'CeWssRegister',     meta: { title: 'WSS Register',          permissions: ['view_ce_wss_register'] },      component: () => import('../views/Ce/CeWssRegister.vue') },
    ],
  },

  // ── Secretary Portal (Province-wide oversight, fate-decision approval) ─
  // Every child route is permission-gated so admin can lock individual
  // screens for the secretary via the Module Access grid. The umbrella
  // view_secretary_portal grants entry; per-screen perms control which
  // tabs they can open.
  {
    path: '/secretary',
    component: () => import('../layouts/SecretaryLayout.vue'),
    meta: { requiresAuth: true, portal: 'secretary', permissions: ['view_secretary_portal'] },
    children: [
      { path: '', redirect: '/secretary/dashboard' },
      { path: 'dashboard',        name: 'SecretaryDashboard',       meta: { title: 'Dashboard',                          permissions: ['view_secretary_dashboard'] },        component: () => import('../views/Secretary/SecretaryDashboard.vue') },
      { path: 'ce/:regionId',     name: 'SecretaryCeUnfit',         meta: { title: 'CE — Unfit Trail',                   permissions: ['view_secretary_ce_unfit'] },         component: () => import('../views/Secretary/SecretaryCeUnfit.vue') },
      { path: 'fate-decisions',   name: 'SecretaryFateDecisions',   meta: { title: 'WSS Fate Decisions',                 permissions: ['view_secretary_fate_decisions'] },   component: () => import('../views/Secretary/SecretaryFateDecisions.vue') },
      { path: 'persistent-unfit', name: 'SecretaryPersistentUnfit', meta: { title: 'Persistent Unfit WSS — Province-wide', permissions: ['view_secretary_persistent_unfit'] }, component: () => import('../views/Secretary/SecretaryPersistentUnfit.vue') },
      { path: 'gar',              name: 'SecretaryGar',             meta: { title: 'GAR — Province',                     permissions: ['view_secretary_gar'] },              component: () => import('../views/Secretary/SecretaryGar.vue') },
      { path: 'wss-register',     name: 'SecretaryWssRegister',     meta: { title: 'WSS Register',                       permissions: ['view_secretary_wss_register'] },     component: () => import('../views/Secretary/SecretaryWssRegister.vue') },
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
// XEN portal is shared by SE and XEN. CE has its own portal at /ce/*.
// Secretary now has its own dedicated portal at /secretary/* (province-wide
// oversight + fate-decision authority), so 'secretary' is no longer routed
// through XEN_ROLES.
const XEN_ROLES       = ['xen', 'se', 'superintending-engineer']
const CE_ROLES        = ['chief-engineer', 'ce']
const SECRETARY_ROLES = ['secretary']

router.beforeEach((to, from, next) => {
  const userStr = localStorage.getItem('user')
  const isAuthenticated = !!userStr
  let user = null
  try { user = userStr ? JSON.parse(userStr) : null } catch { user = null }
  const roleSlug   = (user?.role_slug || user?.role || '').toString().toLowerCase()
  const isXen       = XEN_ROLES.includes(roleSlug)
  const isCe        = CE_ROLES.includes(roleSlug)
  const isSecretary = SECRETARY_ROLES.includes(roleSlug)
  const isClient    = user?.user_type === 'client'

  // Not logged in — redirect to login
  if (to.meta.requiresAuth && !isAuthenticated) {
    return next('/login')
  }

  // Already logged in — redirect away from login page based on user type
  if (to.path === '/login' && isAuthenticated) {
    if (isSecretary) return next('/secretary/dashboard')
    if (isCe)        return next('/ce/dashboard')
    if (isXen)       return next('/xen/dashboard')
    if (isClient)    return next('/client-portal/results')
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

  // RBAC: per-route gating. Two declarative options on route meta:
  //   meta.permissions: ['view_water_samples', 'add_water_samples']
  //     → user must hold ANY one of these permissions
  //   meta.roles: ['lab-incharge']
  //     → legacy fallback; user must hold ANY of these roles
  // Either field (or both) may be present. SA + unscoped roles always pass.
  const UNSCOPED_ROLES = ['system-administrator', 'system-manager', 'view-only-admin', 'general-view-account']
  const userRoles = Array.isArray(user?.roles) && user.roles.length
    ? user.roles.map(r => (r?.name || r || '').toString().toLowerCase().replace(/\s+/g, '-'))
    : [String(user?.role || '').toLowerCase().replace(/\s+/g, '-')]
  const isUnscopedUser = userRoles.some(r => UNSCOPED_ROLES.includes(r))
  const userPerms = Array.isArray(user?.permission_names) ? user.permission_names : []

  // Loop-safe redirect-home. If the home target IS the route the user was
  // trying to reach (e.g. secretary lacks view_secretary_dashboard, but
  // /secretary/dashboard is also their home), don't loop — log them out so
  // they can sign back in with a fresh session. Without this, a stale
  // localStorage cache or a mis-configured perm bundle would cause Vue
  // Router's "infinite redirect" abort.
  const redirectHome = () => {
    let home = '/dashboard'
    if (isSecretary) home = '/secretary/dashboard'
    else if (isCe)        home = '/ce/dashboard'
    else if (isXen)       home = '/xen/dashboard'
    else if (isClient)    home = '/client-portal/results'

    if (to.path === home) {
      // Would loop — bail to login. Caller likely needs a session refresh.
      try { localStorage.removeItem('user') } catch (_) { /* ignore */ }
      return next('/login')
    }
    return next(home)
  }

  if (!isUnscopedUser) {
    // Permission-based gate (preferred)
    if (Array.isArray(to.meta.permissions) && to.meta.permissions.length > 0) {
      const allowed = to.meta.permissions.some(p => userPerms.includes(p))
      if (!allowed) return redirectHome()
    }
    // Legacy role-based gate (still respected when present)
    if (Array.isArray(to.meta.roles) && to.meta.roles.length > 0) {
      const allowed = to.meta.roles.some(r => userRoles.includes(r))
      if (!allowed) return redirectHome()
    }
  }

  next()
})

export default router
