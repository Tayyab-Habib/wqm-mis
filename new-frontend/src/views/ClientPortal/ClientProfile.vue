<script setup>
import { ref } from 'vue'
import { clientPortalService } from '../../services/clientPortalService.js'
import { useUserStore } from '../../stores/useUserStore.js'

const userStore = useUserStore()
const client    = userStore.currentUser

const form = ref({
  current_password:          '',
  new_password:              '',
  new_password_confirmation: '',
})
const loading     = ref(false)
const success     = ref('')
const errorMsg    = ref('')
const fieldErrors = ref({})

const showCurrent = ref(false)
const showNew     = ref(false)
const showConfirm = ref(false)

async function changePassword() {
  loading.value     = true
  success.value     = ''
  errorMsg.value    = ''
  fieldErrors.value = {}

  try {
    await clientPortalService.changePassword(form.value)
    success.value = 'Password updated successfully.'
    form.value = { current_password: '', new_password: '', new_password_confirmation: '' }
  } catch (e) {
    if (e.response?.status === 422) {
      fieldErrors.value = e.response.data?.errors || {}
      errorMsg.value    = e.response.data?.message || 'Validation failed.'
    } else {
      errorMsg.value = e.response?.data?.message || 'Failed to update password.'
    }
  } finally {
    loading.value = false
  }
}

const initials = (client?.name || '').split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2)
</script>

