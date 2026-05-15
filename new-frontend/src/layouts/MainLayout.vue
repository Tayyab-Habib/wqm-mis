<script setup>
import Sidebar from '../components/common/Sidebar/Sidebar.vue'
import Topbar from '../components/common/Topbar/Topbar.vue'
import { useUserStore } from '../stores/useUserStore.js'

const userStore = useUserStore()
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
