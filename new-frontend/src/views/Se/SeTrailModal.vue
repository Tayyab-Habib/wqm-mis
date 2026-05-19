<script setup>
import { ref, computed, watch } from 'vue'
import { seService } from '../../services/seService.js'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  sampleId:   { type: [Number, String], default: null },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const loading = ref(false)
const saving  = ref(false)
const data    = ref(null)
const error   = ref('')

const toast = ref({ show: false, message: '', type: 'success' })
let toastTimer = null
function showToast(message, type = 'success') {
  clearTimeout(toastTimer)
  toast.value = { show: true, message, type }
  toastTimer = setTimeout(() => { toast.value.show = false }, 4000)
}

const form = ref({
  action_type: 'Chlorination Done',
  date:        new Date().toISOString().slice(0, 10),
  details:     '',
})

async function load() {
  if (!props.sampleId) return
  loading.value = true
  error.value = ''
  try {
    data.value = await seService.trailDetail(props.sampleId)
    form.value.action_type = data.value?.action_types?.[0] || 'Chlorination Done'
    form.value.date        = new Date().toISOString().slice(0, 10)
    form.value.details     = ''
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load trail'
  } finally {
    loading.value = false
  }
}
watch(() => [props.modelValue, props.sampleId], ([open]) => { if (open) load() })

function close() { emit('update:modelValue', false) }

async function saveAction() {
  if (!form.value.details.trim()) {
    error.value = 'Details are required.'
    showToast('⚠️ Details are required.', 'error')
    return
  }
  saving.value = true
  error.value = ''
  try {
    await seService.requestRetest({
      water_sample_id: props.sampleId,
      action_type:     form.value.action_type,
      details:         form.value.details,
      action_date:     form.value.date,
    })
    const savedType = form.value.action_type
    await load()
    form.value.details = ''
    showToast(`✅ ${savedType} logged on the trail`, 'success')
    emit('saved')
  } catch (e) {
    const msg = e?.response?.data?.message || e?.message || 'Failed to save action'
    error.value = msg
    showToast('❌ ' + msg, 'error')
  } finally {
    saving.value = false
  }
}

