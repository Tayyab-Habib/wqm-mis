<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { secretaryService } from '../services/secretaryService.js'
import { useUserStore } from '../stores/useUserStore.js'

const router = useRouter()
const userStore = useUserStore()

const me = ref(null)
const fatePending = ref(0)
const persistentCount = ref(0)
const notifUnread = ref(0)

async function loadIdentity() {
  try { me.value = await secretaryService.me() }
  catch {
    const cached = userStore.currentUser
    if (cached) {
      me.value = {
        id: cached.id,
        name: cached.name,
        designation: 'Secretary',
        ces: [],
      }
    }
  }
}
async function loadCounts() {
  try {
    const dash = await secretaryService.dashboard()
    fatePending.value     = dash?.row2?.fate_decisions_pending ?? 0
    persistentCount.value = dash?.row2?.persistent_unfit_wss ?? 0
    notifUnread.value     = (dash?.notifications || []).length
  } catch { /* silent */ }
}
onMounted(() => { loadIdentity(); loadCounts() })

function logout() {
  userStore.logout()
  router.push({ path: '/login', query: { loggedOut: '1' } })
}

const userName = computed(() => me.value?.name || 'Secretary')

// RBAC: every nav item carries the permission that gates the corresponding
// route. Items with no `perm` are visible to anyone in the portal.
// Unscoped admins (system-administrator etc.) bypass perm checks by default
// via userStore.hasPermission (SA bypass lives in useUserStore).
const navItems = computed(() => {
  const ces = me.value?.ces || []
  const all = [
    { kind: 'section', label: 'Overview' },
    { kind: 'item',    label: 'Dashboard', icon: '📊', route: '/secretary/dashboard', perm: 'view_secretary_dashboard' },
    { kind: 'section', label: 'Unfit Trail — By CE' },
    ...ces.map(c => ({
      kind: 'item',
      label: c.label,
      icon: '📍',
      route: `/secretary/ce/${c.id}`,
      perm: 'view_secretary_ce_unfit',
    })),
    { kind: 'section', label: 'Decisions Required' },
    { kind: 'item', label: 'WSS Fate Decisions',  icon: '⚖️', route: '/secretary/fate-decisions',   badgeRef: 'fate', perm: 'view_secretary_fate_decisions' },
    { kind: 'item', label: 'Persistent Unfit WSS', icon: '🔴', route: '/secretary/persistent-unfit', perm: 'view_secretary_persistent_unfit' },
    { kind: 'section', label: 'Reports' },
    { kind: 'item', label: 'GAR — Province', icon: '📄', route: '/secretary/gar',           perm: 'view_secretary_gar' },
    { kind: 'item', label: 'WSS Register',    icon: '💧', route: '/secretary/wss-register', perm: 'view_secretary_wss_register' },
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

function badgeFor(ref) {
  if (ref === 'fate') return fatePending.value || null
  return null
}
</script>

<template>
  <div class="sec-app">
    <aside class="sec-sidebar">
      <div class="sb-brand">
        <div class="sb-eyebrow">PHED KP — SECRETARY PORTAL</div>
        <div class="sb-title">Water Lab MIS</div>
        <div class="sb-ver">v1.0 · Secretary</div>
      </div>

      <div class="sb-scope">
        <div class="sb-scope-label">YOUR SCOPE</div>
        <div class="sb-scope-name">Secretary — PHED KP</div>
        <div class="sb-scope-sub">Province-wide · All CEs</div>
      </div>

      <nav class="sb-nav">
        <template v-for="(item, idx) in navItems" :key="idx">
          <div v-if="item.kind === 'section'" class="sb-sec">{{ item.label }}</div>
          <RouterLink v-else :to="item.route" class="sb-item" active-class="active">
            <span class="ic">{{ item.icon }}</span>
            <span class="lbl">{{ item.label }}</span>
            <span v-if="badgeFor(item.badgeRef)" class="badge">{{ badgeFor(item.badgeRef) }}</span>
          </RouterLink>
        </template>
      </nav>
    </aside>

    <div class="sec-main">
      <header class="sec-topbar">
        <div class="st-title">{{ $route.meta?.title || 'Dashboard' }}</div>
        <div class="st-right">
          <span class="st-scope-chip">
            <span class="dot"></span>
            Secretary PHED KP · Province-wide
          </span>
          <span class="st-user">{{ userName }} — Secretary PHED</span>
          <span class="st-bell" :class="{ has: notifUnread > 0 }" title="Notifications">🔔
            <span v-if="notifUnread > 0" class="bell-dot"></span>
          </span>
          <button class="st-logout" @click="logout" title="Logout">↩</button>
        </div>
      </header>
      <div class="sec-content">
        <RouterView :me="me" />
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.sec-app { display: flex; height: 100vh; overflow: hidden; background: #f5f6f8; font-family: 'DM Sans', sans-serif; }

.sec-sidebar { width: 230px; background: #14304b; color: #fff; display: flex; flex-direction: column; overflow-y: auto; flex-shrink: 0; }
.sb-brand { padding: 14px 16px 12px; border-bottom: 1px solid rgba(255,255,255,.08); }
.sb-eyebrow { font-size: 9.5px; color: rgba(255,255,255,.42); letter-spacing: .08em; }
.sb-title   { font-size: 17px; font-weight: 800; margin-top: 3px; }
.sb-ver     { font-size: 10.5px; color: rgba(255,255,255,.4); margin-top: 1px; }
.sb-scope { margin: 12px 12px 4px; background: rgba(255,255,255,.07); border-radius: 6px; padding: 10px 12px; }
.sb-scope-label { font-size: 9.5px; color: rgba(255,255,255,.5); letter-spacing: .07em; }
.sb-scope-name  { font-size: 13.5px; font-weight: 700; margin-top: 4px; }
.sb-scope-sub   { font-size: 11px; color: rgba(255,255,255,.62); margin-top: 2px; line-height: 1.3; }

.sb-nav { padding: 6px 0 12px; }
.sb-sec { padding: 14px 16px 4px; font-size: 9.5px; color: rgba(255,255,255,.4); letter-spacing: .08em; font-weight: 600; text-transform: uppercase; }
.sb-item {
  display: flex; align-items: center; gap: 10px; padding: 8px 16px;
  color: rgba(255,255,255,.7); font-size: 12.5px; text-decoration: none;
  border-left: 2.5px solid transparent; cursor: pointer; transition: all .12s;
  .ic { font-size: 14px; width: 16px; text-align: center; }
  .lbl { flex: 1; }
  .badge { background: #dc2626; color: #fff; font-size: 9.5px; font-weight: 700; border-radius: 10px; padding: 1px 7px; min-width: 18px; text-align: center; }
  &:hover { background: rgba(255,255,255,.06); color: #fff; }
  &.active { background: rgba(66,165,245,.18); color: #fff; border-left-color: #42a5f5; }
}

.sec-main { flex: 1; display: flex; flex-direction: column; min-width: 0; }
.sec-topbar {
  background: #fff; border-bottom: 1px solid #e1e6ee; padding: 10px 22px;
  display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-shrink: 0;
}
.st-title { font-size: 16px; font-weight: 700; color: #0f172a; }
.st-right { display: flex; align-items: center; gap: 16px; }
.st-scope-chip {
  display: inline-flex; align-items: center; gap: 6px;
  background: #1c2e44; color: #fff; padding: 5px 13px; border-radius: 18px;
  font-size: 11.5px; font-weight: 600;
  .dot { width: 8px; height: 8px; border-radius: 50%; background: #facc15; }
}
.st-user { font-size: 12.5px; font-weight: 600; color: #334155; }
.st-bell { position: relative; font-size: 16px; cursor: pointer;
  .bell-dot { position: absolute; top: -2px; right: -2px; width: 7px; height: 7px; border-radius: 50%; background: #dc2626; }
}
.st-logout { background: transparent; border: 1px solid #cbd5e1; color: #475569; padding: 4px 10px; border-radius: 5px; font-size: 12px; cursor: pointer;
  &:hover { background: #f1f5f9; color: #0f172a; }
}

.sec-content { flex: 1; overflow-y: auto; padding: 20px 24px; }
</style>
