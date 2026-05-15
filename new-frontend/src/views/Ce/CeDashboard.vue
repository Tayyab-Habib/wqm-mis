<script setup>
import { useRouter } from 'vue-router'
import { useUserStore } from '../../stores/useUserStore.js'

const router    = useRouter()
const userStore = useUserStore()

function logout() {
  userStore.logout()
  router.push({ path: '/login', query: { loggedOut: '1' } })
}
</script>

<template>
  <div class="ce-page">
    <div class="ce-card">
      <div class="ce-brand">
        <div class="ce-org">PHED — Khyber Pakhtunkhwa</div>
        <div class="ce-title">Chief Engineer Dashboard</div>
      </div>

      <div class="ce-greeting">
        Welcome, <b>{{ userStore.currentUser?.name || 'CE' }}</b>
      </div>

      <div class="ce-pill">🚧 Coming Soon — CE-specific dashboard under construction</div>

      <p class="ce-note">
        This portal will surface region-level KPIs, SE rollups, and corrective-action
        oversight. Until then, only this placeholder is shown for the Chief Engineer role.
      </p>

      <button class="ce-logout" @click="logout">⏻ Logout</button>
    </div>
  </div>
</template>

<style scoped>
.ce-page {
  min-height: 100vh;
  background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.ce-card {
  max-width: 520px;
  width: 100%;
  background: #fff;
  border-radius: 12px;
  padding: 36px 30px;
  box-shadow: 0 20px 50px rgba(0,0,0,0.25);
  text-align: center;
}
.ce-brand { margin-bottom: 24px; }
.ce-org   { font-size: 11px; font-weight: 700; color: #6b7280; letter-spacing: .08em; text-transform: uppercase; }
.ce-title { font-size: 22px; font-weight: 800; color: #1e3a8a; margin-top: 6px; }
.ce-greeting { font-size: 14px; color: #374151; margin-bottom: 18px; }
.ce-pill {
  display: inline-block;
  background: #fef3c7;
  color: #92400e;
  border: 1px solid #fcd34d;
  border-radius: 999px;
  padding: 6px 14px;
  font-size: 12px;
  font-weight: 600;
  margin-bottom: 18px;
}
.ce-note  { font-size: 12.5px; color: #6b7280; line-height: 1.6; margin: 0 0 24px; }
.ce-logout {
  background: #1e3a8a;
  color: #fff;
  border: 0;
  border-radius: 8px;
  padding: 10px 22px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}
.ce-logout:hover { background: #1d4ed8; }
</style>
