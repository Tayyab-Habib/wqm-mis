<script setup>
import { computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useUserStore } from '../stores/useUserStore.js'
import { clientPortalService } from '../services/clientPortalService.js'

const router    = useRouter()
const route     = useRoute()
const userStore = useUserStore()

const client = computed(() => userStore.currentUser)

const navItems = [
  { path: '/client-portal/results',       icon: '🧪', label: 'My Results' },
  { path: '/client-portal/email-reports', icon: '📧', label: 'Email Reports' },
  { path: '/client-portal/billing',       icon: '💳', label: 'Billing' },
  { path: '/client-portal/profile',       icon: '👤', label: 'My Profile' },
]

function isActive(path) {
  return route.path.startsWith(path)
}

const pageTitle = computed(() =>
  navItems.find(n => isActive(n.path))?.label || ''
)

const initials = computed(() => {
  const name = client.value?.name || ''
  return name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2)
})

async function logout() {
  try { await clientPortalService.logout() } catch (_) {}
  userStore.logout()
  router.push('/login?loggedOut=1')
}
</script>

<template>
  <div class="cp-shell">
    <!-- ── Sidebar ── -->
    <aside class="cp-sidebar">
      <!-- Brand -->
      <div class="cp-brand">
        <div class="cp-brand-icon">💧</div>
        <div>
          <div class="cp-brand-name">WQM Lab MIS</div>
          <div class="cp-brand-sub">Client Portal</div>
        </div>
      </div>

      <!-- Avatar + client info -->
      <div class="cp-user-card">
        <div class="cp-avatar">{{ initials }}</div>
        <div class="cp-user-info">
          <div class="cp-user-name">{{ client?.name }}</div>
          <div v-if="client?.organization_name" class="cp-user-org">{{ client.organization_name }}</div>
          <div class="cp-user-email">{{ client?.email }}</div>
        </div>
      </div>

      <!-- Nav -->
      <nav class="cp-nav">
        <RouterLink
          v-for="item in navItems"
          :key="item.path"
          :to="item.path"
          class="cp-nav-item"
          :class="{ 'cp-nav-item--active': isActive(item.path) }"
        >
          <span class="cp-nav-icon">{{ item.icon }}</span>
          <span>{{ item.label }}</span>
        </RouterLink>
      </nav>

      <!-- Logout -->
      <div class="cp-sidebar-footer">
        <button class="cp-logout-btn" @click="logout">
          <span>🚪</span>
          <span>Sign Out</span>
        </button>
      </div>
    </aside>

    <!-- ── Main ── -->
    <div class="cp-main">
      <!-- Topbar -->
      <header class="cp-topbar">
        <div class="cp-topbar-left">
          <span class="cp-topbar-breadcrumb">Client Portal</span>
          <span class="cp-topbar-sep">›</span>
          <span class="cp-topbar-title">{{ pageTitle }}</span>
        </div>
        <div class="cp-topbar-right">
          <div class="cp-topbar-avatar">{{ initials }}</div>
          <span class="cp-topbar-user">{{ client?.name }}</span>
        </div>
      </header>

      <!-- Content -->
      <div class="cp-content">
        <RouterView />
      </div>
    </div>
  </div>
</template>

<style scoped>
.cp-shell {
  display: flex;
  height: 100vh;
  overflow: hidden;
  font-family: inherit;
  background: #f0f4f8;
}

/* ── Sidebar ── */
.cp-sidebar {
  width: 240px;
  flex-shrink: 0;
  background: linear-gradient(180deg, #0f2d5e 0%, #1a3f7a 60%, #1e4d96 100%);
  display: flex;
  flex-direction: column;
  box-shadow: 4px 0 20px rgba(0,0,0,.18);
  z-index: 10;
}

.cp-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 22px 18px 18px;
  border-bottom: 1px solid rgba(255,255,255,.1);
}
.cp-brand-icon { font-size: 26px; line-height: 1; }
.cp-brand-name { color: #fff; font-weight: 700; font-size: 13.5px; line-height: 1.2; }
.cp-brand-sub  { color: rgba(255,255,255,.45); font-size: 10px; margin-top: 2px; letter-spacing: .4px; }

.cp-user-card {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 14px 18px;
  border-bottom: 1px solid rgba(255,255,255,.08);
  background: rgba(255,255,255,.05);
}
.cp-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: linear-gradient(135deg, #3b82f6, #1d4ed8);
  color: #fff;
  font-size: 13px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border: 2px solid rgba(255,255,255,.2);
}
.cp-user-info { min-width: 0; }
.cp-user-name  { color: #fff; font-size: 12px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cp-user-org   { color: rgba(255,255,255,.5); font-size: 10px; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cp-user-email { color: rgba(255,255,255,.4); font-size: 10px; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.cp-nav { flex: 1; padding: 14px 10px; display: flex; flex-direction: column; gap: 2px; }

.cp-nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 8px;
  font-size: 12.5px;
  font-weight: 500;
  text-decoration: none;
  color: rgba(255,255,255,.6);
  transition: all .15s ease;
}
.cp-nav-item:hover {
  background: rgba(255,255,255,.1);
  color: #fff;
}
.cp-nav-item--active {
  background: rgba(255,255,255,.15);
  color: #fff;
  box-shadow: inset 3px 0 0 #60a5fa;
}
.cp-nav-icon { font-size: 15px; width: 20px; text-align: center; }

.cp-sidebar-footer {
  padding: 12px 10px;
  border-top: 1px solid rgba(255,255,255,.08);
}
.cp-logout-btn {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 8px;
  color: rgba(255,255,255,.65);
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  font-family: inherit;
  transition: all .15s ease;
}
.cp-logout-btn:hover {
  background: rgba(239,68,68,.2);
  border-color: rgba(239,68,68,.3);
  color: #fca5a5;
}

/* ── Main ── */
.cp-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  min-width: 0;
}

.cp-topbar {
  height: 56px;
  background: #fff;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  flex-shrink: 0;
  box-shadow: 0 1px 4px rgba(0,0,0,.06);
}
.cp-topbar-left { display: flex; align-items: center; gap: 8px; }
.cp-topbar-breadcrumb { font-size: 12px; color: #94a3b8; }
.cp-topbar-sep   { color: #cbd5e1; font-size: 14px; }
.cp-topbar-title { font-size: 13px; font-weight: 700; color: #0f2d5e; }
.cp-topbar-right { display: flex; align-items: center; gap: 8px; }
.cp-topbar-avatar {
  width: 28px; height: 28px; border-radius: 50%;
  background: linear-gradient(135deg, #3b82f6, #1d4ed8);
  color: #fff; font-size: 10px; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
}
.cp-topbar-user { font-size: 12px; font-weight: 600; color: #334155; }

.cp-content {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
}
</style>
