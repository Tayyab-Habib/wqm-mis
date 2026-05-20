<script setup>
import { ref, onMounted, watch } from 'vue'
import { seService } from '../../services/seService.js'
import SeSkelRow from './SeSkelRow.vue'

const loading = ref(true)
const data    = ref(null)
const filters = ref({ q: '', district_id: '', result: '', schedule: '' })

async function load() {
  loading.value = true
  try {
    const params = {}
    if (filters.value.q)           params.q = filters.value.q
    if (filters.value.district_id) params.district_id = filters.value.district_id
    if (filters.value.result)      params.result = filters.value.result
    if (filters.value.schedule)    params.schedule = filters.value.schedule
    data.value = await seService.wssRegister(params)
  } catch { data.value = null }
  finally { loading.value = false }
}
onMounted(load)
let t = null
watch(filters, () => { clearTimeout(t); t = setTimeout(load, 300) }, { deep: true })

const fmtDate = (d) => {
  if (!d) return '—'
  try { return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: '2-digit' }) }
  catch { return d }
}
function resultPill(r) {
  if (r === 'Fit')   return 'p-green'
  if (r === 'Unfit') return 'p-red'
  return 'p-grey'
}
function exportCsv() {
  const rows = (data.value?.groups || []).flatMap(g => g.rows)
  if (!rows.length) return
  const head = ['WSS Code', 'WSS Name', 'District', 'Source', 'Solar', 'Op. Status', 'Times Tested', 'Last Result', 'Last Sampled', 'Next Scheduled']
  const csv = [head, ...rows.map(r => [
    r.wss_code, r.wss_name, r.district, r.source_type,
    (r.power_input || '').toLowerCase().includes('solar') ? 'Solar' : 'Non-Solar',
    r.operational_status, r.times_tested, r.last_result, fmtDate(r.last_sampled_at), fmtDate(r.next_scheduled),
  ])].map(r => r.map(c => `"${(c ?? '').toString().replace(/"/g, '""')}"`).join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = `se_wss_register_${new Date().toISOString().slice(0,10)}.csv`
  a.click()
}
</script>

<template>
  <div class="sd">
    <div class="sd-scope">
      <span class="pin">💧</span>
      <b>WSS Register — {{ data?.scope?.circle || '—' }} Circle only.</b>
      <span class="sep">·</span>
      <span>All water supply schemes in {{ (data?.scope?.districts || []).join(', ') }} under your circle. Click <b>Trail</b> to see the full testing history for each scheme.</span>
    </div>

    <div class="sd-cards cards-5">
      <div class="c"><div class="lbl">TOTAL WSS</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.total ?? 0 }}</div>
        <div class="sub" v-if="!loading">{{ data?.scope?.circle }} circle</div>
      </div>
      <div class="c c-green"><div class="lbl">LAST RESULT: FIT</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.last_fit ?? 0 }}</div>
      </div>
      <div class="c c-red"><div class="lbl">LAST RESULT: UNFIT</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.last_unfit ?? 0 }}</div>
      </div>
      <div class="c"><div class="lbl">UNTESTED</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.untested ?? 0 }}</div>
      </div>
      <div class="c c-amber"><div class="lbl">SCHEDULE OVERDUE</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.overdue ?? 0 }}</div>
      </div>
    </div>

    <div class="sd-toolbar">
      <div class="fg"><label>Search</label><input v-model="filters.q" type="text" placeholder="WSS name, code…"></div>
      <div class="fg"><label>District</label>
        <select v-model="filters.district_id">
          <option value="">All Districts ({{ data?.scope?.circle }})</option>
          <option v-for="d in (data?.districts || [])" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
      </div>
      <div class="fg"><label>WQ Result</label>
        <select v-model="filters.result">
          <option value="">All WQ Results</option>
          <option value="Fit">Fit</option>
          <option value="Unfit">Unfit</option>
          <option value="Untested">Untested</option>
        </select>
      </div>
      <div class="fg"><label>Schedule</label>
        <select v-model="filters.schedule">
          <option value="">All Schedule Status</option>
          <option value="overdue">Overdue</option>
          <option value="scheduled">Scheduled</option>
        </select>
      </div>
      <div class="spacer"></div>
      <button class="sd-btn sd-btn-sec" @click="exportCsv">⬇ Export</button>
    </div>

    <table class="sd-tbl">
      <thead>
        <tr>
          <th>WSS Code</th><th>WSS Name</th><th>District</th><th>Source Type</th><th>Solar</th>
          <th>Op. Status</th><th>Times Tested</th><th>Last Result</th><th>Last Sampled</th><th>Next Scheduled</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <template v-if="loading">
          <SeSkelRow v-for="n in 5" :key="'wr' + n" :cols="[100, 160, 80, 110, 50, 70, 50, 60, 80, 90, 110]" />
        </template>
        <template v-else>
          <template v-for="g in (data?.groups || [])" :key="g.district">
            <tr class="group-h"><td colspan="11">📍 {{ g.district }} District — {{ g.rows.length }} schemes</td></tr>
            <tr v-for="r in g.rows" :key="r.id">
              <td class="sid">{{ r.wss_code }}</td>
              <td><b>{{ r.wss_name }}</b></td>
              <td>{{ r.district }}</td>
              <td>{{ r.source_type }}</td>
              <td style="text-align:center">
                <span v-if="(r.power_input || '').toLowerCase().includes('solar')">🟡 Solar</span>
                <span v-else>⚡ Non-Solar</span>
              </td>
              <td><span class="sd-pill p-green">{{ r.operational_status }}</span></td>
              <td>{{ r.times_tested }}</td>
              <td><span class="sd-pill" :class="resultPill(r.last_result)">{{ r.last_result }}</span></td>
              <td>{{ fmtDate(r.last_sampled_at) }}</td>
              <td :style="r.overdue ? 'color:#b91c1c;font-weight:700' : ''">
                {{ fmtDate(r.next_scheduled) }}<span v-if="r.overdue"> ⚠</span>
              </td>
              <td>
                <button class="sd-btn sd-btn-sec">📊 Trail</button>
                <RouterLink v-if="r.last_sample_id" :to="`/se/isr/${r.last_sample_id}`" class="sd-btn sd-btn-pri" style="margin-left:4px">📋 ISR</RouterLink>
              </td>
            </tr>
          </template>
          <tr v-if="!(data?.groups || []).length"><td colspan="11" class="empty">No water schemes match these filters.</td></tr>
        </template>
      </tbody>
    </table>

    <div style="font-size:11px;color:#64748b;margin-top:8px">
      Showing {{ data?.stats?.total ?? 0 }} schemes across {{ (data?.scope?.districts || []).length }} districts — {{ data?.scope?.circle }} Circle
      <span style="margin-left:14px">⚠ <b>Overdue</b></span>
      <span style="margin-left:10px">✓ <b>Scheduled</b></span>
      <span style="margin-left:10px">— <b>Untested</b></span>
    </div>
  </div>
</template>

<style scoped lang="scss">
@use './se-shared.scss' as *;
</style>
