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
const notifItems = ref([])
const notifOpen = ref(false)
const notifLoading = ref(false)
const bellRef = ref(null)
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
    notifItems.value  = notif?.items ?? []
  } catch {
    /* silent */
  }
}

async function refreshNotifications() {
  notifLoading.value = true
  try {
    const notif = await xenService.notifications()
    notifUnread.value = notif?.count ?? 0
    notifItems.value  = notif?.items ?? []
  } catch {
    /* silent */
  } finally {
    notifLoading.value = false
  }
}

function toggleNotifications() {
  notifOpen.value = !notifOpen.value
  if (notifOpen.value) refreshNotifications()
}

async function markRead(id) {
  const item = notifItems.value.find(n => n.id === id)
  if (!item || item.read_at) return
  item.read_at = new Date().toISOString()
  notifUnread.value = Math.max(0, notifUnread.value - 1)
  try {
    await xenService.markNotificationsRead([id])
  } catch {
    item.read_at = null
    notifUnread.value += 1
  }
}

async function markAllRead() {
  const unreadIds = notifItems.value.filter(n => !n.read_at).map(n => n.id)
  if (unreadIds.length === 0) return
  const now = new Date().toISOString()
  notifItems.value.forEach(n => { if (!n.read_at) n.read_at = now })
  const prev = notifUnread.value
  notifUnread.value = 0
  try {
    await xenService.markNotificationsRead(unreadIds)
  } catch {
    notifUnread.value = prev
    notifItems.value.forEach(n => { if (n.read_at === now) n.read_at = null })
  }
}

function timeAgo(iso) {
  if (!iso) return ''
  const diff = (Date.now() - new Date(iso).getTime()) / 1000
  if (diff < 60)        return 'just now'
  if (diff < 3600)      return Math.floor(diff / 60) + 'm ago'
  if (diff < 86400)     return Math.floor(diff / 3600) + 'h ago'
  if (diff < 86400 * 7) return Math.floor(diff / 86400) + 'd ago'
  return new Date(iso).toLocaleDateString()
}

function onDocClick(e) {
  if (!notifOpen.value) return
  if (bellRef.value && !bellRef.value.contains(e.target)) {
    notifOpen.value = false
  }
}

