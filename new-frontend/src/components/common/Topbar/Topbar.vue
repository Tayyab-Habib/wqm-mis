<script setup>
import { useUserStore } from '../../../stores/useUserStore.js'
import { useRoute, useRouter } from 'vue-router'
import { computed } from 'vue'

const userStore = useUserStore()
const route = useRoute()
const router = useRouter()

const breadcrumb = computed(() => route.meta?.title || route.name || 'Dashboard')

const displayName = computed(() => userStore.currentUser?.name || 'User')
const displayRole = computed(() => userStore.currentUser?.role || '')
const displayLab  = computed(() => userStore.currentUser?.laboratory?.name || 'Central Lab — Peshawar')

async function handleLogout() {
  userStore.logout()
  router.push('/login')
}
</script>

<template>
  <div class="topbar">
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
</style>