function dotClass(item) {
  const t = (item.type || '').toLowerCase()
  const title = (item.title || '').toLowerCase()
  if (t === 'test' && title.includes('unfit')) return 'd-red'
  if (t === 'notification') return 'd-blue'
  if (t === 'action') return 'd-green'
  return 'd-grey'
}
function dotIcon(item) {
  const t = (item.type || '').toLowerCase()
  if (t === 'notification') return '✉'
  if (t === 'action') return '✓'
  return ''
}
function fmtDate(d) {
  if (!d) return ''
  try { return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: '2-digit' }) }
  catch { return d }
}
function fmtTime(d) {
  if (!d) return ''
  try { return new Date(d).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', hour12: false }) }
  catch { return '' }
}

const sampleInfo = computed(() => data.value?.sample_info || {})
const notifs     = computed(() => data.value?.notifications_panel || [])
const timeline   = computed(() => data.value?.timeline || [])
const actionTypes = computed(() => data.value?.action_types || ['Chlorination Done','Source Cleaned','Inspected','Maintenance Done','Other'])
</script>

<template>
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
    <div v-if="modelValue" class="st-overlay" @click.self="close">
      <div class="st-modal" role="dialog" aria-modal="true">
        <div class="st-head">
          <div>
            <div class="st-title">Trail — {{ sampleInfo.sample_id || sampleId }}</div>
            <div class="st-sub">{{ sampleInfo.wss || '—' }} · {{ sampleInfo.phed_division || '—' }} · {{ sampleInfo.district || '—' }}</div>
          </div>
          <button class="st-close" @click="close" aria-label="Close">✕ Close</button>
        </div>

        <div class="st-body">
          <div class="st-left">
            <div class="st-section-h">📋 ACTION TIMELINE</div>

            <div v-if="loading" class="st-loading">Loading…</div>
            <div v-else-if="error && !timeline.length" class="st-err">{{ error }}</div>

            <ul v-else class="st-timeline">
              <li v-for="(item, i) in timeline" :key="i" class="st-tl-item">
                <div class="st-dot" :class="dotClass(item)">{{ dotIcon(item) }}</div>
                <div class="st-tl-card">
                  <div class="st-tl-title">
                    {{ item.title }}
                    <span v-if="item.round > 0" class="st-tl-round">R{{ item.round }}</span>
                  </div>
                  <div v-if="item.details" class="st-tl-details">{{ item.details }}</div>
                  <div class="st-tl-meta">
                    {{ fmtDate(item.date) }}<span v-if="fmtTime(item.date)">  ·  {{ fmtTime(item.date) }}</span>
                    <span class="st-tl-user"> · {{ item.user || 'System' }}</span>
                  </div>
                </div>
              </li>
              <li v-if="!timeline.length" class="st-empty">No events recorded yet.</li>
            </ul>

            <div class="st-divider"></div>
            <div class="st-section-h">➕ LOG NEW ACTION</div>

            <div class="st-form">
              <div class="st-grid-2">
                <label class="st-field">
                  <span>Action Type</span>
                  <select v-model="form.action_type">
                    <option v-for="t in actionTypes" :key="t" :value="t">{{ t }}</option>
                  </select>
                </label>
                <label class="st-field">
                  <span>Date</span>
                  <input type="date" v-model="form.date">
                </label>
              </div>
              <label class="st-field">
                <span>Details <em>*</em></span>
                <textarea v-model="form.details" rows="3" placeholder="Describe corrective action…"></textarea>
              </label>
              <div v-if="error && timeline.length" class="st-err small">{{ error }}</div>
              <div class="st-actions">
                <button class="st-btn st-btn-pri" :disabled="saving" @click="saveAction">
                  ✅ {{ saving ? 'Saving…' : 'Save Action' }}
                </button>
              </div>
            </div>
          </div>

          <div class="st-right">
            <div class="st-side-h">SAMPLE INFO</div>
            <dl class="st-info">
              <div><dt>Sample ID</dt><dd class="mono">{{ sampleInfo.sample_id || '—' }}</dd></div>
              <div><dt>WSS</dt><dd>{{ sampleInfo.wss || '—' }}</dd></div>
              <div><dt>PHE Division</dt><dd>{{ sampleInfo.phed_division || '—' }}</dd></div>
              <div><dt>District</dt><dd>{{ sampleInfo.district || '—' }}</dd></div>
              <div><dt>SE</dt><dd>{{ sampleInfo.se_name || '—' }}</dd></div>
              <div><dt>Cause</dt><dd class="st-cause">{{ sampleInfo.cause || '—' }}</dd></div>
            </dl>

            <div class="st-side-h" style="margin-top:18px">🔔 NOTIFICATIONS</div>
            <div v-if="!notifs.length" class="st-empty small">No notifications.</div>
            <ul v-else class="st-notifs">
              <li v-for="n in notifs" :key="n.id">
                <div class="st-n-name">{{ n.recipient || 'System' }}</div>
                <div class="st-n-meta">
                  {{ fmtDate(n.created_at) }} {{ fmtTime(n.created_at) }}
                  <span class="st-n-status">· {{ n.status }}</span>
                </div>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped lang="scss">
.st-overlay {
  position: fixed; inset: 0; z-index: 1100;
  background: rgba(15, 23, 42, .55);
  display: flex; align-items: center; justify-content: center;
  padding: 24px;
}
.st-modal {
  background: #fff;
  width: 100%; max-width: 980px; max-height: 92vh;
  border-radius: 10px; overflow: hidden;
  display: flex; flex-direction: column;
  box-shadow: 0 16px 48px rgba(0, 0, 0, .28);
  font-family: 'DM Sans', sans-serif;
}
.st-head {
  background: #1c2e44; color: #fff;
  padding: 14px 22px;
  display: flex; align-items: center; justify-content: space-between;
  flex: 0 0 auto;
}
.st-title { font-size: 16px; font-weight: 700; }
.st-sub   { font-size: 12px; color: rgba(255,255,255,.65); margin-top: 2px; }
.st-close {
  background: rgba(255,255,255,.12); color: #fff;
  border: 1px solid rgba(255,255,255,.2);
  padding: 5px 13px; border-radius: 5px;
  font-size: 12px; cursor: pointer;
  &:hover { background: rgba(255,255,255,.22); }
}
.st-body { display: grid; grid-template-columns: 1fr 300px; overflow-y: auto; background: #fff; }
.st-left  { padding: 18px 22px; }
.st-right { padding: 18px 18px; background: #f8fafc; border-left: 1px solid #e2e8f0; }
.st-section-h { font-size: 11px; font-weight: 700; color: #475569; letter-spacing: .08em; margin-bottom: 12px; }
.st-side-h    { font-size: 10px; font-weight: 700; color: #64748b; letter-spacing: .1em; margin-bottom: 10px; }
.st-divider   { height: 1px; background: #e2e8f0; margin: 20px 0 16px; }
.st-timeline  { list-style: none; padding: 0; margin: 0; position: relative; }
.st-timeline::before { content: ''; position: absolute; left: 14px; top: 6px; bottom: 6px; width: 2px; background: #e2e8f0; }
.st-tl-item { position: relative; padding-left: 42px; padding-bottom: 12px; &:last-child { padding-bottom: 0; } }
.st-dot {
  position: absolute; left: 4px; top: 4px;
  width: 22px; height: 22px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; color: #fff; font-weight: 700;
  border: 2px solid #fff;
  &.d-red   { background: #dc2626; }
  &.d-blue  { background: #2563eb; }
  &.d-green { background: #16a34a; }
  &.d-grey  { background: #94a3b8; }
}
.st-tl-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 14px; }
.st-tl-title { font-size: 13px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px; }
.st-tl-round { font-size: 10px; font-weight: 700; padding: 1px 7px; background: #e2e8f0; color: #334155; border-radius: 10px; }
.st-tl-details { font-size: 12px; color: #475569; margin-top: 4px; line-height: 1.45; }
.st-tl-meta { font-size: 11px; color: #94a3b8; margin-top: 6px; .st-tl-user { color: #64748b; } }
.st-empty { font-size: 12px; color: #94a3b8; font-style: italic; padding: 10px 0; }
.st-empty.small { padding: 4px 0; }
.st-loading { font-size: 12px; color: #64748b; padding: 16px 0; text-align: center; }
.st-err { font-size: 12px; color: #b91c1c; background: #fef2f2; padding: 8px 12px; border-radius: 4px; }
.st-err.small { margin-top: 4px; }
.st-form { display: flex; flex-direction: column; gap: 10px; }
.st-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.st-field {
  display: flex; flex-direction: column; gap: 4px;
  span { font-size: 11.5px; font-weight: 600; color: #475569; em { color: #dc2626; font-style: normal; } }
  select, input, textarea {
    border: 1px solid #cbd5e1; border-radius: 4px; padding: 6px 9px;
    font-size: 13px; font-family: inherit;
    &:focus { outline: none; border-color: #1a6bbf; box-shadow: 0 0 0 2px rgba(26,107,191,.15); }
  }
  textarea { resize: vertical; min-height: 56px; }
}
.st-actions { display: flex; justify-content: flex-end; margin-top: 4px; }
.st-btn { border: none; border-radius: 4px; padding: 7px 14px; font-size: 12.5px; font-weight: 600; cursor: pointer; &:disabled { opacity: .6; cursor: not-allowed; } }
.st-btn-pri { background: #1a6bbf; color: #fff; &:hover:not(:disabled) { background: #14559c; } }
.st-info { display: flex; flex-direction: column; gap: 6px; margin: 0 0 4px; }
.st-info > div {
  display: grid; grid-template-columns: 100px 1fr; gap: 8px;
  padding: 5px 0;
  border-bottom: 1px dashed #e2e8f0;
  &:last-child { border-bottom: none; }
}
.st-info dt { font-size: 11.5px; color: #64748b; }
.st-info dd { font-size: 12.5px; color: #0f172a; font-weight: 600; margin: 0; word-break: break-word; }
.st-info dd.mono { font-family: 'DM Mono', monospace; }
.st-cause { color: #dc2626 !important; }
.st-notifs { list-style: none; padding: 0; margin: 0; }
.st-notifs li { padding: 8px 0; border-bottom: 1px dashed #e2e8f0; &:last-child { border-bottom: none; } }
.st-n-name { font-size: 12px; font-weight: 600; color: #0f172a; }
.st-n-meta { font-size: 10.5px; color: #94a3b8; margin-top: 2px; .st-n-status { color: #16a34a; font-weight: 600; } }
@media (max-width: 760px) {
  .st-body { grid-template-columns: 1fr; }
  .st-right { border-left: none; border-top: 1px solid #e2e8f0; }
  .st-grid-2 { grid-template-columns: 1fr; }
}
</style>

<style>
.toast-slide-enter-active, .toast-slide-leave-active { transition: all 0.3s ease; }
.toast-slide-enter-from, .toast-slide-leave-to     { opacity: 0; transform: translateX(60px); }
</style>
