<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ceService } from '../services/ceService.js'
import { useUserStore } from '../stores/useUserStore.js'

const router = useRouter()
const route  = useRoute()
const userStore = useUserStore()

// Off-canvas mobile nav
const navOpen = ref(false)
function toggleNav() { navOpen.value = !navOpen.value }
function closeNav()  { navOpen.value = false }
watch(() => route.fullPath, closeNav)

const me = ref(null)
const ceEscalatedCount = ref(0)
const notifUnread = ref(0)

async function loadIdentity() {
  try {
    me.value = await ceService.me()
  } catch (e) {
    const cached = userStore.currentUser
    if (cached) {
      me.value = {
        id: cached.id,
        name: cached.name,
        designation: 'Chief Engineer',
        region: cached.region,
        circles: [],
      }
    }
  }
}

async function loadCounts() {
  try {
    const dash = await ceService.dashboard()
    ceEscalatedCount.value = dash?.row2?.ce_escalated_no_action ?? 0
    notifUnread.value = (dash?.notifications || []).length
  } catch (e) { /* silent */ }
}

onMounted(() => {
  loadIdentity()
  loadCounts()
})

function logout() {
  userStore.logout()
  router.push({ path: '/login', query: { loggedOut: '1' } })
}

const regionLabel = computed(() => me.value?.region?.name || 'Region —')
const ceName      = computed(() => me.value?.name || 'CE Officer')

// RBAC: every nav item carries the perm that gates its route. Items the
// user lacks the perm for get filtered out, and any section header that
// loses all its children disappears too. Unscoped admins bypass via
// userStore.hasPermission().
const navItems = computed(() => {
  const circles = me.value?.circles || []
  const all = [
    { kind: 'section', label: 'Overview' },
    { kind: 'item',    label: 'Dashboard', icon: '📊', route: '/ce/dashboard', perm: 'view_ce_dashboard' },
    { kind: 'section', label: 'Unfit Trail — By SE Circle' },
    ...circles.map(c => ({
      kind: 'item',
      label: c.label,
      icon: '📍',
      route: `/ce/circles/${c.id}`,
      perm: 'view_ce_circle_detail',
    })),
    { kind: 'section', label: 'Escalations' },
    { kind: 'item', label: 'CE Escalated Cases',   icon: '⚠️', route: '/ce/escalated-cases',  badgeRef: 'ce', perm: 'view_ce_escalated_cases' },
    { kind: 'item', label: 'Persistent Unfit WSS', icon: '🔴', route: '/ce/persistent-unfit', perm: 'view_ce_persistent_unfit' },
    { kind: 'section', label: 'Reports' },
    { kind: 'item', label: 'GAR — My Area',  icon: '📄', route: '/ce/gar',          perm: 'view_ce_gar' },
    { kind: 'item', label: 'WSS Register',   icon: '💧', route: '/ce/wss-register', perm: 'view_ce_wss_register' },
  ]

  // Step 1: drop items the user lacks the perm for.
  const visible = all.filter(it => {
    if (it.kind !== 'item') return true
    if (!it.perm) return true
    return userStore.hasPermission(it.perm)
  })

  // Step 2: drop section headers that no longer have any items beneath them.
  const out = []
  for (let i = 0; i < visible.length; i++) {
    const it = visible[i]
    if (it.kind === 'section') {
      let hasChild = false
      for (let j = i + 1; j < visible.length; j++) {
        if (visible[j].kind === 'section') break
        hasChild = true
        break
      }
      if (hasChild) out.push(it)
    } else {
      out.push(it)
    }
  }
  return out
})

const scopeChips = computed(() => {
  const circles = me.value?.circles || []
  // CE — Peshawar · SE Psh · SE Mrd · SE Khyber  (short form for topbar pill)
  const shortNames = circles.slice(0, 4).map(c => 'SE ' + c.name.slice(0, 3))
  return ['CE ' + (me.value?.region?.name?.replace(/^CE\s*—\s*/, '') || ''), ...shortNames]
})

function badgeFor(ref) {
  if (ref === 'ce') return ceEscalatedCount.value || null
  return null
}
</script>

