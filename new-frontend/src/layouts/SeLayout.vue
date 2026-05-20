<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { seService } from '../services/seService.js'
import { useUserStore } from '../stores/useUserStore.js'

const router = useRouter()
const userStore = useUserStore()

const me = ref(null)
const unfitCount = ref(0)
const retestCount = ref(0)

// ── Notification bell state ─────────────────────────────────────────
const notifUnread  = ref(0)
const notifItems   = ref([])
const notifOpen    = ref(false)
const notifLoading = ref(false)
const bellRef      = ref(null)
let pollTimer = null


async function loadIdentity() {
  try { me.value = await seService.me() }
  catch (e) {
    const cached = userStore.currentUser
    if (cached) {
      me.value = {
        name: cached.name,
        circle: cached.circle,
        districts: [],
        designation: 'Superintendent Engineer',
      }
    }
  }
}
async function loadCounts() {
  try {
    const dash = await seService.dashboard()
    unfitCount.value  = dash?.stats?.unfit_no_action ?? 0
    retestCount.value = dash?.stats?.retests_pending ?? 0
  } catch { /* silent */ }
  // Fire notification refresh independently so a missing/forbidden
  // dashboard endpoint doesn't kill the bell.
  await refreshNotifications()
}

async function refreshNotifications() {
  notifLoading.value = true
  try {
    const n = await seService.notifications()
    notifUnread.value = n?.count ?? 0
    notifItems.value  = n?.items ?? []
  } catch { /* silent */ }
  finally { notifLoading.value = false }
}

function toggleNotifications() {
  notifOpen.value = !notifOpen.value
  if (notifOpen.value) refreshNotifications()
}

