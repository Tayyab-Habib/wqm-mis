<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import { useUserStore } from '../../stores/useUserStore.js'

const router    = useRouter()
const route     = useRoute()
const userStore = useUserStore()

const BASE = import.meta.env.DEV
  ? ''
  : import.meta.env.VITE_API_BASE_URL || 'http://localhost:8002'

const form         = ref({ email: '', password: '' })
const loading      = ref(false)
const error        = ref('')
const showPassword = ref(false)

const currentYear = computed(() => new Date().getFullYear())

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
    // Step 1: Get CSRF cookie
    await axios.get(`${BASE}/sanctum/csrf-cookie`, { withCredentials: true })

    // Step 2: Read XSRF-TOKEN cookie
    const xsrfToken = document.cookie
      .split('; ')
      .find(row => row.startsWith('XSRF-TOKEN='))
      ?.split('=')[1]

    const headers = {
      'Accept':       'application/json',
      'Content-Type': 'application/json',
      'X-XSRF-TOKEN': xsrfToken ? decodeURIComponent(xsrfToken) : '',
    }
    const creds = { email: form.value.email, password: form.value.password }

    // Step 3: Try admin login first; fall back to client portal login on 401
    let response     = null
    let isClientLogin = false

    try {
      response = await axios.post(`${BASE}/api/login`, creds, { withCredentials: true, headers })
    } catch (adminErr) {
      if (adminErr.response?.status === 401) {
        response = await axios.post(`${BASE}/api/client-portal/login`, creds, { withCredentials: true, headers })
        isClientLogin = true
      } else {
        throw adminErr
      }
    }

    const userData = response.data?.data || response.data

    if (userData?.token) {
      if (isClientLogin || userData.user_type === 'client') {
        const clientUser = {
          id:                userData.id,
          name:              userData.name,
          email:             userData.email,
          phone:             userData.phone,
          organization_name: userData.organization_name,
          token:             userData.token,
          user_type:         'client',
          role:              'client',
          permissions:       [],
        }
        localStorage.setItem('user', JSON.stringify(clientUser))
        userStore.setUser(clientUser)
        router.push('/client-portal/results')
      } else {
        const user = {
          id:          userData.id,
          name:        userData.name,
          email:       userData.email,
          token:       userData.token,
          role:        userData.roles?.[0]?.name || userData.role || 'user',
          permissions: userData.permission_names || userData.permissions || [],
          permission_names: Array.isArray(userData.permission_names) ? userData.permission_names : [],
          laboratory:  userData.laboratory  || null,
          district:    userData.district    || null,
          district_id: userData.district_id || userData.district?.id || null,
          role_slug:        userData.role_slug || null,
          phone:            userData.phone || null,
          phed_division:    userData.phed_division || null,
          phed_division_id: userData.phed_division_id || null,
          circle:           userData.circle || null,
          circle_id:        userData.circle_id || userData.circle?.id || null,
          region:           userData.region || null,
          region_id:        userData.region_id || userData.region?.id || null,
          is_view_only:    !!userData.is_view_only,
          is_dummy:        !!userData.is_dummy,
          allowed_modules: userData.allowed_modules || null,
        }
        localStorage.setItem('user', JSON.stringify(user))
        userStore.setUser(user)

        const roleSlug = (userData.role_slug || '').toString().toLowerCase()
        let target = '/dashboard'
        if (roleSlug === 'secretary') {
          target = '/secretary/dashboard'
        } else if (roleSlug === 'chief-engineer' || roleSlug === 'ce') {
          target = '/ce/dashboard'
        } else if (roleSlug === 'superintending-engineer' || roleSlug === 'xen' ||
                   roleSlug === 'se') {
          target = '/xen/dashboard'
        }
        router.push({ path: target, query: { loggedIn: '1' } })
      }
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
  <div class="login-shell">

    <!-- ── Toast ── -->
    <Teleport to="body">
      <Transition name="toast-slide">
        <div v-if="toast.show" class="login-toast" :class="`is-${toast.type}`">
          <span class="login-toast-msg">{{ toast.message }}</span>
          <button class="login-toast-close" @click="toast.show = false" aria-label="Dismiss">✕</button>
        </div>
      </Transition>
    </Teleport>

    <!-- Decorative animated background blobs -->
    <div class="bg-blobs" aria-hidden="true">
      <span class="blob blob-1"></span>
      <span class="blob blob-2"></span>
      <span class="blob blob-3"></span>
    </div>

    <div class="login-card">

      <!-- ── LEFT BRAND PANEL ── -->
      <aside class="brand-panel">
        <div class="brand-top">
          <div class="brand-mark">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z" />
            </svg>
          </div>
          <div class="brand-meta">
            <div class="brand-name">KOICA-WQM-MIS</div>
            <div class="brand-tag">Water Quality Management</div>
          </div>
        </div>

        <div class="brand-hero">
          <h2>Clean water, <br/>clear decisions.</h2>
          <p>A unified information system for water quality testing, monitoring, and reporting across PHED Khyber Pakhtunkhwa.</p>
        </div>

        <ul class="brand-features">
          <li>
            <span class="ft-dot"></span>
            <div><b>Secure by design</b><i>Sanctum auth · role-based access</i></div>
          </li>
          <li>
            <span class="ft-dot"></span>
            <div><b>Real-time insight</b><i>Field-to-lab sample tracking</i></div>
          </li>
          <li>
            <span class="ft-dot"></span>
            <div><b>Audit ready</b><i>Full activity trail & reporting</i></div>
          </li>
        </ul>

        <div class="brand-bottom">
          <span class="brand-chip">PHED · Khyber Pakhtunkhwa</span>
        </div>
      </aside>

      <!-- ── RIGHT FORM PANEL ── -->
      <section class="form-panel">

        <div class="form-head">
          <h1>Welcome back</h1>
          <p>Sign in to continue to <b>KOICA-WQM-MIS</b>.</p>
        </div>

        <form @submit.prevent="handleLogin" class="login-form" novalidate>

          <!-- Email -->
          <div class="field-group">
            <label for="email">Email Address</label>
            <div class="field-wrap has-badge" :class="{ 'is-disabled': loading }">
              <span class="field-badge field-badge-email" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="5" width="18" height="14" rx="2.5" />
                  <path d="M3.5 6.5l8.5 6.5 8.5-6.5" />
                </svg>
              </span>
              <input
                id="email"
                v-model="form.email"
                type="email"
                autocomplete="username"
                placeholder="your.email@example.com"
                :disabled="loading"
                required
              />
            </div>
          </div>

          <!-- Password -->
          <div class="field-group">
            <label for="password">Password</label>
            <div class="field-wrap has-badge" :class="{ 'is-disabled': loading }">
              <span class="field-badge field-badge-password" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="4" y="11" width="16" height="10" rx="2.2" />
                  <path d="M8 11V8a4 4 0 0 1 8 0v3" />
                </svg>
              </span>
              <input
                id="password"
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                autocomplete="current-password"
                placeholder="Enter your password"
                :disabled="loading"
                required
              />
              <button
                type="button"
                class="field-toggle"
                @click="showPassword = !showPassword"
                :disabled="loading"
                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                :title="showPassword ? 'Hide password' : 'Show password'"
              >
                <svg v-if="!showPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" />
                  <circle cx="12" cy="12" r="3" />
                </svg>
                <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M3 3l18 18" />
                  <path d="M10.6 6.2A10.7 10.7 0 0 1 12 6c6.5 0 10 6 10 6a17.3 17.3 0 0 1-3.4 4.2" />
                  <path d="M6.6 6.6C3.6 8.2 2 12 2 12s3.5 7 10 7c1.5 0 2.9-.3 4.1-.8" />
                  <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Error -->
          <Transition name="err-fade">
            <div v-if="error" class="error-message">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10" />
                <path d="M12 8v4" /><path d="M12 16h.01" />
              </svg>
              <span>{{ error }}</span>
            </div>
          </Transition>

          <button type="submit" class="btn-login" :disabled="loading">
            <span v-if="!loading" class="btn-login-content">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                <path d="M10 17l5-5-5-5" /><path d="M15 12H3" />
              </svg>
              Sign In
            </span>
            <span v-else class="btn-login-content">
              <span class="spinner" aria-hidden="true"></span>
              Signing in…
            </span>
          </button>
        </form>

        <div class="form-foot">
          <p class="copyright">
            © {{ currentYear }} KOICA WQM MIS. Crafted with
            <span class="heart" aria-label="love">❤️</span>
            by <b>HA Technologies</b>
          </p>
        </div>

      </section>
    </div>
  </div>
</template>

<style scoped>
/* ───────────────────────────── Page shell ──────────────────────── */
.login-shell {
  position: relative;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  background:
    radial-gradient(1200px 600px at 10% 10%, rgba(56, 189, 248, .22), transparent 60%),
    radial-gradient(900px 500px at 90% 90%, rgba(99, 102, 241, .18), transparent 55%),
    linear-gradient(135deg, #0b1d3a 0%, #0e2a52 45%, #103a6a 100%);
  overflow: hidden;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  color: #0f172a;
}

/* Animated decorative blobs */
.bg-blobs { position: absolute; inset: 0; pointer-events: none; }
.blob {
  position: absolute;
  border-radius: 50%;
  filter: blur(70px);
  opacity: .35;
  will-change: transform;
}
.blob-1 {
  width: 420px; height: 420px;
  background: radial-gradient(circle, #38bdf8 0%, transparent 70%);
  top: -100px; left: -120px;
  animation: float 18s ease-in-out infinite;
}
.blob-2 {
  width: 520px; height: 520px;
  background: radial-gradient(circle, #818cf8 0%, transparent 70%);
  bottom: -160px; right: -160px;
  animation: float 22s ease-in-out infinite reverse;
}
.blob-3 {
  width: 320px; height: 320px;
  background: radial-gradient(circle, #06b6d4 0%, transparent 70%);
  top: 40%; left: 55%;
  animation: float 26s ease-in-out infinite;
  opacity: .22;
}
@keyframes float {
  0%, 100% { transform: translate(0, 0) scale(1); }
  33%      { transform: translate(40px, -30px) scale(1.05); }
  66%      { transform: translate(-30px, 40px) scale(.97); }
}

/* ───────────────────────────── Card ─────────────────────────────── */
.login-card {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 1040px;
  min-height: 580px;
  display: grid;
  grid-template-columns: 1.05fr 1fr;
  background: rgba(255, 255, 255, .98);
  border-radius: 20px;
  overflow: hidden;
  box-shadow:
    0 30px 80px rgba(8, 23, 50, .45),
    0 1px 0 rgba(255, 255, 255, .6) inset;
}

/* ────────────────────────── Brand panel (left) ──────────────────── */
.brand-panel {
  position: relative;
  padding: 44px 44px 36px;
  color: #fff;
  background:
    radial-gradient(700px 360px at 15% 0%, rgba(56, 189, 248, .35), transparent 60%),
    radial-gradient(600px 380px at 110% 110%, rgba(129, 140, 248, .35), transparent 55%),
    linear-gradient(160deg, #0b1d3a 0%, #143062 60%, #1a4683 100%);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.brand-panel::after {
  /* subtle decorative grid */
  content: '';
  position: absolute; inset: 0;
  background-image:
    linear-gradient(rgba(255,255,255,.04) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,.04) 1px, transparent 1px);
  background-size: 36px 36px;
  mask-image: radial-gradient(closest-side, #000 35%, transparent 80%);
  pointer-events: none;
}

.brand-top { display: flex; align-items: center; gap: 14px; }
.brand-mark {
  width: 48px; height: 48px;
  border-radius: 14px;
  display: grid; place-items: center;
  color: #0b1d3a;
  background: linear-gradient(135deg, #ffffff 0%, #cbeafe 100%);
  box-shadow: 0 6px 16px rgba(0,0,0,.25), inset 0 1px 0 rgba(255,255,255,.7);
}
.brand-mark svg { width: 26px; height: 26px; }
.brand-name {
  font-size: 18px; font-weight: 800; letter-spacing: .5px;
  text-shadow: 0 1px 0 rgba(0,0,0,.2);
}
.brand-tag { font-size: 11.5px; opacity: .75; letter-spacing: .3px; }

.brand-hero { margin: 56px 0 36px; max-width: 360px; position: relative; }
.brand-hero h2 {
  font-size: 32px;
  line-height: 1.15;
  font-weight: 800;
  margin: 0 0 14px 0;
  letter-spacing: -.3px;
  background: linear-gradient(180deg, #ffffff 0%, #cfe5ff 100%);
  -webkit-background-clip: text; background-clip: text;
  -webkit-text-fill-color: transparent;
}
.brand-hero p {
  font-size: 13.5px; line-height: 1.65;
  opacity: .82; margin: 0;
}

.brand-features {
  list-style: none; padding: 0; margin: 0 0 auto;
  display: flex; flex-direction: column; gap: 14px;
  position: relative;
}
.brand-features li {
  display: flex; gap: 12px; align-items: flex-start;
}
.ft-dot {
  width: 8px; height: 8px; flex-shrink: 0;
  margin-top: 6px;
  border-radius: 50%;
  background: linear-gradient(135deg, #38bdf8, #818cf8);
  box-shadow: 0 0 0 4px rgba(56,189,248,.12);
}
.brand-features b {
  display: block; font-size: 13px; font-weight: 700; letter-spacing: .2px;
}
.brand-features i {
  display: block; font-size: 11.5px; font-style: normal;
  opacity: .72; margin-top: 2px;
}

.brand-bottom { margin-top: 28px; position: relative; }
.brand-chip {
  display: inline-block;
  padding: 6px 12px;
  font-size: 10.5px;
  letter-spacing: 1px;
  text-transform: uppercase;
  border-radius: 99px;
  background: rgba(255,255,255,.08);
  border: 1px solid rgba(255,255,255,.12);
  backdrop-filter: blur(8px);
}

/* ─────────────────────────── Form panel (right) ─────────────────── */
.form-panel {
  padding: 56px 56px 28px;
  display: flex; flex-direction: column; justify-content: center;
  background: #ffffff;
  position: relative;
}
.form-head { margin-bottom: 28px; }
.form-head h1 {
  font-size: 26px; font-weight: 800;
  letter-spacing: -.3px;
  color: #0b1d3a;
  margin: 0 0 6px;
}
.form-head p {
  font-size: 13px; color: #64748b; margin: 0;
}
.form-head p b { color: #0b1d3a; font-weight: 700; }

.login-form { display: flex; flex-direction: column; gap: 16px; }

.field-group { display: flex; flex-direction: column; gap: 6px; }
.field-group label {
  font-size: 11.5px; font-weight: 700;
  color: #334155; letter-spacing: .35px;
  text-transform: uppercase;
}

.field-wrap {
  position: relative;
  display: flex; align-items: center;
  background: #f8fafc;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  transition: border-color .18s, box-shadow .18s, background .18s;
}
.field-wrap:focus-within {
  background: #fff;
  border-color: #0e7ad1;
  box-shadow: 0 0 0 4px rgba(14, 122, 209, .12);
}
.field-wrap.is-disabled { opacity: .65; pointer-events: none; }

.field-icon {
  position: absolute; left: 14px; top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
  pointer-events: none;
  display: grid; place-items: center;
}
.field-icon svg { width: 18px; height: 18px; }
.field-wrap:focus-within .field-icon { color: #0e7ad1; }

/* Colored badge variant — used by the email field for stronger visual emphasis */
.field-badge {
  position: absolute;
  left: 10px;
  top: 50%;
  transform: translateY(-50%);
  width: 32px;
  height: 32px;
  border-radius: 9px;
  display: grid;
  place-items: center;
  color: #fff;
  pointer-events: none;
  box-shadow: 0 4px 10px rgba(14, 122, 209, .35), inset 0 1px 0 rgba(255,255,255,.18);
  transition: transform .2s, box-shadow .2s, background .2s;
}
.field-badge svg { width: 17px; height: 17px; }
.field-badge-email,
.field-badge-password {
  background: linear-gradient(135deg, #38bdf8 0%, #0e7ad1 60%, #1556b0 100%);
}
.field-wrap:focus-within .field-badge {
  transform: translateY(-50%) scale(1.04);
  box-shadow: 0 6px 14px rgba(14, 122, 209, .5), inset 0 1px 0 rgba(255,255,255,.22);
}

/* Push the input text further right when a badge is shown */
.field-wrap.has-badge input { padding-left: 54px; }

.field-wrap input {
  width: 100%;
  padding: 13px 14px 13px 44px;
  background: transparent;
  border: none;
  outline: none;
  font-size: 14px;
  font-family: inherit;
  color: #0f172a;
  letter-spacing: .15px;
}
.field-wrap input::placeholder { color: #94a3b8; }
.field-wrap input[type="password"] { letter-spacing: 4px; }
.field-wrap input[type="password"]::placeholder { letter-spacing: .15px; }

.field-toggle {
  position: absolute; right: 8px; top: 50%;
  transform: translateY(-50%);
  background: transparent; border: none;
  color: #94a3b8;
  padding: 8px;
  cursor: pointer;
  border-radius: 6px;
  transition: color .15s, background .15s;
  display: grid; place-items: center;
}
.field-toggle svg { width: 18px; height: 18px; }
.field-toggle:hover:not(:disabled) { color: #0e7ad1; background: rgba(14, 122, 209, .08); }
.field-toggle:disabled { opacity: .5; cursor: not-allowed; }

/* Right-pad the password input so it doesn't sit under the toggle */
.field-wrap input[type="password"],
.field-wrap input[autocomplete="current-password"] {
  padding-right: 48px;
}
.field-wrap:has(.field-toggle) input { padding-right: 48px; }

/* ─── Error ─── */
.error-message {
  display: flex; align-items: center; gap: 10px;
  background: linear-gradient(180deg, #fef2f2 0%, #fee2e2 100%);
  border: 1px solid #fca5a5;
  color: #991b1b;
  padding: 11px 14px;
  border-radius: 10px;
  font-size: 12.5px;
  font-weight: 500;
}
.error-message svg { width: 18px; height: 18px; flex-shrink: 0; }
.err-fade-enter-active, .err-fade-leave-active { transition: all .25s ease; }
.err-fade-enter-from, .err-fade-leave-to { opacity: 0; transform: translateY(-6px); }

/* ─── Submit ─── */
.btn-login {
  width: 100%;
  padding: 14px;
  background: linear-gradient(135deg, #0e7ad1 0%, #1556b0 100%);
  color: #fff;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 700;
  letter-spacing: .3px;
  cursor: pointer;
  font-family: inherit;
  margin-top: 4px;
  box-shadow: 0 8px 22px rgba(21, 86, 176, .35), inset 0 1px 0 rgba(255,255,255,.18);
  transition: transform .15s, box-shadow .2s, opacity .15s;
}
.btn-login:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 12px 28px rgba(21, 86, 176, .42), inset 0 1px 0 rgba(255,255,255,.18);
}
.btn-login:active:not(:disabled) { transform: translateY(0); }
.btn-login:disabled { opacity: .65; cursor: not-allowed; }
.btn-login-content {
  display: inline-flex; align-items: center; justify-content: center; gap: 10px;
}
.btn-login-content svg { width: 18px; height: 18px; }
.spinner {
  width: 16px; height: 16px;
  border: 2px solid rgba(255,255,255,.35);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin .65s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ─── Footer ─── */
.form-foot {
  margin-top: 32px;
  padding-top: 18px;
  border-top: 1px solid #e2e8f0;
  text-align: center;
}
.copyright {
  font-size: 11.5px; color: #64748b; margin: 0;
  letter-spacing: .15px;
}
.copyright b { color: #0b1d3a; font-weight: 700; }
.heart {
  display: inline-block;
  animation: heart-beat 1.6s ease-in-out infinite;
  margin: 0 2px;
}
@keyframes heart-beat {
  0%, 100% { transform: scale(1); }
  15%, 35% { transform: scale(1.18); }
  25%      { transform: scale(.95); }
}

/* ─── Toast ─── */
.login-toast {
  position: fixed; top: 22px; right: 24px; z-index: 9999;
  min-width: 300px; max-width: 460px;
  color: #fff;
  border-radius: 10px;
  padding: 14px 18px;
  box-shadow: 0 12px 36px rgba(0,0,0,.32);
  font-size: 13px;
  display: flex; align-items: flex-start; gap: 10px;
}
.login-toast.is-success { background: linear-gradient(135deg, #047857 0%, #065f46 100%); }
.login-toast.is-error   { background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%); }
.login-toast-msg   { flex: 1; line-height: 1.5; }
.login-toast-close {
  background: rgba(255,255,255,.2); border: none; color: #fff;
  border-radius: 4px; padding: 2px 8px; cursor: pointer; font-size: 13px;
}
.toast-slide-enter-active, .toast-slide-leave-active { transition: all .3s ease; }
.toast-slide-enter-from, .toast-slide-leave-to { opacity: 0; transform: translateX(60px); }

/* ─── Responsive ─── */
@media (max-width: 920px) {
  .login-shell { padding: 16px; }
  .login-card {
    grid-template-columns: 1fr;
    max-width: 480px;
    min-height: 0;
  }
  .brand-panel {
    padding: 28px 28px 24px;
    min-height: auto;
  }
  .brand-hero { margin: 22px 0 18px; }
  .brand-hero h2 { font-size: 26px; }
  .brand-features { gap: 10px; }
  .brand-features li { font-size: 12px; }
  .brand-bottom { display: none; }
  .form-panel { padding: 28px 28px 22px; }
  .form-head h1 { font-size: 22px; }
}
@media (max-width: 420px) {
  .brand-hero h2 { font-size: 22px; }
  .form-panel   { padding: 24px 20px 20px; }
}
</style>