<template>
  <div class="cpr-page">
    <!-- Page header -->
    <div class="cp-page-header">
      <div>
        <h1 class="cp-page-title">My Profile</h1>
        <p class="cp-page-sub">View your account details and manage your password</p>
      </div>
    </div>

    <div class="cpr-grid">
      <!-- Left: Profile card -->
      <div class="cpr-profile-card">
        <div class="cpr-avatar-wrap">
          <div class="cpr-avatar">{{ initials }}</div>
          <div class="cpr-avatar-ring"></div>
        </div>
        <div class="cpr-name">{{ client?.name }}</div>
        <div v-if="client?.organization_name" class="cpr-org">{{ client.organization_name }}</div>
        <div class="cpr-badge-client">Client Account</div>

        <div class="cpr-details">
          <div class="cpr-detail-row">
            <span class="cpr-detail-icon">✉️</span>
            <div>
              <div class="cpr-detail-lbl">Email Address</div>
              <div class="cpr-detail-val">{{ client?.email || '—' }}</div>
            </div>
          </div>
          <div class="cpr-detail-row" v-if="client?.phone">
            <span class="cpr-detail-icon">📞</span>
            <div>
              <div class="cpr-detail-lbl">Phone Number</div>
              <div class="cpr-detail-val">{{ client.phone }}</div>
            </div>
          </div>
          <div class="cpr-detail-row" v-if="client?.organization_name">
            <span class="cpr-detail-icon">🏢</span>
            <div>
              <div class="cpr-detail-lbl">Organization</div>
              <div class="cpr-detail-val">{{ client.organization_name }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: Change password -->
      <div class="cpr-password-card">
        <div class="cpr-section-header">
          <div class="cpr-section-icon">🔐</div>
          <div>
            <div class="cpr-section-title">Change Password</div>
            <div class="cpr-section-sub">Keep your account secure with a strong password</div>
          </div>
        </div>

        <!-- Success -->
        <div v-if="success" class="cp-alert cp-alert--success">
          ✅ {{ success }}
        </div>
        <!-- Error -->
        <div v-if="errorMsg && !Object.keys(fieldErrors).length" class="cp-alert cp-alert--error">
          ⚠️ {{ errorMsg }}
        </div>

        <form @submit.prevent="changePassword" class="cpr-form">
          <!-- Current password -->
          <div class="cpr-field">
            <label class="cpr-label">Current Password</label>
            <div class="cpr-input-wrap">
              <input
                v-model="form.current_password"
                :type="showCurrent ? 'text' : 'password'"
                class="cpr-input"
                :class="{ 'cpr-input--error': fieldErrors.current_password }"
                placeholder="Enter your current password"
                required
              />
              <button type="button" class="cpr-eye" @click="showCurrent = !showCurrent">
                {{ showCurrent ? '🙈' : '👁️' }}
              </button>
            </div>
            <div v-if="fieldErrors.current_password" class="cpr-field-error">
              {{ fieldErrors.current_password[0] }}
            </div>
          </div>

          <!-- New password -->
          <div class="cpr-field">
            <label class="cpr-label">New Password</label>
            <div class="cpr-input-wrap">
              <input
                v-model="form.new_password"
                :type="showNew ? 'text' : 'password'"
                class="cpr-input"
                :class="{ 'cpr-input--error': fieldErrors.new_password }"
                placeholder="Minimum 6 characters"
                required
              />
              <button type="button" class="cpr-eye" @click="showNew = !showNew">
                {{ showNew ? '🙈' : '👁️' }}
              </button>
            </div>
            <div v-if="fieldErrors.new_password" class="cpr-field-error">
              {{ fieldErrors.new_password[0] }}
            </div>
          </div>

          <!-- Confirm password -->
          <div class="cpr-field">
            <label class="cpr-label">Confirm New Password</label>
            <div class="cpr-input-wrap">
              <input
                v-model="form.new_password_confirmation"
                :type="showConfirm ? 'text' : 'password'"
                class="cpr-input"
                placeholder="Re-enter new password"
                required
              />
              <button type="button" class="cpr-eye" @click="showConfirm = !showConfirm">
                {{ showConfirm ? '🙈' : '👁️' }}
              </button>
            </div>
          </div>

          <button type="submit" class="cpr-submit-btn" :disabled="loading">
            <span v-if="loading" class="cp-spinner cp-spinner--sm"></span>
            {{ loading ? 'Updating…' : '🔒 Update Password' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
.cpr-page { display: flex; flex-direction: column; gap: 20px; }

.cpr-grid {
  display: grid;
  grid-template-columns: 300px 1fr;
  gap: 20px;
  align-items: start;
}

/* Profile card */
.cpr-profile-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 28px 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  box-shadow: 0 2px 8px rgba(0,0,0,.06);
}
.cpr-avatar-wrap { position: relative; margin-bottom: 14px; }
.cpr-avatar {
  width: 72px; height: 72px; border-radius: 50%;
  background: linear-gradient(135deg, #3b82f6, #1d4ed8);
  color: #fff; font-size: 24px; font-weight: 800;
  display: flex; align-items: center; justify-content: center;
  position: relative; z-index: 1;
}
.cpr-avatar-ring {
  position: absolute; inset: -4px; border-radius: 50%;
  border: 2px solid #bfdbfe; z-index: 0;
}
.cpr-name { font-size: 16px; font-weight: 700; color: #0f2d5e; margin-bottom: 4px; }
.cpr-org  { font-size: 12px; color: #64748b; margin-bottom: 10px; }
.cpr-badge-client {
  display: inline-block;
  background: #eff6ff; color: #1d4ed8;
  font-size: 10px; font-weight: 700;
  padding: 3px 10px; border-radius: 20px;
  border: 1px solid #bfdbfe;
  text-transform: uppercase; letter-spacing: .5px;
  margin-bottom: 20px;
}
.cpr-details { width: 100%; display: flex; flex-direction: column; gap: 12px; text-align: left; }
.cpr-detail-row { display: flex; align-items: flex-start; gap: 10px; }
.cpr-detail-icon { font-size: 15px; margin-top: 1px; }
.cpr-detail-lbl { font-size: 10px; color: #94a3b8; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 2px; }
.cpr-detail-val { font-size: 12.5px; font-weight: 600; color: #334155; word-break: break-all; }

/* Password card */
.cpr-password-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 28px 28px;
  box-shadow: 0 2px 8px rgba(0,0,0,.06);
}
.cpr-section-header {
  display: flex; align-items: flex-start; gap: 14px; margin-bottom: 22px;
  padding-bottom: 18px; border-bottom: 1px solid #f1f5f9;
}
.cpr-section-icon { font-size: 28px; }
.cpr-section-title { font-size: 15px; font-weight: 700; color: #0f2d5e; }
.cpr-section-sub   { font-size: 12px; color: #94a3b8; margin-top: 2px; }

.cpr-form { display: flex; flex-direction: column; gap: 18px; }
.cpr-field { display: flex; flex-direction: column; gap: 6px; }
.cpr-label { font-size: 12px; font-weight: 600; color: #334155; }
.cpr-input-wrap { position: relative; }
.cpr-input {
  width: 100%; padding: 10px 40px 10px 14px;
  border: 1.5px solid #e2e8f0; border-radius: 8px;
  font-size: 13px; font-family: inherit;
  box-sizing: border-box; transition: border-color .15s;
  background: #fafafa;
}
.cpr-input:focus { outline: none; border-color: #3b82f6; background: #fff; }
.cpr-input--error { border-color: #f87171; }
.cpr-eye {
  position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
  background: none; border: none; cursor: pointer; font-size: 15px; padding: 2px;
}
.cpr-field-error { font-size: 11px; color: #dc2626; }

.cpr-submit-btn {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  padding: 11px 24px; background: linear-gradient(135deg, #1d4ed8, #1e40af);
  color: #fff; border: none; border-radius: 8px;
  font-size: 13px; font-weight: 600; cursor: pointer;
  font-family: inherit; transition: all .15s; margin-top: 4px;
  box-shadow: 0 2px 8px rgba(29,78,216,.3);
}
.cpr-submit-btn:hover:not(:disabled) {
  background: linear-gradient(135deg, #1e40af, #1e3a8a);
  box-shadow: 0 4px 14px rgba(29,78,216,.4);
  transform: translateY(-1px);
}
.cpr-submit-btn:disabled { opacity: .6; cursor: not-allowed; transform: none; }
</style>
