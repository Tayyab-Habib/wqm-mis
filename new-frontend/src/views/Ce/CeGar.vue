<script setup>
import { ref, onMounted, watch } from 'vue'
import { ceService } from '../../services/ceService.js'
import CeSkelRow from './CeSkelRow.vue'

const loading = ref(true)
const data    = ref(null)
const filters = ref({ from_date: '', to_date: '', circle_id: '', district_id: '' })

async function load() {
  loading.value = true
  try {
    const params = {}
    if (filters.value.from_date)  params.from_date  = filters.value.from_date
    if (filters.value.to_date)    params.to_date    = filters.value.to_date
    if (filters.value.circle_id)  params.circle_id  = filters.value.circle_id
    if (filters.value.district_id) params.district_id = filters.value.district_id
    data.value = await ceService.gar(params)
  } catch { data.value = null } finally { loading.value = false }
}
onMounted(() => {
  // Default date range: financial year (Jul – Mar)
  const today = new Date()
  const yr = today.getMonth() >= 6 ? today.getFullYear() : today.getFullYear() - 1
  filters.value.from_date = `${yr}-07-01`
  filters.value.to_date   = today.toISOString().slice(0, 10)
  load()
})

let t = null
watch(filters, () => { clearTimeout(t); t = setTimeout(load, 300) }, { deep: true })

function ragBadge(rag) {
  return (rag || '').toLowerCase()
}