async function markRead(id) {
  const item = notifItems.value.find(n => n.id === id)
  if (!item || item.read_at) return
  // Optimistic update + rollback on failure
  item.read_at = new Date().toISOString()
  notifUnread.value = Math.max(0, notifUnread.value - 1)
  try {
    await seService.markNotificationsRead([id])
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
    await seService.markNotificationsRead(unreadIds)
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
  pollTimer = setInterval(refreshNotifications, 60_000)
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

const circleName = computed(() => me.value?.circle?.name || '—')
const seName     = computed(() => me.value?.name || 'SE Officer')

// Districts scope label — collapse to a count + first 3 names when there are
// many districts (e.g. the test SA account has access to all 34 KP districts,
// which otherwise becomes a 3-line wall of comma-separated text in the
// sidebar). Click to expand / collapse.
const districtsList = computed(() => (me.value?.districts || []).map(d => d.name))
const districtsExpanded = ref(false)
const SCOPE_PREVIEW_COUNT = 3
const districtsLabel = computed(() => {
  const names = districtsList.value
  if (!names.length) return '—'
  if (districtsExpanded.value || names.length <= SCOPE_PREVIEW_COUNT + 1) {
    return names.join(' · ')
  }
  const more = names.length - SCOPE_PREVIEW_COUNT
  return `${names.slice(0, SCOPE_PREVIEW_COUNT).join(' · ')} +${more} more`
})
const districtsToggleable = computed(() => districtsList.value.length > SCOPE_PREVIEW_COUNT + 1)
function toggleDistricts() {
  if (districtsToggleable.value) districtsExpanded.value = !districtsExpanded.value
}

const navItems = [
  { kind: 'section', label: 'My Division' },
  { kind: 'item', label: 'Dashboard', icon: '📊', route: '/se/dashboard' },
  { kind: 'section', label: 'Reports — My Circle' },
  { kind: 'item', label: 'GAR — My Area',         icon: '📄', route: '/se/gar' },
  { kind: 'item', label: 'GSR — My Area',         icon: '📋', route: '/se/gsr' },
  { kind: 'item', label: 'Individual Report (ISR)', icon: '📑', route: '/se/isr' },
  { kind: 'section', label: 'Unfit Samples' },
  { kind: 'item', label: 'Unfit Trail',    icon: '⚠️', route: '/se/unfit-trail',    badgeRef: 'unfit' },
  { kind: 'item', label: 'Retest Samples', icon: '🧪', route: '/se/retest-samples', badgeRef: 'retest' },
  { kind: 'section', label: 'WSS' },
  { kind: 'item', label: 'WSS Register',   icon: '💧', route: '/se/wss-register' },
]
function badgeFor(ref) {
  if (ref === 'unfit')  return unfitCount.value  || null
  if (ref === 'retest') return retestCount.value || null
  return null
}
</script>

<template>
  <div class="se-app">
    <aside class="se-sidebar">
      <div class="sb-brand">
        <div class="sb-eyebrow">PHED KP — SE PORTAL</div>
        <div class="sb-title">Water Lab MIS</div>
        <div class="sb-ver">v1.1 · Circle Officer</div>
      </div>

      <div class="sb-scope">
        <div class="sb-scope-label">YOUR SCOPE</div>
        <div class="sb-scope-name">SE — {{ circleName }} Circle</div>
        <div class="sb-scope-sub" :class="{ clickable: districtsToggleable }"
             :title="districtsToggleable ? (districtsExpanded ? 'Click to collapse' : 'Click to see all districts') : ''"
             @click="toggleDistricts">
          <span class="sb-scope-count">{{ districtsList.length }} districts</span>
          <span v-if="districtsList.length">:</span>
          {{ districtsLabel }}
          <span v-if="districtsToggleable" class="sb-scope-caret">{{ districtsExpanded ? '▴' : '▾' }}</span>
        </div>
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
    <div class="se-main">
      <header class="se-topbar">
        <div class="st-title">{{ $route.meta?.title || 'Dashboard' }}</div>
        <div class="st-right">
          <span class="st-scope-chip">
            <span class="dot"></span>
            SE {{ circleName }} · {{ districtsLabel }}
          </span>
          <span class="st-user">{{ seName }} — SE</span>

          <!-- Notification bell + dropdown -->
          <div class="st-bell-wrap" ref="bellRef">
            <button
              type="button"
              class="st-bell"
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
                  <button v-if="notifUnread > 0" class="notif-mark-all" type="button" @click="markAllRead">Mark all read</button>
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

          <button class="st-logout" @click="logout" title="Logout">↩</button>
        </div>
      </header>
      <div class="se-content">
        <RouterView :me="me" />
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.se-app {
  display: flex;
  height: 100vh;
  overflow: hidden;
  background: #f5f6f8;
  font-family: 'DM Sans', sans-serif;
}
.se-sidebar {
  width: 230px;
  background: #14304b;
  color: #fff;
  display: flex; flex-direction: column;
  overflow-y: auto;
  flex-shrink: 0;
}
.sb-brand {
  padding: 14px 16px 12px;
  border-bottom: 1px solid rgba(255,255,255,.08);
}
.sb-eyebrow { font-size: 9.5px; color: rgba(255,255,255,.42); letter-spacing: .08em; }
.sb-title   { font-size: 17px; font-weight: 800; margin-top: 3px; }
.sb-ver     { font-size: 10.5px; color: rgba(255,255,255,.4); margin-top: 1px; }

.sb-scope {
  margin: 12px 12px 4px;
  background: rgba(255,255,255,.07);
  border-radius: 6px;
  padding: 10px 12px;
}
.sb-scope-label { font-size: 9.5px; color: rgba(255,255,255,.5); letter-spacing: .07em; }
.sb-scope-name  { font-size: 13.5px; font-weight: 700; margin-top: 4px; }
.sb-scope-sub   {
  font-size: 11px; color: rgba(255,255,255,.62); margin-top: 4px; line-height: 1.4;
  user-select: none;
  &.clickable {
    cursor: pointer;
    transition: color .15s;
    &:hover { color: rgba(255,255,255,.85); }
  }
}
.sb-scope-count {
  display: inline-block;
  font-weight: 700;
  color: rgba(255,255,255,.92);
  background: rgba(255,255,255,.10);
  border-radius: 10px;
  padding: 1px 8px;
  font-size: 10px;
  letter-spacing: .03em;
  margin-right: 2px;
}
.sb-scope-caret {
  display: inline-block;
  margin-left: 4px;
  font-size: 10px;
  color: rgba(255,255,255,.55);
}

.sb-nav { padding: 6px 0 12px; }
.sb-sec {
  padding: 14px 16px 4px;
  font-size: 9.5px;
  color: rgba(255,255,255,.4);
  letter-spacing: .08em;
  font-weight: 600;
  text-transform: uppercase;
}
.sb-item {
  display: flex; align-items: center; gap: 10px;
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
    background: #dc2626; color: #fff;
    font-size: 9.5px; font-weight: 700;
    border-radius: 10px; padding: 1px 7px;
    min-width: 18px; text-align: center;
  }
  &:hover { background: rgba(255,255,255,.06); color: #fff; }
  &.active {
    background: rgba(66,165,245,.18);
    color: #fff;
    border-left-color: #42a5f5;
  }
}

.se-main { flex: 1; display: flex; flex-direction: column; min-width: 0; }
.se-topbar {
  background: #fff;
  border-bottom: 1px solid #e1e6ee;
  padding: 10px 22px;
  display: flex; align-items: center; justify-content: space-between;
  gap: 16px;
  flex-shrink: 0;
}
.st-title { font-size: 16px; font-weight: 700; color: #0f172a; }
.st-right { display: flex; align-items: center; gap: 16px; }
.st-scope-chip {
  display: inline-flex; align-items: center; gap: 6px;
  background: #1c2e44; color: #fff;
  padding: 5px 13px;
  border-radius: 18px;
  font-size: 11.5px;
  font-weight: 600;
  .dot { width: 8px; height: 8px; border-radius: 50%; background: #4ade80; }
}
.st-user { font-size: 12.5px; font-weight: 600; color: #334155; }

/* Bell + notification dropdown — same shape as the XEN layout */
.st-bell-wrap { position: relative; }
.st-bell {
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
  .bell-icon { font-size: 18px; display: inline-block; transform-origin: 50% 0; }
  &.ringing .bell-icon { animation: bell-ring 1.6s ease-in-out infinite; }
  .bell-count {
    position: absolute;
    top: -3px; right: -4px;
    min-width: 16px; height: 16px;
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
  10% { transform: rotate(14deg); }
  20% { transform: rotate(-12deg); }
  30% { transform: rotate(10deg); }
  40% { transform: rotate(-8deg); }
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
  display: flex; align-items: center; justify-content: space-between;
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
.notif-msg  { font-size: 12px; color: #1e293b; line-height: 1.4; margin-bottom: 4px; }
.notif-meta { display: flex; gap: 8px; align-items: center; font-size: 10.5px; color: #64748b; }
.notif-slug { font-weight: 600; color: #475569; }
.notif-unread-dot {
  position: absolute;
  top: 14px; right: 12px;
  width: 7px; height: 7px;
  border-radius: 50%;
  background: #2563eb;
}
.notif-slide-enter-active, .notif-slide-leave-active { transition: opacity .15s ease, transform .15s ease; }
.notif-slide-enter-from, .notif-slide-leave-to       { opacity: 0; transform: translateY(-6px); }

.st-logout {
  background: transparent; border: 1px solid #cbd5e1; color: #475569;
  padding: 4px 10px; border-radius: 5px; font-size: 12px; cursor: pointer;
  &:hover { background: #f1f5f9; color: #0f172a; }
}

.se-content { flex: 1; overflow-y: auto; padding: 20px 24px; }
</style>
