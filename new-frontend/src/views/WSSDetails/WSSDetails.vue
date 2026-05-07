<script setup>
import { ref, computed, onMounted } from 'vue'
import { waterSchemeService } from '../../services/waterSchemeService.js'

const loading  = ref(false)
const errorMsg = ref('')
const wssData  = ref([])

// Map backend fields to display format
function mapWss(w) {
  return {
    id: w.id,
    code: w.code || `WSS-${w.id}`,
    name: w.name || '—',
    div: w.phedDivision?.name || w.division?.name || '—',
    district: w.district?.name || '—',
    source: w.source_type || '—',
    solar: w.power_input === 'Solar',
    opStatus: w.operation || 'Operational',
    tested: w.water_samples_count || 0,
    lastWQ: w.last_sample_result || 'Untested',
    lastSampled: w.last_sampled_at ? w.last_sampled_at.split(' ')[0] : '—',
    nextScheduled: w.next_scheduled_at ? w.next_scheduled_at.split(' ')[0] : '—',
    schedStatus: w.next_scheduled_at ? 'scheduled' : (w.last_sampled_at ? 'overdue' : 'none'),
    latitude: w.latitude,
    longitude: w.longitude,
  }
}

async function loadWss() {
  loading.value = true
  errorMsg.value = ''
  try {
    const res = await waterSchemeService.getAll({})
    const data = res.data?.data || res.data || []
    wssData.value = data.map(mapWss)
  } catch (e) {
    errorMsg.value = 'Failed to load water schemes'
    console.error('WSS load error:', e)
  } finally {
    loading.value = false
  }
}

const searchText  = ref('')
const wqFilter    = ref('')
const schedFilter = ref('')

const filtered = computed(() => wssData.value.filter(w => {
  const matchSearch = !searchText.value || w.name.toLowerCase().includes(searchText.value.toLowerCase()) || w.code.toLowerCase().includes(searchText.value.toLowerCase()) || w.district.toLowerCase().includes(searchText.value.toLowerCase())
  const matchWQ     = !wqFilter.value    || w.lastWQ === wqFilter.value
  const matchSched  = !schedFilter.value || w.schedStatus === schedFilter.value
  return matchSearch && matchWQ && matchSched
}))

// ── Schedule modal ────────────────────────────────────────────────────
const showSchedModal = ref(false)
const schedTarget    = ref(null)
const schedDate      = ref('')
const schedNote      = ref('')
const schedSaving    = ref(false)

function openSchedule(wss) {
  schedTarget.value = wss
  schedDate.value   = ''
  schedNote.value   = ''
  showSchedModal.value = true
}

async function saveSchedule() {
  if (!schedDate.value) { alert('Please select a sampling date.'); return }
  schedSaving.value = true
  try {
    await waterSchemeService.createSchedule({
      water_scheme_id: schedTarget.value.id,
      scheduled_at: schedDate.value + ' 09:00:00',
      note: schedNote.value,
    })
    // Update local state
    const wss = wssData.value.find(w => w.id === schedTarget.value.id)
    if (wss) {
      wss.nextScheduled = schedDate.value
      wss.schedStatus   = 'scheduled'
    }
    showSchedModal.value = false
  } catch (e) {
    alert('Failed to save schedule: ' + (e.response?.data?.message || e.message))
    console.error('Schedule save error:', e)
  } finally {
    schedSaving.value = false
  }
}

// ── Trail modal ───────────────────────────────────────────────────────
const showTrailModal  = ref(false)
const trailTarget     = ref(null)
const trailData       = ref([])
const trailLoading    = ref(false)

async function openTrail(wss) {
  trailTarget.value = wss
  trailData.value   = []
  showTrailModal.value = true
  trailLoading.value = true
  try {
    const res = await waterSchemeService.getSamples(wss.id)
    const samples = res.data?.data || res.data || []
    trailData.value = samples.map(s => ({
      date: s.sampled_at ? s.sampled_at.split(' ')[0] : '—',
      id: s.slug || s.id,
      result: s.analysis_result || s.current_status || '—',
      cause: s.analysis_result_cause || '—',
      ion: s.analysis_result_detail || '—',
    }))
  } catch (e) {
    console.error('Trail load error:', e)
  } finally {
    trailLoading.value = false
  }
}

function wqClass(wq) {
  return wq === 'Fit' ? 'r-green' : wq === 'Unfit' ? 'r-red' : 'r-grey'
}

onMounted(loadWss)
</script>