function exportXlsx() {
  // Simple CSV export
  if (!data.value?.se_abstract?.length) return
  const head = ['SE Circle', 'SE', 'Tested', 'Fit', 'Unfit', '% Unfit', 'RAG']
  const rows = data.value.se_abstract.map(r => [r.circle, r.se_name, r.tested, r.fit, r.unfit, r.pct_unfit + '%', r.rag])
  const csv = [head, ...rows].map(r => r.map(c => `"${(c ?? '').toString().replace(/"/g, '""')}"`).join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = `ce_gar_${new Date().toISOString().slice(0, 10)}.csv`
  a.click()
}
</script>

<template>
  <div class="cd">
    <div class="cd-scope">
      <span class="pin">📍</span>
      <b>GAR scoped to CE {{ data?.scope?.region?.replace(/^CE\s*—\s*/, '') || '—' }}</b>
      <span class="sep">—</span>
      <span>{{ (data?.scope?.circles || []).join(', ') }}. Filters below are within this scope.</span>
    </div>

    <div class="cd-toolbar">
      <div class="fg">
        <label>From</label>
        <input type="date" v-model="filters.from_date">
      </div>
      <div class="fg">
        <label>To</label>
        <input type="date" v-model="filters.to_date">
      </div>
      <div class="fg">
        <label>SE Circle</label>
        <select v-model="filters.circle_id">
          <option value="">All (CE Region)</option>
          <option v-for="(c, i) in (data?.se_abstract || [])" :key="i" :value="c.circle_id">{{ c.circle }}</option>
        </select>
      </div>
      <div class="fg">
        <label>District</label>
        <select v-model="filters.district_id">
          <option value="">All Districts</option>
        </select>
      </div>
      <div class="fg">
        <label>Sample Type</label>
        <select>
          <option value="">All</option>
        </select>
      </div>
      <div class="spacer"></div>
      <button class="cd-btn cd-btn-sec" @click="exportXlsx">📥 Export .xlsx</button>
      <button class="cd-btn cd-btn-sec" @click="() => window.print()">🖨 Print PDF</button>
    </div>

    <div class="cd-banner ok">
      GAR | {{ filters.from_date ? new Date(filters.from_date).toLocaleDateString('en-GB', { month: 'short', year: 'numeric' }) : '—' }} – {{ filters.to_date ? new Date(filters.to_date).toLocaleDateString('en-GB', { month: 'short', year: 'numeric' }) : '—' }} | CE: <b style="margin-left:4px">{{ data?.scope?.region?.replace(/^CE\s*—\s*/, '') || '—' }}</b> &nbsp;|&nbsp; {{ (data?.scope?.circles || []).join(' · ') }}
    </div>

    <div class="cd-cards cards-6">
      <div class="c">
        <div class="lbl">TOTAL TESTED</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val">{{ data?.kpi?.total_tested?.toLocaleString() ?? 0 }}</div>
        <div class="sub" v-if="!loading">CE {{ data?.scope?.region?.replace(/^CE\s*—\s*/, '') || '' }} total</div>
      </div>
      <div class="c c-green">
        <div class="lbl">FIT</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val">{{ data?.kpi?.fit?.toLocaleString() ?? 0 }}</div>
      </div>
      <div class="c c-red">
        <div class="lbl">UNFIT</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val">{{ data?.kpi?.unfit?.toLocaleString() ?? 0 }}</div>
      </div>
      <div class="c">
        <div class="lbl">% UNFIT</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val" style="color:#dc2626">{{ data?.kpi?.pct_unfit ?? 0 }}%</div>
      </div>
      <div class="c">
        <div class="lbl">WSS COVERED</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val">{{ data?.kpi?.wss_covered ?? 0 }}</div>
        <div class="sub" v-if="!loading">of {{ data?.kpi?.wss_total ?? 0 }}</div>
      </div>
      <div class="c">
        <div class="lbl">LABS</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val">{{ data?.kpi?.lab_count ?? 0 }}</div>
        <div class="sub" v-if="!loading">{{ (data?.kpi?.lab_names || []).join(' · ') || '—' }}</div>
      </div>
    </div>

    <h3 style="font-size:13px;font-weight:700;color:#0f172a;margin:8px 0">SE Circle-wise Abstract</h3>
    <div class="cd-scope" style="background:#f8fafc;border-color:#e2e8f0;border-left-color:#94a3b8;font-size:11.5px">
      Click ▶ to expand SE Circle → District → PHE Division
    </div>

    <table class="cd-tbl">
      <thead>
        <tr>
          <th>SE Circle / District / PHE Division</th>
          <th>Tested</th><th>Fit</th><th>Unfit</th><th>% Unfit</th><th>Fit vs Unfit</th><th>RAG</th>
        </tr>
      </thead>
      <tbody>
        <template v-if="loading">
          <CeSkelRow v-for="n in 3" :key="'gr' + n" :cols="[220, 60, 50, 50, 60, 100, 50]" />
        </template>
        <template v-else>
          <tr v-for="r in (data?.se_abstract || [])" :key="r.circle_id">
            <td>
              <span style="color:#1d4ed8;cursor:pointer">▶</span>
              <RouterLink :to="`/ce/circles/${r.circle_id}`" style="color:#1d4ed8;font-weight:700;text-decoration:none;margin-left:6px">{{ r.circle }}</RouterLink>
              <div style="font-size:10.5px;color:#64748b;margin-top:2px;padding-left:18px">{{ r.se_name }}</div>
            </td>
            <td>{{ r.tested.toLocaleString() }}</td>
            <td style="color:#16a34a;font-weight:600">{{ r.fit }}</td>
            <td style="color:#dc2626;font-weight:700">{{ r.unfit }}</td>
            <td>{{ r.pct_unfit }}%</td>
            <td>
              <span class="fvu-bar" :class="r.pct_fit >= 90 ? 'good' : ''">
                <span class="fvu-fit" :style="`width:${r.pct_fit}%`">{{ r.pct_fit }}%</span>
              </span>
            </td>
            <td><span class="cd-rag" :class="ragBadge(r.rag)"><span class="dot"></span></span></td>
          </tr>
          <tr v-if="!(data?.se_abstract || []).length">
            <td colspan="7" class="empty">No SE Circle data in this date range.</td>
          </tr>
          <tr class="total-r" v-if="(data?.se_abstract || []).length > 0">
            <td>CE {{ data?.scope?.region?.replace(/^CE\s*—\s*/, '').toUpperCase() || '' }} TOTAL</td>
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
@use './ce-shared.scss' as *;
</style>
