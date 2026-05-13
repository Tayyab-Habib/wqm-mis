<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { xenService } from '../services/xenService.js'
import { useUserStore } from '../stores/useUserStore.js'

const router = useRouter()
const userStore = useUserStore()

const me = ref(null)
const unfitCount = ref(0)
const retestCount = ref(0)
const notifUnread = ref(0)
let pollTimer = null

async function loadIdentity() {
  try {
    me.value = await xenService.me()
  } catch (e) {
    const cached = userStore.currentUser
    if (cached) {
      me.value = {
        name: cached.name,
        phed_division: cached.phed_division,
        district: cached.district,
        sub_area: cached.phed_division?.name,
        phone: cached.phone,
      }
    }
  }
}

async function loadCounts() {
  try {
    const [dash, retest, notif] = await Promise.all([
      xenService.dashboard().catch(() => null),
      xenService.retestSamples().catch(() => null),
      xenService.notifications().catch(() => null),
    ])
    unfitCount.value = dash?.stats?.unfit_no_action ?? dash?.unfit_samples?.length ?? 0
    retestCount.value = retest?.retests?.length ?? 0
    notifUnread.value = notif?.count ?? 0
  } catch {
    /* silent */
  }
}

onMounted(() => {
  loadIdentity()
  loadCounts()
  pollTimer = setInterval(loadCounts, 60_000)
})
onUnmounted(() => clearInterval(pollTimer))

function logout() {
  userStore.logout()
  router.push({ path: '/login', query: { loggedOut: '1' } })
}

const divisionLabel = computed(() => me.value?.phed_division?.name || 'Division —')
const subAreaLabel  = computed(() => me.value?.sub_area || me.value?.phed_division?.name || '—')
const districtLabel = computed(() => me.value?.district?.name || '—')
const xenName       = computed(() => me.value?.name || 'XEN Officer')
const xenPhone      = computed(() => me.value?.phone || '—')

const navItems = [
  { kind: 'section', label: 'My Division' },
  { kind: 'item', label: 'Dashboard',      icon: '📊', route: '/xen/dashboard' },
  { kind: 'item', label: 'Unfit Trail',    icon: '⚠️', route: '/xen/unfit-trail',    badgeRef: 'unfit' },
  { kind: 'item', label: 'Retest Samples', icon: '🧪', route: '/xen/retest-samples', badgeRef: 'retest' },
  { kind: 'section', label: 'Reports — My Division' },
  { kind: 'item', label: 'GSR — My Division',    icon: '📄', route: '/xen/gsr' },
  { kind: 'item', label: 'Individual Report (ISR)', icon: '📋', route: '/xen/isr' },
  { kind: 'section', label: 'WSS' },
  { kind: 'item', label: 'WSS Register',   icon: '💧', route: '/xen/wss-register' },
  { kind: 'section', label: 'System' },
  { kind: 'item', label: 'Settings',       icon: '⚙️', route: '/xen/settings' },
]

function badgeFor(ref) {
  if (ref === 'unfit')  return unfitCount.value  || null
  if (ref === 'retest') return retestCount.value || null
  return null
}
</script>

