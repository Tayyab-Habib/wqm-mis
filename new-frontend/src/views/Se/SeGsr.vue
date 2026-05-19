<script setup>
import { ref, onMounted, watch } from 'vue'
import { seService } from '../../services/seService.js'
import SeSkelRow from './SeSkelRow.vue'

const loading = ref(true)
const data    = ref(null)
const filters = ref({ from_date: '', to_date: '', district_id: '', result: '', sample_type: '' })

async function load() {
  loading.value = true
  try {
    const params = {}
    if (filters.value.from_date)  params.from_date  = filters.value.from_date
    if (filters.value.to_date)    params.to_date    = filters.value.to_date
    if (filters.value.district_id) params.district_id = filters.value.district_id
    if (filters.value.result)     params.result     = filters.value.result
    data.value = await seService.gsr(params)
  } catch { data.value = null }
  finally { loading.value = false }
}
onMounted(() => {
  const t = new Date()
  const s = new Date(t.getFullYear(), t.getMonth(), 1)
  filters.value.from_date = s.toISOString().slice(0, 10)
  filters.value.to_date   = t.toISOString().slice(0, 10)
  load()
})
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
function typePill(t) {
  if (!t) return 'p-grey'
  const s = t.toLowerCase()
  if (s.includes('micro')) return 'p-violet'
  if (s.includes('pcm')   || s.includes('chemical')) return 'p-cyan'
  return 'p-blue'
}
function exportCsv() {
  const rows = (data.value?.groups || []).flatMap(g => g.rows)
  if (!rows.length) return
  const head = ['#', 'Sample ID', 'WSS', 'Sampling Date', 'Point', 'District', 'PHE Div.', 'Type', 'Result', 'Cause', 'Parameter']
  const csv = [head, ...rows.map((r, i) => [i+1, r.slug, r.wss_name, fmtDate(r.sampling_date), r.point, r.district, r.phe_division, r.type, r.result, r.cause || '', r.parameter || ''])]
    .map(r => r.map(c => `"${(c ?? '').toString().replace(/"/g, '""')}"`).join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = `se_gsr_${new Date().toISOString().slice(0,10)}.csv`
  a.click()
}
</script>

<template>
  <div class="sd">
    <div class="sd-scope">
      <span class="pin">📍</span>
      <b>GSR scoped to {{ data?.scope?.circle || '—' }} Circle</b>
      <span class="sep">—</span>
      <span>{{ (data?.scope?.districts || []).join(', ') }} only.</span>
    </div>

    <div class="sd-toolbar">
      <div class="fg"><label>From</label><input type="date" v-model="filters.from_date"></div>
      <div class="fg"><label>To</label><input type="date" v-model="filters.to_date"></div>
      <div class="fg"><label>District</label>
        <select v-model="filters.district_id">
          <option value="">All ({{ data?.scope?.circle }})</option>
          <option v-for="(d, i) in (data?.scope?.districts || [])" :key="i" :value="d">{{ d }}</option>
        </select>
      </div>
      <div class="fg"><label>Result</label>
        <select v-model="filters.result">
          <option value="">All</option>
          <option value="Fit">Fit</option>
          <option value="Unfit">Unfit</option>
        </select>
      </div>
      <div class="fg"><label>Sample Type</label>
        <select v-model="filters.sample_type">
          <option value="">All</option>
        </select>
      </div>
      <div class="spacer"></div>
      <button class="sd-btn sd-btn-sec" @click="exportCsv">📥 Export .xlsx</button>
      <button class="sd-btn sd-btn-sec" @click="() => window.print()">🖨 Print PDF</button>
    </div>

    <div class="sd-cards cards-5">
      <div class="c"><div class="lbl">TOTAL SAMPLES</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.total ?? 0 }}</div>
      </div>
      <div class="c c-green"><div class="lbl">FIT</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.fit ?? 0 }}</div>
      </div>
      <div class="c c-red"><div class="lbl">UNFIT</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.unfit ?? 0 }}</div>
      </div>
      <div class="c"><div class="lbl">% UNFIT</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val" style="color:#dc2626">{{ data?.stats?.pct_unfit ?? 0 }}%</div>
      </div>
      <div class="c"><div class="lbl">WSS COVERED</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.wss_covered ?? 0 }}</div>
      </div>
    </div>

    <h3 style="font-size:13px;font-weight:700;color:#0f172a;margin:6px 0">Sample-wise Results — {{ data?.scope?.circle }}</h3>
    <table class="sd-tbl">
      <thead>
        <tr>
          <th>#</th><th>Sample ID</th><th>WSS Name</th><th>Sampling Date</th><th>Point</th>
          <th>District</th><th>PHE Div.</th><th>Type</th><th>Result</th><th>Cause</th><th>Parameter</th>
        </tr>
      </thead>
      <tbody>
        <template v-if="loading">
          <SeSkelRow v-for="n in 6" :key="'gs' + n" :cols="[20, 90, 140, 80, 60, 80, 90, 50, 60, 80, 120]" />
        </template>
        <template v-else>
          <template v-for="g in (data?.groups || [])" :key="g.district">
            <tr class="group-h"><td colspan="11">{{ g.district.toUpperCase() }} DISTRICT</td></tr>
            <tr v-for="(r, i) in g.rows" :key="r.id">
              <td>{{ i + 1 }}</td>
              <td class="sid" :class="{ red: r.result === 'Unfit' }">{{ r.slug }}</td>
              <td><b>{{ r.wss_name }}</b></td>
              <td>{{ fmtDate(r.sampling_date) }}</td>
              <td>{{ r.point }}</td>
              <td>{{ r.district }}</td>
              <td>{{ r.phe_division }}</td>
              <td><span class="sd-pill" :class="typePill(r.type)">{{ r.type }}</span></td>
              <td><span class="sd-pill" :class="resultPill(r.result)">{{ r.result || '—' }}</span></td>
              <td>{{ r.cause || '—' }}</td>
              <td>{{ r.parameter || '—' }}</td>
            </tr>
          </template>
          <tr v-if="!(data?.groups || []).length"><td colspan="11" class="empty">No samples in this date range.</td></tr>
          <tr class="total-r" v-if="(data?.groups || []).length">
            <td colspan="9" style="text-align:right">TOTALS</td>
            <td><span class="sd-pill p-green">Fit: {{ data?.stats?.fit }}</span></td>
            <td><span class="sd-pill p-red">Unfit: {{ data?.stats?.unfit }}</span></td>
          </tr>
        </template>
      </tbody>
    </table>

    <div style="font-size:11px;color:#64748b;margin-top:8px">
      PCM=Physical+Chemical+Microbial · M=Microbial · PC=Physical+Chemical | Scoped to {{ data?.scope?.circle }} Circle
    </div>
  </div>
</template>

<style scoped lang="scss">
@use './se-shared.scss' as *;
</style>
