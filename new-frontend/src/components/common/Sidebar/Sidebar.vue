<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useUiStore } from '../../../stores/useUiStore.js'
import { api } from '../../../services/api.js'

const store = useUiStore()

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

const navItems = [
  { label: 'Dashboard',           icon: '🏠', route: '/dashboard' },
  { section: 'Water Quality' },
  { label: 'Sample Registration', icon: '🧪', route: '/water-quality/sample-registration' },
  { label: 'Analysis Entry',      icon: '⚗️', route: '/water-quality/analysis-entry' },
  { label: 'Unfit Sample Trail',  icon: '⚠️', route: '/water-quality/unfit-sample-trail', badgeKey: 'unfit' },
  { section: 'Reports' },
  { label: 'Individual Sample Report', icon: '🧪', route: '/reports/individual-sample' },
  { label: 'GAR (Abstract)',      icon: '📄', route: '/reports/gar' },
  { label: 'GSR (Summary)',       icon: '📋', route: '/reports/gsr' },
  { label: 'ASR (Analysis Summary)', icon: '📊', route: '/reports/asr' },
  { label: 'CE-Wise Report',      icon: '🗺️', route: '/reports/ce-wise' },
  { label: 'PWR (Parameter-wise)', icon: '🔬', route: '/reports/pwr' },
  { label: 'WSS Map',             icon: '🗾', route: '/reports/wss-map' },
  { section: 'Finance' },
  { label: 'Invoices / Revenue',  icon: '🧾', route: '/finance/invoices' },
  { label: 'SBP Submissions',     icon: '🏦', route: '/finance/sbp-submissions' },
  { section: 'Asset Management' },
  { label: 'Stock / Inventory',   icon: '📦', route: '/assets/stock-inventory' },
  { label: 'Equipment Register',  icon: '🔧', route: '/assets/equipment-register' },
  { label: 'Demand & Issuance',   icon: '🔄', route: '/assets/demand-issuance' },
  { section: 'Admin' },
  { label: 'Users / HR',          icon: '👥', route: '/admin/users-hr' },
  { label: 'KPI Framework',       icon: '📊', route: '/admin/kpi-framework' },
  { label: 'Diaries / Dispatches', icon: '📝', route: '/admin/diaries-dispatches' },
  { label: 'Water Scheme Details', icon: '💧', route: '/wss-details' },
]

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
      <template v-for="item in navItems" :key="item.label || item.section">
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