<template>
  <div class="xen-app">
    <!-- ── Sidebar ────────────────────────────────────────────── -->
    <aside class="xen-sidebar">
      <div class="xb-brand">
        <div class="xb-eyebrow">PHED KP — XEN PORTAL</div>
        <div class="xb-title">Water Lab MIS</div>
        <div class="xb-ver">v1.1 · Division Officer</div>
      </div>

      <div class="xb-scope">
        <div class="xb-scope-label">YOUR SCOPE</div>
        <div class="xb-scope-name">{{ divisionLabel }}</div>
        <div class="xb-scope-sub">{{ subAreaLabel }}</div>
      </div>

      <nav class="xb-nav">
        <template v-for="(item, idx) in navItems" :key="idx">
          <div v-if="item.kind === 'section'" class="xb-sec">{{ item.label }}</div>
          <RouterLink v-else :to="item.route" class="xb-item" active-class="active">
            <span class="ic">{{ item.icon }}</span>
            <span class="lbl">{{ item.label }}</span>
            <span v-if="badgeFor(item.badgeRef)" class="badge">{{ badgeFor(item.badgeRef) }}</span>
          </RouterLink>
        </template>
      </nav>
    </aside>

    <!-- ── Main ───────────────────────────────────────────────── -->
    <div class="xen-main">
      <header class="xen-topbar">
        <div class="xt-title">{{ $route.meta?.title || 'XEN Dashboard' }}</div>
        <div class="xt-right">
          <span class="xt-scope-chip">
            <span class="dot"></span>
            {{ divisionLabel }} · {{ subAreaLabel }}
          </span>
          <span class="xt-user">{{ xenName }} — XEN</span>
          <span class="xt-bell" :class="{ has: notifUnread > 0 }" title="Notifications">🔔
            <span v-if="notifUnread > 0" class="bell-dot"></span>
          </span>
          <button class="xt-logout" @click="logout" title="Logout">↩</button>
        </div>
      </header>
      <div class="xen-content">
        <RouterView :me="me" />
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.xen-app {
  display: flex;
  height: 100vh;
  overflow: hidden;
  background: #f5f6f8;
  font-family: 'DM Sans', sans-serif;
}

.xen-sidebar {
  width: 230px;
  background: #14304b;
  color: #fff;
  display: flex;
  flex-direction: column;
  overflow-y: auto;
  flex-shrink: 0;
}
.xb-brand {
  padding: 14px 16px 12px;
  border-bottom: 1px solid rgba(255,255,255,.08);
}
.xb-eyebrow { font-size: 9.5px; color: rgba(255,255,255,.42); letter-spacing: .08em; }
.xb-title   { font-size: 17px; font-weight: 800; margin-top: 3px; }
.xb-ver     { font-size: 10.5px; color: rgba(255,255,255,.4); margin-top: 1px; }

.xb-scope {
  margin: 12px 12px 4px;
  background: rgba(255,255,255,.07);
  border-radius: 6px;
  padding: 10px 12px;
}
.xb-scope-label { font-size: 9.5px; color: rgba(255,255,255,.5); letter-spacing: .07em; }
.xb-scope-name  { font-size: 13.5px; font-weight: 700; margin-top: 4px; }
.xb-scope-sub   { font-size: 11px; color: rgba(255,255,255,.62); margin-top: 2px; line-height: 1.3; }

.xb-nav { padding: 6px 0 12px; }
.xb-sec {
  padding: 14px 16px 4px;
  font-size: 9.5px;
  color: rgba(255,255,255,.4);
  letter-spacing: .08em;
  font-weight: 600;
  text-transform: uppercase;
}
.xb-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 16px;
  color: rgba(255,255,255,.7);
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

  &:hover { background: rgba(255,255,255,.06); color: #fff; }
  &.active {
    background: rgba(66,165,245,.18);
    color: #fff;
    border-left-color: #42a5f5;
  }
}

.xen-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.xen-topbar {
  background: #fff;
  border-bottom: 1px solid #e1e6ee;
  padding: 10px 22px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-shrink: 0;
}
.xt-title { font-size: 16px; font-weight: 700; color: #0f172a; }
.xt-right { display: flex; align-items: center; gap: 16px; }
.xt-scope-chip {
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
.xt-user { font-size: 12.5px; font-weight: 600; color: #334155; }
.xt-bell {
  position: relative; font-size: 16px; cursor: pointer;
  .bell-dot {
    position: absolute; top: -2px; right: -2px;
    width: 7px; height: 7px; border-radius: 50%; background: #dc2626;
  }
}
.xt-logout {
  background: transparent; border: 1px solid #cbd5e1; color: #475569;
  padding: 4px 10px; border-radius: 5px; font-size: 12px; cursor: pointer;
  &:hover { background: #f1f5f9; color: #0f172a; }
}

.xen-content { flex: 1; overflow-y: auto; padding: 20px 24px; }
</style>