<template>
  <div class="ce-app" :class="{ 'nav-open': navOpen }">
    <!-- ── Sidebar ────────────────────────────────────────────── -->
    <aside class="ce-sidebar">
      <div class="cb-brand">
        <div class="cb-eyebrow">PHED KP — CE PORTAL</div>
        <div class="cb-title">Water Lab MIS</div>
        <div class="cb-ver">v1.0 · Chief Engineer</div>
      </div>

      <div class="cb-scope">
        <div class="cb-scope-label">YOUR SCOPE</div>
        <div class="cb-scope-name">CE — {{ regionLabel.replace(/^CE\s*—\s*/, '') }}</div>
        <div class="cb-scope-sub">{{ (me?.circles || []).map(c => 'SE ' + c.name).join(' · ') }}</div>
      </div>

      <nav class="cb-nav">
        <template v-for="(item, idx) in navItems" :key="idx">
          <div v-if="item.kind === 'section'" class="cb-sec">{{ item.label }}</div>
          <RouterLink v-else :to="item.route" class="cb-item" active-class="active">
            <span class="ic">{{ item.icon }}</span>
            <span class="lbl">{{ item.label }}</span>
            <span v-if="badgeFor(item.badgeRef)" class="badge">{{ badgeFor(item.badgeRef) }}</span>
          </RouterLink>
        </template>
      </nav>
    </aside>
    <div class="mobile-nav-backdrop" @click="closeNav"></div>

    <!-- ── Main ───────────────────────────────────────────────── -->
    <div class="ce-main">
      <header class="ce-topbar">
        <button class="mobile-nav-toggle" @click="toggleNav" title="Menu" aria-label="Menu">☰</button>
        <div class="ct-title">{{ $route.meta?.title || 'Dashboard' }}</div>
        <div class="ct-right">
          <span class="ct-scope-chip">
            <span class="dot"></span>
            <span v-for="(c, i) in scopeChips" :key="i">{{ c }}<span v-if="i < scopeChips.length - 1"> · </span></span>
          </span>
          <span class="ct-user">{{ ceName }} — CE</span>
          <span class="ct-bell" :class="{ has: notifUnread > 0 }" title="Notifications">🔔
            <span v-if="notifUnread > 0" class="bell-dot"></span>
          </span>
          <button class="ct-logout" @click="logout" title="Logout">↩</button>
        </div>
      </header>
      <div class="ce-content">
        <RouterView :me="me" />
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.ce-app {
  display: flex;
  height: 100vh;
  overflow: hidden;
  background: #f5f6f8;
  font-family: 'DM Sans', sans-serif;
}

.ce-sidebar {
  width: 230px;
  background: #14304b;
  color: #fff;
  display: flex;
  flex-direction: column;
  overflow-y: auto;
  flex-shrink: 0;
}
.cb-brand {
  padding: 14px 16px 12px;
  border-bottom: 1px solid rgba(255, 255, 255, .08);
}
.cb-eyebrow { font-size: 9.5px; color: rgba(255, 255, 255, .42); letter-spacing: .08em; }
.cb-title   { font-size: 17px; font-weight: 800; margin-top: 3px; }
.cb-ver     { font-size: 10.5px; color: rgba(255, 255, 255, .4); margin-top: 1px; }

.cb-scope {
  margin: 12px 12px 4px;
  background: rgba(255, 255, 255, .07);
  border-radius: 6px;
  padding: 10px 12px;
}
.cb-scope-label { font-size: 9.5px; color: rgba(255, 255, 255, .5); letter-spacing: .07em; }
.cb-scope-name  { font-size: 13.5px; font-weight: 700; margin-top: 4px; }
.cb-scope-sub   { font-size: 11px; color: rgba(255, 255, 255, .62); margin-top: 2px; line-height: 1.3; }

.cb-nav { padding: 6px 0 12px; }
.cb-sec {
  padding: 14px 16px 4px;
  font-size: 9.5px;
  color: rgba(255, 255, 255, .4);
  letter-spacing: .08em;
  font-weight: 600;
  text-transform: uppercase;
}
.cb-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 16px;
  color: rgba(255, 255, 255, .7);
  font-size: 12.5px;
  text-decoration: none;
  border-left: 2.5px solid transparent;
  cursor: pointer;
  transition: all .12s;

  .ic { font-size: 14px; width: 16px; text-align: center; }
  .lbl { flex: 1; }
  .badge {
    background: #dc2626;
    color: #fff;
    font-size: 9.5px;
    font-weight: 700;
    border-radius: 10px;
    padding: 1px 7px;
    min-width: 18px;
    text-align: center;
  }

  &:hover { background: rgba(255, 255, 255, .06); color: #fff; }
  &.active {
    background: rgba(66, 165, 245, .18);
    color: #fff;
    border-left-color: #42a5f5;
  }
}

.ce-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.ce-topbar {
  background: #fff;
  border-bottom: 1px solid #e1e6ee;
  padding: 10px 22px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-shrink: 0;
}
.ct-title { font-size: 16px; font-weight: 700; color: #0f172a; }
.ct-right { display: flex; align-items: center; gap: 16px; }
.ct-scope-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #1c2e44;
  color: #fff;
  padding: 5px 13px;
  border-radius: 18px;
  font-size: 11.5px;
  font-weight: 600;
  .dot { width: 8px; height: 8px; border-radius: 50%; background: #4ade80; }
}
.ct-user { font-size: 12.5px; font-weight: 600; color: #334155; }
.ct-bell {
  position: relative;
  font-size: 16px;
  cursor: pointer;
  .bell-dot {
    position: absolute;
    top: -2px;
    right: -2px;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #dc2626;
  }
}
.ct-logout {
  background: transparent;
  border: 1px solid #cbd5e1;
  color: #475569;
  padding: 4px 10px;
  border-radius: 5px;
  font-size: 12px;
  cursor: pointer;
  &:hover { background: #f1f5f9; color: #0f172a; }
}

.ce-content { flex: 1; overflow-y: auto; padding: 20px 24px; }
</style>
