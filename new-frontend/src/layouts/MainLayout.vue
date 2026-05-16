<script setup>
import { onMounted, onUnmounted } from 'vue'
import Sidebar from '../components/common/Sidebar/Sidebar.vue'
import Topbar from '../components/common/Topbar/Topbar.vue'
import { useUserStore } from '../stores/useUserStore.js'

const userStore = useUserStore()

// Live RBAC: when the tab regains focus, pull fresh roles + permissions
// from /api/me so admin edits made elsewhere take effect without a logout.
// useUserStore.refreshSession() is throttled to one call per 15s.
function onVisible() {
  if (document.visibilityState === 'visible') {
    userStore.refreshSession()
  }
}
onMounted(() => {
  // First pull on mount so a user landing here after login picks up any
  // changes made between login time and arrival.
  userStore.refreshSession()
  document.addEventListener('visibilitychange', onVisible)
})
onUnmounted(() => {
  document.removeEventListener('visibilitychange', onVisible)
})
</script>

<template>
  <div class="app">
    <Sidebar />
    <div class="main">
      <Topbar />
      <!-- ── Dummy-account banner (SRS §1.2 demo mode) ── -->
      <div v-if="userStore.isDummy" class="dummy-banner">
        🎭 Demo Mode — changes are not saved. You're using a training account.
      </div>
      <!-- ── View-only banner (Director Labs / system-admin-view-only) ── -->
      <div v-else-if="userStore.isViewOnly" class="viewonly-banner">
        🔒 View-Only Account — you can browse data but cannot make changes.
      </div>
      <div class="content">
        <RouterView />
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.app {
  display: flex;
  height: 100vh;
  overflow: hidden;
}
.main {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  min-width: 0;
}
.content {
  flex: 1;
  overflow-y: auto;
  padding: 18px;
}

/* RBAC banners — sit between Topbar and content */
.dummy-banner,
.viewonly-banner {
  padding: 7px 18px;
  font-size: 12px;
  font-weight: 600;
  border-bottom: 1px solid;
  text-align: center;
}
.dummy-banner {
  background: #fef3c7;
  color: #92400e;
  border-color: #fcd34d;
}
.viewonly-banner {
  background: #e0e7ff;
  color: #3730a3;
  border-color: #a5b4fc;
}
</style>
