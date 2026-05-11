<script setup>
import { useUserStore } from '../../../stores/useUserStore.js'
import { useRoute, useRouter } from 'vue-router'
import { computed, ref, watch } from 'vue'

const userStore = useUserStore()
const route = useRoute()
const router = useRouter()

const breadcrumb = computed(() => route.meta?.title || route.name || 'Dashboard')

const displayName = computed(() => userStore.currentUser?.name || 'User')
const displayRole = computed(() => userStore.currentUser?.role || '')
const displayLab  = computed(() => userStore.currentUser?.laboratory?.name || 'Central Lab — Peshawar')

const successAlert = ref(false)

function closeSuccessAlert() {
  successAlert.value = false
}

watch(
  () => route.query.loggedIn,
  (loggedIn) => {
    if (loggedIn === '1') {
      successAlert.value = true
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
    <Transition name="sweet-alert">
      <div v-if="successAlert" class="sweet-alert-overlay">
        <div class="sweet-alert-box">
          <div class="sweet-alert-icon">✓</div>
          <h2>Success</h2>
          <p>Successfully logged in</p>
          <button type="button" @click="closeSuccessAlert">OK</button>
        </div>
      </div>
    </Transition>

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

.sweet-alert-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.35);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}

.sweet-alert-box {
  width: 100%;
  max-width: 360px;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 18px 48px rgba(15, 23, 42, 0.24);
  text-align: center;
  padding: 28px 26px 24px;
}

.sweet-alert-icon {
  width: 68px;
  height: 68px;
  border: 3px solid #22c55e;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #16a34a;
  font-size: 38px;
  line-height: 1;
  margin-bottom: 14px;
}

.sweet-alert-box h2 {
  margin: 0 0 8px;
  color: #111827;
  font-size: 24px;
}

.sweet-alert-box p {
  margin: 0 0 22px;
  color: #4b5563;
  font-size: 14px;
}

.sweet-alert-box button {
  min-width: 86px;
  border: 0;
  border-radius: 5px;
  background: #2563eb;
  color: #fff;
  font-size: 14px;
  font-weight: 600;
  padding: 9px 18px;
  cursor: pointer;
  font-family: inherit;
}

.sweet-alert-enter-active,
.sweet-alert-leave-active {
  transition: opacity .18s ease;
}

.sweet-alert-enter-from,
.sweet-alert-leave-to {
  opacity: 0;
}
</style>
