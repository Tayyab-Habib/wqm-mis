<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useUiStore } from '../../../stores/useUiStore.js'
import { useUserStore } from '../../../stores/useUserStore.js'
import { api } from '../../../services/api.js'

const store     = useUiStore()
const userStore = useUserStore()

// RBAC role groups for sidebar gating.
// SA + view-only-admin + general-view-account can see virtually everything
// (writes are blocked elsewhere for view-only/general-view).
const ALL_ADMINS   = ['system-administrator', 'system-manager', 'view-only-admin', 'general-view-account']
const LAB_ROLES    = ['lab-incharge', 'junior-clerk', 'laboratory-assistant']
const READ_ANY     = [...ALL_ADMINS, 'lab-incharge']
const WRITE_LAB    = ['system-administrator', 'system-manager', 'lab-incharge']
const DATA_ENTRY   = ['system-administrator', 'system-manager', 'lab-incharge', 'junior-clerk']
const SAMPLE_ENTRY = [...DATA_ENTRY, 'laboratory-assistant']

// ── Dynamic badge counts ──────────────────────────────────────────────
const unfitCount = ref(0)

async function fetchUnfitCount() {
  try {
    const res = await api.post('/search-water-sample', { result: 'Unfit' })
    const data = res.data?.data
    // Handle paginated response
    if (data?.total !== undefined) {
      unfitCount.value = data.total
    } else if (Array.isArray(data)) {
      unfitCount.value = data.length
    } else if (data?.data) {
      unfitCount.value = data.data.length
    }
  } catch (e) {
    // Silently fail — badge just won't show
  }
}

// Refresh every 2 minutes
let refreshTimer = null
onMounted(() => {
  fetchUnfitCount()
  refreshTimer = setInterval(fetchUnfitCount, 120_000)
})
onUnmounted(() => clearInterval(refreshTimer))

// Each item gets a `roles` array → only members of those roles see it.
// Section headers also have `roles` → entire section vanishes if user has none of them.
// Sections with no `roles` are visible to all authenticated users.
const navItems = [
  { label: 'Dashboard',           icon: '🏠', route: '/dashboard' },
  { section: 'Water Quality',                                                                          roles: [...ALL_ADMINS, ...LAB_ROLES] },
  { label: 'Sample Registration', icon: '🧪', route: '/water-quality/sample-registration',             roles: SAMPLE_ENTRY },
  { label: 'Analysis Entry',      icon: '⚗️', route: '/water-quality/analysis-entry',                  roles: SAMPLE_ENTRY.filter(r => r !== 'junior-clerk') },
  { label: 'Unfit Sample Trail',  icon: '⚠️', route: '/water-quality/unfit-sample-trail', badgeKey: 'unfit', roles: READ_ANY },
  { section: 'Reports',                                                                                roles: READ_ANY },
  { label: 'Individual Sample Report', icon: '🧪', route: '/reports/individual-sample',                roles: READ_ANY },
  { label: 'GAR (Abstract)',      icon: '📄', route: '/reports/gar',                                   roles: READ_ANY },
  { label: 'GSR (Summary)',       icon: '📋', route: '/reports/gsr',                                   roles: READ_ANY },
  { label: 'ASR (Analysis Summary)', icon: '📊', route: '/reports/asr',                                roles: READ_ANY },
  { label: 'CE-Wise Report',      icon: '🗺️', route: '/reports/ce-wise',                              roles: READ_ANY },
  { label: 'PWR (Parameter-wise)', icon: '🔬', route: '/reports/pwr',                                  roles: READ_ANY },
  { label: 'WSS Map',             icon: '🗾', route: '/reports/wss-map',                               roles: READ_ANY },
  { section: 'Finance',                                                                                roles: DATA_ENTRY },
  { label: 'Invoices / Revenue',  icon: '🧾', route: '/finance/invoices',                              roles: DATA_ENTRY },
  { label: 'SBP Submissions',     icon: '🏦', route: '/finance/sbp-submissions',                       roles: ['system-administrator', 'system-manager'] },
  { section: 'Asset Management',                                                                       roles: WRITE_LAB },
  { label: 'Stock / Inventory',   icon: '📦', route: '/assets/stock-inventory',                        roles: WRITE_LAB },
  { label: 'Equipment Register',  icon: '🔧', route: '/assets/equipment-register',                     roles: WRITE_LAB },
  { label: 'Demand & Issuance',   icon: '🔄', route: '/assets/demand-issuance',                        roles: WRITE_LAB },
  { section: 'Admin',                                                                                  roles: ['system-administrator', 'system-manager', 'view-only-admin'] },
  { label: 'Users / HR',          icon: '👥', route: '/admin/users-hr',                                roles: ['system-administrator'] },
  { label: 'KPI Framework',       icon: '📊', route: '/admin/kpi-framework',                           roles: ['system-administrator', 'system-manager', 'view-only-admin'] },
  { label: 'Diaries / Dispatches', icon: '📝', route: '/admin/diaries-dispatches',                     roles: DATA_ENTRY },
  { label: 'Water Scheme Details', icon: '💧', route: '/wss-details',                                  roles: READ_ANY },
]

// Filter the nav items based on the current user's role.
// SA always sees everything (extra defensive). Items without `roles` are universal.
function canSeeItem(item) {
  if (!item.roles || item.roles.length === 0) return true
  if (userStore.isSuperAdmin) return true
  return item.roles.some(r => userStore.hasRole(r))
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
  if (item.badgeKey === 'unfit') return unfitCount.value || null
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
