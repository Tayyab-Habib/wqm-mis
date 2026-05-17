<script setup>
import { ref, computed, watch } from 'vue'
import { xenService } from '../../services/xenService.js'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  sampleId:   { type: [Number, String], default: null },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const loading = ref(false)
const saving  = ref(false)
const data    = ref(null)
const error   = ref('')

// ── Toast (matches the pattern in Topbar.vue / UsersHR.vue) ───────────
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
    data.value = await xenService.trailDetail(props.sampleId)
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
    await xenService.requestRetest({
      water_sample_id: props.sampleId,
      action_type:     form.value.action_type,
      details:         form.value.details,
      action_date:     form.value.date,
    })
    const savedType = form.value.action_type
    await load()                 // refresh timeline
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

// ── Timeline visuals ────────────────────────────────────────────────
function dotClass(item) {
  const t = (item.type || '').toLowerCase()
  const title = (item.title || '').toLowerCase()
  if (t === 'test' && title.includes('unfit')) return 'd-red'
  if (t === 'notification') return 'd-blue'
  if (t === 'action') return 'd-green'
  if (t === 'test' && title.includes('retest')) return 'd-grey'
  return 'd-grey'
}
function dotIcon(item) {
  const t = (item.type || '').toLowerCase()
  if (t === 'test' && (item.title || '').toLowerCase().includes('unfit')) return ''
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
    <div v-if="modelValue" class="xt-overlay" @click.self="close">
      <div class="xt-modal" role="dialog" aria-modal="true">
        <!-- Header -->
        <div class="xt-head">
          <div>
            <div class="xt-title">Trail — {{ sampleInfo.sample_id || sampleId }}</div>
            <div class="xt-sub">{{ sampleInfo.wss || '—' }} · {{ sampleInfo.phed_division || '—' }}</div>
          </div>
          <button class="xt-close" @click="close" aria-label="Close">✕ Close</button>
        </div>

        <!-- Body grid -->
        <div class="xt-body">
          <!-- LEFT: Timeline + Log form -->
          <div class="xt-left">
            <div class="xt-section-h">📋 ACTION TIMELINE</div>

            <div v-if="loading" class="xt-loading">Loading…</div>
            <div v-else-if="error && !timeline.length" class="xt-err">{{ error }}</div>

            <ul v-else class="xt-timeline">
              <li v-for="(item, i) in timeline" :key="i" class="xt-tl-item">
                <div class="xt-dot" :class="dotClass(item)">{{ dotIcon(item) }}</div>
                <div class="xt-tl-card">
                  <div class="xt-tl-title">
                    {{ item.title }}
                    <span v-if="item.round > 0" class="xt-tl-round">R{{ item.round }}</span>
                  </div>
                  <div v-if="item.details" class="xt-tl-details">{{ item.details }}</div>
                  <div class="xt-tl-meta">
                    {{ fmtDate(item.date) }}<span v-if="fmtTime(item.date)">  ·  {{ fmtTime(item.date) }}</span>
                    <span class="xt-tl-user"> · {{ item.user || 'System' }}</span>
                  </div>
                </div>
              </li>
              <li v-if="!timeline.length" class="xt-empty">No events recorded yet.</li>
            </ul>

            <!-- LOG NEW ACTION -->
            <div class="xt-divider"></div>
            <div class="xt-section-h">➕ LOG NEW ACTION</div>

            <div class="xt-form">
              <div class="xt-grid-2">
                <label class="xt-field">
                  <span>Action Type</span>
                  <select v-model="form.action_type">
                    <option v-for="t in actionTypes" :key="t" :value="t">{{ t }}</option>
                  </select>
                </label>
                <label class="xt-field">
                  <span>Date</span>
                  <input type="date" v-model="form.date">
                </label>
              </div>
              <label class="xt-field">
                <span>Details <em>*</em></span>
                <textarea v-model="form.details" rows="3" placeholder="Describe corrective action…"></textarea>
              </label>
              <div v-if="error && timeline.length" class="xt-err small">{{ error }}</div>
              <div class="xt-actions">
                <button class="xt-btn xt-btn-pri" :disabled="saving" @click="saveAction">
                  ✅ {{ saving ? 'Saving…' : 'Save Action' }}
                </button>
              </div>
            </div>
          </div>

          <!-- RIGHT: Sidebar -->
          <div class="xt-right">
            <div class="xt-side-h">SAMPLE INFO</div>
            <dl class="xt-info">
              <div><dt>Sample ID</dt><dd class="mono">{{ sampleInfo.sample_id || '—' }}</dd></div>
              <div><dt>WSS</dt><dd>{{ sampleInfo.wss || '—' }}</dd></div>
              <div><dt>PHE Division</dt><dd>{{ sampleInfo.phed_division || '—' }}</dd></div>
              <div><dt>XEN</dt><dd>{{ sampleInfo.xen_name || '—' }}</dd></div>
              <div><dt>Cause</dt><dd class="xt-cause">{{ sampleInfo.cause || '—' }}</dd></div>
            </dl>

            <div class="xt-side-h" style="margin-top:18px">🔔 NOTIFICATIONS</div>
            <div v-if="!notifs.length" class="xt-empty small">No notifications.</div>
            <ul v-else class="xt-notifs">
              <li v-for="n in notifs" :key="n.id">
                <div class="xt-n-name">{{ n.recipient || 'System' }}</div>
                <div class="xt-n-meta">
                  {{ fmtDate(n.created_at) }} {{ fmtTime(n.created_at) }}
                  <span class="xt-n-status">· {{ n.status }}</span>
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
.xt-overlay {
  position: fixed; inset: 0; z-index: 1100;
  background: rgba(15, 23, 42, .55);
  display: flex; align-items: center; justify-content: center;
  padding: 24px;
}
.xt-modal {
  background: #fff;
  width: 100%; max-width: 980px; max-height: 92vh;
  border-radius: 10px; overflow: hidden;
  display: flex; flex-direction: column;
  box-shadow: 0 16px 48px rgba(0, 0, 0, .28);
  font-family: 'DM Sans', sans-serif;
}

/* Header */
.xt-head {
  background: #1c2e44; color: #fff;
  padding: 14px 22px;
  display: flex; align-items: center; justify-content: space-between;
  flex: 0 0 auto;
}
.xt-title { font-size: 16px; font-weight: 700; }
.xt-sub   { font-size: 12px; color: rgba(255,255,255,.65); margin-top: 2px; }
.xt-close {
  background: rgba(255,255,255,.12); color: #fff;
  border: 1px solid rgba(255,255,255,.2);
  padding: 5px 13px; border-radius: 5px;
  font-size: 12px; cursor: pointer;
  &:hover { background: rgba(255,255,255,.22); }
}

/* Body */
.xt-body {
  display: grid;
  grid-template-columns: 1fr 300px;
  overflow-y: auto;
  background: #fff;
}
.xt-left  { padding: 18px 22px; }
.xt-right { padding: 18px 18px; background: #f8fafc; border-left: 1px solid #e2e8f0; }

.xt-section-h {
  font-size: 11px; font-weight: 700; color: #475569;
  letter-spacing: .08em; margin-bottom: 12px;
}
.xt-side-h {
  font-size: 10px; font-weight: 700; color: #64748b;
  letter-spacing: .1em; margin-bottom: 10px;
}
.xt-divider { height: 1px; background: #e2e8f0; margin: 20px 0 16px; }

/* Timeline */
.xt-timeline { list-style: none; padding: 0; margin: 0; position: relative; }
.xt-timeline::before {
  content: ''; position: absolute; left: 14px; top: 6px; bottom: 6px;
  width: 2px; background: #e2e8f0;
}
.xt-tl-item {
  position: relative;
  padding-left: 42px; padding-bottom: 12px;
  &:last-child { padding-bottom: 0; }
}
.xt-dot {
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
.xt-tl-card {
  background: #fff; border: 1px solid #e2e8f0;
  border-radius: 6px; padding: 10px 14px;
}
.xt-tl-title {
  font-size: 13px; font-weight: 700; color: #0f172a;
  display: flex; align-items: center; gap: 8px;
}
.xt-tl-round {
  font-size: 10px; font-weight: 700; padding: 1px 7px;
  background: #e2e8f0; color: #334155; border-radius: 10px;
}
.xt-tl-details {
  font-size: 12px; color: #475569; margin-top: 4px;
  line-height: 1.45;
}
.xt-tl-meta {
  font-size: 11px; color: #94a3b8; margin-top: 6px;
  .xt-tl-user { color: #64748b; }
}
.xt-empty { font-size: 12px; color: #94a3b8; font-style: italic; padding: 10px 0; }
.xt-empty.small { padding: 4px 0; }
.xt-loading { font-size: 12px; color: #64748b; padding: 16px 0; text-align: center; }
.xt-err { font-size: 12px; color: #b91c1c; background: #fef2f2; padding: 8px 12px; border-radius: 4px; }
.xt-err.small { margin-top: 4px; }

/* Form */
.xt-form { display: flex; flex-direction: column; gap: 10px; }
.xt-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.xt-field {
  display: flex; flex-direction: column; gap: 4px;
  span { font-size: 11.5px; font-weight: 600; color: #475569; em { color: #dc2626; font-style: normal; } }
  select, input, textarea {
    border: 1px solid #cbd5e1; border-radius: 4px; padding: 6px 9px;
    font-size: 13px; font-family: inherit;
    &:focus { outline: none; border-color: #1a6bbf; box-shadow: 0 0 0 2px rgba(26,107,191,.15); }
  }
  textarea { resize: vertical; min-height: 56px; }
}
.xt-actions { display: flex; justify-content: flex-end; margin-top: 4px; }
.xt-btn {
  border: none; border-radius: 4px; padding: 7px 14px;
  font-size: 12.5px; font-weight: 600; cursor: pointer;
  &:disabled { opacity: .6; cursor: not-allowed; }
}
.xt-btn-pri { background: #1a6bbf; color: #fff;
  &:hover:not(:disabled) { background: #14559c; }
}

/* Sidebar */
.xt-info { display: flex; flex-direction: column; gap: 6px; margin: 0 0 4px; }
.xt-info > div {
  display: grid; grid-template-columns: 100px 1fr; gap: 8px;
  padding: 5px 0;
  border-bottom: 1px dashed #e2e8f0;
  &:last-child { border-bottom: none; }
}
.xt-info dt { font-size: 11.5px; color: #64748b; }
.xt-info dd { font-size: 12.5px; color: #0f172a; font-weight: 600; margin: 0; word-break: break-word; }
.xt-info dd.mono { font-family: 'DM Mono', monospace; }
.xt-cause { color: #dc2626 !important; }

.xt-notifs { list-style: none; padding: 0; margin: 0; }
.xt-notifs li {
  padding: 8px 0; border-bottom: 1px dashed #e2e8f0;
  &:last-child { border-bottom: none; }
}
.xt-n-name { font-size: 12px; font-weight: 600; color: #0f172a; }
.xt-n-meta { font-size: 10.5px; color: #94a3b8; margin-top: 2px;
  .xt-n-status { color: #16a34a; font-weight: 600; }
}

@media (max-width: 760px) {
  .xt-body { grid-template-columns: 1fr; }
  .xt-right { border-left: none; border-top: 1px solid #e2e8f0; }
  .xt-grid-2 { grid-template-columns: 1fr; }
}
</style>

<style>
/* Global so the toast in <Teleport to="body"> picks up the transition */
.toast-slide-enter-active,
.toast-slide-leave-active { transition: all 0.3s ease; }
.toast-slide-enter-from,
.toast-slide-leave-to     { opacity: 0; transform: translateX(60px); }
</style>
