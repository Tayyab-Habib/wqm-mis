<script setup>
import { ref, onMounted, watch } from 'vue'
import { secretaryService } from '../../services/secretaryService.js'
import SecSkelRow from './SecSkelRow.vue'

const loading = ref(true)
const data    = ref(null)
const filters = ref({ from_date: '', to_date: '', region_id: '' })

async function load() {
  loading.value = true
  try {
    const params = {}
    if (filters.value.from_date) params.from_date = filters.value.from_date
    if (filters.value.to_date)   params.to_date   = filters.value.to_date
    if (filters.value.region_id) params.region_id = filters.value.region_id
    data.value = await secretaryService.gar(params)
  } catch { data.value = null } finally { loading.value = false }
}

onMounted(() => {
  const today = new Date()
  const yr = today.getMonth() >= 6 ? today.getFullYear() : today.getFullYear() - 1
  filters.value.from_date = `${yr}-07-01`
  filters.value.to_date   = today.toISOString().slice(0, 10)
  load()
})

let t = null
watch(filters, () => { clearTimeout(t); t = setTimeout(load, 300) }, { deep: true })

function ragBadge(rag) { return (rag || '').toLowerCase() }

function exportCsv() {
  if (!data.value?.ce_abstract?.length) return
  const head = ['Chief Engineer', 'CE Name', 'Tested', 'Fit', 'Unfit', '% Unfit', 'RAG']
  const rows = data.value.ce_abstract.map(r => [r.ce, r.ce_name, r.tested, r.fit, r.unfit, r.pct_unfit + '%', r.rag])
  const csv = [head, ...rows].map(r => r.map(c => `"${(c ?? '').toString().replace(/"/g, '""')}"`).join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = `secretary_gar_${new Date().toISOString().slice(0, 10)}.csv`
  a.click()
}
</script>

<template>
  <div class="sd">
    <div class="sd-scope">
      <span class="pin">📍</span>
      <b>GAR — Province-wide.</b>
      <span class="sep">—</span>
      <span>General Analytical Report for all CEs and labs across Khyber Pakhtunkhwa. Filters apply province-wide.</span>
    </div>

    <div class="sd-toolbar">
      <div class="fg"><label>From</label><input type="date" v-model="filters.from_date"></div>
      <div class="fg"><label>To</label><input type="date" v-model="filters.to_date"></div>
      <div class="fg">
        <label>Chief Engineer</label>
        <select v-model="filters.region_id">
          <option value="">All CEs</option>
          <option v-for="r in (data?.regions || [])" :key="r.id" :value="r.id">{{ r.name }}</option>
        </select>
      </div>
      <div class="fg">
        <label>Sample Type</label>
        <select><option value="">All</option></select>
      </div>
      <div class="spacer"></div>
      <button class="sd-btn sd-btn-sec" @click="exportCsv">📥 Export .xlsx</button>
      <button class="sd-btn sd-btn-sec" @click="() => window.print()">🖨 Print PDF</button>
    </div>

    <div class="sd-banner ok">
      GAR | {{ filters.from_date ? new Date(filters.from_date).toLocaleDateString('en-GB', { month: 'short', year: 'numeric' }) : '—' }} – {{ filters.to_date ? new Date(filters.to_date).toLocaleDateString('en-GB', { month: 'short', year: 'numeric' }) : '—' }} | Scope: <b style="margin-left:4px">Province-wide</b> &nbsp;|&nbsp; All Chief Engineers · All Circles
    </div>

    <div class="sd-cards cards-6">
      <div class="c">
        <div class="lbl">TOTAL TESTED</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.kpi?.total_tested?.toLocaleString() ?? 0 }}</div>
        <div class="sub" v-if="!loading">Province total</div>
      </div>
      <div class="c c-green">
        <div class="lbl">FIT</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.kpi?.fit?.toLocaleString() ?? 0 }}</div>
      </div>
      <div class="c c-red">
        <div class="lbl">UNFIT</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.kpi?.unfit?.toLocaleString() ?? 0 }}</div>
      </div>
      <div class="c">
        <div class="lbl">% UNFIT</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val" style="color:#dc2626">{{ data?.kpi?.pct_unfit ?? 0 }}%</div>
      </div>
      <div class="c">
        <div class="lbl">WSS COVERED</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.kpi?.wss_covered ?? 0 }}</div>
        <div class="sub" v-if="!loading">of {{ data?.kpi?.wss_total ?? 0 }}</div>
      </div>
      <div class="c">
        <div class="lbl">LABS</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.kpi?.lab_count ?? 0 }}</div>
        <div class="sub" v-if="!loading">Province-wide</div>
      </div>
    </div>

    <h3 style="font-size:13px;font-weight:700;color:#0f172a;margin:8px 0">CE-wise Abstract — FY {{ filters.from_date ? new Date(filters.from_date).getFullYear() : '' }}-{{ filters.to_date ? String(new Date(filters.to_date).getFullYear()).slice(2) : '' }}</h3>
    <div class="sd-scope" style="background:#f8fafc;border-color:#e2e8f0;border-left-color:#94a3b8;font-size:11.5px">
      Click ▶ to expand CE → SE Circle → District → PHE Division
    </div>

    <table class="sd-tbl">
      <thead>
        <tr><th>CE / SE Circle / District / PHE Division</th><th>Tested</th><th>Fit</th><th>Unfit</th><th>% Unfit</th><th>Fit vs Unfit</th><th>RAG</th></tr>
      </thead>
      <tbody>
        <template v-if="loading">
          <SecSkelRow v-for="n in 4" :key="'gr' + n" :cols="[220, 60, 50, 50, 60, 100, 50]" />
        </template>
        <template v-else>
          <tr v-for="r in (data?.ce_abstract || [])" :key="r.region_id">
            <td>
              <span style="color:#1d4ed8;cursor:pointer">▶</span>
              <RouterLink :to="`/secretary/ce/${r.region_id}`" style="color:#1d4ed8;font-weight:700;text-decoration:none;margin-left:6px">{{ r.ce }}</RouterLink>
              <div style="font-size:10.5px;color:#64748b;margin-top:2px;padding-left:18px">{{ r.ce_name }}</div>
            </td>
            <td>{{ r.tested.toLocaleString() }}</td>
            <td style="color:#16a34a;font-weight:600">{{ r.fit }}</td>
            <td style="color:#dc2626;font-weight:700">{{ r.unfit }}</td>
            <td>{{ r.pct_unfit }}%</td>
            <td>
              <span class="sd-fvu-bar" :class="r.pct_fit >= 90 ? 'good' : ''">
                <span class="fit" :style="`width:${r.pct_fit}%`">{{ r.pct_fit }}%</span>
              </span>
            </td>
            <td><span class="sd-rag" :class="ragBadge(r.rag)"><span class="dot"></span></span></td>
          </tr>
          <tr v-if="!(data?.ce_abstract || []).length">
            <td colspan="7" class="empty">No CE data in this date range.</td>
          </tr>
          <tr class="total-r" v-if="(data?.ce_abstract || []).length > 0">
            <td>PROVINCE TOTAL</td>
            <td>{{ data?.kpi?.total_tested?.toLocaleString() }}</td>
            <td>{{ data?.kpi?.fit?.toLocaleString() }}</td>
            <td>{{ data?.kpi?.unfit?.toLocaleString() }}</td>
            <td>{{ data?.kpi?.pct_unfit }}%</td>
            <td>—</td>
            <td>—</td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>

<style scoped lang="scss">
@use './secretary-shared.scss' as *;
</style>
