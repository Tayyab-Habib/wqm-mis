<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { seService } from '../../services/seService.js'
import SeSkelRow from './SeSkelRow.vue'

const loading = ref(true)
const data    = ref(null)
const filters = ref({ from_date: '', to_date: '', district_id: '', sample_type: '' })

async function load() {
  loading.value = true
  try {
    const params = {}
    if (filters.value.from_date)  params.from_date  = filters.value.from_date
    if (filters.value.to_date)    params.to_date    = filters.value.to_date
    if (filters.value.district_id) params.district_id = filters.value.district_id
    data.value = await seService.gar(params)
  } catch { data.value = null }
  finally { loading.value = false }
}
onMounted(() => {
  // Default to FY window
  const t = new Date()
  const yr = t.getMonth() >= 6 ? t.getFullYear() : t.getFullYear() - 1
  filters.value.from_date = `${yr}-07-01`
  filters.value.to_date   = t.toISOString().slice(0, 10)
  load()
})
let t = null
watch(filters, () => { clearTimeout(t); t = setTimeout(load, 300) }, { deep: true })

const fmtPct = (n) => (n ?? 0).toFixed ? n.toFixed(1) + '%' : `${n}%`
function ragBadge(rag) {
  return rag === 'high' ? 'high' : rag === 'moderate' ? 'moderate' : 'good'
}
function ragLabel(rag) {
  return rag === 'high' ? 'High' : rag === 'moderate' ? 'Moderate' : 'Good'
}

