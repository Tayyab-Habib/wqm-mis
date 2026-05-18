<script setup>
import { computed } from 'vue'
import { useUiStore } from '../../../stores/useUiStore.js'
import { useUserStore } from '../../../stores/useUserStore.js'

const store     = useUiStore()
const userStore = useUserStore()

// RBAC: sidebar items are gated by PERMISSION, not by role name.
// This means any custom role granted the right permission via the admin UI
// gets the corresponding menu without a code change. Roles are no longer
// hardcoded here.
//
// Helpers — accept a permission name (string) or array of permission names.
// Item is visible if user has ANY of the listed permissions, OR if the user
// is unscoped (SA / system-manager / view-only-admin / general-view-account),
// since unscoped roles see everything by definition.
const UNSCOPED_ROLES = ['system-administrator', 'system-manager', 'view-only-admin', 'general-view-account']
const isUnscoped = computed(() => UNSCOPED_ROLES.some(r => userStore.hasRole(r)))

// Each item declares `permissions: ['perm_name', ...]`. The item is visible
// when the user has ANY of those permissions, OR when the user is unscoped.
// Items / sections with no `permissions` key are visible to all authenticated
// users (e.g. Dashboard).
//
// `adminOnly: true` is an escape hatch for routes that are SA-tier without a
// natural permission (e.g. the Users/HR admin page) — only unscoped roles see
// them, regardless of fine-grained perms. Use sparingly.
const navItems = [
  { label: 'Dashboard',           icon: '🏠', route: '/dashboard' },
  { section: 'Water Quality',                                                                          permissions: ['view_water_samples', 'add_water_samples', 'add_water_sample_details'] },
  // Sample Registration is for junior-clerk (data entry). Lab-incharge
  // oversees rather than registers — they get the "Water Samples" view
  // below instead. Analysis Entry similarly is the lab-assistant's screen.
  { label: 'Sample Registration', icon: '🧪', route: '/water-quality/sample-registration',             permissions: ['add_water_samples'],                                hideForRoles: ['lab-incharge'] },
  { label: 'Analysis Entry',      icon: '⚗️', route: '/water-quality/analysis-entry',                  permissions: ['add_water_sample_details', 'edit_water_sample_results'], hideForRoles: ['lab-incharge'] },
  // Lab-incharge's sample-overview screen — read-only, filtered to their
  // lab. Other roles already have richer views (Sample Registration,
  // Analysis Entry, Unfit Sample Trail) so we show this only to them.
  { label: 'Water Samples',       icon: '💧', route: '/water-quality/lab-samples',                     permissions: ['view_water_samples'],                                onlyForRoles: ['lab-incharge'] },
  // Unfit Sample Trail is XEN/SE-only — they access it via the dedicated /xen
  // portal (XenUnfitTrail). Hidden from the main-app sidebar entirely.
  { section: 'Reports',                                                                                permissions: ['view_water_samples', 'view_reports'] },
  { label: 'Individual Sample Report', icon: '🧪', route: '/reports/individual-sample',                permissions: ['view_individual_sample_report'] },
  { label: 'GAR (Abstract)',      icon: '📄', route: '/reports/gar',                                   permissions: ['view_gar'] },
  { label: 'GSR (Summary)',       icon: '📋', route: '/reports/gsr',                                   permissions: ['view_gsr'] },
  { label: 'ASR (Analysis Summary)', icon: '📊', route: '/reports/asr',                                permissions: ['view_asr'] },
  { label: 'CE-Wise Report',      icon: '🗺️', route: '/reports/ce-wise',                              permissions: ['view_ce_wise_report'] },
  { label: 'PWR (Parameter-wise)', icon: '🔬', route: '/reports/pwr',                                  permissions: ['view_pwr'] },
  { label: 'WSS Map',             icon: '🗾', route: '/reports/wss-map',                               permissions: ['view_water_schemes'] },
  { section: 'Finance',                                                                                permissions: ['view_invoices', 'view_payments', 'view_sbp_submissions'] },
  { label: 'Invoices / Revenue',  icon: '🧾', route: '/finance/invoices',                              permissions: ['view_invoices'] },
  { label: 'SBP Submissions',     icon: '🏦', route: '/finance/sbp-submissions',                       permissions: ['view_sbp_submissions'] },
  { section: 'Asset Management',                                                                       permissions: ['view_inventories', 'view_assets', 'view_materials'] },
  { label: 'Stock / Inventory',   icon: '📦', route: '/assets/stock-inventory',                        permissions: ['view_inventories', 'view_materials'] },
  { label: 'Equipment Register',  icon: '🔧', route: '/assets/equipment-register',                     permissions: ['view_assets'] },
  { label: 'Demand & Issuance',   icon: '🔄', route: '/assets/demand-issuance',                        permissions: ['view_demands'] },
  { section: 'Admin',                                                                                  adminOnly: true },
  { label: 'Users / HR',          icon: '👥', route: '/admin/users-hr',                                adminOnly: true },
  { label: 'KPI Framework',       icon: '📊', route: '/admin/kpi-framework',                           adminOnly: true },
  { label: 'Diaries / Dispatches', icon: '📝', route: '/admin/diaries-dispatches',                     permissions: ['view_diaries', 'view_dispatches'] },
  { label: 'Water Scheme Details', icon: '💧', route: '/wss-details',                                  permissions: ['view_water_schemes'] },
  { section: 'Settings',                                                                               adminOnly: true },
  { label: 'Provinces',           icon: '🗺️', route: '/settings/provinces',                            adminOnly: true },
  { label: 'Divisions',           icon: '🗺️', route: '/settings/divisions',                            adminOnly: true },
  { label: 'Districts',           icon: '🗺️', route: '/settings/districts',                            adminOnly: true },
  { label: 'Tehsils',             icon: '🗺️', route: '/settings/tehsils',                              adminOnly: true },
  { label: 'Union Councils',      icon: '🗺️', route: '/settings/union-councils',                       adminOnly: true },
  { label: 'Designations',        icon: '👔', route: '/settings/designations',                         adminOnly: true },
  { label: 'Water Parameters',    icon: '🧪', route: '/settings/water-parameters',                     adminOnly: true },
  { label: 'Abbreviations',       icon: '🔤', route: '/settings/abbreviations',                        adminOnly: true },
  { label: 'Units',               icon: '📏', route: '/settings/units',                                adminOnly: true },
  { label: 'Complaint Type',      icon: '📋', route: '/settings/complaint-type',                       adminOnly: true },
  { label: 'Discounts',           icon: '💰', route: '/settings/discounts',                            adminOnly: true },
  { label: 'Roles & Permissions', icon: '🔐', route: '/admin/roles-permissions',                       adminOnly: true },
]