onMounted(() => {
  loadIdentity()
  loadCounts()
  pollTimer = setInterval(loadCounts, 60_000)
  document.addEventListener('click', onDocClick)
})
onUnmounted(() => {
  clearInterval(pollTimer)
  document.removeEventListener('click', onDocClick)
})

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
          <div class="xt-bell-wrap" ref="bellRef">
            <button
              type="button"
              class="xt-bell"
              :class="{ ringing: notifUnread > 0, open: notifOpen }"
              title="Notifications"
              @click.stop="toggleNotifications"
            >
              <span class="bell-icon">🔔</span>
              <span v-if="notifUnread > 0" class="bell-count">{{ notifUnread > 99 ? '99+' : notifUnread }}</span>
            </button>
            <transition name="notif-slide">
              <div v-if="notifOpen" class="notif-pop" @click.stop>
                <div class="notif-head">
                  <span class="notif-title">Notifications</span>
                  <button
                    v-if="notifUnread > 0"
                    class="notif-mark-all"
                    type="button"
                    @click="markAllRead"
                  >Mark all read</button>
                </div>
                <div class="notif-list">
                  <div v-if="notifLoading && notifItems.length === 0" class="notif-empty">Loading…</div>
                  <div v-else-if="notifItems.length === 0" class="notif-empty">You're all caught up</div>
                  <button
                    v-for="n in notifItems"
                    :key="n.id"
                    type="button"
                    class="notif-item"
                    :class="{ unread: !n.read_at }"
                    @click="markRead(n.id)"
                  >
                    <span class="notif-kind" :data-kind="n.kind">{{ n.kind }}</span>
                    <div class="notif-body">
                      <div class="notif-msg">{{ n.message || ('Sample ' + (n.sample_slug || '')) }}</div>
                      <div class="notif-meta">
                        <span v-if="n.sample_slug" class="notif-slug">{{ n.sample_slug }}</span>
                        <span class="notif-time">{{ timeAgo(n.created_at) }}</span>
                      </div>
                    </div>
                    <span v-if="!n.read_at" class="notif-unread-dot"></span>
                  </button>
                </div>
              </div>
            </transition>
          </div>
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
.xt-bell-wrap { position: relative; }
.xt-bell {
  position: relative;
  background: transparent;
  border: none;
  padding: 4px 6px;
  border-radius: 8px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: background .15s;
  &:hover { background: #f1f5f9; }
  &.open  { background: #e2e8f0; }

  .bell-icon {
    font-size: 18px;
    display: inline-block;
    transform-origin: 50% 0;
  }
  &.ringing .bell-icon {
    animation: bell-ring 1.6s ease-in-out infinite;
  }

  .bell-count {
    position: absolute;
    top: -3px;
    right: -4px;
    min-width: 16px;
    height: 16px;
    padding: 0 4px;
    border-radius: 8px;
    background: #dc2626;
    color: #fff;
    font-size: 9.5px;
    font-weight: 700;
    line-height: 16px;
    text-align: center;
    box-shadow: 0 0 0 2px #fff;
  }
}

@keyframes bell-ring {
  0%, 50%, 100% { transform: rotate(0deg); }
  10%           { transform: rotate(14deg); }
  20%           { transform: rotate(-12deg); }
  30%           { transform: rotate(10deg); }
  40%           { transform: rotate(-8deg); }
}

.notif-pop {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  width: 340px;
  max-height: 440px;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  box-shadow: 0 10px 30px rgba(15, 23, 42, .14);
  z-index: 1000;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.notif-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 14px;
  border-bottom: 1px solid #eef1f5;
}
.notif-title { font-size: 13px; font-weight: 700; color: #0f172a; }
.notif-mark-all {
  background: transparent;
  border: none;
  color: #2563eb;
  font-size: 11.5px;
  font-weight: 600;
  cursor: pointer;
  padding: 2px 4px;
  border-radius: 4px;
  &:hover { background: #eff6ff; }
}
.notif-list { overflow-y: auto; max-height: 380px; }
.notif-empty { padding: 24px 16px; text-align: center; color: #94a3b8; font-size: 12px; }
.notif-item {
  position: relative;
  width: 100%;
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 14px;
  background: transparent;
  border: none;
  border-bottom: 1px solid #f1f5f9;
  text-align: left;
  cursor: pointer;
  transition: background .12s;
  &:hover  { background: #f8fafc; }
  &.unread { background: #f0f7ff; }
  &.unread:hover { background: #e6f0ff; }
  &:last-child { border-bottom: none; }
}
.notif-kind {
  flex-shrink: 0;
  font-size: 9px;
  font-weight: 700;
  letter-spacing: .04em;
  padding: 3px 7px;
  border-radius: 4px;
  text-transform: uppercase;
  background: #e2e8f0;
  color: #475569;
  &[data-kind="Unfit"]      { background: #fee2e2; color: #b91c1c; }
  &[data-kind="Retest"]     { background: #fef3c7; color: #92400e; }
  &[data-kind="Escalation"] { background: #fce7f3; color: #9d174d; }
}
.notif-body { flex: 1; min-width: 0; }
.notif-msg {
  font-size: 12px;
  color: #1e293b;
  line-height: 1.4;
  margin-bottom: 4px;
  white-space: normal;
}
.notif-meta {
  display: flex;
  gap: 8px;
  align-items: center;
  font-size: 10.5px;
  color: #64748b;
}
.notif-slug { font-weight: 600; color: #475569; }
.notif-unread-dot {
  position: absolute;
  top: 14px;
  right: 12px;
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #2563eb;
}
.notif-slide-enter-active, .notif-slide-leave-active {
  transition: opacity .15s ease, transform .15s ease;
}
.notif-slide-enter-from, .notif-slide-leave-to {
  opacity: 0; transform: translateY(-6px);
}
.xt-logout {
  background: transparent; border: 1px solid #cbd5e1; color: #475569;
  padding: 4px 10px; border-radius: 5px; font-size: 12px; cursor: pointer;
  &:hover { background: #f1f5f9; color: #0f172a; }
}

.xen-content { flex: 1; overflow-y: auto; padding: 20px 24px; }
</style>