<template>
  <div>
    <div class="abar blue">💧 WSS Register — Testing Trail &amp; Sampling Schedule</div>

    <!-- Toolbar -->
    <div class="toolbar">
      <input type="text" v-model="searchText" placeholder="🔍 WSS name, code, district…">
      <select v-model="wqFilter" style="border:1px solid var(--input-border);border-radius:4px;padding:6px 8px;font-size:12px;font-family:inherit">
        <option value="">All WQ Results</option>
        <option value="Fit">Fit</option>
        <option value="Unfit">Unfit</option>
        <option value="Untested">Untested</option>
      </select>
      <select v-model="schedFilter" style="border:1px solid var(--input-border);border-radius:4px;padding:6px 8px;font-size:12px;font-family:inherit">
        <option value="">All Schedule Status</option>
        <option value="overdue">Overdue / Unscheduled</option>
        <option value="scheduled">Scheduled</option>
        <option value="none">Not Yet Scheduled</option>
      </select>
      <div class="tsp"></div>
      <button class="btn btn-sec btn-sm">⬇ Export</button>
      <button class="btn btn-pri btn-sm">+ Add WSS</button>
    </div>

    <!-- Table -->
    <div class="tbl-wrap">
      <table style="font-size:11.5px">
        <thead>
          <tr>
            <th>WSS Code</th><th>WSS Name</th><th>PHE Div.</th><th>Source Type</th><th>Solar?</th>
            <th>Op. Status</th><th style="text-align:center">Tested</th><th style="text-align:center">Last WQ</th>
            <th>Last Sampled</th><th>Next Scheduled</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(w, i) in filtered" :key="w.code" :class="i%2===1?'alt':''">
            <td class="mono" style="font-size:10.5px">{{ w.code }}</td>
            <td><b>{{ w.name }}</b></td>
            <td>{{ w.div }}</td>
            <td>{{ w.source }}</td>
            <td>{{ w.solar ? '☀️ Solar' : '⚡ Non-Solar' }}</td>
            <td><span class="rag r-green">{{ w.opStatus }}</span></td>
            <td style="text-align:center;font-weight:700" :style="w.tested === 0 ? 'color:var(--muted)' : ''">{{ w.tested }}</td>
            <td style="text-align:center">
              <span class="rag" :class="wqClass(w.lastWQ)">{{ w.lastWQ }}</span>
            </td>
            <td :style="w.lastSampled === '—' ? 'color:var(--muted)' : ''">{{ w.lastSampled }}</td>
            <td :style="w.schedStatus === 'overdue' ? 'color:var(--red);font-weight:600' : w.schedStatus === 'none' ? 'color:var(--muted)' : ''">
              {{ w.nextScheduled }} <span v-if="w.schedStatus === 'overdue'">⚠</span>
            </td>
            <td style="white-space:nowrap">
              <button class="btn btn-sec btn-xs" @click="openTrail(w)" :disabled="w.tested === 0">📊 Trail</button>
              <button class="btn btn-xs" style="margin-left:4px"
                      :style="w.schedStatus === 'scheduled' ? 'background:#16a34a;color:#fff;border:none' : 'background:#dc2626;color:#fff;border:none'"
                      @click="openSchedule(w)">
                {{ w.schedStatus === 'scheduled' ? '✅ Sched.' : '📅 Sched.' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
      <div class="tbl-footer">
        <span>Showing {{ filtered.length }} schemes</span>
      </div>
    </div>

    <!-- ── SCHEDULE MODAL ── -->
    <Teleport to="body">
      <div v-if="showSchedModal" @click.self="showSchedModal = false"
           style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:4000;align-items:center;justify-content:center">
        <div style="background:#fff;border-radius:10px;width:420px;box-shadow:0 8px 40px rgba(0,0,0,.28);overflow:hidden">
          <div style="background:var(--navy);color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between">
            <div>
              <div style="font-size:14px;font-weight:700">📅 Schedule Next Sampling</div>
              <div style="font-size:11px;opacity:.7;margin-top:2px">{{ schedTarget?.code }}</div>
            </div>
            <button @click="showSchedModal = false" style="background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:5px;padding:5px 12px;cursor:pointer">✕</button>
          </div>
          <div style="padding:20px 24px">
            <div style="margin-bottom:14px">
              <label style="font-size:11px;font-weight:600;color:var(--muted);display:block;margin-bottom:4px">WSS Name</label>
              <div style="font-weight:700;font-size:13px;color:var(--navy)">{{ schedTarget?.name }}</div>
            </div>
            <div class="fg2" style="margin-bottom:14px">
              <label>Scheduled Sampling Date *</label>
              <input type="date" v-model="schedDate" style="width:100%;border:1px solid var(--border);border-radius:5px;padding:8px 10px;font-size:13px;font-family:inherit">
            </div>
            <div class="fg2" style="margin-bottom:20px">
              <label>Note (optional)</label>
              <input type="text" v-model="schedNote" placeholder="e.g. Joint sampling with DHO" style="width:100%;border:1px solid var(--border);border-radius:5px;padding:8px 10px;font-size:12.5px;font-family:inherit;box-sizing:border-box">
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
              <button class="btn btn-sec" @click="showSchedModal = false">Cancel</button>
              <button class="btn btn-pri" @click="saveSchedule">💾 Save Schedule</button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── TRAIL MODAL ── -->
    <Teleport to="body">
      <div v-if="showTrailModal" @click.self="showTrailModal = false"
           style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:4000;align-items:center;justify-content:center">
        <div style="background:#fff;border-radius:10px;width:700px;max-height:80vh;overflow-y:auto;box-shadow:0 8px 40px rgba(0,0,0,.28)">
          <div style="background:var(--navy);color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:1">
            <div>
              <div style="font-size:14px;font-weight:700">📊 Analysis Trail</div>
              <div style="font-size:11px;opacity:.7;margin-top:2px">{{ trailTarget?.code }} — {{ trailTarget?.name }}, {{ trailTarget?.district }}</div>
            </div>
            <button @click="showTrailModal = false" style="background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:5px;padding:5px 12px;cursor:pointer">✕</button>
          </div>
          <div style="padding:20px 24px">
            <div v-if="!trailData.length" style="text-align:center;color:var(--muted);padding:20px">No samples recorded yet.</div>
            <div class="tbl-wrap" v-else>
              <table style="font-size:11.5px">
                <thead>
                  <tr><th>Date</th><th>Sample ID</th><th style="text-align:center">Result</th><th>Cause</th><th>Detail</th></tr>
                </thead>
                <tbody>
                  <tr v-for="(r, i) in trailData" :key="r.id" :class="i%2===1?'alt':''">
                    <td class="mono">{{ r.date }}</td>
                    <td class="mono">{{ r.id }}</td>
                    <td style="text-align:center"><span class="rag" :class="r.result==='Fit'?'r-green':'r-red'">{{ r.result }}</span></td>
                    <td>{{ r.cause }}</td>
                    <td>{{ r.ion }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
