<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import { useUserStore } from '../../stores/useUserStore.js'

const router   = useRouter()
const route    = useRoute()
const userStore = useUserStore()

const BASE = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8002'

const form    = ref({ email: '', password: '' })
const loading = ref(false)
const error   = ref('')
const showPassword = ref(false)

// ── Toast ─────────────────────────────────────────────────────────────
const toast = ref({ show: false, message: '', type: 'success' })
let toastTimer = null

function showToast(message, type = 'success') {
  clearTimeout(toastTimer)
  toast.value = { show: true, message, type }
  toastTimer = setTimeout(() => { toast.value.show = false }, 4000)
}

onMounted(() => {
  if (route.query.loggedOut === '1') {
    showToast('✅ Successfully logged out', 'success')
    router.replace('/login')
  }
})

async function handleLogin() {
  if (!form.value.email || !form.value.password) {
    error.value = 'Please enter email and password'
    return
  }

  loading.value = true
  error.value   = ''

  try {
    // Step 1: Get CSRF cookie — required because EnsureFrontendRequestsAreStateful is active
    await axios.get(`${BASE}/sanctum/csrf-cookie`, {
      withCredentials: true,
    })

    // Step 2: Read the XSRF-TOKEN cookie that Laravel just set
    const xsrfToken = document.cookie
      .split('; ')
      .find(row => row.startsWith('XSRF-TOKEN='))
      ?.split('=')[1]

    // Step 3: POST login with the XSRF token in header
    const response = await axios.post(
      `${BASE}/api/login`,
      { email: form.value.email, password: form.value.password },
      {
        withCredentials: true,
        headers: {
          'Accept':       'application/json',
          'Content-Type': 'application/json',
          'X-XSRF-TOKEN': xsrfToken ? decodeURIComponent(xsrfToken) : '',
        },
      }
    )

    const userData = response.data?.data || response.data

    if (userData?.token) {
      const user = {
        id:          userData.id,
        name:        userData.name,
        email:       userData.email,
        token:       userData.token,
        role:        userData.roles?.[0]?.name || userData.role || 'user',
        permissions: userData.permissions || [],
        laboratory:  userData.laboratory  || null,
        district:    userData.district    || null,
        district_id: userData.district_id ?? null,
        division_id: userData.division_id ?? null,
      }
      localStorage.setItem('user', JSON.stringify(user))
      userStore.setUser(user)
      router.push({ path: '/dashboard', query: { loggedIn: '1' } })
    } else {
      error.value = 'Login failed. No token received.'
    }

  } catch (err) {
    console.error('Login error:', err)
    if (err.response?.status === 401) {
      error.value = 'Invalid email or password.'
    } else if (err.response?.status === 419) {
      error.value = 'CSRF error. Please refresh the page and try again.'
    } else {
      error.value = err.response?.data?.message || 'Login failed. Please try again.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="login-page">
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

    <div class="login-box">
      <div class="login-header">
        <div class="logo">💧</div>
        <h1>WQM Lab MIS</h1>
        <p>PHED — Khyber Pakhtunkhwa</p>
      </div>

      <form @submit.prevent="handleLogin" class="login-form">
        <div class="form-group">
          <label for="email">Email Address</label>
          <input
            id="email"
            v-model="form.email"
            type="email"
            placeholder="your.email@example.com"
            required
            :disabled="loading"
          />
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <div class="password-wrap">
            <input
              id="password"
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              placeholder="Enter your password"
              required
              :disabled="loading"
            />
            <button
              type="button"
              class="password-toggle"
              @click="showPassword = !showPassword"
              :disabled="loading"
              :aria-label="showPassword ? 'Hide password' : 'Show password'"
              :title="showPassword ? 'Hide password' : 'Show password'"
            >
              {{ showPassword ? '🙈' : '👁️' }}
            </button>
          </div>
        </div>

        <div v-if="error" class="error-message">
          ⚠️ {{ error }}
        </div>

        <button type="submit" class="btn-login" :disabled="loading">
          {{ loading ? '🔄 Logging in...' : '🔐 Login' }}
        </button>
      </form>

      <div class="login-footer">
        <p>Water Quality Management Information System</p>
        <p>v2.1 · March 2026</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #1565c0 0%, #0d47a1 100%);
  padding: 20px;
}

.login-box {
  background: white;
  border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
  width: 100%;
  max-width: 420px;
  overflow: hidden;
}

.login-header {
  background: var(--navy);
  color: white;
  padding: 32px 24px;
  text-align: center;
}

.logo {
  font-size: 48px;
  margin-bottom: 12px;
}

.login-header h1 {
  font-size: 24px;
  font-weight: 700;
  margin: 0 0 8px 0;
}

.login-header p {
  font-size: 13px;
  opacity: 0.8;
  margin: 0;
}

.login-form {
  padding: 32px 24px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: var(--navy);
  margin-bottom: 6px;
}

.form-group input {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid var(--border);
  border-radius: 6px;
  font-size: 14px;
  font-family: inherit;
  transition: border-color 0.2s;
  box-sizing: border-box;
}

.form-group input:focus {
  outline: none;
  border-color: var(--blue);
}

.form-group input:disabled {
  background: #f5f5f5;
  cursor: not-allowed;
}

.password-wrap {
  position: relative;
}

.password-wrap input {
  padding-right: 44px;
}

.password-toggle {
  position: absolute;
  top: 50%;
  right: 8px;
  transform: translateY(-50%);
  background: transparent;
  border: 0;
  padding: 4px 6px;
  font-size: 16px;
  line-height: 1;
  cursor: pointer;
  color: var(--muted);
  border-radius: 4px;
  font-family: inherit;
}

.password-toggle:hover:not(:disabled) {
  background: #f1f5f9;
}

.password-toggle:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.error-message {
  background: #fef2f2;
  border: 1px solid #fca5a5;
  color: #b91c1c;
  padding: 10px 14px;
  border-radius: 6px;
  font-size: 13px;
  margin-bottom: 20px;
}

.btn-login {
  width: 100%;
  padding: 12px;
  background: var(--blue);
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
  font-family: inherit;
}

.btn-login:hover:not(:disabled) {
  background: var(--navy);
}

.btn-login:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.login-footer {
  background: #f8f9fa;
  padding: 16px 24px;
  text-align: center;
  border-top: 1px solid var(--border);
}

.login-footer p {
  font-size: 11px;
  color: var(--muted);
  margin: 4px 0;
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