// Permission-based visibility. Unscoped admin roles see everything.
// Two optional role-based escape hatches:
//   onlyForRoles: ['role-slug', ...] — item is visible ONLY to those roles
//     (overrides the unscoped bypass — useful for role-specific screens
//     like the lab-incharge "Water Samples" overview)
//   hideForRoles: ['role-slug', ...] — item is hidden from those roles even
//     if they hold the gating permission (used to redirect a role to a
//     different screen, e.g. lab-incharge → Water Samples instead of
//     Sample Registration / Analysis Entry)
function canSeeItem(item) {
  if (Array.isArray(item.onlyForRoles) && item.onlyForRoles.length > 0) {
    return item.onlyForRoles.some(r => userStore.hasRole(r))
  }
  if (Array.isArray(item.hideForRoles) && item.hideForRoles.some(r => userStore.hasRole(r))) {
    return false
  }
  if (item.adminOnly) return isUnscoped.value
  if (!item.permissions || item.permissions.length === 0) return true
  if (isUnscoped.value) return true
  return item.permissions.some(p => userStore.hasPermission(p))
}

const visibleNavItems = computed(() => {
  // Two passes: first filter individual items by role, then drop section
  // headers that have no remaining items beneath them.
  const filtered = navItems.filter(canSeeItem)
  const out = []
  for (let i = 0; i < filtered.length; i++) {
    const item = filtered[i]
    if (item.section) {
      // Look ahead for at least one non-section item before the next section.
      let hasChild = false
      for (let j = i + 1; j < filtered.length; j++) {
        if (filtered[j].section) break
        hasChild = true
        break
      }
      if (hasChild) out.push(item)
    } else {
      out.push(item)
    }
  }
  return out
})

function getBadge(item) {
  return item.badge || null
}
</script>

<template>
  <aside class="sidebar" :class="{ 'sidebar--collapsed': store.sidebarCollapsed }">
    <!-- Brand -->
    <div class="sb-brand">
      <div class="sb-org">PHED — Khyber Pakhtunkhwa</div>
      <div class="sb-name">Lab MIS</div>
      <div class="sb-ver">v2.1 · March 2026</div>
    </div>

    <!-- Nav -->
    <nav>
      <template v-for="item in visibleNavItems" :key="item.label || item.section">
        <!-- Section header -->
        <div v-if="item.section" class="sb-sec">{{ item.section }}</div>

        <!-- Nav item -->
        <RouterLink
          v-else
          :to="item.route"
          class="sb-item"
          active-class="active"
        >
          <span class="ic">{{ item.icon }}</span>
          {{ item.label }}
          <span v-if="getBadge(item)" class="badge">{{ getBadge(item) }}</span>
        </RouterLink>
      </template>
    </nav>
  </aside>
</template>

<style lang="scss" scoped>
.sidebar {
  width: var(--sidebar);
  background: var(--navy);
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  overflow-y: auto;
  transition: width .2s;

  &--collapsed {
    width: 56px;
    .sb-org, .sb-name, .sb-ver { display: none; }
  }
}

.sb-brand {
  padding: 14px 14px 10px;
  border-bottom: 1px solid rgba(255,255,255,.08);
}
.sb-org  { font-size: 9.5px; color: rgba(255,255,255,.4); letter-spacing: .07em; text-transform: uppercase; margin-bottom: 2px; }
.sb-name { font-size: 13px; font-weight: 700; color: #fff; }
.sb-ver  { font-size: 10px; color: rgba(255,255,255,.3); margin-top: 1px; }

.sb-sec {
  padding: 12px 14px 4px;
  font-size: 9.5px;
  letter-spacing: .07em;
  text-transform: uppercase;
  color: rgba(255,255,255,.3);
  font-weight: 600;
}

.sb-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 7px 14px;
  cursor: pointer;
  color: rgba(255,255,255,.6);
  font-size: 12px;
  transition: all .12s;
  border-left: 2px solid transparent;
  user-select: none;
  text-decoration: none;

  &:hover { background: rgba(255,255,255,.05); color: #fff; }

  &.active {
    background: rgba(21,101,192,.35);
    color: #fff;
    border-left-color: #42a5f5;
  }

  .ic { font-size: 13px; width: 16px; text-align: center; }

  .badge {
    margin-left: auto;
    background: #b91c1c;
    color: #fff;
    font-size: 9px;
    padding: 1px 5px;
    border-radius: 8px;
  }
}
</style>
