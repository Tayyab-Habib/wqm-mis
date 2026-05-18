<script setup>
import { ref, computed, onMounted } from 'vue'
import { financeService } from '../../../services/financeService.js'
import { useUserStore } from '../../../stores/useUserStore.js'

const userStore = useUserStore()

/* ── Permission gates ──────────────────────────────────────────── */
/* SRS §2.10 Sr#2: Discount values editable by Super Admin.
   The backend PUT /api/finance/discount route is locked to
   `role:system-administrator` — mirror that on the UI so non-admins
   only ever see read-only state. */
const isSuperAdmin = computed(() => userStore.hasRole('system-administrator'))

/* ── State ─────────────────────────────────────────────────────── */
const loading = ref(false)
const saving  = ref(false)

// Server-side state
const discount     = ref({ name: '', description: '', value: 0 })
const errorMsg     = ref('')

// Local editable form (split from `discount` so we can show "current vs new")
const formValue       = ref(0)
const formDescription = ref('')

const currentValue   = computed(() => Number(discount.value?.value || 0))
const hasChanges     = computed(() =>
  Number(formValue.value)       !== currentValue.value ||
  String(formDescription.value) !== String(discount.value?.description || '')
)

/* Plain-English math preview — helps the admin sanity-check before saving.
   Mirrors the formula in GenerateWaterSampleInvoice::execute() exactly. */
const previewRate = 1000
const previewPayable = computed(() => {
  const d = Math.max(0, Math.min(100, Number(formValue.value) || 0))
  return Math.round(previewRate * (1 - d / 100) * 100) / 100
})

/* ── Toast ─────────────────────────────────────────────────────── */
const toast = ref({ show: false, message: '', type: 'success' })
let toastTimer = null
function showToast(message, type = 'success') {
  clearTimeout(toastTimer)
  toast.value = { show: true, message, type }
  toastTimer = setTimeout(() => { toast.value.show = false }, 4000)
}

/* ── API actions ──────────────────────────────────────────────── */
async function fetchDiscount() {
  loading.value = true
  errorMsg.value = ''
  try {
    const res = await financeService.getDiscount()
    discount.value = res?.data || { name: '', description: '', value: 0 }
    formValue.value       = Number(discount.value.value || 0)
    formDescription.value = discount.value.description || ''
  } catch (err) {
    console.error('Fetch discount error:', err)
    errorMsg.value = err?.response?.data?.message || err.message || 'Failed to load discount.'
    showToast('❌ ' + errorMsg.value, 'error')
  } finally {
    loading.value = false
  }
}

async function saveDiscount() {
  // Client-side validation matches the backend rule: numeric, 0..100.
  const v = Number(formValue.value)
  if (Number.isNaN(v) || v < 0 || v > 100) {
    showToast('❌ Discount must be a number between 0 and 100.', 'error')
    return
  }

  saving.value = true
  try {
    const res = await financeService.updateDiscount(v)
    // Surface the freshly-saved row so "current" reflects what's in the DB.
    if (res?.data) discount.value = res.data
    showToast(`✅ Discount updated — PHE clients now pay ${100 - v}% of the standard rate.`)
  } catch (err) {
    console.error('Update discount error:', err)
    const e = err?.response?.data
    const msg = e?.errors
      ? Object.values(e.errors).flat().join('\n')
      : (e?.message || err.message || 'Failed to save discount.')
    showToast('❌ ' + msg, 'error')
  } finally {
    saving.value = false
  }
}

function resetForm() {
  formValue.value       = currentValue.value
  formDescription.value = discount.value?.description || ''
}

onMounted(fetchDiscount)
</script>

