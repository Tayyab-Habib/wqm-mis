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
const successAlert = ref(false)

function closeSuccessAlert() {
  successAlert.value = false
}

onMounted(() => {
  if (route.query.loggedOut === '1') {
    successAlert.value = true
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
    <Transition name="sweet-alert">
      <div v-if="successAlert" class="sweet-alert-overlay">
        <div class="sweet-alert-box">
          <div class="sweet-alert-icon">✓</div>
          <h2>Success</h2>
          <p>Successfully logged out</p>
          <button type="button" @click="closeSuccessAlert">OK</button>
        </div>
      </div>
    </Transition>

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
          <input
            id="password"
            v-model="form.password"
            type="password"
            placeholder="Enter your password"
            required
            :disabled="loading"
          />
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
