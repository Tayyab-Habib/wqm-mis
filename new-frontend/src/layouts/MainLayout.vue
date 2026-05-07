<script setup>
import Sidebar from '../components/common/Sidebar/Sidebar.vue'
import Topbar from '../components/common/Topbar/Topbar.vue'
</script>

<template>
  <div class="app">
    <Sidebar />
    <div class="main">
      <Topbar />
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
</style>