<template>
  <div class="settings-page">
    <!-- ── Toast ───────────────────────────────────────────────── -->
    <Teleport to="body">
      <Transition name="toast-slide">
        <div v-if="toast.show"
             :style="`position:fixed;top:22px;right:24px;z-index:9999;min-width:280px;max-width:460px;
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

    <!-- ── Breadcrumbs ────────────────────────────────────────── -->
    <div class="breadcrumbs">
      <span class="bc-icon">🏠</span>
      <span class="bc-sep">›</span>
      <span>Dashboard</span>
      <span class="bc-sep">›</span>
      <span class="active">Discount</span>
    </div>

    <!-- ── Main editor card ───────────────────────────────────── -->
    <section class="data-card">
      <header class="card-header">
        <div>
          <h1>PHE Invoice Discount</h1>
          <p class="card-sub">Applied automatically by <code>GenerateWaterSampleInvoice</code> when a PHE department registers a water sample.</p>
        </div>
        <span v-if="!isSuperAdmin" class="ro-pill">🔒 Read-only</span>
      </header>

      <!-- Loading skeleton -->
      <div v-if="loading" class="editor-body">
        <div class="sk sk-text" style="width: 220px; height: 22px; margin-bottom: 16px;"></div>
        <div class="sk sk-text" style="width: 100%; height: 44px; margin-bottom: 24px;"></div>
        <div class="sk sk-text" style="width: 80%; height: 14px; margin-bottom: 10px;"></div>
        <div class="sk sk-text" style="width: 60%; height: 14px;"></div>
      </div>

      <!-- Editor -->
      <div v-else class="editor-body">
        <!-- Current value summary -->
        <div class="current-row">
          <div class="current-box">
            <div class="current-label">Current Discount</div>
            <div class="current-val">{{ currentValue }}%</div>
            <div class="current-sub">PHE pays {{ 100 - currentValue }}% of full rate</div>
          </div>
          <div class="current-arrow" aria-hidden="true">→</div>
          <div class="current-box new-box" :class="{ changed: hasChanges }">
            <div class="current-label">Pending Save</div>
            <div class="current-val">{{ Number(formValue) || 0 }}%</div>
            <div class="current-sub">PHE will pay {{ Math.max(0, 100 - (Number(formValue) || 0)) }}% of full rate</div>
          </div>
        </div>

        <div class="form-grid">
          <!-- Slider + number -->
          <div>
            <label class="field-label">Discount % <span class="required">*</span>
              <span class="field-hint">0 = full price · 100 = free</span>
            </label>
            <div class="slider-row">
              <input
                type="range"
                min="0" max="100" step="1"
                v-model.number="formValue"
                :disabled="!isSuperAdmin || saving"
                class="slider"
              />
              <input
                type="number"
                min="0" max="100" step="0.1"
                v-model.number="formValue"
                :disabled="!isSuperAdmin || saving"
                class="num-input"
              />
            </div>
          </div>

          <!-- Description -->
          <div>
            <label class="field-label">Description
              <span class="field-hint">Internal note (optional)</span>
            </label>
            <textarea class="field-input" rows="2"
                      v-model="formDescription"
                      :disabled="!isSuperAdmin || saving"
                      maxlength="500"
                      placeholder="Why this rate? Who approved it?"></textarea>
          </div>
        </div>

        <!-- Live formula preview -->
        <div class="preview-box">
          <div class="preview-row">
            <div class="preview-title">📐 How the math will be applied</div>
            <code class="preview-formula">amount_due = full_rate × (1 − discount / 100)</code>
          </div>
          <div class="preview-example">
            <span class="ex-label">Example:</span> a PHE sample with a full rate of
            <strong>Rs {{ previewRate.toLocaleString() }}</strong>
            will be invoiced at
            <strong class="ex-result">Rs {{ previewPayable.toLocaleString() }}</strong>
          </div>
        </div>

        <!-- Save / Reset buttons -->
        <div class="actions-row">
          <button class="btn-secondary"
                  @click="resetForm"
                  :disabled="!hasChanges || saving || !isSuperAdmin">
            Reset
          </button>
          <button class="btn-primary"
                  @click="saveDiscount"
                  :disabled="!hasChanges || saving || !isSuperAdmin">
            {{ saving ? 'Saving…' : '💾 Save Discount' }}
          </button>
        </div>

        <p v-if="!isSuperAdmin" class="ro-note">
          🚫 Only the <strong>system-administrator</strong> role may change the discount value.
          You can view the current setting but cannot edit it.
        </p>
      </div>
    </section>
  </div>
</template>

