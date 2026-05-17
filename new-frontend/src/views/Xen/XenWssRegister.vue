<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { xenService } from '../../services/xenService.js'
import SkelRow from './SkelRow.vue'
import XenTrailModal from './XenTrailModal.vue'

const router = useRouter()
const loading = ref(true)
const stats = ref({ total: 0, last_fit: 0, last_unfit: 0, overdue: 0 })
const schemes = ref([])
const q = ref('')
const result = ref('all')

// ── Toast (matches the pattern in Topbar.vue / UsersHR.vue) ───────────
const toast = ref({ show: false, message: '', type: 'success' })
let toastTimer = null
function showToast(message, type = 'success') {
  clearTimeout(toastTimer)
  toast.value = { show: true, message, type }
  toastTimer = setTimeout(() => { toast.value.show = false }, 4000)
}

// ── Trail modal ───────────────────────────────────────────────
const showTrailModal = ref(false)
const trailSampleId  = ref(null)
function openTrail(scheme) {
  if (!scheme.last_sample_id) {
    showToast('⚠️ No samples recorded for this WSS yet.', 'error')
    return
  }
  trailSampleId.value = scheme.last_sample_id
  showTrailModal.value = true
}

// ── ISR navigation ────────────────────────────────────────────
function openIsr(scheme) {
  if (!scheme.last_sample_id) {
    showToast('⚠️ No samples recorded for this WSS yet.', 'error')
    return
  }
  router.push(`/xen/isr/${scheme.last_sample_id}`)
}

function onActionSaved() {
  load()
  showToast('✅ Action saved', 'success')
}

async function load() {
  loading.value = true
  try {
    const params = {}
    if (q.value) params.q = q.value
    if (result.value && result.value !== 'all') params.result = result.value
    const res = await xenService.wssRegister(params)
    stats.value = res.stats || stats.value
    schemes.value = res.schemes || []
  } catch { schemes.value = [] } finally { loading.value = false }
}
onMounted(load)
let t = null
watch([q, result], () => { clearTimeout(t); t = setTimeout(load, 300) })

const fmtDate = (d) => {
  if (!d) return '—'
  try { return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: '2-digit' }) }
  catch { return d }
}
function resultClass(r) {
  r = (r || '').toLowerCase()
  if (r === 'fit') return 'st-green'
  if (r === 'unfit') return 'st-red'
  return 'st-grey'
}
function exportCsv() {
  const head = ['WSS Code','WSS Name','Source','Power Input','Times Tested','Last Result','Last Sampled','Next Scheduled']
  const data = schemes.value.map(s => [s.wss_code, s.wss_name, s.source_type, s.power_input, s.times_tested, s.last_result, fmtDate(s.last_sampled_at), s.next_scheduled])
  const csv = [head, ...data].map(r => r.map(c => `"${(c ?? '').toString().replace(/"/g,'""')}"`).join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv' })
  const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'wss_register.csv'; a.click()
}
</script>

<template>
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

  <div class="xd">
    <div class="xd-scope">
      <span class="pin">📍</span>
      <span class="t1">WSS Register — your division only.</span>
      <span class="sep">·</span>
      <span class="t2">Click <b>📊 Trail</b> to see the full testing history.</span>
    </div>

    <div class="xd-cards">
      <div class="c">
        <div class="lbl">TOTAL WSS</div>
        <div v-if="loading" class="skel val-skel"></div>
        <div v-else class="val">{{ stats.total }}</div>
      </div>
      <div class="c c-green">
        <div class="lbl">LAST: FIT</div>
        <div v-if="loading" class="skel val-skel"></div>
        <div v-else class="val">{{ stats.last_fit }}</div>
      </div>
      <div class="c c-red">
        <div class="lbl">LAST: UNFIT</div>
        <div v-if="loading" class="skel val-skel"></div>
        <div v-else class="val">{{ stats.last_unfit }}</div>
      </div>
      <div class="c c-amber">
        <div class="lbl">OVERDUE</div>
        <div v-if="loading" class="skel val-skel"></div>
        <div v-else class="val">{{ stats.overdue }}</div>
      </div>
    </div>

    <div class="xen-toolbar">
      <input type="text" v-model="q" placeholder="🔍 WSS name, code…" style="min-width:260px" />
      <select v-model="result">
        <option value="all">All Results</option>
        <option value="Fit">Fit</option>
        <option value="Unfit">Unfit</option>
      </select>
      <div class="spacer"></div>
      <button class="btn-export" @click="exportCsv">⬇ Export</button>
    </div>

    <div class="panel">
      <table class="tbl">
        <thead>
          <tr>
            <th>WSS Code</th><th>WSS Name</th><th>Source</th><th>Power</th><th>Times Tested</th><th>Last Result</th><th>Last Sampled</th><th>Next Scheduled</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <template v-if="loading">
            <SkelRow v-for="n in 7" :key="'wr' + n" :cols="[110, 180, 90, 80, 50, 70, 90, 100, 130]" />
          </template>
          <template v-else>
            <tr v-for="s in schemes" :key="s.id">
              <td class="sid">{{ s.wss_code }}</td>
              <td><b>{{ s.wss_name }}</b></td>
              <td>{{ s.source_type }}</td>
              <td>
                <span v-if="(s.power_input || '').toLowerCase() === 'solar'">🟡 Solar</span>
                <span v-else>⚡ {{ s.power_input || '—' }}</span>
              </td>
              <td>{{ s.times_tested }}</td>
              <td><span class="pill" :class="resultClass(s.last_result)">{{ s.last_result }}</span></td>
              <td>{{ fmtDate(s.last_sampled_at) }}</td>
              <td>
                <span :class="{ overdue: s.overdue }">{{ fmtDate(s.next_scheduled) }}</span>
                <span v-if="s.overdue" class="overdue-icon">⚠</span>
              </td>
              <td>
                <button class="btn btn-sec"
                        :disabled="!s.last_sample_id"
                        :title="s.last_sample_id ? 'View testing history' : 'No samples yet'"
                        @click="openTrail(s)">📊 Trail</button>
                <button class="btn btn-pri"
                        :disabled="!s.last_sample_id"
                        :title="s.last_sample_id ? 'View latest sample report' : 'No samples yet'"
                        @click="openIsr(s)">📋 ISR</button>
              </td>
            </tr>
            <tr v-if="schemes.length === 0"><td colspan="9" class="empty">No water schemes match.</td></tr>
          </template>
        </tbody>
      </table>
    </div>

    <XenTrailModal v-model="showTrailModal" :sample-id="trailSampleId" @saved="onActionSaved" />
  </div>
</template>

<style scoped lang="scss">
@use './xen-shared.scss' as *;
.overdue { color: #b91c1c; font-weight: 700; }
.overdue-icon { color: #b91c1c; margin-left: 4px; }
.btn:disabled { opacity: .5; cursor: not-allowed; }
</style>

<style>
/* Global so the toast in <Teleport to="body"> picks up the transition */
.toast-slide-enter-active,
.toast-slide-leave-active { transition: all 0.3s ease; }
.toast-slide-enter-from,
.toast-slide-leave-to     { opacity: 0; transform: translateX(60px); }
</style>
