<script setup>
import { useUserStore } from '../../../stores/useUserStore.js'
import { useRoute, useRouter } from 'vue-router'
import { computed, ref, watch } from 'vue'

const userStore = useUserStore()
const route = useRoute()
const router = useRouter()
defineEmits(['toggle-nav'])

const breadcrumb = computed(() => route.meta?.title || route.name || 'Dashboard')

const displayName = computed(() => userStore.currentUser?.name || 'User')
const displayRole = computed(() => userStore.currentUser?.role || '')
const displayLab  = computed(() => userStore.currentUser?.laboratory?.name || 'Central Lab — Peshawar')

// ── Toast ─────────────────────────────────────────────────────────────
const toast = ref({ show: false, message: '', type: 'success' })
let toastTimer = null

function showToast(message, type = 'success') {
  clearTimeout(toastTimer)
  toast.value = { show: true, message, type }
  toastTimer = setTimeout(() => { toast.value.show = false }, 4000)
}

watch(
  () => route.query.loggedIn,
  (loggedIn) => {
    if (loggedIn === '1') {
      showToast('✅ Successfully logged in', 'success')
      router.replace('/dashboard')
    }
  },
  { immediate: true }
)

async function handleLogout() {
  userStore.logout()
  router.push({ path: '/login', query: { loggedOut: '1' } })
}
</script>

<template>
  <div class="topbar">
    <!-- ── Toast notification ── -->
    <Teleport to="body">
      <Transition name="toast-slide">
        <div v-if="toast.show"
             :style="`position:fixed;top:22px;right:24px;z-index:9999;min-width:300px;max-width:460px;
                      background:${toast.type === 'success' ? '#065f46' : '#991b1b'};
                      color:#fff;border-radius:8px;padding:14px 18px;
                      box-shadow:0 6px 32px rgba(0,0,0,.28);font-size:13px;display:flex;align-items:flex-start;gap:10px`">
          <span style="flex:1;line-height:1.5">{{ toast.message }}</span>
          <button @click="toast.show = false"
                  style="background:rgba(255,255,255,.2);border:none;color:#fff;border-radius:4px;
                         padding:2px 8px;cursor:pointer;font-size:13px;margin-left:4px">✕</button>
        </div>
      </Transition>
    </Teleport>

    <button class="mobile-nav-toggle" @click="$emit('toggle-nav')" title="Menu" aria-label="Menu">☰</button>
    <div class="tbar-bc" id="bc">{{ breadcrumb }}</div>
    <div class="tbar-sp"></div>
    <div class="notif">
      🔔
      <div class="notif-dot"></div>
    </div>
    <div class="lab-tag">🏛 {{ displayLab }}</div>
    <div class="user-tag">{{ displayName }} &nbsp;·&nbsp; {{ displayRole }}</div>
    <button class="btn-logout" @click="handleLogout" title="Logout">⏻ Logout</button>
  </div>
</template>

<style lang="scss" scoped>
.topbar {
  height: var(--hdr);
  background: var(--white);
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  padding: 0 18px;
  gap: 10px;
  flex-shrink: 0;
  position: sticky;
  top: 0;
  z-index: 100;
}
.tbar-bc { font-size: 12px; color: var(--muted); }
.tbar-sp { flex: 1; }
.lab-tag {
  background: var(--navy);
  color: #fff;
  font-size: 11px;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: 4px;
}
.user-tag { font-size: 12px; color: var(--muted); }
.notif {
  position: relative;
  cursor: pointer;
  font-size: 17px;
}
.notif-dot {
  position: absolute;
  top: -1px;
  right: -1px;
  width: 7px;
  height: 7px;
  background: #b91c1c;
  border-radius: 50%;
  border: 1.5px solid var(--white);
}
.btn-logout {
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 4px;
  padding: 4px 10px;
  font-size: 11px;
  color: var(--muted);
  cursor: pointer;
  font-family: inherit;
  transition: all .15s;
  &:hover {
    background: #fef2f2;
    border-color: #fca5a5;
    color: #b91c1c;
  }
}

.toast-slide-enter-active,
.toast-slide-leave-active {
  transition: all 0.3s ease;
}
.toast-slide-enter-from,
.toast-slide-leave-to {
  opacity: 0;
  transform: translateX(60px);
}
</style>
