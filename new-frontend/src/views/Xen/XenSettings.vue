<script setup>
import { ref, onMounted, computed } from 'vue'
import { xenService } from '../../services/xenService.js'
import SkelRow from './SkelRow.vue'

const loading = ref(true)
const saving = ref(false)
const me = ref(null)
const original = ref(null)

const form = ref({
  name: '',
  phone: '',
  transfer_change_date: '',
  remarks: '',
  district: '',
  sub_area: '',
})

const changes = ref([])
const flash = ref('')

// ── Toast (matches the pattern in Topbar.vue / UsersHR.vue) ───────────
const toast = ref({ show: false, message: '', type: 'success' })
let toastTimer = null
function showToast(message, type = 'success') {
  clearTimeout(toastTimer)
  toast.value = { show: true, message, type }
  toastTimer = setTimeout(() => { toast.value.show = false }, 4000)
}

async function load() {
  loading.value = true
  try {
    me.value = await xenService.me()
    form.value.name = me.value.name || ''
    form.value.phone = me.value.phone || ''
    form.value.district = me.value.district?.name || ''
    form.value.sub_area = me.value.sub_area || ''
    original.value = { ...form.value }
  } catch (e) {
    flash.value = 'Failed to load profile.'
  } finally { loading.value = false }
}
onMounted(load)

async function save() {
  saving.value = true
  flash.value = ''
  try {
    const res = await xenService.updateSettings(form.value)
    flash.value = res.message || 'Saved.'
    changes.value = [...changes.value, ...(res.changes || [])]
    original.value = { ...form.value }
    me.value = { ...me.value, ...res.user }
    showToast('✅ Settings saved successfully', 'success')
  } catch (e) {
    const msg = e?.response?.data?.message || e?.message || 'Failed to save settings.'
    flash.value = msg
    showToast('❌ ' + msg, 'error')
  } finally { saving.value = false }
}

function reset() {
  form.value = { ...original.value }
}

const preview = computed(() => ({
  division:  me.value?.phed_division?.name || '—',
  district:  form.value.district || me.value?.district?.name || '—',
  name:      form.value.name || '—',
  phone:     form.value.phone || '—',
  sub_area:  form.value.sub_area || me.value?.sub_area || '—',
}))
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
    <div v-if="flash" class="xd-err" :class="{ ok: flash.toLowerCase().includes('success') || flash.toLowerCase().includes('saved') }">{{ flash }}</div>

    <div class="settings-grid">
      <!-- Left: form -->
      <div class="left-col">
        <div class="panel">
          <div class="panel-h panel-h-navy">👤 XEN Officer Details <span class="badge-chip">{{ preview.division }}</span></div>
          <div class="form-body">
            <div class="fg2 full">
              <label>XEN Full Name *</label>
              <input v-model="form.name" type="text" />
              <small>Include title (Engr.) and full name as it should appear in reports and notifications.</small>
            </div>
            <div class="fg2 full">
              <label>Mobile Phone Number *</label>
              <input v-model="form.phone" type="text" />
              <small>Used for notifications and the XEN contact panel. Format: 03XX-XXX-XXXX</small>
            </div>
            <div class="fg2 full">
              <label>Transfer / Change Date</label>
              <input v-model="form.transfer_change_date" type="date" />
              <small>Date the new XEN assumed charge. Recorded in the change log.</small>
            </div>
            <div class="fg2 full">
              <label>Remarks / Reason for Change</label>
              <textarea v-model="form.remarks" rows="2" placeholder="e.g. Transfer on promotion / Routine posting / Relieved on retirement…"></textarea>
            </div>
          </div>
        </div>

        <div class="panel" style="margin-top: 14px">
          <div class="panel-h panel-h-navy">📍 Division Scope &amp; Sub-Area</div>
          <div class="form-body">
            <div class="fg2 full">
              <label>District</label>
              <input v-model="form.district" type="text" />
              <small>Administrative district this division covers.</small>
            </div>
            <div class="fg2 full">
              <label>Sub-Area / Coverage Description</label>
              <textarea v-model="form.sub_area" rows="2"></textarea>
              <small>Describes the geographic sub-area shown in the scope badge and identity bar.</small>
            </div>
          </div>
          <div class="form-actions">
            <button class="btn btn-sec" @click="reset" :disabled="saving">↶ Reset to Current</button>
            <button v-write="'update_xen_settings'" class="btn btn-pri" @click="save" :disabled="saving">{{ saving ? 'Saving…' : '✅ Save Changes' }}</button>
          </div>
        </div>
      </div>

      <!-- Right: live preview + change log -->
      <div class="right-col">
        <div class="panel">
          <div class="panel-h panel-h-green">👁 Live Preview</div>
          <div class="prev">
            <div class="prev-section">
              <div class="prev-label">IDENTITY BAR (DASHBOARD)</div>
              <div class="prev-id-bar">
                <span><b>Division:</b> {{ preview.division }}</span>
                <span><b>District:</b> {{ preview.district }}</span>
                <span><b>XEN:</b> {{ preview.name }}</span>
                <span><b>Phone:</b> {{ preview.phone }}</span>
              </div>
            </div>
            <div class="prev-section">
              <div class="prev-label">SCOPE BADGE</div>
              <span class="scope-chip"><span class="dot"></span>{{ preview.division }} · {{ preview.sub_area }}</span>
            </div>
            <div class="prev-section">
              <div class="prev-label">SIDEBAR SCOPE BLOCK</div>
              <div class="prev-sidebar">
                <div class="lab">YOUR SCOPE</div>
                <div class="nm">{{ preview.division }}</div>
                <div class="sb">{{ preview.sub_area }}</div>
              </div>
            </div>
          </div>
        </div>

        <div class="panel" style="margin-top: 12px">
          <div class="panel-h panel-h-navy">📒 Change Log</div>
          <table class="tbl">
            <thead><tr><th>Division</th><th>Field</th><th>Old Value</th><th>New Value</th><th>Changed</th></tr></thead>
            <tbody>
              <template v-if="loading">
                <SkelRow v-for="n in 3" :key="'cl' + n" :cols="[100, 90, 110, 110, 130]" />
              </template>
              <template v-else>
                <tr v-for="(c, i) in changes" :key="i">
                  <td>{{ preview.division }}</td>
                  <td>{{ c.field }}</td>
                  <td>{{ c.old || '—' }}</td>
                  <td>{{ c.new }}</td>
                  <td>{{ new Date().toLocaleString('en-GB') }}</td>
                </tr>
                <tr v-if="changes.length === 0">
                  <td colspan="5" class="empty">No changes recorded yet.</td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
