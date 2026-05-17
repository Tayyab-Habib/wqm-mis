<script setup>
import { ref, watch } from 'vue'
import { xenService } from '../../services/xenService.js'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  sampleId:   { type: [Number, String], default: null },
  sampleSlug: { type: String, default: '' },
  wssName:    { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const ACTION_TYPES = [
  'Chlorination Done',
  'Source Cleaned',
  'Inspected',
  'Maintenance Done',
  'Operator Trained',
  'Source Replaced',
  'Retest Requested',
  'Other',
]

const form = ref({
  action_type: 'Chlorination Done',
  date:        new Date().toISOString().slice(0, 10),
  details:     '',
})
const saving = ref(false)
const error  = ref('')

// ── Toast (matches the pattern in Topbar.vue / UsersHR.vue) ───────────
const toast = ref({ show: false, message: '', type: 'success' })
let toastTimer = null
function showToast(message, type = 'success') {
  clearTimeout(toastTimer)
  toast.value = { show: true, message, type }
  toastTimer = setTimeout(() => { toast.value.show = false }, 4000)
}

watch(() => props.modelValue, (open) => {
  if (open) {
    form.value = {
      action_type: 'Chlorination Done',
      date:        new Date().toISOString().slice(0, 10),
      details:     '',
    }
    error.value = ''
  }
})

function close() { emit('update:modelValue', false) }

async function save() {
  if (!form.value.details.trim()) {
    error.value = 'Details are required.'
    showToast('⚠️ Details are required.', 'error')
    return
  }
  saving.value = true
  error.value = ''
  try {
    await xenService.requestRetest({
      water_sample_id: props.sampleId,
      action_type:     form.value.action_type,
      details:         form.value.details,
      action_date:     form.value.date,
    })
    const savedType = form.value.action_type
    const savedSlug = props.sampleSlug || props.sampleId
    showToast(`✅ ${savedType} logged for ${savedSlug}`, 'success')
    emit('saved')
    close()
  } catch (e) {
    const msg = e?.response?.data?.message || e?.message || 'Failed to save action'
    error.value = msg
    showToast('❌ ' + msg, 'error')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <!-- ── Toast notification (sits above the modal — z-index 9999) ── -->
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

  <Teleport to="body">
    <div v-if="modelValue" class="xl-overlay" @click.self="close">
      <div class="xl-modal" role="dialog" aria-modal="true">
        <div class="xl-head">
          <div>
            <div class="xl-title">➕ Log XEN Action</div>
            <div class="xl-sub">{{ sampleSlug || sampleId }} — {{ wssName || '—' }}</div>
          </div>
          <button class="xl-close" @click="close" aria-label="Close">✕</button>
        </div>
        <div class="xl-body">
          <div class="xl-grid-2">
            <label class="xl-field">
              <span>Action Type <em>*</em></span>
              <select v-model="form.action_type">
                <option v-for="t in ACTION_TYPES" :key="t" :value="t">{{ t }}</option>
              </select>
            </label>
            <label class="xl-field">
              <span>Date <em>*</em></span>
              <input type="date" v-model="form.date">
            </label>
          </div>
          <label class="xl-field">
            <span>Details <em>*</em></span>
            <textarea v-model="form.details" rows="3" placeholder="Describe corrective action taken…"></textarea>
          </label>
          <div v-if="error" class="xl-err">{{ error }}</div>
        </div>
        <div class="xl-foot">
          <button class="xl-btn xl-btn-sec" @click="close" :disabled="saving">Cancel</button>
          <button class="xl-btn xl-btn-pri" @click="save" :disabled="saving">
            ✅ {{ saving ? 'Saving…' : 'Save Action' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped lang="scss">
.xl-overlay {
  position: fixed; inset: 0; z-index: 1200;
  background: rgba(15, 23, 42, .55);
  display: flex; align-items: center; justify-content: center;
  padding: 24px;
}
.xl-modal {
  background: #fff;
  width: 100%; max-width: 540px;
  border-radius: 10px; overflow: hidden;
  box-shadow: 0 16px 48px rgba(0, 0, 0, .28);
  font-family: 'DM Sans', sans-serif;
  display: flex; flex-direction: column;
}
.xl-head {
  padding: 13px 18px;
  display: flex; align-items: center; justify-content: space-between;
  border-bottom: 1px solid #e2e8f0;
}
.xl-title { font-size: 15px; font-weight: 700; color: #0f172a; }
.xl-sub   { font-size: 11.5px; color: #64748b; margin-top: 2px; }
.xl-close {
  background: #f1f5f9; border: 1px solid #cbd5e1;
  width: 26px; height: 26px; border-radius: 5px;
  font-size: 12px; cursor: pointer; color: #64748b;
  &:hover { background: #e2e8f0; color: #0f172a; }
}

.xl-body { padding: 16px 18px; display: flex; flex-direction: column; gap: 12px; }
.xl-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.xl-field {
  display: flex; flex-direction: column; gap: 4px;
  span { font-size: 11.5px; font-weight: 600; color: #475569; em { color: #dc2626; font-style: normal; } }
  select, input, textarea {
    border: 1px solid #cbd5e1; border-radius: 4px; padding: 6px 9px;
    font-size: 13px; font-family: inherit;
    &:focus { outline: none; border-color: #1a6bbf; box-shadow: 0 0 0 2px rgba(26,107,191,.15); }
  }
  textarea { resize: vertical; min-height: 64px; }
}
.xl-err {
  background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b;
  padding: 7px 11px; border-radius: 4px; font-size: 12px;
}
.xl-foot {
  padding: 12px 18px; border-top: 1px solid #e2e8f0;
  display: flex; justify-content: flex-end; gap: 10px;
}
.xl-btn {
  border: none; border-radius: 4px; padding: 7px 14px;
  font-size: 12.5px; font-weight: 600; cursor: pointer;
  &:disabled { opacity: .6; cursor: not-allowed; }
}
.xl-btn-sec { background: #fff; color: #334155; border: 1px solid #cbd5e1;
  &:hover:not(:disabled) { background: #f1f5f9; }
}
.xl-btn-pri { background: #1a6bbf; color: #fff;
  &:hover:not(:disabled) { background: #14559c; }
}

@media (max-width: 560px) {
  .xl-grid-2 { grid-template-columns: 1fr; }
}
</style>

<style>
/* Global so the toast in <Teleport to="body"> picks up the transition */
.toast-slide-enter-active,
.toast-slide-leave-active { transition: all 0.3s ease; }
.toast-slide-enter-from,
.toast-slide-leave-to     { opacity: 0; transform: translateX(60px); }
</style>