<style scoped>
.settings-page { max-width: 1100px; margin: 0 auto; padding: 16px 24px 32px; color: #0f172a; }

/* ── Breadcrumbs ──────────────────────────────────────────────── */
.breadcrumbs {
  display: flex; align-items: center; gap: 8px;
  background: #fff;
  border: 1px solid #e2e8f0; border-radius: 8px;
  padding: 10px 16px; margin-bottom: 16px;
  font-size: 13px; color: #475569;
}
.bc-icon { font-size: 14px; }
.bc-sep { color: #94a3b8; }
.breadcrumbs .active { color: #0f172a; font-weight: 600; }

/* ── Card ─────────────────────────────────────────────────────── */
.data-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 1px 2px rgba(0,0,0,.03);
}
.card-header {
  display: flex; justify-content: space-between; align-items: flex-start;
  padding: 16px 20px;
  border-bottom: 1px solid #e2e8f0;
  gap: 12px;
}
.card-header h1 { font-size: 16px; font-weight: 700; margin: 0 0 4px; color: #0f172a; }
.card-sub       { margin: 0; font-size: 12.5px; color: #64748b; }
.card-sub code  { background: #f1f5f9; padding: 1px 5px; border-radius: 3px; font-size: 11.5px; }
.ro-pill {
  display: inline-block; flex-shrink: 0;
  background: #fef3c7; color: #92400e; border: 1px solid #fcd34d;
  font-size: 11.5px; font-weight: 600;
  padding: 4px 10px; border-radius: 999px;
}

.editor-body { padding: 24px; }

/* ── "Current → New" comparison ───────────────────────────────── */
.current-row {
  display: flex; align-items: center; gap: 18px;
  margin-bottom: 24px;
}
.current-box {
  flex: 1;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 16px;
  text-align: center;
}
.current-arrow { font-size: 22px; color: #94a3b8; }
.current-label { font-size: 11px; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; }
.current-val   { font-size: 28px; font-weight: 700; color: #1e3a8a; margin: 4px 0 2px; }
.current-sub   { font-size: 12px; color: #475569; }
.new-box       { background: #eff6ff; border-color: #bfdbfe; }
.new-box.changed {
  background: #dbeafe; border-color: #60a5fa;
}
.new-box.changed .current-val { color: #1d4ed8; }

/* ── Slider + number input ────────────────────────────────────── */
.field-label {
  display: block;
  font-size: 12.5px; font-weight: 600;
  color: #334155;
  margin-bottom: 8px;
}
.field-hint  { display: inline-block; margin-left: 6px; font-weight: 400; color: #94a3b8; font-size: 11px; }
.required    { color: #dc2626; }
.slider-row  { display: flex; align-items: center; gap: 14px; }
.slider      { flex: 1; accent-color: #2563eb; cursor: pointer; }
.slider:disabled { cursor: not-allowed; opacity: .55; }
.num-input {
  width: 100px;
  padding: 8px 10px;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  font-size: 14px;
  font-weight: 600;
  text-align: right;
  outline: none;
}
.num-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,.1); }
.num-input:disabled { background: #f1f5f9; color: #94a3b8; cursor: not-allowed; }

.field-input {
  width: 100%;
  padding: 9px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  font-size: 13px;
  outline: none;
  font-family: inherit;
  resize: vertical;
}
.field-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,.1); }
.field-input:disabled { background: #f1f5f9; color: #94a3b8; cursor: not-allowed; }

/* ── Form grid (slider + description side-by-side on wide screens) ── */
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 18px 24px;
}
@media (max-width: 720px) {
  .form-grid { grid-template-columns: 1fr; }
}

/* ── Formula preview ──────────────────────────────────────────── */
.preview-box {
  margin-top: 22px;
  padding: 14px 16px;
  background: #f0f9ff;
  border: 1px solid #bae6fd;
  border-radius: 6px;
}
.preview-row {
  display: flex; flex-wrap: wrap; align-items: center; gap: 10px 16px;
  margin-bottom: 6px;
}
.preview-title { font-size: 12px; font-weight: 600; color: #0c4a6e; flex-shrink: 0; }
.preview-formula {
  font-family: 'SF Mono', Menlo, Consolas, monospace;
  font-size: 12.5px;
  color: #0c4a6e;
  background: #fff;
  padding: 5px 10px;
  border-radius: 4px;
  border: 1px dashed #93c5fd;
}
.preview-example { font-size: 13px; color: #1e3a5f; margin-top: 4px; }
.preview-example .ex-label { color: #64748b; }
.preview-example .ex-result { color: #166534; }

/* ── Actions ──────────────────────────────────────────────────── */
.actions-row {
  display: flex; justify-content: flex-end; gap: 10px;
  margin-top: 22px;
}
.btn-primary, .btn-secondary {
  font-size: 13px; font-weight: 600;
  padding: 9px 18px;
  border-radius: 4px;
  border: 1px solid transparent;
  cursor: pointer;
  transition: background .15s, border-color .15s, opacity .15s;
}
.btn-primary { background: #2563eb; color: #fff; border-color: #2563eb; }
.btn-primary:hover:not(:disabled)   { background: #1d4ed8; border-color: #1d4ed8; }
.btn-primary:disabled               { opacity: .55; cursor: not-allowed; }
.btn-secondary { background: #fff; color: #334155; border-color: #cbd5e1; }
.btn-secondary:hover:not(:disabled) { background: #f1f5f9; }
.btn-secondary:disabled             { opacity: .55; cursor: not-allowed; }

.ro-note {
  margin: 18px 0 0;
  padding: 12px 14px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
  border-radius: 6px;
  font-size: 12.5px;
}

/* ── Skeleton ─────────────────────────────────────────────────── */
.sk {
  display: block;
  background: linear-gradient(90deg, #eef2f7 0%, #f7fafc 50%, #eef2f7 100%);
  background-size: 200% 100%;
  animation: sk-shimmer 1.4s infinite ease-in-out;
  border-radius: 4px;
}
@keyframes sk-shimmer {
  0%   { background-position: -200% 0; }
  100% { background-position:  200% 0; }
}

.small  { font-size: 12px; }
.muted  { color: #64748b; }
</style>

<style>
.toast-slide-enter-active,
.toast-slide-leave-active { transition: all 0.3s ease; }
.toast-slide-enter-from,
.toast-slide-leave-to     { opacity: 0; transform: translateX(60px); }
</style>