@use './xen-shared.scss' as *;

.settings-grid {
  display: grid;
  grid-template-columns: 1fr 360px;
  gap: 16px;
}
.panel-h-green { background: #14532d !important; }
.badge-chip {
  display: inline-block;
  background: #dc2626;
  color: #fff;
  font-size: 10.5px;
  font-weight: 700;
  padding: 2px 9px;
  border-radius: 11px;
  margin-left: 8px;
}
.form-body {
  padding: 16px 18px;
  display: grid;
  gap: 12px;
}
.fg2 {
  display: flex;
  flex-direction: column;
  gap: 4px;
  &.full { width: 100%; }
  label { font-size: 11.5px; color: #334155; font-weight: 600; }
  input, textarea {
    border: 1px solid #cbd5e1; border-radius: 5px; padding: 7px 10px;
    font-size: 13px; font-family: inherit;
  }
  small { font-size: 10.5px; color: #64748b; }
}
.form-actions {
  padding: 12px 18px;
  border-top: 1px solid #eef1f6;
  display: flex; justify-content: flex-end; gap: 10px; background: #f8fafc;
}
.prev { padding: 14px 18px; display: flex; flex-direction: column; gap: 14px; }
.prev-label { font-size: 10px; color: #64748b; font-weight: 700; letter-spacing: .05em; margin-bottom: 6px; }
.prev-id-bar { background: #1c2e44; color: #fff; padding: 10px 12px; border-radius: 5px;
  display: flex; flex-direction: column; gap: 4px; font-size: 11.5px;
  b { color: rgba(255,255,255,.55); font-weight: 600; margin-right: 3px; }
}
.scope-chip {
  display: inline-flex; align-items: center; gap: 6px;
  background: #1c2e44; color: #fff; padding: 4px 12px; border-radius: 14px; font-size: 11px; font-weight: 600;
  .dot { width: 7px; height: 7px; border-radius: 50%; background: #4ade80; }
}
.prev-sidebar {
  background: #14304b; color: #fff; padding: 10px 12px; border-radius: 5px;
  .lab { font-size: 9.5px; color: rgba(255,255,255,.5); letter-spacing: .08em; }
  .nm  { font-size: 13px; font-weight: 700; margin-top: 4px; }
  .sb  { font-size: 11px; color: rgba(255,255,255,.62); margin-top: 2px; }
}
.xd-err.ok { background: #ecfdf5; border-color: #a7f3d0; color: #14532d; }
</style>

<style>
/* Global so the toast in <Teleport to="body"> picks up the transition */
.toast-slide-enter-active,
.toast-slide-leave-active { transition: all 0.3s ease; }
.toast-slide-enter-from,
.toast-slide-leave-to     { opacity: 0; transform: translateX(60px); }
</style>