function exportCsv() {
  const rows = data.value?.district_abstract || []
  if (!rows.length) return
  const head = ['#', 'District', 'PHE Division', 'Tested', 'Fit', 'Unfit', '% Unfit', 'RAG']
  const csv = [head, ...rows.map((r, i) => [i + 1, r.district, r.phe_division, r.tested, r.fit, r.unfit, r.pct_unfit + '%', ragLabel(r.rag)])]
    .map(r => r.map(c => `"${(c ?? '').toString().replace(/"/g, '""')}"`).join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = `se_gar_${new Date().toISOString().slice(0,10)}.csv`
  a.click()
}
</script>

<template>
  <div class="sd">
    <div class="sd-scope">
      <span class="pin">📍</span>
      <b>GAR scoped to {{ data?.scope?.circle || '—' }} Circle</b>
      <span class="sep">—</span>
      <span>{{ (data?.scope?.districts || []).join(', ') }}. Filters below are within this scope.</span>
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
      <div class="fg"><label>Sample Type</label>
        <select v-model="filters.sample_type">
          <option value="">All</option>
        </select>
      </div>
      <div class="spacer"></div>
      <button class="sd-btn sd-btn-sec" @click="exportCsv">📥 Export .xlsx</button>
      <button class="sd-btn sd-btn-sec" @click="() => window.print()">🖨 Print PDF</button>
    </div>

    <div class="sd-cards cards-6">
      <div class="c"><div class="lbl">TOTAL TESTED</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.kpi?.total_tested?.toLocaleString() ?? 0 }}</div>
        <div class="sub" v-if="!loading">{{ data?.scope?.circle }} total</div>
      </div>
      <div class="c c-green"><div class="lbl">FIT</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.kpi?.fit?.toLocaleString() ?? 0 }}</div>
      </div>
      <div class="c c-red"><div class="lbl">UNFIT</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.kpi?.unfit?.toLocaleString() ?? 0 }}</div>
      </div>
      <div class="c"><div class="lbl">% UNFIT</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val" style="color:#dc2626">{{ data?.kpi?.pct_unfit ?? 0 }}%</div>
      </div>
      <div class="c"><div class="lbl">WSS COVERED</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.kpi?.wss_covered ?? 0 }}</div>
        <div class="sub" v-if="!loading">of {{ data?.kpi?.wss_total ?? 0 }}</div>
      </div>
      <div class="c"><div class="lbl">LAB</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val" style="font-size:16px">{{ data?.scope?.circle || '—' }}</div>
      </div>
    </div>

    <h3 style="font-size:13px;font-weight:700;color:#0f172a;margin:6px 0">District-wise Abstract — {{ data?.scope?.circle }}</h3>
    <table class="sd-tbl" style="margin-bottom:16px">
      <thead>
        <tr><th>#</th><th>District</th><th>PHE Division</th><th>Tested</th><th>Fit</th><th>Unfit</th><th>% Unfit</th><th>Fit vs Unfit</th><th>RAG</th></tr>
      </thead>
      <tbody>
        <template v-if="loading">
          <SeSkelRow v-for="n in 3" :key="'da' + n" :cols="[20, 80, 100, 50, 50, 50, 60, 100, 60]" />
        </template>
        <template v-else>
          <tr v-for="(r, i) in (data?.district_abstract || [])" :key="r.district_id">
            <td>{{ i + 1 }}</td>
            <td><b>{{ r.district }}</b></td>
            <td>{{ r.phe_division }}</td>
            <td>{{ r.tested.toLocaleString() }}</td>
            <td style="color:#16a34a;font-weight:600">{{ r.fit }}</td>
            <td style="color:#dc2626;font-weight:700">{{ r.unfit }}</td>
            <td>{{ r.pct_unfit }}%</td>
            <td>
              <span class="fvu-bar" :class="r.pct_unfit >= 10 ? 'bad' : ''">
                <span class="fvu-fit" :style="`width:${r.pct_fit}%`">{{ r.pct_fit }}%</span>
              </span>
            </td>
            <td><span class="sd-rag" :class="ragBadge(r.rag)">{{ ragLabel(r.rag) }}</span></td>
          </tr>
          <tr v-if="!(data?.district_abstract || []).length"><td colspan="9" class="empty">No districts in this scope.</td></tr>
          <tr class="total-r" v-if="(data?.district_abstract || []).length">
            <td colspan="3">{{ data?.scope?.circle?.toUpperCase() }} TOTAL</td>
            <td>{{ data?.kpi?.total_tested?.toLocaleString() }}</td>
            <td>{{ data?.kpi?.fit?.toLocaleString() }}</td>
            <td>{{ data?.kpi?.unfit?.toLocaleString() }}</td>
            <td>{{ data?.kpi?.pct_unfit }}%</td>
            <td>—</td><td>—</td>
          </tr>
        </template>
      </tbody>
    </table>

    <h3 style="font-size:13px;font-weight:700;color:#0f172a;margin:6px 0">Month-wise Abstract — {{ data?.scope?.circle }} <span style="font-size:11px;color:#64748b;font-weight:400">T=Tested F=Fit U=Unfit</span></h3>
    <div class="sd-tbl-wrap" style="overflow-x:auto;margin-bottom:14px">
      <table class="sd-tbl">
        <thead>
          <tr>
            <th>#</th><th>District</th><th>PHE Div.</th>
            <th style="text-align:center">Net Total</th>
            <th v-for="m in (data?.month_abstract?.months || [])" :key="m.ym" colspan="3" style="text-align:center">{{ m.label }}</th>
          </tr>
          <tr>
            <th colspan="3"></th>
            <th style="text-align:center">T</th>
            <template v-for="m in (data?.month_abstract?.months || [])" :key="'h' + m.ym">
              <th>T</th><th>F</th><th>U</th>
            </template>
          </tr>
        </thead>
        <tbody>
          <template v-if="loading">
            <SeSkelRow v-for="n in 3" :key="'ma' + n" :cols="[20, 80, 100, 50, 30, 30, 30, 30, 30, 30, 30, 30, 30]" />
          </template>
          <template v-else>
            <tr v-for="(r, i) in (data?.month_abstract?.rows || [])" :key="r.district_id">
              <td>{{ i + 1 }}</td>
              <td><b>{{ r.district }}</b></td>
              <td>{{ r.phe_division }}</td>
              <td style="text-align:center;font-weight:700">{{ r.totals?.tested ?? 0 }}</td>
              <template v-for="c in r.cells" :key="r.district_id + c.ym">
                <td style="text-align:center">{{ c.tested || '—' }}</td>
                <td style="text-align:center;color:#16a34a">{{ c.fit || '—' }}</td>
                <td style="text-align:center;color:#dc2626">{{ c.unfit || '—' }}</td>
              </template>
            </tr>
            <tr v-if="!(data?.month_abstract?.rows || []).length"><td :colspan="4 + (data?.month_abstract?.months?.length || 0) * 3" class="empty">No data in this date range.</td></tr>
          </template>
        </tbody>
      </table>
    </div>

    <div style="font-size:11px;color:#64748b">
      RAG: <b style="color:#166534">Good</b> &lt;10% · <b style="color:#92400e">Moderate</b> 10–20% · <b style="color:#991b1b">High</b> &gt;20%
      | Data scoped to {{ data?.scope?.circle }} Circle only.
    </div>
  </div>
</template>

<style scoped lang="scss">
@use './se-shared.scss' as *;
</style>
