<script setup>
import { ref, onMounted, watch } from 'vue'
import { xenService } from '../../services/xenService.js'
import SkelRow from './SkelRow.vue'

const loading = ref(false)
const filters = ref({
  from: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0,10),
  to:   new Date().toISOString().slice(0,10),
  result: 'all',
})
const stats = ref({ total: 0, fit: 0, unfit: 0, percent_unfit: 0 })
const rows  = ref([])
const meta  = ref({ phed_division: '—' })

async function load() {
  loading.value = true
  try {
    const params = { from: filters.value.from, to: filters.value.to }
    if (filters.value.result && filters.value.result !== 'all') params.result = filters.value.result
    const res = await xenService.gsr(params)
    stats.value = res.stats || stats.value
    rows.value  = res.rows  || []
    meta.value  = res.meta  || meta.value
  } catch { rows.value = [] } finally { loading.value = false }
}
onMounted(load)
watch(filters, load, { deep: true })

const fmtDate = (d) => {
  if (!d) return '—'
  try { return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: '2-digit' }) }
  catch { return d }
}
const monthLabel = (d) => {
  if (!d) return '—'
  try { return new Date(d).toLocaleDateString('en-US', { month: 'short', year: 'numeric' }) }
  catch { return '—' }
}

function resultClass(r) {
  r = (r || '').toLowerCase()
  if (r === 'fit') return 'st-green'
  if (r === 'unfit') return 'st-red'
  return 'st-grey'
}

function exportCsv() {
  const head = ['#','Sample ID','WSS / Point','Date','Point','PHE Div.','Type','Result','Cause','Parameter']
  const data = rows.value.map(r => [r.index, r.slug, r.wss_name, fmtDate(r.sampled_at), r.point, r.phed_division, r.type, r.result, r.cause, r.parameter])
  const csv = [head, ...data].map(r => r.map(c => `"${(c ?? '').toString().replace(/"/g,'""')}"`).join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob); a.download = `gsr_${filters.value.from}_${filters.value.to}.csv`; a.click()
}
</script>

<template>
  <div class="xd">
    <div class="xd-scope">
      <span class="pin">📍</span>
      <span class="t1">GSR — your division only.</span>
      <span class="sep">·</span>
      <span class="t2">Sample-wise results for your PHE Division.</span>
    </div>

    <div class="xen-toolbar">
      <div class="fg2"><label>From</label><input type="date" v-model="filters.from" /></div>
      <div class="fg2"><label>To</label><input type="date" v-model="filters.to" /></div>
      <div class="fg2"><label>Result</label>
        <select v-model="filters.result">
          <option value="all">All</option>
          <option value="Fit">Fit</option>
          <option value="Unfit">Unfit</option>
        </select>
      </div>
      <div class="spacer"></div>
      <button class="btn-export" @click="exportCsv">⬇ Export</button>
      <button class="btn-export" @click="() => window.print()">🖨 Print</button>
    </div>

    <div class="gsr-banner">
      📄 GSR | {{ monthLabel(filters.from) }} | PHE Division: <b>{{ meta.phed_division || '—' }}</b>
    </div>

    <div class="xd-cards">
      <div class="c">
        <div class="lbl">TOTAL</div>
        <div v-if="loading" class="skel val-skel"></div>
        <div v-else class="val">{{ stats.total }}</div>
      </div>
      <div class="c c-green">
        <div class="lbl">FIT</div>
        <div v-if="loading" class="skel val-skel"></div>
        <div v-else class="val">{{ stats.fit }}</div>
      </div>
      <div class="c c-red">
        <div class="lbl">UNFIT</div>
        <div v-if="loading" class="skel val-skel"></div>
        <div v-else class="val">{{ stats.unfit }}</div>
      </div>
      <div class="c c-amber">
        <div class="lbl">% UNFIT</div>
        <div v-if="loading" class="skel val-skel"></div>
        <div v-else class="val">{{ stats.percent_unfit }}%</div>
      </div>
    </div>

    <div class="panel">
      <table class="tbl">
        <thead>
          <tr>
            <th>#</th><th>Sample ID</th><th>WSS / Point</th><th>Date</th><th>Point</th><th>PHE Div.</th><th>Type</th><th>Result</th><th>Cause</th><th>Parameter</th>
          </tr>
        </thead>
        <tbody>
          <template v-if="loading">
            <SkelRow v-for="n in 8" :key="'gs' + n" :cols="[24, 90, 160, 70, 70, 100, 60, 60, 90, 120]" />
          </template>
          <template v-else>
            <tr v-for="r in rows" :key="r.id" :class="{ 'is-no-action': r.result === 'Unfit' }">
              <td>{{ r.index }}</td>
              <td class="sid" :class="{ red: r.result === 'Unfit' }">{{ r.slug }}</td>
              <td><RouterLink :to="`/xen/isr/${r.id}`" class="link">{{ r.wss_name }}</RouterLink></td>
              <td>{{ fmtDate(r.sampled_at) }}</td>
              <td>{{ r.point }}</td>
              <td>{{ r.phed_division }}</td>
              <td><span class="pill st-blue">{{ r.type }}</span></td>
              <td><span class="pill" :class="resultClass(r.result)">{{ r.result }}</span></td>
              <td>{{ r.cause }}</td>
              <td>{{ r.parameter }}</td>
            </tr>
            <tr v-if="rows.length === 0"><td colspan="10" class="empty">No samples in this period.</td></tr>
          </template>
        </tbody>
        <tfoot v-if="rows.length">
          <tr>
            <td colspan="10" class="tfoot">
              TOTALS &nbsp;
              <span class="pill st-green">Fit: {{ stats.fit }}</span>
              <span class="pill st-red">Unfit: {{ stats.unfit }}</span>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
    <div class="gsr-footer">Scoped to PHE Division <b>{{ meta.phed_division || '—' }}</b> only.</div>
  </div>
</template>

<style scoped lang="scss">
@use './xen-shared.scss' as *;

.gsr-banner {
  background: #ecfdf5;
  border: 1px solid #a7f3d0;
  color: #14532d;
  padding: 9px 14px;
  border-radius: 6px;
  font-size: 12.5px;
  margin-bottom: 12px;
  display: flex; align-items: center; gap: 6px;
}
.tfoot {
  background: #1c2e44 !important;
  color: #fff;
  text-align: right;
  font-weight: 700;
  font-size: 12px;
  .pill { margin-left: 8px; }
}
.gsr-footer { margin-top: 12px; font-size: 11.5px; color: #64748b; }
</style>
