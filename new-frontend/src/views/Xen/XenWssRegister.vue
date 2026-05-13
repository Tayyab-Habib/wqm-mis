<script setup>
import { ref, onMounted, watch } from 'vue'
import { xenService } from '../../services/xenService.js'
import SkelRow from './SkelRow.vue'

const loading = ref(true)
const stats = ref({ total: 0, last_fit: 0, last_unfit: 0, overdue: 0 })
const schemes = ref([])
const q = ref('')
const result = ref('all')

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
                <button class="btn btn-sec">📊 Trail</button>
                <button class="btn btn-pri">📋 ISR</button>
              </td>
            </tr>
            <tr v-if="schemes.length === 0"><td colspan="9" class="empty">No water schemes match.</td></tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped lang="scss">
@use './xen-shared.scss' as *;
.overdue { color: #b91c1c; font-weight: 700; }
.overdue-icon { color: #b91c1c; margin-left: 4px; }
</style>
